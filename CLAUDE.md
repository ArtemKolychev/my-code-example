# CLAUDE.md

## Stack
- Gateway: NestJS 11 (`src/gateway`) — JWT verify + routing only, no business logic
- Backend: Laravel 13 + PHP 8.4 (`src/backend`) — modular monolith, hexagonal DDD per domain (`App\Auth\`, `App\Users\`, `App\Tenants\`)
- Frontend: Next.js 15 (`src/frontend`)
- Queue: RabbitMQ 4 — mutations async (202+SSE), queries via HTTP proxy
- DB: PostgreSQL 16, separate schema per module (`auth`, `users`, `tenants`)

## Package managers
- TypeScript: pnpm (gateway, frontend, shared-types)
- PHP: composer (`src/backend/` — single vendor, one project)

## Non-negotiables

| Rule | Enforcement |
|---|---|
| Domain: zero Laravel/Eloquent imports | PHPAt LayerBoundaryTest |
| Handlers: `final readonly class`, single `__invoke` | PHPStan HandlersMustBeReadonlyRule |
| Commands/VOs: `final readonly class` | PHPStan ApplicationDtosMustBeFinalReadonlyRule |
| Http\Action: `final`, single `__invoke`, suffix `Action` | PHPAt |
| No `array` in public method signatures — use typed DTO/VO/Collection | PHPStan level max |
| Cross-schema JOIN only in `Infrastructure/Query/CrossSchema/` | NoCrossSchemaJoinRule |
| `Domain\Repository\`: только `save()` + `findBy*Id()` | `DomainRepositoryMustBeWriteSideRule` |
| No `@` error suppression | NoErrorSuppressionRule |
| No repository call in loop (N+1) | NoRepositoryCallInLoopRule |
| Cognitive complexity: function ≤8, class ≤25 | PHPStan cognitive-complexity |
| OpenAPI auto-generated from PHP types | dedoc/scramble (no manual yaml) |
| JWT RS256 with `kid` claim for key rotation | lcobucci/jwt |

## Commands
```
make dev                # start full stack
make stop               # stop all containers
make jwt-keys           # generate RSA key pair in docker/keys/
make migrate            # run migrations for all modules
make test               # all tests
make lint               # PHPStan + PHPAt + ESLint
make lint-fix           # auto-fix (pint + eslint)
make rector             # refactor via Rector (dry-run: make rector-dry)
```

## Skills
- `copilot-orchestrator`  — delegate code review / arch Q&A to gh copilot CLI
- `nestjs-best-practices` — NestJS patterns, DI, guards, security
- `react-best-practices`  — Next.js/React performance (40+ rules)
- `web-design-guidelines` — UI accessibility + UX (100+ rules)
- `vercel-optimize`       — Vercel/Next.js performance audits

## Architecture
Full spec: `docs/superpowers/specs/2026-06-07-core-architecture-design.md` *(not yet created)*
