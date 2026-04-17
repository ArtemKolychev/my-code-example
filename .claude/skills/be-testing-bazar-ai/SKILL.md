---
name: be-testing-bazar-ai
description: Testing guardrails for PHPUnit / Symfony 7.3 in the bazar_ai backend. Always activate for: "write test", "create test", "fix failing test", "test postArticlesHandler", "test entity", "test action", "test repository", "add coverage", "test voter", "test controller". Use for all backend tests in src/be/tests/.
---

# Backend Testing Strategy — Pyramid & Best Practices

## Strict Rules (non-negotiable)

1. **No `if`/`switch`/loops in test methods for logic branching.** Guard checks are allowed (e.g. `file_exists` before cleanup), but `if` must never change which code path under test is exercised.
2. **Use `#[DataProvider]` for 2+ structurally identical cases** (same mock setup + same assertion shape, different input/expected).
3. **The `@` error-suppression operator is forbidden everywhere** (PHPStan rule enforces this). Use explicit guards instead: `if (file_exists($path)) { unlink($path); }`.
4. **`declare(strict_types=1)` at the top of every test file.**
5. **Mock interfaces, never concrete classes.**
6. **No `new Entity()` outside Object Mother classes in `tests/Shared/Mother/`.**

```php
// ❌ if in test — hides which branch is being executed
private function createUser(bool $complete): User
{
    $user = UserMother::withEmail('u@test.com');
    if ($complete) {          // ← FORBIDDEN
        $user->name = 'Jan';
    }
    return $user;
}

// ✅ two explicit methods — each test knows exactly what it gets
private function createCompleteUser(): User { ... }
private function createIncompleteUser(): User { ... }
```

```php
// ✅ DataProvider for 2+ structurally identical cases
#[DataProvider('platformUrlProvider')]
public function testDetectsPlatformFromUrl(string $url, string $expectedPlatform): void
{
    // one shared setup, different data
}

public static function platformUrlProvider(): array
{
    return [
        'bazos URL → bazos' => ['https://www.bazos.cz/inzerat/1', 'bazos'],
        'sbazar URL → seznam' => ['https://www.sbazar.cz/inzerat/1', 'seznam'],
    ];
}
```

---



Before writing a test, state:
> "I am testing **[ClassName]** at the **[Unit|Integration|Functional|Arch]** level. Double strategy: **[Mock/Stub/Foundry/Real]**. Behavior to verify: **[what changes, not how]**."

Then answer:
1. What **behavior** am I verifying? → name the test after it, not after the method
2. Does it need the database? → Integration. Does it need HTTP? → Functional. Otherwise → Unit
3. Am I testing a private method or internal state? → NO — restructure the design if so
4. Do I have 2+ similar cases? → use `#[DataProvider]`

---

## Test Pyramid

```
         ┌───────────────────┐
         │  Architecture     │  CI boundary checks — phpat
         │  tests/Arch/      │  Fewest, fastest, zero runtime
         └────────┬──────────┘
        ┌─────────┴──────────┐
        │   Functional       │  HTTP round-trip — WebTestCase
        │   tests/Functional/│  ~1 file per Action, real kernel
        └────────┬───────────┘
      ┌──────────┴────────────┐
      │    Integration        │  Real DB / real client — DAMA auto-rollback
      │    tests/Integration/ │  Repos, Voters, external adapters
      └──────────┬────────────┘
   ┌─────────────┴─────────────┐
   │       Unit                │  No I/O, no container, no framework
   │       tests/Unit/         │  Majority of the suite — fast and precise
   └───────────────────────────┘
```

**Rule:** test behavior at the lowest layer that can verify it. Push tests down whenever possible.

---

## Unit Tests (`tests/Unit/`)

**Target:** Domain Entities, Value Objects, Application Handlers.
**Base class:** `PHPUnit\Framework\TestCase` — no Symfony, no DB.

### Naming — describe behavior, not methods

```php
// ❌ Method-focused (tells nothing about the invariant)
public function testPublish(): void { ... }

// ✅ Behavior-focused
public function testDraftArticleBecomesPublishedWhenPublishIsCalled(): void { ... }
public function testPublishThrowsWhenArticleIsNotDraft(): void { ... }
```

### Domain Entity — state transitions + Domain Events

```php
final class ArticlePublishTest extends TestCase
{
    public function testDraftBecomesPublished(): void
    {
        $article = Article::createDraft(ArticleId::generate(), 'A valid title here');

        $article->publish(new DateTimeImmutable('2024-06-01'));

        $this->assertTrue($article->isPublished());
        $this->assertEquals(new DateTimeImmutable('2024-06-01'), $article->getPublishedAt());
    }

    public function testPublishRaisesArticlePublishedEvent(): void
    {
        $article = Article::createDraft(ArticleId::generate(), 'A valid title here');

        $article->publish(new DateTimeImmutable());

        $events = $article->pullDomainEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(ArticlePublished::class, $events[0]);
    }

    public function testPublishThrowsWhenAlreadyPublished(): void
    {
        $article = Article::createDraft(ArticleId::generate(), 'A valid title here');
        $article->publish(new DateTimeImmutable());

        $this->expectException(\DomainException::class);
        $article->publish(new DateTimeImmutable());
    }
}
```

### Application Handler — mock interfaces + domain exception coverage

Every postArticlesHandler test **must** cover:
1. The happy path (correct dispatch / state change)
2. **Every `App\Domain\Exception\*` the postArticlesHandler can throw** — one test per exception

```php
final class PublishArticleHandlerTest extends TestCase
{
    public function testPublishesArticleAndPersists(): void
    {
        $clock   = new MockClock(new DateTimeImmutable('2024-06-01'));
        $repo    = $this->createMock(ArticleRepositoryInterface::class);
        $article = Article::createDraft(ArticleId::generate(), 'Valid title here');

        $repo->method('findById')->willReturn($article);
        $repo->expects($this->once())->method('save')->with($article);

        $postArticlesHandler = new PublishArticleHandler($repo, $clock);
        ($postArticlesHandler)(new PublishArticleCommand($article->getId()));

        $this->assertTrue($article->isPublished());
        $this->assertEquals(new DateTimeImmutable('2024-06-01'), $article->getPublishedAt());
    }

    public function testThrowsWhenArticleNotFound(): void
    {
        $repo = $this->createMock(ArticleRepositoryInterface::class);
        $repo->method('findById')->willReturn(null);

        $this->expectException(ArticleNotFoundException::class);

        $postArticlesHandler = new PublishArticleHandler($repo, new MockClock(new DateTimeImmutable()));
        ($postArticlesHandler)(new PublishArticleCommand(ArticleId::generate()));
    }
}
```

### Value Object — exhaustive edge cases with DataProvider

```php
final class MoneyTest extends TestCase
{
    #[DataProvider('validAmounts')]
    public function testCreatesWithValidAmount(int $amount, string $currency): void
    {
        $money = new Money($amount, $currency);
        $this->assertSame($amount, $money->amount);
    }

    public static function validAmounts(): array
    {
        return [[0, 'CZK'], [1, 'EUR'], [999_99, 'USD']];
    }

    public function testRejectsNegativeAmount(): void
    {
        $this->expectException(\DomainException::class);
        new Money(-1, 'CZK');
    }
}
```

### Test doubles — choose the right tool

| Need | Use | Why |
|---|---|---|
| Verify a method **was called** | `createMock()` + `expects($this->once())` | Spy on interactions |
| Just **return a value** | `createStub()` | No accidental assertion |
| Stateful fake (e.g. in-memory repo) | Hand-rolled `InMemoryArticleRepository` | More readable than mock chains |
| Fixed time | `MockClock` | Deterministic — no flaky midnight failures |

---

## Integration Tests (`tests/Integration/`)

**Target:** Doctrine Repositories, Voters, external API clients (Clicker).
**Base class:** `Symfony\Bundle\FrameworkBundle\Test\KernelTestCase`
**Isolation:** `dama/doctrine-test-bundle` — every test runs in a rolled-back transaction automatically.

### Repository — verify real queries

```php
final class DoctrineArticleRepositoryTest extends KernelTestCase
{
    private ArticleRepositoryInterface $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->repository = self::getContainer()->get(ArticleRepositoryInterface::class);
    }

    public function testFindsById(): void
    {
        $article = ArticleFactory::new()->asDraft()->create();

        $found = $this->repository->findById($article->getId());

        $this->assertNotNull($found);
        $this->assertTrue($article->getId()->equals($found->getId()));
    }

    public function testReturnsNullWhenNotFound(): void
    {
        $this->assertNull($this->repository->findById(ArticleId::generate()));
    }
}
```

### Voter — verify Grant / Deny / Abstain (all three must be covered)

A Voter test is incomplete without the **Abstain** case — it proves the voter doesn't accidentally interfere with attributes it doesn't own.

```php
final class ArticleVoterTest extends KernelTestCase
{
    public function testOwnerIsGrantedPublish(): void
    {
        $owner   = UserFactory::createOne();
        $article = ArticleFactory::new(['user' => $owner])->asDraft()->createOne();

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->vote($owner->object(), 'PUBLISH', $article->object()));
    }

    public function testStrangerIsDeniedPublish(): void
    {
        $stranger = UserFactory::createOne();
        $article  = ArticleFactory::new()->asDraft()->createOne();

        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->vote($stranger->object(), 'PUBLISH', $article->object()));
    }

    public function testVoterAbstainsForUnknownAttribute(): void
    {
        $user    = UserFactory::createOne();
        $article = ArticleFactory::new()->asDraft()->createOne();

        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $this->vote($user->object(), 'UNKNOWN_ATTR', $article->object()));
    }

    private function vote(User $user, string $attribute, mixed $subject): int
    {
        $voter = self::getContainer()->get(ArticleVoter::class);
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());

        return $voter->vote($token, $subject, [$attribute]);
    }
}
```

---

## Functional Tests (`tests/Functional/`)

**Target:** EntryPoint Actions. One test file per Action.
**Base class:** `Symfony\Bundle\FrameworkBundle\Test\WebTestCase`

**Golden rule: assert HTTP status + dispatched Command only — never entity state via repository.**

```php
final class PublishArticleActionTest extends WebTestCase
{
    public function testReturns202WhenOwnerPublishes(): void
    {
        $owner   = UserFactory::createOne();
        $article = ArticleFactory::new(['user' => $owner])->asDraft()->createOne();

        $client = static::createClient();
        $client->loginUser($owner->object());
        $client->request('POST', "/articles/{$article->getId()}/publish");

        $this->assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);

        // Verify the Command was dispatched — not that the article was actually saved
        $this->assertQueuedMessage(PublishArticleCommand::class);
        // or with Symfony MessengerAssertionsTrait:
        // self::assertMessageIsDispatched(PublishArticleCommand::class);
    }

    public function testReturns403WhenNotOwner(): void
    {
        $stranger = UserFactory::createOne();
        $article  = ArticleFactory::new()->asDraft()->createOne();

        $client = static::createClient();
        $client->loginUser($stranger->object());
        $client->request('POST', "/articles/{$article->getId()}/publish");

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testReturns401WhenUnauthenticated(): void
    {
        $article = ArticleFactory::new()->asDraft()->createOne();

        static::createClient()->request('POST', "/articles/{$article->getId()}/publish");

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
```

**Functional test checklist:**
- [ ] Happy path (2xx) + assert dispatched Command (`assertQueuedMessage`)
- [ ] Unauthorized: 401 (no user)
- [ ] Forbidden: 403 (wrong user / incomplete profile)
- [ ] Validation error: 422/400 on bad input
- [ ] Assert HTTP status + dispatched Command — **never** entity state

---

## Architecture Tests (`tests/Architecture/`)

**Target:** Layer boundary enforcement. Tool: **phpat** (`vendor/phpat/phpat`). Runs in CI, zero runtime cost.

```php
final class LayerBoundaryTest extends TestCase
{
    public function testDomainHasNoDoctrineDependency(): void
    {
        $result = ArchRuleBuilder::allClasses()
            ->that(ResideInOneOfTheseNamespaces::inNamespace('App\Domain'))
            ->should(NotDependOnTheseNamespaces::inNamespace('Doctrine'))
            ->because('Domain is pure PHP — framework independence is non-negotiable');

        $this->assertArchitectureRulePasses($result);
    }

    public function testApplicationDoesNotImportUI(): void
    {
        $result = ArchRuleBuilder::allClasses()
            ->that(ResideInOneOfTheseNamespaces::inNamespace('App\Application'))
            ->should(NotDependOnTheseNamespaces::inNamespace('App\EntryPoint'))
            ->because('Dependency direction is UI → Application, never the reverse');

        $this->assertArchitectureRulePasses($result);
    }

    public function testActionsAreFinal(): void
    {
        $result = ArchRuleBuilder::allClasses()
            ->that(ResideInOneOfTheseNamespaces::inNamespace('App\EntryPoint\Action'))
            ->should(BeFinal::create())
            ->because('Single Action Controllers must be final');

        $this->assertArchitectureRulePasses($result);
    }
}
```

---

## Data Generation — ZenstruckFoundry

Never use `new Entity()` in tests except for pure Value Object unit tests.

```php
// ❌ Brittle — breaks when constructor changes
$user = new User();
$user->setEmail('test@test.com');
$em->persist($user); $em->flush();

// ✅ Foundry
$owner   = UserFactory::createOne(['email' => 'owner@test.com']);
$article = ArticleFactory::new()->withOwner($owner)->asDraft()->create();
$many    = ArticleFactory::new()->asDraft()->createMany(5, ['user' => $owner]);
```

**Factory pattern:**
```php
final class ArticleFactory extends ModelFactory
{
    protected function getDefaults(): array
    {
        return [
            'title'  => self::faker()->sentence(6),
            'status' => ArticleStatus::Draft,
            'user'   => UserFactory::new(),
        ];
    }

    public function asDraft(): self   { return $this->with(['status' => ArticleStatus::Draft]); }
    public function asPublished(): self { return $this->with(['status' => ArticleStatus::Published]); }

    protected static function getClass(): string { return Article::class; }
}
```

---

## Contract Tests — JSON Schema (`tests/Contract/`)

**Target:** API endpoints that return JSON. Verifies the response shape doesn't silently break.
**Tool:** `justinrainbow/json-schema` or Symfony's `assertJsonStringMatchesJsonFile`.

```php
final class ArticleResponseContractTest extends WebTestCase
{
    public function testArticleEndpointMatchesSchema(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/articles/1');

        $this->assertResponseIsSuccessful();

        $data = json_decode((string) $client->getResponse()->getContent(), true);

        $validator = new Validator();
        $schema    = json_decode(file_get_contents(__DIR__.'/schema/article.json'));
        $validator->validate($data, $schema);

        $this->assertTrue($validator->isValid(), implode(', ', array_column($validator->getErrors(), 'message')));
    }
}
```

**When to add a contract test:**
- New JSON endpoint is introduced
- Response shape changes (add/remove fields)
- External consumer (clicker robot, frontend) depends on a stable API shape

---

## Anti-patterns — Do Not Replicate

| ❌ Anti-pattern | ✅ Correct approach |
|---|---|
| `if`/`switch` inside a test method | Split into separate tests or use `#[DataProvider]` |
| `testPublish()` — method name | `testDraftBecomesPublishedOnPublish()` — behavior |
| Testing private methods directly | Test via public API; if hard → design smell, fix the class |
| `new Article(...)` in every test | `ArticleFactory::new()->asDraft()->create()` |
| `createMock(DoctrineArticleRepository::class)` | `createMock(ArticleRepositoryInterface::class)` — mock interfaces only |
| `$em->flush()` in Unit test | Use in-memory stub or move to Integration test |
| `new DateTimeImmutable()` in assertion | `MockClock` + fixed date — prevents flaky midnight failures |
| 10 assertions in one test | One logical assertion per test |
| Functional test asserting entity state via repo | Functional tests assert HTTP + dispatched Command only |
| No rollback between tests | `dama/doctrine-test-bundle` — always |

---

## Definition of Done

| Changed | Required test | Base class |
|---|---|---|
| Domain Entity / VO invariant | Unit — all edge cases + `DomainException` paths | `TestCase` |
| Domain Event raised | Unit — `pullDomainEvents()` assertion | `TestCase` |
| Application Handler | Unit — happy path + **every `App\Domain\Exception\*`** it can throw | `TestCase` |
| EntryPoint Action | Functional — 2xx + 401 + 403 + 422 + `assertQueuedMessage` | `WebTestCase` |
| Voter | Integration — Grant + Deny + **Abstain** | `KernelTestCase` |
| Repository query method | Integration — real DB via DAMA | `KernelTestCase` |
| JSON API endpoint shape | Contract — JSON schema validation | `WebTestCase` |
| Layer boundary | Architecture test rule | **phpat** |
