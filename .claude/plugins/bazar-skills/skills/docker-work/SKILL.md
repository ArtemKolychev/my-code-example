---
name: docker-work
description: This skill should be used when the user asks to work with Docker, run containers, check logs, restart services, rebuild images, asks about "docker", "kontejner", "kompoz", "docker-compose", "service", "rebuild", "logs", or needs to execute commands inside containers for this project.
version: 1.0.0
---

# Docker Work — Bazar AI

This project uses Docker Compose. The `docker-compose.yml` is at the project root (`/Users/artemkolychev/www/bazar_ai/`).

## Services

| Service | Description | URL/Port |
|---------|-------------|----------|
| `nginx` | Web server (main app) | https://bazar_ai.localhost |
| `php` | PHP-FPM (Symfony backend) | internal port 9000 |
| `node` / `clicker` | NestJS frontend | localhost:3000 |
| `postgres` | PostgreSQL 15 | localhost:5432 |

## Common Commands

**Check status:**
```bash
docker-compose ps
```

**View logs:**
```bash
docker-compose logs -f php
docker-compose logs -f postgres
docker-compose logs --tail=50 nginx
```

**Execute command in container:**
```bash
# Symfony console
docker-compose exec php bin/console <command>

# PostgreSQL shell
docker-compose exec postgres psql -U postgres -d bazar_ai

# Node/NestJS shell
docker-compose exec node sh
```

**Restart a service:**
```bash
docker-compose restart php
docker-compose restart nginx
```

**Rebuild after Dockerfile change:**
```bash
docker-compose build php
docker-compose up -d php
```

**Full rebuild:**
```bash
docker-compose down
docker-compose build
docker-compose up -d
```

## Symfony Console (via Docker)

Always run Symfony commands through the `php` container:

```bash
# Migrations
docker-compose exec php bin/console doctrine:migrations:migrate

# Cache clear
docker-compose exec php bin/console cache:clear

# Routes list
docker-compose exec php bin/console debug:router

# Check container services
docker-compose exec php bin/console debug:container
```

## PostgreSQL Access

```bash
# Connect to DB
docker-compose exec postgres psql -U postgres -d bazar_ai

# Common SQL
\dt           -- list tables
\d table_name -- describe table
\q            -- quit
```

## Troubleshooting

- **PHP container won't start** → Check logs: `docker-compose logs php`
- **Port already in use** → `lsof -i :3000` or `lsof -i :5432`, kill the process
- **Database connection refused** → Make sure `postgres` service is running: `docker-compose ps`
- **Changes not reflected** → For PHP, no restart needed (code mounted). For compiled assets, rebuild node container
- **Permissions error** → Files created in container may be owned by root: `docker-compose exec php chown -R www-data:www-data var/`

## Rules

- Never run `php bin/console` directly on host — always via `docker-compose exec php`
- Use `docker-compose logs` before asking why something isn't working
- After changing `Dockerfile` or `docker-compose.yml`, rebuild the affected service
