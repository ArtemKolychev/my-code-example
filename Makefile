help: ## Outputs this help screen
	@grep -E '(^[a-zA-Z0-9_-]+:.*?##.*$$)|(^##)' $(firstword $(MAKEFILE_LIST)) | awk 'BEGIN {FS = ":.*?## "}{printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m##/[33m/'

## —— General ──────────────────────────────────────────────────
local-cert-generate: ## Generate self-signed SSL certificates for local development
	mkdir -p ./docker/nginx/certs/${HOST}
	openssl req -x509 -nodes -days 365 -newkey rsa:2048 -keyout ./docker/nginx/certs/${HOST}/privkey.pem -out ./docker/nginx/certs/${HOST}/fullchain.pem -subj "/C=RU/ST=State/L=City/O=Organization/OU=Department/CN=${HOST}"

HOST := $(shell grep '^HOST=' .env | cut -d= -f2 | tr -d "\"'")
SERVER_HOST := $(shell grep '^SERVER_HOST=' .env | cut -d= -f2 | tr -d "\"'")
PROXY_PORT := 8888
PROD_PROJECT_NAME ?= bazarai
PROD_COMPOSE = APP_ENV=prod docker compose -f docker-compose.yml --profile prod --project-name $(PROD_PROJECT_NAME)

plugin-w: ## Watch for changes in the media-loader-plugin and rebuild it
	 docker compose exec node_be sh -c "cd src/plugins/media-loader-plugin; yarn watch"

## —— Docker ───────────────────────────────────────────────────
dev: ## Start in dev mode (foreground, with build) + proxy tunnel if PROXY_URLS is set
	@$(MAKE) -s _proxy-maybe-start
	APP_ENV=dev docker compose up --build --remove-orphans $(ARGS)

dev-d: ## Start in dev mode (background, with build) + proxy tunnel if PROXY_URLS is set
	@$(MAKE) -s _proxy-maybe-start
	APP_ENV=dev docker compose up -d --build --remove-orphans $(ARGS)

prod: ## Start in prod mode (background, with build)
	$(PROD_COMPOSE) up -d --build --remove-orphans $(ARGS)

stop: ## Stop all containers
	docker compose stop $(ARGS)

restart: ## Restart containers (dev by default): make restart ARGS=clicker
	docker compose stop $(ARGS)
	docker compose up -d --remove-orphans $(ARGS)

## —— Database ─────────────────────────────────────────────────
migrate: ## Run database migrations
	docker compose up -d db php
	docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction

prod-migrate: ## Run database migrations for the prod stack
	$(PROD_COMPOSE) up -d db php
	$(PROD_COMPOSE) exec -T php php bin/console doctrine:migrations:migrate --no-interaction

## ── Linters (check only) ─────────────────────────────────────
.PHONY: lint lint-be lint-clicker lint-frontend
lint: lint-be lint-clicker lint-frontend

lint-be: ### Check PHP code style and analyze code with PHPStan
	docker compose exec -T php vendor/bin/php-cs-fixer fix --dry-run --diff
	docker compose exec -T php vendor/bin/phpstan analyse

lint-clicker: ### Check TypeScript code style and analyze code with ESLint
	docker compose exec -T clicker npx tsc -p tsconfig.build.json --noEmit
	docker compose exec -T clicker npx eslint "{src,apps,libs,test}/**/*.ts"

lint-frontend: ### Check TypeScript code style with ESLint
	docker compose exec -T frontend npm run lint

## ── Linters (auto-fix) ───────────────────────────────────────
.PHONY: lint-fix lint-be-fix lint-clicker-fix lint-frontend-fix
lint-fix: lint-be-fix lint-clicker-fix lint-frontend-fix

lint-be-fix: ### Fix PHP code style issues with PHP-CS-Fixer
	docker compose exec -T php vendor/bin/php-cs-fixer fix

lint-clicker-fix: ### Fix TypeScript code style issues with ESLint and Prettier
	docker compose exec -T clicker npx eslint "{src,apps,libs,test}/**/*.ts" --fix
	docker compose exec -T clicker npx prettier --write "src/**/*.ts" "test/**/*.ts"

lint-frontend-fix: ### Fix TypeScript code style issues with ESLint and Prettier
	docker compose exec -T frontend npm run lint
	docker compose exec -T frontend npx prettier --write "src/**/*.{ts,tsx}" "app/**/*.{ts,tsx}"

## —— Proxy ────────────────────────────────────────────────────
_proxy-maybe-start:
	@PROXY_URLS=$$(grep '^PROXY_URLS=' .env | cut -d= -f2 | tr -d "\"'"); \
	if [ -n "$$PROXY_URLS" ]; then \
		if ! which microsocks > /dev/null 2>&1; then \
			echo "[proxy] microsocks not found, skipping proxy. (Install: brew install microsocks / apt install microsocks)"; \
		else \
			pkill microsocks 2>/dev/null || true; \
			microsocks -p $(PROXY_PORT) &>/dev/null & \
			sleep 1; \
			echo "[proxy] microsocks started on port $(PROXY_PORT)"; \
			ssh -fN -R 0.0.0.0:$(PROXY_PORT):localhost:$(PROXY_PORT) root@$(SERVER_HOST) && \
			echo "[proxy] SSH tunnel to $(SERVER_HOST):$(PROXY_PORT) established" || \
			echo "[proxy] WARNING: SSH tunnel failed, continuing without proxy"; \
		fi; \
	fi

proxy-tunnel: ## Start local SOCKS5 proxy + SSH reverse tunnel to server (requires: brew install microsocks)
	@which microsocks > /dev/null 2>&1 || (echo "microsocks not found. Install: brew install microsocks" && exit 1)
	@echo "Starting microsocks on port $(PROXY_PORT)..."
	@pkill microsocks 2>/dev/null || true
	@microsocks -p $(PROXY_PORT) &
	@sleep 1
	@echo "Opening reverse SSH tunnel to $(SERVER_HOST):$(PROXY_PORT)..."
	@echo "Traffic from Docker will go through your local machine."
	@echo "Press Ctrl+C to stop."
	ssh -N -R 0.0.0.0:$(PROXY_PORT):localhost:$(PROXY_PORT) root@$(SERVER_HOST)


tunnel-stop: ## Stop SSH port-forward tunnel
	@pkill -f "ssh.*-L.*bazarai" 2>/dev/null && echo "Tunnel stopped" || echo "Tunnel was not running"

proxy-stop: ## Stop local SOCKS5 proxy + SSH tunnel
	@pkill microsocks 2>/dev/null && echo "microsocks stopped" || echo "microsocks was not running"
	@pkill -f "ssh.*$(PROXY_PORT):localhost:$(PROXY_PORT)" 2>/dev/null && echo "SSH tunnel stopped" || true

proxy-status: ## Show current proxy configuration
	@echo "SERVER_HOST: $(SERVER_HOST)"
	@echo "PROXY_PORT:  $(PROXY_PORT)"
	@echo "PROXY_URLS:  $$(grep '^PROXY_URLS=' .env | cut -d= -f2)"
	@pgrep -l microsocks 2>/dev/null && echo "microsocks: running" || echo "microsocks: stopped"
	@pgrep -fa "ssh.*$(PROXY_PORT):localhost" 2>/dev/null && echo "SSH tunnel: running" || echo "SSH tunnel: stopped"

claude:
	claude --dangerously-skip-permissions

## —— Fixtures ─────────────────────────────────────────────────
record-fixtures-start: ## Restart clicker with RECORD_FIXTURES=1 (then trigger actions normally via API)
	RECORD_FIXTURES=1 docker compose up -d --no-deps --remove-orphans clicker
	@echo "Clicker restarted in record mode. Fixtures saved to uploads/{platform}/{action}/fixtures/"

record-fixtures-stop: ## Restart clicker without RECORD_FIXTURES (back to normal)
	docker compose up -d --no-deps --remove-orphans clicker

## —— Tests ────────────────────────────────────────────────────
_ADAPTER := $(filter-out test test-be test-clicker test-clicker-unit,$(MAKECMDGOALS))

.PHONY: test test-be test-clicker test-clicker-unit
test: test-be test-clicker-unit test-clicker ## Run all tests (PHP unit + clicker Jest unit + clicker Playwright adapters)

test-be: ## Run PHP unit tests
	docker compose run --rm -T php php vendor/bin/phpunit tests/Unit/

test-clicker-unit: ## Run clicker Jest unit tests (*.spec.ts)
	docker compose run --rm -T clicker npx jest --no-coverage

test-clicker: ## Run all clicker adapter tests or a specific one: make test-clicker bazos
	docker compose run --rm -T clicker npx playwright test $(if $(_ADAPTER),src/adapters/$(_ADAPTER)/test.spec.ts,) --project=chromium-xvfb

%:
	@:
