# Bazar AI

Bazar AI is a platform for automating the publication of second-hand listings across multiple Czech and European marketplaces.

The user uploads product photos; the AI pipeline classifies items, groups similar photos, suggests a title, description, and price; then a stealth browser automation service publishes the listing on every selected platform simultaneously.

**Supported marketplaces:** seznam.cz · bazos.cz · vinted.com · motoinzerce.cz

### How it works

```
User uploads photos
       │
       ▼
AI Agent (GPT-4o vision)
  • groups photos by item
  • detects category, condition, brand, colour
  • enriches vehicles via VIN / plate lookup
  • generates title, description, price estimate
       │
       ▼
RabbitMQ job queue
       │
       ▼
Clicker (stealth Chromium)
  • logs in with user credentials
  • fills the platform form
  • uploads images
  • submits the listing
```

## Services & Addresses

| Service | URL / Address | Notes |
|---|---|---|
| Main App | https://bazar_ai.localhost | Nginx (80/443) |
| Frontend | http://localhost:3001 | Next.js |
| Clicker API | http://localhost:3000 | NestJS |
| RabbitMQ Management | http://localhost:15672 | guest / guest |
| NoVNC (browser viewer) | http://localhost:6080 | Headless Chrome UI |
| PostgreSQL | localhost:5432 | DB: bazar, user: root |
| RabbitMQ AMQP | localhost:5672 | guest / guest |
| **Grafana** | **http://localhost:3200** | **admin / admin** |
| Loki | http://localhost:3100 | Log storage (internal) |

## Stack

- **Backend:** PHP 8.4 / Symfony 7.3 (`src/be/`)
- **Frontend:** Next.js / React + Tailwind CSS (`src/frontend/`)
- **Clicker:** Node.js / NestJS 11 (`src/clicker/`)
- **AI Agent:** Node.js / NestJS 11 (`src/ai-agent/`)
- **Queue:** RabbitMQ 3
- **Cache:** Redis 7
- **DB:** PostgreSQL 16
- **Browser:** Chromium + rebrowser-playwright (stealth)
- **Logging:** Loki + Grafana + Promtail (structured logs from all services)

## Quick Start

```bash
# Generate local SSL cert (first time)
make local-cert-generate

# Start all services (background)
make dev-d

# Start with logs (foreground)
make dev

# Run DB migrations
make migrate
```

## Production deploy (using .env.prod)

Use the provided `.env.prod` to run services in production mode. It contains production secrets; trusted proxy ranges are configured inside the Symfony application (see `src/be/config/services.yaml`).

For production URL settings:

- `APP_URL` should stay the public HTTPS origin users see (for example `https://bazarai.visaczech.cz`).
- `INTERNAL_APP_URL` should point to the internal Docker-reachable app origin used by background workers for image fetches (for example `http://nginx`).

Example deploy steps:

```bash
# build and run services with production env
docker compose --env-file .env.prod up -d --build nginx php tunnel

# clear/warmup symfony cache inside php container
docker compose exec php php bin/console cache:clear --no-warmup --env=prod
docker compose exec php php bin/console cache:warmup --env=prod

# tail logs
docker compose logs -f nginx php
```

Notes:
- Keep `.env.prod` secure and out of VCS. It includes DB credentials and API keys.
- Periodically check Cloudflare IP ranges (source: https://www.cloudflare.com/ips/) and update `src/be/config/services.yaml` if you need to change trusted proxies.
- Nginx config forwards X-Forwarded-* headers and uses Cloudflare's `CF-Connecting-IP` via `real_ip_header` so PHP/Symfony see the original client IP.
- In production, background services should use `INTERNAL_APP_URL=http://nginx`; using `https://nginx` will break internal image fetches because the prod nginx container serves HTTP inside the Docker network.

## Logging & Observability

All services send structured logs to Loki, viewable in Grafana at **http://localhost:3200** (admin / admin).

**Dashboards:**
- **Overview** — log volume + error rate per service
- **LLM** — token usage, latency, model activity
- **Clicker** — browser events by jobId / sessionId
- **Trace** — cross-service trace by jobId

**Correlation fields:**
- `jobId` — ties PHP, clicker, and ai-agent logs for one job
- `traceId` — UUID per message handler invocation
- `sessionId` — browser session within a job (`{jobId}_{timestamp}`)

```bash
# Open Grafana (after make start)
open http://localhost:3200
# Navigate: Explore → Loki → filter by {service="clicker"} or {jobId="..."}
```

## Lint

```bash
make lint        # check
make lint-fix    # auto-fix
```
