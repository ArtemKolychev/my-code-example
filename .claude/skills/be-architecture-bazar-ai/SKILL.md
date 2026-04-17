---
name: be-architecture-bazar-ai
description: Architecture guardrails for PHP 8.4 / Symfony 7.3 (Hexagonal + DDD) in the bazar_ai backend. Activate whenever writing, reviewing, or refactoring any PHP class in src/be/src/ — controllers, entities, services, repositories, commands, handlers, DTOs, voters, events. Always use for: "create controller", "add endpoint", "write entity", "add service", "create command", "add repository", "refactor", any PHP generation for bazar_ai backend.
---

# Backend Architecture — Hexagonal / DDD / CQRS / PHP 8.4

## Pre-flight (run before writing any class)

Before writing code, state out loud:
> "I am writing a **[Layer]** class. Constraint: **[Key Rule]**. Test required: **[Test Type]**."

Then verify:
1. Which layer? → place the file correctly
2. Does it import from a forbidden layer? → fix the dependency
3. Does it touch an external system (DB, HTTP, filesystem, time)? → use an interface
4. What test is required? → plan it before writing the implementation

---

## Layer Map & Data Flow

```
HTTP Request
     │
     ▼
┌──────────────────┐
│ EntryPoint Layer │  ← maps Request → DTO, dispatches Command, returns Response
└──────┬───────────┘
       │ MessageBus
       ▼
┌──────────────────┐
│ Application Layer│  ← Handler receives Command, calls Domain via interfaces
└──────┬───────────┘
       │ Repository Interface (Domain contract)
       ▼
┌──────────────┐        ┌──────────────────────┐
│ Domain Layer │        │ Infrastructure Layer  │
│  (pure PHP)  │◄───────│ implements interfaces │
└──────────────┘        └──────────────────────┘
```

**Dependency rule:** EntryPoint → Application → Domain ← Infrastructure  
Domain has zero knowledge of anything outside itself.

## CQRS-lite (Write/Read Split)

Use exactly two entry points between layers:

### 1) Write side — Commands
- EntryPoint dispatches Commands via `MessageBusInterface`
- State changes live only in `App\Application\Handler\*Handler`
- Handlers use Domain interfaces (`RepositoryInterface`) to persist state
- EntryPoint must not execute write logic directly

Flow:
`EntryPoint Action -> MessageBusInterface -> Application Handler -> Domain -> RepositoryInterface`

### 2) Read side — Queries / Providers
- Read services live in `App\Infrastructure\Query\`
- Contracts live in `App\Application\Query\`
- Name read services as `*Provider` or `*QueryService`
- Return DTOs/ReadModels (or shaped arrays), **never Domain entities** — providers must not allow callers to mutate entity state
- EntryPoint injects `ProviderInterface`/`QueryServiceInterface` directly for rendering

Flow:
`EntryPoint Action -> Application Query Interface -> Infrastructure Query Provider -> DTO/ReadModel`

Example:

```php
final class ArticleProvider implements ArticleProviderInterface {
    public function getDashboardStats(UserId $id): ArticleStatsDto {
        // Pure SQL is acceptable here for fast read models.
    }
}
```

---

## Domain Layer

**Purpose:** Express business rules in pure PHP. No Symfony, no Doctrine, no HTTP, no `new DateTimeImmutable()` calls.

### Entities — Rich Model, not Anemic

An entity owns its state transitions. Named methods enforce invariants — not setters.

```php
// ❌ Anemic (current pattern — do not replicate)
$article->setStatus('published');

// ✅ PHP 8.4 Rich Model with Property Hooks
final class Article {
    // public-read, private-write — immutable from outside
    public private(set) ArticleStatus $status = ArticleStatus::Draft;

    // Property hook: validates on write
    public string $title {
        set {
            if (mb_strlen($value) < 10) {
                throw new \DomainException('Title must be at least 10 characters.');
            }
            $this->title = $value;
        }
    }

    /** @var list<DomainEvent> */
    private array $domainEvents = [];

    public function publish(DateTimeImmutable $at): void {
        if ($this->status !== ArticleStatus::Draft) {
            throw new \DomainException('Only draft articles can be published.');
        }
        $this->status = ArticleStatus::Published;
        $this->publishedAt = $at;
        $this->domainEvents[] = new ArticlePublished($this->id, $at);
    }

    /** @return list<DomainEvent> */
    public function pullDomainEvents(): array {
        $events = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }
}
```

### Value Objects — prevent Primitive Obsession

If a property has validation rules, it is not a primitive — it is a Value Object.

| Primitive (❌) | Value Object (✅) |
|---|---|
| `string $email` | `Email $email` |
| `float $price` | `Money $price` |
| `int $articleId` | `ArticleId $articleId` |
| `string $platform` | `Platform $platform` (Enum) |

```php
readonly final class Money {
    public function __construct(
        public int $amount,      // in cents
        public string $currency,
    ) {
        if ($this->amount < 0) {
            throw new \DomainException('Price cannot be negative.');
        }
    }
}
```

### Enums with Domain Logic
The project already uses enums (`Platform`, `Category`, `Condition`). Follow this pattern — add domain methods directly to enums:

```php
enum Platform: string {
    case Seznam = 'seznam';
    case Bazos  = 'bazos';

    public function label(): string { ... }
    public function supportsImages(): bool { ... }
}
```

### Domain Entity Business Rule Methods

Business predicates that combine multiple entity fields must live on the entity, **not** in Action or Application service. Never scatter nil-checks across layers.

```php
// ❌ Nil-checks in Action / Application service — forbidden
if (!$user->getName() || !$user->getAddress() || !$user->getZip() || !$user->getPhone()) { ... }

// ✅ Single method on entity (Domain layer)
// Domain/Entity/User.php
public function isProfileComplete(): bool
{
    return null !== $this->name
        && null !== $this->address
        && null !== $this->zip
        && null !== $this->phone;
}

// Application/Service/ArticlePublishService.php — one clean guard
public function publishArticles(array $articles, User $user, Platform $platform): array
{
    if (!$user->isProfileComplete()) {
        return ['published' => 0, 'needsInput' => 0, 'profileIncomplete' => true];
    }
    // ...
}
```

**Rule:** Every multi-field business predicate gets a named method on the entity. The caller (Application service or Handler) reads it in a single call. The EntryPoint layer reads the return value of the service/postArticlesHandler — never the raw entity fields.

### Repository Interfaces
Declare here. Never import Doctrine in Domain.

```php
// Domain/Repository/ArticleRepositoryInterface.php
interface ArticleRepositoryInterface {
    public function findById(ArticleId $id): ?Article;
    public function save(Article $article): void;
    public function findDraftsByUser(UserId $userId): array;
}
```

---

## Application Layer

**Purpose:** Orchestrate use cases. Depends only on Domain interfaces. Never on Doctrine or Symfony internals.

In CQRS-lite terms, this layer owns write use cases (`Command` + `Handler`).
Read contracts (`*ProviderInterface`, `*QueryServiceInterface`) belong in `Application/Query`, with implementations in Infrastructure.

### Application Service as Guard — Domain Exceptions

When a business rule must be checked before performing a side-effect (e.g., upload, publish), the **Application service** throws a specific **Domain exception** instead of returning a boolean flag. The EntryPoint (FormHandler) catches it:

```php
// Domain/Exception/InsufficientTokensException.php  (Domain — no deps)
final class InsufficientTokensException extends RuntimeException
{
    public static function forUser(int $userId): self
    {
        return new self(sprintf('User %d has no AI tokens remaining.', $userId));
    }
}

// Application/Service/ImageUploadService.php
public function uploadImages(array $images, User $user): ImageBatch
{
    if (!$user->hasTokens()) {
        throw InsufficientTokensException::forUser((int) $user->getId()); // guard here ✅
    }
    // ... do upload
}

// UI/Service/AddImagesFormHandler.php  (catches the domain exception)
try {
    $batch = $this->imageUploadService->uploadImages($images, $user);
} catch (InsufficientTokensException) {
    return new AddImagesResult(redirectNeeded: false, flashes: ['error' => 'Vyčerpali jste všechny AI tokeny.']);
}
```

**Why throw in Application, catch in UI?** The Application service doesn't know about flash messages or HTTP responses. It communicates failure via typed exceptions. The UI (FormHandler) translates them into user-friendly messages. This keeps Application layer pure.

### Application Service Result — typed VOs, not arrays

When an Application Service returns **a multi-field result** (success flag + error message, or counts), return a `final readonly` Result VO instead of a raw array. Raw arrays have no types, no IDE completion, and silent key-typo bugs.

```php
// ❌ Stringly-typed — no type safety, silent typo hazard
public function publishArticles(...): array          // array{published: int, needsInput: int, profileIncomplete?: bool}
{
    return ['published' => 0, 'needsInput' => 0, 'profileIncomplete' => true];
}
// caller: $result['profileIncomplete']   ← typo undetected

// ✅ Typed Result VO — IDE navigable, refactor-safe
final readonly class PublishArticlesResult {
    public function __construct(
        public int  $published,
        public int  $needsInput,
        public bool $profileIncomplete = false,
    ) {}
}

public function publishArticles(...): PublishArticlesResult {
    return new PublishArticlesResult(published: 0, needsInput: 0, profileIncomplete: true);
}
// caller: $result->profileIncomplete     ← typed, IDE-navigable
```

**Where Result VOs live:** `Application/DTO/Response/` — alongside ViewModels.

**Naming:** `Verb + Entity + Result` — `PublishArticlesResult`, `WithdrawArticleResult`, `BatchOperationResult`.

**Rule:** this applies to Application *Services* (not Handlers — Handlers return `void` and communicate failure via Domain exceptions).

### Clock — never use `new DateTimeImmutable()` in handlers or services

`new DateTimeImmutable()` in a postArticlesHandler makes it impossible to write a deterministic unit test. Use `ClockInterface` (PSR-20 / Symfony Clock component):

```php
// ❌ Untestable
$article->publish(new DateTimeImmutable());

// ✅ Inject clock — fully testable
use Psr\Clock\ClockInterface;

final readonly class PublishArticleHandler {
    public function __construct(
        private ArticleRepositoryInterface $articles,
        private ClockInterface $clock,         // ← injected
    ) {}

    public function __invoke(PublishArticleCommand $command): void {
        $article = $this->articles->findById($command->articleId)
            ?? throw new ArticleNotFoundException($command->articleId);

        $article->publish($this->clock->now());  // ← testable
        $this->articles->save($article);
    }
}
```

In tests: `new MockClock(new DateTimeImmutable('2024-01-01'))`.

### Commands & Queries

Commands are **immutable data carriers** — always `final readonly class`:

```php
// Application/Command/PublishArticleCommand.php
final readonly class PublishArticleCommand {   // ← final readonly, never just readonly
    public function __construct(
        public ArticleId $articleId,
        public UserId $publishedBy,
    ) {}
}
```

**Naming rule:** `Verb + Entity + Command` — `PublishArticleCommand`, `RemoveArticleCommand`, `UpdateUserProfileCommand`. Never abbreviated (`PublishCommand`) — breaks when a second entity needs the same verb.

### Handler naming

`Verb + Entity + Handler` — `PublishArticleHandler`, `RemoveArticleHandler`, `UpdateUserProfileHandler`.

- One Handler per Command — never merge two commands into one Handler
- PHPat enforces: `final` + `__invoke` only
- **Handlers MUST be `final readonly class`** — all dependencies are constructor-injected and immutable

```php
// ✅ Correct
final readonly class PublishArticleHandler {
    public function __construct(
        private ArticleRepositoryInterface $articles,
        private ClockInterface $clock,
    ) {}

    public function __invoke(PublishArticleCommand $command): void { ... }
}

// ❌ Wrong — missing readonly
final class PublishArticleHandler { ... }
```

### Input DTOs
Map raw input immediately. Never pass arrays downstream.

```php
readonly class PublishArticleRequest {
    public function __construct(
        public string $articleId,
    ) {}

    public static function fromRequest(Request $request): self {
        return new self(
            articleId: $request->attributes->getString('id'),
        );
    }
}
```

---

### No arrays in Commands — use typed DTOs

**Rule:** `App\Application\Command\*` fields must be typed DTOs, never `array<string, mixed>`.

This applies in particular to `ClickerCommand`, which wraps AMQP payloads sent to the clicker/AI-agent. Every payload is a `final readonly` DTO implementing `ClickerPayloadInterface` (`Application/DTO/Clicker/`).

```
Application/DTO/Clicker/
├── ClickerPayloadInterface      (toArray(): array<string, mixed>)
├── ActionInputPayload           (jobId, code)
├── ActionPublishPayload         (jobId, articleId, userId, platform, ArticleData, CredentialData)
├── ActionDeletePayload          (jobId, articleId, userId, platform, articleUrl, CredentialData)
├── ActionGroupImagesPayload     (jobId, batchId, list<ImageData>, vehicleIdentifier, ?Condition)
├── ActionEnrichVehiclePayload   (jobId, articleId, vin, spz, list<ImageData>)
├── ActionSuggestPricePayload    (jobId, articleId, title, description, condition)
├── ArticleData                  (nested: full article fields + list<PublishImageData>)
├── CredentialData               (login, password)
├── ImageData                    (path, url — AI-agent actions)
└── PublishImageData             (name, mimetype, url — clicker publish)
```

`ClickerCommandSerializer` calls `$command->getPayload()->toArray()` before `json_encode()`.

Scalar lists (`list<string>`) are allowed — the rule targets untyped associative arrays only.

---

### Form DTO (Payload) — Mutable Input DTO for Symfony Forms

When a Symfony form needs a `data_class`, **do NOT use a Domain Entity**. Use a dedicated Payload DTO in `App\Application\DTO\Payload\`.

**Why not the entity?**  The form's `PropertyAccessor` writes POST data (strings) directly into the object, bypassing domain invariants. The entity must never be in an intermediate/invalid state.

```php
// Application/DTO/Payload/ArticleUpdatePayload.php
class ArticleUpdatePayload                      // NOT readonly — PropertyAccessor needs to write
{
    public int|string|null $id = null;          // HiddenType returns string from HTTP
    public ?string $title = null;
    public ?float  $price = null;
    public ?Category  $category = null;
    public ?Condition $condition = null;
    /** @var array<string, mixed>|null */
    public ?array $metaFields = null;           // decoded by JsonStringTransformer

    public static function fromArticle(Article $article): self  // factory for pre-population
    {
        $p = new self();
        $p->id          = $article->getId();
        $p->title       = $article->getTitle();
        $p->price       = $article->getPrice();
        $p->category    = $article->getCategory();
        $p->condition   = $article->getCondition();
        return $p;
    }
}
```

**Rules:**
- Lives in `Application\DTO\Payload\` (shared between UI and Application)
- `class` (not `readonly`) so Symfony's PropertyAccessor can populate it
- Always has a static `fromEntity()` / `fromXxx()` factory — the Action never builds it field-by-field
- After `$form->getData()` the Action hands it directly to a Command constructor — no raw field reading
- **NEVER** place Payload DTOs in `EntryPoint\Payload\` — that namespace is deprecated/empty

### JSON Request DTOs — `Application\DTO\Request\*Request`

For JSON API endpoints (not form-based), use a `*Request` DTO in `Application\DTO\Request\`:

```php
// Application/DTO/Request/ArticleInputRequest.php
final readonly class ArticleInputRequest
{
    public function __construct(
        public readonly string $inputType = '',
        public readonly array  $fields    = [],
        public readonly string $code      = '',
    ) {}

    public static function fromJsonBody(string $content, string $inputType): self { ... }
}
```

- Lives in `Application\DTO\Request\`
- `readonly` or `final readonly` (no PropertyAccessor)
- `fromJsonBody()` factory encapsulates JSON parsing
- **NOT** in `EntryPoint\Payload\`

### DataTransformer — Hidden JSON Fields in Forms

When a form submits data as a JSON string in a `HiddenType` field, use a `DataTransformerInterface` to convert between `array` (model) and `string` (view):

```php
// UI/Form/DataTransformer/JsonStringTransformer.php
final class JsonStringTransformer implements DataTransformerInterface
{
    public function transform(mixed $value): string        // array → JSON for rendering
    {
        return is_array($value) && $value !== [] ? json_encode($value, JSON_THROW_ON_ERROR) : '';
    }

    public function reverseTransform(mixed $value): ?array // JSON → array after submit
    {
        if (!is_string($value) || '' === $value) return null;
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : null;
    }
}
```

Wire it after `$builder->add(...)`:
```php
$builder->get('metaFields')->addModelTransformer(new JsonStringTransformer());
```

The corresponding JS must write to the **Symfony-rendered** hidden input, not create a new raw input:
```js
// ✅ Correct — targets the field Symfony already rendered
const hidden = form.querySelector('input[id$="_metaFields"]');
if (hidden) hidden.value = JSON.stringify(meta);

// ❌ Wrong — creates a stray input outside the form namespace
hidden = document.createElement('input');
hidden.name = 'meta_fields';  // bypasses Symfony form; raw $request->request->get() needed
```

### Form Types — Must Be Final

All Symfony Form types must be `final class` to prevent accidental extension:

```php
// ✅ Correct
final class EditArticleType extends AbstractType { ... }

// ❌ Wrong — not final
class EditArticleType extends AbstractType { ... }
```

### ViewModel Provider — Read-side Assembly

When a page needs data aggregated from multiple sources (repositories, services, domain registries), extract it to an `Application\Query\*ViewModelProvider`. Never assemble view-data loops in the Action.

```php
// Application/Query/ArticleEditViewModelProvider.php
final class ArticleEditViewModelProvider
{
    public function __construct(
        private readonly ArticleSubmissionRepositoryInterface $articleSubmissionRepository,
        private readonly ArticlePublishServiceInterface $articlePublishService,
    ) {}

    /** @param Article[] $articles */
    public function build(array $articles, User $user): ArticleEditViewModel
    {
        return new ArticleEditViewModel(
            submissions:        $this->buildSubmissionsMap($articles),
            availablePlatforms: $this->articlePublishService->getAvailablePlatforms($user),
            categoryFields:     $this->buildCategoryFields(),
        );
    }

    private function buildSubmissionsMap(array $articles): array { ... }
    private function buildCategoryFields(): array { ... }
}
```

**Companion ViewModel DTO** in `Application\DTO\Response\`:

```php
// Application/DTO/Response/ArticleEditViewModel.php
final readonly class ArticleEditViewModel
{
    public function __construct(
        public array $submissions,        // array<int, array<string, ArticleSubmission>>
        public array $availablePlatforms, // Platform[]
        public array $categoryFields,     // array<string, mixed>
    ) {}
}
```

**Rules:**
- `final class` (has injected services, not readonly)
- Returns typed ViewModel DTOs — never raw entities to the template
- Lives in `Application\Query\` alongside Provider interfaces

---

## EntryPoint Layer

**Purpose:** HTTP/CLI boundary only. Translate Request → Command → Response. Nothing else.

> **Action is an adapter, not an orchestrator.**
> Its only job: map the HTTP protocol (Request) to the application language (Commands/Queries) and return a Response.

CQRS-lite rule in EntryPoint:
- Write endpoints dispatch Commands through `MessageBusInterface`
- Read endpoints call `ProviderInterface`/`QueryServiceInterface` directly

### Thin Action — the cardinal rule

Action **must not** make business decisions. If you see any of these — it is a bug:

```php
// ❌ Business logic in Action — move to Handler or Domain Entity
if ($user->hasTokens()) { ... }
if ($article->isReady()) { ... }
$article->setStatus(ArticleStatus::Published);  // state change without Handler
```

| ❌ Fat Action (forbidden) | ✅ Thin Action (correct) |
|---|---|
| Direct repository write injection | Only `MessageBusInterface` for writes |
| Direct Domain repository injection for reads (e.g. `ArticleRepositoryInterface`) | Inject `Application\Query\*Interface` or Application service delegation method |
| `if ($user->hasRole('ADMIN'))` logic | `#[IsGranted]` + Voter |
| File upload / resize / API calls | Hidden behind Command + Handler |
| More than ~20 lines in `__invoke` | Extract orchestration to `EntryPoint\Service\*FormHandler` |
| `$em->flush()` in controller | EntityManager belongs in Infrastructure only |
| Form creation loop + `handleRequest` loop in `__invoke` | Extract to `EntryPoint\Service\*FormHandler` |
| Mapping Entity fields → form data inline in `__invoke` | `Payload::fromEntity()` static factory |
| Building `$submissions`/`$categoryFields` in `__invoke` | `Application\Query\*ViewModelProvider` |
| `$request->request->get('some_field')` bypassing form | Add field to form + DataTransformer |
| `if (!$user->getName() \|\| !$user->getAddress() ...)` in Action | `$user->isProfileComplete()` Domain method, check in Application service |
| `userRepository->remove($article)` in Action | Dispatch `RemoveArticleCommand`, Handler calls remove |
| Multiple `if/else` with flash messages in `__invoke` | `*FormHandler` returns `*Result` with `$flashes` array |
| Form `createForm/handleRequest/isSubmitted/isValid` inline in `__invoke` | Extract to `EntryPoint\Service\Handler\*FormHandler` |

### What MUST be in Action

1. **Auth guard** — `#[IsGranted]` attribute or `$this->denyAccessUnlessGranted()`
2. **Fetch data for display** — read via `Application\Query\*Interface` or Application service method (NOT direct Domain repo injection)
3. **Delegate form processing** — call `EntryPoint\Service\Handler\*FormHandler`
4. **Dispatch or redirect** — dispatch Command via `MessageBusInterface`, add flash, redirect
5. **Delegate view model assembly** — call `Application\Query\*ViewModelProvider`
6. **Return Response** — `render()`, `redirectToRoute()`, `JsonResponse`

### Pre-flight checklist (run before writing any Action)

- [ ] Only `__invoke()` method — no other public methods
- [ ] No direct Domain repository injection — inject `Application\Query\*Interface` or Application service instead
- [ ] No `EntityManagerInterface` injection — replace immediately with Command/Handler
- [ ] No business `if` conditions — move to Domain or Handler
- [ ] No `$request->request->get()` for form data — use typed Payload DTO + DataTransformer
- [ ] No form creation / `handleRequest` / `isSubmitted` / `isValid` inline — extract to `EntryPoint\Service\Handler\*FormHandler`
- [ ] No view-data assembly loops (`foreach Platform::cases()` etc.) — extract to `Application\Query\*ViewModelProvider`
- [ ] ACL via `#[IsGranted]` or `$this->denyAccessUnlessGranted()` — never manual `if ($user === ...)` in Action
- [ ] Never pass Entity directly to Twig — use Response DTO / Normalizer
- [ ] `__invoke` fits in ~20 lines — if longer, extract to Service

### UI Service — `App\EntryPoint\Service\*`

When an Action needs to handle **multi-step form orchestration** (loop over entities, create named forms, handle requests, dispatch commands), extract it to an `EntryPoint\Service` class.

**Pattern: `*FormHandler`**

```php
// UI/Service/ArticleEditFormHandler.php
final class ArticleEditFormHandler
{
    public function __construct(
        private readonly FormFactoryInterface $formFactory,
        private readonly MessageBusInterface $messageBus,
    ) {}

    /** @param Article[] $articles */
    public function handle(array $articles, Request $request): ArticleEditFormsResult
    {
        $forms = [];
        foreach ($articles as $article) {
            $payload = ArticleUpdatePayload::fromArticle($article);        // ← factory, not inline mapping
            $form = $this->formFactory->createNamed('edit_article_'.$article->getId(), EditArticleType::class, $payload);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $submitted = $form->getData();
                // security check: form id matches article id
                if ((int) $submitted->id === $article->getId()) {
                    $this->messageBus->dispatch(UpdateArticleCommand::fromPayload($submitted, $form->get('images')->getData() ?? []));
                    return new ArticleEditFormsResult(forms: [], dispatched: true);
                }
            }
            $forms[] = ['form' => $form->createView(), 'article' => $article];
        }
        return new ArticleEditFormsResult(forms: $forms, dispatched: false);
    }
}
```

**Pattern: `*FormsResult`** — simple readonly VO returned by the postArticlesHandler:

```php
// UI/Service/ArticleEditFormsResult.php
final readonly class ArticleEditFormsResult
{
    /** @param list<array{form: mixed, article: mixed}> $forms */
    public function __construct(
        public array $forms,
        public bool  $dispatched,   // true → caller should redirect
    ) {}
}
```

**Rules for `EntryPoint\Service\*`:**
- May depend on `FormFactoryInterface`, `MessageBusInterface`, Application DTOs, UI Forms
- Must **not** depend on `EntityManagerInterface` or Infrastructure classes
- Must **not** inject Domain repository interfaces directly — use `Application\Query\*Interface` for reads
- For write operations, dispatch Commands via `MessageBusInterface` — never call repositories
- `final class` (not readonly — has constructor-injected services)

### Reference implementation — Edit Action (multi-form + ViewModel)

The canonical example of a fully-refactored edit Action. `__invoke` is ~20 lines and has **zero loops**:

```php
#[Route('/market/article/{id}/edit', name: 'app_edit_article')]
#[IsGranted('ROLE_USER')]
final class EditArticleAction extends AbstractController
{
    public function __construct(
        private readonly ArticleQueryServiceInterface $articleEditQueryService, // Application\Query
        private readonly ArticleEditFormHandler           $addImagesFormHandler,              // EntryPoint\Service
        private readonly ArticleEditViewModelProvider     $articleEditViewModelProvider,       // Application\Query
    ) {}

    public function __invoke(string $id, Request $request): Response
    {
        /** @var User $user */
        $user     = $this->getUser();
        $articles = $this->articleEditQueryService->findByIdsForUser(explode('-', $id), $user);

        $formResult = $this->addImagesFormHandler->handle($articles, $request);
        if ($formResult->dispatched) {
            $this->addFlash('success', 'Article updated successfully!');
            return $this->redirectToRoute('app_edit_article', ['id' => $id]);
        }

        $viewModel = $this->articleEditViewModelProvider->build($articles, $user);

        return $this->render('market/edit_article.html.environment', [
            'forms'              => $formResult->forms,
            'submissions'        => $viewModel->submissions,
            'availablePlatforms' => $viewModel->availablePlatforms,
            'categoryFields'     => $viewModel->categoryFields,
        ]);
    }
}
```

### Reference implementation — simple write Action with FormHandler

The canonical form-with-result pattern. `AddImagesFormHandler` owns all form logic; Action has zero branching:

```php
// UI/Service/AddImagesFormHandler.php
final class AddImagesFormHandler
{
    public function __construct(
        private readonly FormFactoryInterface $formFactory,
        private readonly ImageUploadService $imageUploadService,
    ) {}

    public function createForm(): FormInterface
    {
        return $this->formFactory->create(AddArticleImages::class);
    }

    public function handle(FormInterface $form, User $user, Request $request): AddImagesResult
    {
        $form->handleRequest($request);
        if (!$form->isSubmitted()) {
            return new AddImagesResult(redirectNeeded: false);
        }
        $images = $form->get('images')->getData();
        if (empty($images)) {
            return new AddImagesResult(redirectNeeded: false, flashes: ['error' => 'Please select at least one image.']);
        }
        if (!$form->isValid()) {
            return new AddImagesResult(redirectNeeded: false, flashes: ['error' => 'Image upload failed.']);
        }
        try {
            $batch = $this->imageUploadService->uploadImages($images, $user);
        } catch (InsufficientTokensException) {
            return new AddImagesResult(redirectNeeded: false, flashes: ['error' => 'Vyčerpali jste všechny AI tokeny.']);
        }
        return new AddImagesResult(redirectNeeded: true, batchId: (int) $batch->getId());
    }
}

// UI/Service/AddImagesResult.php
final readonly class AddImagesResult
{
    /** @param array<string, string> $flashes */
    public function __construct(
        public bool $redirectNeeded,
        public ?int $batchId = null,
        public array $flashes = [],
    ) {}
}

// UI/Action/AddImagesAction.php
final class AddImagesAction extends AbstractController
{
    public function __construct(private readonly AddImagesFormHandler $addImagesFormHandler) {}

    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $form = $this->addImagesFormHandler->createForm();
        $result = $this->addImagesFormHandler->handle($form, $user, $request);

        foreach ($result->flashes as $type => $message) {
            $this->addFlash($type, $message);
        }
        if ($result->redirectNeeded) {
            return $this->redirectToRoute('app_processing', ['batchId' => $result->batchId]);
        }
        return $this->render('market/images_loader.html.environment', [
            'form' => $form->createView(),
            'tokenBalance' => $user->getTokenBalance(),
        ]);
    }
}
```

**Key rule:** When a single-form Action needs conditional flash messages or error branches — extract to a `*FormHandler` returning a `*Result` VO with a `$flashes` array. Action loops over `$result->flashes` and calls `addFlash()`.

### Guard Clause in FormHandlers

Always use the **guard clause** (early return on failure) instead of nesting the happy path in a positive `if`:

```php
// ❌ Nested happy path — hard to read, grows rightward
if ($form->isSubmitted() && $form->isValid()) {
    // ... long happy path ...
    return ['saved' => true];
}
return ['saved' => false];

// ✅ Guard clause — happy path runs at root indent level
if (!$form->isSubmitted() || !$form->isValid()) {
    return ['saved' => false];
}
// ... long happy path ...
return ['saved' => true];
```

Same rule applies to any conditional block where the "skip" branch is shorter than the "proceed" branch.

### SRP in FormHandlers — One Responsibility Per Handler

A `*FormHandler` must handle **one form concern only**. When a postArticlesHandler processes two logically independent forms, split it:

```
// ❌ One postArticlesHandler, two concerns
UserProfileFormHandler::handle()
    → creates profile form + dispatches UpdateUserProfileCommand
    → iterates CREDENTIAL_SERVICES + calls ServiceCredentialManager

// ✅ Two handlers, one concern each
UserProfileFormHandler::handle()     → profile form only → UserProfileFormResult
UserCredentialsFormHandler::handle() → credentials loop only → UserCredentialsResult
```

The **Action** becomes the explicit orchestrator:
```php
$profileResult = $this->userProfileFormHandler->handle($user, $request);
if ($profileResult->redirectNeeded) { ... return redirect; }

$credentialsResult = $this->userCredentialsFormHandler->handle($user, $request);
if ($credentialsResult->redirectNeeded) { ... return redirect; }

return $this->render('...', [...]);
```

**Rule of thumb:** Each `*FormHandler` should have one reason to change — profile fields change → only `UserProfileFormHandler` changes; credential services change → only `UserCredentialsFormHandler` changes.


### Read-side Action (Query)

```php
#[Route('/articles/{id}', methods: ['GET'])]
final class GetArticleByIdAction extends AbstractController
{
    public function __construct(
        private readonly ArticleProviderInterface $articles,
    ) {}

    public function __invoke(int $id): JsonResponse
    {
        $dto = $this->articles->findById($id)
            ?? throw new NotFoundHttpException();

        return new JsonResponse($dto->toArray());
    }
}
```

---

## Infrastructure Layer

**Purpose:** Implement all interfaces. Wire framework to domain.

In CQRS-lite, this layer also contains read implementations in `Infrastructure/Query`.

### Doctrine Repository

```php
final class DoctrineArticleRepository extends ServiceEntityRepository implements ArticleRepositoryInterface {
    public function findById(ArticleId $id): ?Article {
        return $this->find($id->toString());
    }

    public function save(Article $article): void {
        $this->getEntityManager()->persist($article);
        $this->getEntityManager()->flush();
    }
}
```

`EntityManager` belongs here and **only** here.

### Voters — all ACL lives here

Permission string constants live in **`App\Domain\Security\Permission`** (Domain layer), NOT in Voters:

```php
// Domain/Security/Permission.php
final class Permission {
    public const ARTICLE_DELETE   = 'ARTICLE_DELETE';
    public const ARTICLE_WITHDRAW = 'ARTICLE_WITHDRAW';
    public const IMAGE_DELETE     = 'IMAGE_DELETE';
    // ...
}
```

Voters (Infrastructure) reference `Permission::*`; Actions (EntryPoint) also reference `Permission::*` directly — neither layer has a dependency on the other:

```php
// Infrastructure/Security/ArticleVoter.php
final class ArticleVoter extends Voter {
    public const DELETE = Permission::ARTICLE_DELETE; // delegates to Domain constant

    protected function supports(string $attribute, mixed $subject): bool {
        return in_array($attribute, [Permission::ARTICLE_DELETE, ...], true)
            && $subject instanceof Article;
    }
    // ...
}

// EntryPoint/Action/DeleteArticleAction.php
#[IsGranted(Permission::ARTICLE_DELETE, subject: 'article')]  // ✅ Domain constant, no Infrastructure import
final class DeleteArticleAction extends AbstractController { ... }
```

**Anti-pattern:** importing `ArticleVoter` or `ImageVoter` into an Action to use their constants — this creates an EntryPoint → Infrastructure dependency that PHPat forbids.

---

## Anti-patterns from Current Codebase — Do Not Replicate

| ❌ Current pattern | ✅ Target pattern |
|---|---|
| Controller injects `EntityManagerInterface` | Controller injects `MessageBusInterface` only |
| `$request->request->all()` in controller | `ReadonlyDTO::fromRequest($request)` |
| `$request->request->get('field')` bypassing form | Add field to Symfony form + `DataTransformerInterface` |
| Repository with no interface | Implements `RepositoryInterface` from Domain |
| Service contains business logic + persistence | Handler (logic) + Repository (persistence) |
| `use App\Infrastructure\Security\ArticleVoter` in Action | `use App\Domain\Security\Permission` — constants live in Domain, not Voter |
| `#[IsGranted(ArticleVoter::DELETE)]` in Action | `#[IsGranted(Permission::ARTICLE_DELETE)]` |
| Anemic entity with setters only | Named state-transition methods with invariants |
| `new DateTimeImmutable()` in Handler or Application Service | `ClockInterface::now()` |
| `new DateTimeImmutable()` passed from EntryPoint to Application Service | Inject `ClockInterface` into the Application Service; remove the parameter |
| Application Service returns `array{success: bool, error?: string}` | `final readonly class *Result` VO with typed public properties |
| Action reads `$result['success']` / `$result['error']` / `$result['published']` | `$result->success` / `$result->error` / `$result->published` (typed VO) |
| Entity only has `setStatus(BatchStatus::X)` for every transition | Named domain methods: `startProcessing()`, `complete()`, `fail()`, `requestInput()` |
| `plain class ServiceFoo` (no `final`) | `final class ServiceFoo` — all new classes are `final` by default |
| `string $email` property on Entity | `Email` Value Object |
| EntryPoint reads data via Command Handler | EntryPoint reads via `ProviderInterface` / `QueryServiceInterface` |
| Query service mutates state | State changes only in `App\Application\Handler\*Handler` |
| `string $status = 'pending'` property | `SubmissionStatus $status = SubmissionStatus::Pending` |
| `setStatus('processing')` call | `setStatus(BatchStatus::Processing)` enum call |
| `'completed' === $batch->getStatus()` | `BatchStatus::Completed === $batch->getStatus()` |
| `in_array($status, ['failed', 'withdrawn'])` | `$status->canRetry()` (method on enum) |
| `private static array $cache = []` | Inject `CacheInterface` via constructor |
| Form `data_class` set to Domain Entity | Form `data_class` set to `Application\DTO\Payload\*Payload` |
| Inline `$payload->field = $entity->getField()` in Action | `Payload::fromEntity($entity)` static factory |
| Form creation loop + `handleRequest` loop in `__invoke` | Extract to `EntryPoint\Service\*FormHandler` |
| `foreach Platform::cases()` in Action to build view data | `Application\Query\*ViewModelProvider::build()` |
| `final class UpdateArticleCommand` with per-property `readonly` | `final readonly class UpdateArticleCommand` |
| `!$user->getName() \|\| !$user->getAddress()...` in Action | `$user->isProfileComplete()` Domain method; guard in Application service |
| Token / business rule check in EntryPoint (Action or FormHandler) | Throw `Domain\Exception\*Exception` in Application service; catch in EntryPoint `*FormHandler` |
| `userRepository->findByEmail()` + service call in Action | `SendPasswordResetEmailCommand($email)` — Handler does lookup + action |
| Inline `createFormBuilder()` in Action | Separate `*Type extends AbstractType` form class |
| One `*FormHandler` handling both profile form AND credential forms loop | Split into `UserProfileFormHandler` (profile) + `UserCredentialsFormHandler` (credentials) — SRP |
| `if ($form->isSubmitted() && $form->isValid()) { long code; return; } return;` | Guard clause: `if (!$form->isSubmitted() \|\| !$form->isValid()) { return; }` happy path runs unindented |
| `final class DeleteArticleHandler` (missing `readonly`) | `final readonly class DeleteArticleHandler` — all Handlers immutable |
| `class ArticleInputRequest` (missing `final readonly`) | `final readonly class ArticleInputRequest` — Request DTOs are immutable |
| `class EditArticleType extends AbstractType` (missing `final`) | `final class EditArticleType extends AbstractType` — all Form types `final` |
| FormHandler injects `ArticleRepositoryInterface` directly | Inject `Application\Query\*Interface` for reads; `MessageBusInterface` for writes |
| `use Symfony\Component\Mailer\MailerInterface` in Application Service | Create `Application\Port\MailerInterface`; implement in Infrastructure |
| `use Doctrine\Common\Collections\ArrayCollection` in Application Service | Let Domain Entity manage collection creation internally |
| `class ServiceCredentialManager` (missing `final`) | `final class ServiceCredentialManager` — all classes `final` by default |
| Action with 70+ lines and input-type dispatch logic | Extract to `EntryPoint\Service\Handler\*Handler` returning `*Result` VO |
| `foreach ($user->getArticles() as $article) { $repo->findAllByArticle($article); }` — N+1 query | Add `findAllByArticles(array $articles): array` batch method to the interface; group results by article ID before the loop |
| `if (null === $foo)` / `if (true === $bar)` — Yoda style | `if ($foo === null)` / `if ($bar === true)` — natural reading order; enforced by `'yoda_style' => false` in PHP CS Fixer |

---

## Definition of Done

| Changed | Required test | Location |
|---|---|---|
| Domain Entity / VO / Event | Unit test | `tests/Unit/Domain/` |
| Application Handler | Unit test with mocked interfaces + MockClock | `tests/Unit/Application/` |
| EntryPoint Action | Functional test (real HTTP call) | `tests/Functional/EntryPoint/` |
| Infrastructure Repository | Integration test | `tests/Integration/Infrastructure/` |
| Clicker / AI agent integration | Integration test | `tests/Integration/` |
| **Architecture boundary** | **Architecture test** | `tests/Architecture/` |

### Architecture Test (automate the rules)

Use `phpat/phpat` (registered in `phpstan.neon`) to verify layer boundaries. Tests live in `tests/Architecture/LayerBoundaryTest.php`:

```php
// Domain isolation
PHPat::rule()
    ->classes(Selector::inNamespace('App\Domain'))
    ->shouldNot()->dependOn()
    ->classes(Selector::inNamespace('App\Infrastructure'),
              Selector::inNamespace('App\EntryPoint'), ...);

// Commands must be readonly (immutable data carriers)
PHPat::rule()
    ->classes(Selector::inNamespace('App\Application\Command'))
    ->should()->beReadonly();

// Handlers must be final and invokable
PHPat::rule()
    ->classes(Selector::inNamespace('App\Application\Handler'))
    ->should()->beFinal();
PHPat::rule()
    ->classes(Selector::inNamespace('App\Application\Handler'))
    ->should()->beInvokable();

// Value Objects must be readonly (enums excluded — inherently immutable)
PHPat::rule()
    ->classes(Selector::inNamespace('App\Domain\ValueObject'))
    ->excluding(Selector::isEnum())
    ->should()->beReadonly();
```

Custom phpstan rules in `tests/PHPStan/` catch violations automatically:
- `NoStringLiteralPropertyDefaultRule` — forbids `$status = 'pending'` in Domain/Application
- `NoMutableStaticPropertiesRule` — forbids non-readonly static properties in App\ classes
- `NoRepositoryCallInLoopRule` — forbids read repository methods (`find*`, `get*`, `count*`) inside `foreach` loops; fires on `$this->*Repository` property calls; excludes write methods (`save`, `delete`, `remove`)

### Unit test skeleton (always sketch when generating a postArticlesHandler)

Use **Object Mother** from `tests/Shared/Mother/` to build test fixtures — never construct entities inline with raw values:

```php
// tests/Shared/Mother/ArticleMother.php  (already exists — use it)
final class ArticleMother {
    public static function draft(): Article { ... }
    public static function published(): Article { ... }
}

final class PublishArticleHandlerTest extends TestCase {
    public function testPublishesArticleAndSaves(): void {
        $clock   = new MockClock(new DateTimeImmutable('2024-06-01'));
        $repo    = $this->createMock(ArticleRepositoryInterface::class);
        $article = ArticleMother::draft();          // ← Object Mother, not inline construction

        $repo->method('findById')->willReturn($article);
        $repo->expects($this->once())->method('save')->with($article);

        $postArticlesHandler = new PublishArticleHandler($repo, $clock);
        ($postArticlesHandler)(new PublishArticleCommand($article->getId()));

        $this->assertTrue($article->isPublished());
        $this->assertEquals(new DateTimeImmutable('2024-06-01'), $article->getPublishedAt());
    }
}
```

**Why Object Mother?** Inline `new Article(...)` in every test duplicates construction details. When the entity constructor changes, all tests break. A single `ArticleMother::draft()` factory absorbs the change.

---

## Current Directory Structure

```
src/be/src/
├── Domain/
│   ├── Entity/           # Doctrine-mapped entities (Article, User, ImageBatch…)
│   ├── Enum/             # Backed enums with domain logic (Platform, Category, Condition)
│   ├── Event/            # Domain/integration events (ArticlePublishedEvent, ClickerEvent)
│   ├── Exception/        # Domain exceptions (ArticleNotFoundException…)
│   ├── Port/             # Outbound port interfaces for external systems (PlatformFieldProviderInterface)
│   ├── Registry/         # Domain registries (CategoryFieldRegistry)
│   ├── Repository/       # Repository interfaces (ArticleRepositoryInterface…)
│   ├── Security/         # Permission constants (Permission::ARTICLE_DELETE etc) — used by Actions and Voters
│   └── ValueObject/      # Immutable value types + status enums (SubmissionStatus, BatchStatus)
│
├── Application/
│   ├── Command/          # Immutable write DTOs (final readonly classes): PublishArticleCommand…
│   ├── DTO/
│   │   ├── Payload/      # Mutable form DTOs used as Symfony form data_class (*Payload, with fromEntity() factory)
│   │   ├── Request/      # Shared input DTOs (BatchInputRequest — used by both UI and Application)
│   │   └── Response/     # Read model DTOs (ArticleResponse, ArticleEditViewModel)
│   ├── Handler/          # Write use-case handlers (final + __invoke): PublishArticleHandler…
│   ├── Logging/          # TraceContext (static context holder — legitimate MDC pattern)
│   ├── Query/            # Read-side port interfaces + ViewModel providers (ArticleProviderInterface,
│   │                     #   ArticleEditViewModelProvider, BatchProviderInterface)
│   └── Service/          # Application services (BatchService, ImageUploadService, ArticleFactory…)
│
├── Infrastructure/
│   ├── Bus/              # Messenger middleware/serializers (ClickerCommandMiddleware…)
│   ├── EventListener/    # Symfony event listeners (ArticlePublishedListener)
│   ├── Logging/          # Logging adapters (LokiHandler, TraceIdProcessor)
│   ├── Persistence/
│   │   └── Doctrine/     # Repository implementations (ArticleRepository…)
│   ├── Query/            # Provider implementations (DoctrineArticleProvider, DoctrineBatchProvider)
│   ├── Scheduler/        # Symfony Scheduler tasks
│   └── Service/          # Technical services (ImageService)
│
└── EntryPoint/          # namespace: App\EntryPoint\* (physical path: src/EntryPoint/)
    ├── Action/           # Single-action controllers (one __invoke per file, ~20 lines max)
    ├── Form/
    │   ├── DataTransformer/  # DataTransformerInterface implementations (JsonStringTransformer)
    │   └── *.php             # Symfony Form types (final class, extends AbstractType)
    ├── Payload/          # ⚠️ DEPRECATED — use Application\DTO\Payload\ for new Payload DTOs
    └── Service/          # UI orchestration services
        ├── Handler/      # *FormHandler classes (form loop + dispatch + return *Result)
        └── *.php         # *Result VOs, ServiceCredentialManager, other UI services
```

---

## Universal Standards

- `declare(strict_types=1);` — mandatory, first line after `<?php`
- All new classes: `final` by default
- Exceptions: specific domain exceptions, never raw `\Exception`
- No `mixed` types without justification
- No untyped `array` — use typed collections or shape doc-blocks
- No `new DateTimeImmutable()` in Application/Domain — always `ClockInterface`
- SOLID: one reason to change per class
- DRY: if logic appears twice, extract it
- KISS: the simplest solution that satisfies the invariants wins

### Immutable Commands (PHP 8.2+)

All Commands **must** be `final readonly class` — they are data carriers that must not change after dispatch:

```php
// ✅ Correct
final readonly class PublishArticleCommand { ... }

// ❌ Wrong — missing readonly at class level
final class PublishArticleCommand {
    public function __construct(
        private readonly ArticleId $articleId,  // per-property readonly is not enough
    ) {}
}
```

PHPat enforces this via `testCommandsMustBeReadonly`.

### Thin EntryPoint — no business logic in Actions

An Action (or CLI Command) is **only an adapter**. The moment you see an `if` with a business condition inside `__invoke`, that code belongs elsewhere:

```php
// ❌ Business condition in Action — MOVE IT
public function __invoke(Article $article): Response {
    if ($article->getStatus() === ArticleStatus::Published) {  // ← business rule
        throw new BadRequestHttpException('Already published');
    }
    ...
}

// ✅ Guard lives in Application Handler or Domain Entity
// Action simply dispatches and trusts the Handler to throw DomainException if invalid
public function __invoke(Article $article): Response {
    $this->messageBus->dispatch(new PublishArticleCommand((int) $article->getId()));
    ...
}
```

| Condition type | Belongs in |
|---|---|
| "User owns this resource" | Symfony Voter (`#[IsGranted]`) |
| "Article can be published now" | Domain Entity method |
| "User has enough tokens" | Application Service (throws DomainException) |
| "Form is submitted and valid" | FormHandler guard clause |
| "HTTP request has CSRF token" | Action (infrastructure concern) |

### No Magic Strings — Enums and Constants Only

Every domain concept that has a fixed set of values **must** be an enum. Never use a string literal to represent state, type, platform, category, or condition.

```php
// ❌ String literals as state — forbidden in Domain and Application
$submission->setStatus('pending');
if ('completed' === $batch->getStatus()) { ... }
return in_array($status, ['failed', 'withdrawn']);

// ✅ Typed enum — explicit, refactor-safe, IDE-navigable
$submission->setStatus(SubmissionStatus::Pending);
if (BatchStatus::Completed === $batch->getStatus()) { ... }
return $submission->getStatus()->canRetry();
```

Enums belong in:
- `Domain/Enum/` — platform/domain classification enums (`Platform`, `Category`, `Condition`)
- `Domain/ValueObject/` — lifecycle status enums (`SubmissionStatus`, `BatchStatus`)

Add domain methods to enums instead of scattering `match`/`in_array` logic:
```php
enum SubmissionStatus: string {
    case Failed   = 'failed';
    case Withdrawn = 'withdrawn';

    public function canRetry(): bool {
        return $this === self::Failed || $this === self::Withdrawn;
    }
}
```

**Doctrine**: Map backed enums with `enumType:` — Doctrine stores the backing string value transparently:
```php
#[ORM\Column(enumType: SubmissionStatus::class, length: 32)]
private SubmissionStatus $status = SubmissionStatus::Pending;
```

**phpstan enforces this**: Custom rules `NoStringLiteralPropertyDefaultRule` and `NoMutableStaticPropertiesRule` in `tests/PHPStan/` will catch violations at static analysis time.

### No Mutable Class State

Classes must not have mutable static properties. Static state creates hidden global state, breaks testability, and violates the Dependency Injection principle.

```php
// ❌ Mutable static state — forbidden
class ArticleCache {
    private static array $cache = [];
}

// ✅ Inject a service instead
final readonly class ArticleCache {
    public function __construct(private CacheInterface $cache) {}
}
```

**Allowed exception**: `Application\Logging\TraceContext` uses static properties intentionally as a PHP MDC (Mapped Diagnostic Context) for distributed tracing propagation. This class is explicitly exempt from the rule.

---

## Accepted Framework Seams in Domain

PHPat architecture tests allow certain Symfony/Doctrine imports **in Domain only** as controlled seams. These are **not free passes** — do NOT add new framework dependencies to Domain without explicit approval.

| Allowed in Domain | Why |
|---|---|
| `Doctrine\ORM\Mapping as ORM` | Persistence metadata attributes — no runtime coupling |
| `Doctrine\Common\Collections\Collection` / `ArrayCollection` | Navigation properties on entities — Doctrine collection API |
| `Symfony\Component\Security\Core\User\UserInterface` | User entity implements for Symfony security integration |
| `Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface` | User entity implements for password hashing |
| `Symfony\Contracts\EventDispatcher\Event` | ArticlePublishedEvent extends for Symfony event dispatcher |

**Rule:** These are legacy seams. When creating **new** Domain classes, do NOT import any Symfony or Doctrine types. New domain events should be plain POPOs implementing a Domain `DomainEventInterface` (not extending Symfony Event).

---

## Known Technical Debt

Current violations that exist in the codebase. Agents must **not replicate** these patterns. When touching these files, fix them.

### Domain Layer — Anemic Entities

The following entities use anemic setters instead of named state-transition methods. **ImageBatch is the positive example** — it has `startProcessing()`, `fail()`, `complete()`, `requestInput()`.

| Entity | Issue | Target |
|---|---|---|
| `Article` | Anemic setters (`setTitle`, `setStatus`, etc.) | Named methods: `publish()`, `withdraw()`, `updateDetails()` |
| `ArticleSubmission` | Anemic setters (`setStatus`, `setJobId`, etc.) | Named methods: `submit()`, `markProcessing()`, `complete()`, `fail()` |
| `Image` | Public properties, no domain logic | Encapsulate, add `reposition()`, `attachToArticle()` |
| `User` | Anemic setters, Symfony interface deps | Extract Symfony to Port, add `register()`, `resetPassword()` |

### Domain Layer — `new DateTimeImmutable()` in Constructors

These entity constructors call `new DateTimeImmutable()` directly instead of receiving time from `ClockInterface`:

- `Article::__construct()` — `$this->createdAt = new DateTimeImmutable();`
- `ArticleSubmission::__construct()` — `$this->createdAt` and `$this->updatedAt`
- `ArticleSubmission::preUpdate()` — lifecycle callback
- `ImageBatch::__construct()` — `$this->createdAt`

**Fix pattern:** Pass `DateTimeImmutable` as constructor parameter; caller (Handler/Service) uses `$clock->now()`.

### Application Layer — Missing `readonly` on Handlers

These Handlers are `final class` but should be `final readonly class`:

- `DeleteArticleHandler`, `EnrichVehicleHandler`, `GroupImagesHandler`
- `PublishArticleHandler`, `RemoveArticleHandler`, `SendPasswordResetEmailHandler`
- `SuggestPriceHandler`, `UpdateArticleHandler`, `UpdateUserProfileHandler`

Only `ClickerEventHandler` is correctly `final readonly class`.

### Application Layer — Infrastructure Leaks

| File | Violation | Fix |
|---|---|---|
| `ArticleFactory` | Imports `Doctrine\Common\Collections\ArrayCollection` | Let entity manage collection creation internally |
| `ForgotPasswordService` | Imports `Symfony\Component\Mailer\MailerInterface` | Create `Application\Port\MailerInterface` port |

### Application Layer — Inject Interfaces, Not Concrete Service Classes

Application Services (e.g., `ArticlePublishService`) **must be declared behind an interface** when injected into other classes (Providers, Handlers, etc.).

**Why:** `final readonly class` is unmockable in PHPUnit; depending on a concrete class couples the consumer to an implementation detail.

**Rule:** For every `final readonly class *Service` in `Application\Service\`, create a companion `*ServiceInterface` in the same namespace. Consumers always inject the interface.

```php
// ✅ Correct
interface ArticlePublishServiceInterface {
    /** @return Platform[] */
    public function getAvailablePlatforms(User $user): array;
}

final readonly class ArticlePublishService implements ArticlePublishServiceInterface { ... }

// Consumer:
public function __construct(
    private ArticlePublishServiceInterface $articlePublishService, // ← interface
) {}
```

### Application Layer — Missing `final readonly` on DTOs

| File | Current | Target |
|---|---|---|
| `DTO/Request/ArticleInputRequest` | `class` | `final readonly class` |
| `DTO/Request/BatchInputRequest` | `class` | `final readonly class` |
| `DTO/Response/ArticleResponse` | `class` | `final readonly class` |

### EntryPoint Layer — Form Types Not Final

| Form Type | Fix |
|---|---|
| `AddArticleImages` | Add `final` modifier |
| `EditArticleType` | Add `final` modifier |
| `ServiceCredentialType` | Add `final` modifier |
| `UserProfileType` | Add `final` modifier |

### EntryPoint Layer — FormHandlers Injecting Domain Repositories

| FormHandler | Violation | Fix |
|---|---|---|
| `PostArticlesHandler` | Injects `ArticleRepositoryInterface` | Use `Application\Query\ArticleQueryServiceInterface` |
| `ServiceCredentialManager` | Injects `ServiceCredentialRepositoryInterface` | Create Application service for credential management |

`ServiceCredentialManager` is also missing the `final` modifier.
