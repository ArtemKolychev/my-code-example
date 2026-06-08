.PHONY: dev dev-d stop build install install-php install-ts migrate test test-db lint lint-fix rector jwt-keys dev-observability logs ps shell-backend shell-gateway claude qodana qodana-report

# ─── Stack ────────────────────────────────────────────────────────────────────

dev:
	docker compose up

dev-d:
	docker compose up -d

stop:
	docker compose down

build:
	docker compose build --no-cache

ps:
	docker compose ps

logs:
	docker compose logs -f $(SVC)

# ─── Keys ─────────────────────────────────────────────────────────────────────

jwt-keys:
	mkdir -p docker/keys
	openssl genrsa -out docker/keys/private.pem 2048
	openssl rsa -in docker/keys/private.pem -pubout -out docker/keys/public.pem
	@echo "Keys generated in docker/keys/"

# ─── Install ──────────────────────────────────────────────────────────────────

install: install-php install-ts

install-php:
	docker compose exec backend_php composer install

install-ts:
	pnpm install

# ─── Migrate ──────────────────────────────────────────────────────────────────
# Usage:
#   make migrate
#   make migrate FRESH=1
#   make migrate SEED=1
#   make migrate FRESH=1 SEED=1

MIGRATE_ARGS = --force
ifdef FRESH
  MIGRATE_ARGS += --fresh
endif
ifdef SEED
  MIGRATE_ARGS += --seed
endif

migrate:
	docker compose exec backend_php php artisan migrate $(MIGRATE_ARGS)

# ─── Tests ────────────────────────────────────────────────────────────────────

test:
	docker compose exec postgres psql -U core -c "DROP DATABASE IF EXISTS core_test;" || true
	docker compose exec postgres psql -U core -c "CREATE DATABASE core_test WITH OWNER core;" || true
	docker compose exec -e DB_DATABASE=core_test backend_php php artisan migrate:fresh --seed --force
	docker compose exec backend_php ./vendor/bin/phpunit
	pnpm -r run test

# ─── Lint ─────────────────────────────────────────────────────────────────────

lint:
	docker compose exec backend_php ./vendor/bin/phpstan analyse --memory-limit=512M
	docker compose exec backend_php ./vendor/bin/phpunit --testdox tests/Architecture
	pnpm -r run lint
	pnpm -r run typecheck
	bash scripts/detectors/run.sh

lint-fix:
	docker compose exec backend_php ./vendor/bin/pint
	pnpm -r run lint-fix

rector:
	docker compose exec backend_php php vendor/bin/rector process

rector-dry:
	docker compose exec backend_php php vendor/bin/rector process --dry-run

# ─── Shells ───────────────────────────────────────────────────────────────────

shell-backend:
	docker compose exec backend_php sh

shell-gateway:
	docker compose exec gateway sh

# ─── Observability ────────────────────────────────────────────────────────────

dev-observability:
	docker compose --profile observability up -d

# ─── Qodana ───────────────────────────────────────────────────────────────────

qodana:
	@mkdir -p .qodana/results
	docker run --rm \
		-v $(PWD):/data/project \
		-v $(PWD)/.qodana/results:/data/results \
		jetbrains/qodana-php:2026.1 \
		--fail-threshold 0 || exit 0

qodana-report:
	@mkdir -p .qodana/results
	docker run --rm \
		-v $(PWD):/data/project \
		-v $(PWD)/.qodana/results:/data/results \
		jetbrains/qodana-php:2026.1 \
		--save-report
	docker run --rm \
		-p 8080:8080 \
		-v $(PWD)/.qodana/results:/data/results \
		jetbrains/qodana-php:2026.1 \
		show --results-dir=/data/results

# ─── Claude ───────────────────────────────────────────────────────────────────

claude:
	claude --dangerously-skip-permissions
