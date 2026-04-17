---
name: debug-prod
description: Debug the bazar_ai PRODUCTION environment. Use this skill whenever the user asks about prod issues, says "prover prod", "debug prod", "chto na produe", "pochemu ne rabotaet na proude", "article ne publikuetsja", "clicker zavisan", "submission stuck", "proverь loki", "posmotre logi na proude", or anything about publish flow, submission status, clicker behavior, RabbitMQ queues, or container health on the production server. Trigger even if the user just mentions a production URL (bazarai.visaczech.cz) or asks about tunnel.sh.
---

# Debug Production — bazar_ai

Production is on a **remote server** accessed via `ssh bazarai`.
Local tools (docker-compose, local DB) are NOT production — use this workflow.

---

## Step 0 — Check tunnel

The SSH tunnel (`tunnel.sh`) must be running to access Loki/Grafana/RabbitMQ locally.

```bash
curl -s --max-time 3 http://localhost:3100/ready
```

- Response `ready` → tunnel is up, proceed
- No response → tell user to run `./tunnel.sh` in a separate terminal

---

## Step 1 — Container health

```bash
ssh bazarai "docker ps --format 'table {{.Names}}\t{{.Status}}\t{{.RunningFor}}' | grep bazar"
```

Key containers and what to watch for:

| Container | Role | Red flag |
|---|---|---|
| `bazar-ai-clicker-1` | Browser automation | Restarting / uptime < 2min |
| `bazar-ai-worker-1` | Symfony messenger consumer | Down = publish messages pile up |
| `bazar-ai-php-1` | PHP-FPM backend | Down = site broken |
| `bazar-ai-rabbitmq-1` | Message queue | Unhealthy |
| `bazar-ai-db-1` | PostgreSQL | Unhealthy |

---

## Step 2 — Query logs

Use the `grafana-logs` skill for log querying — it has full LogQL reference.

For production, the `{job="docker"}` label captures all containers.
Useful service labels: `clicker`, `php`, `worker`, `ai-agent`.

**Quick: get clicker logs directly (bypass Loki):**
```bash
ssh bazarai "docker logs bazar-ai-clicker-1 --since 30m 2>&1 | tail -60"
ssh bazarai "docker logs bazar-ai-worker-1 --since 30m 2>&1 | tail -30"
```

**Search for a jobId across all services (via Loki):**
```bash
curl -s -G 'http://localhost:3100/loki/api/v1/query_range' \
  --data-urlencode 'query={job="docker"} | json | jobId="<ID>"' \
  --data-urlencode 'start='"$(date -v-2H +%s)"'000000000' \
  --data-urlencode 'end='"$(date +%s)"'000000000' \
  --data-urlencode 'limit=100' | python3 -c "
import json,sys,re
d=json.load(sys.stdin)
for s in d['data']['result']:
  for ts,line in s['values']:
    clean=re.sub(r'\x1b\[[0-9;]*m','',line).strip()
    try:
      o=json.loads(clean); print(o.get('time',ts[:10]), o.get('context',''), o.get('msg',''))
    except: print(clean[:200])
"
```

---

## Step 3 — Article & submission state

Production DB credentials: user=`bazar`, db=`bazarai`

```bash
# Articles
ssh bazarai "docker exec bazar-ai-db-1 psql -U bazar -d bazarai -c \
  'SELECT id, title, user_id FROM article ORDER BY id;'"

# Submissions — key diagnostic table
ssh bazarai "docker exec bazar-ai-db-1 psql -U bazar -d bazarai -c \
  'SELECT id, article_id, platform, status, job_id, error_data, updated_at FROM article_submission ORDER BY updated_at DESC;'"
```

**Submission status meanings:**

| Status | Meaning |
|---|---|
| `pending` | PublishHandler ran, ClickerCommand sent to AMQP, clicker hasn't started |
| `processing` | Clicker working on it |
| `completed` | Done ✓ |
| `failed` | Clicker failed — check `error_data` column |
| `waiting_input` | Waiting for user to submit SMS code or meta fields |
| `withdrawn` / `deleting` | Being removed from platform |

---

## Step 4 — RabbitMQ queue state

```bash
curl -s -u guest:guest http://localhost:15672/api/queues | python3 -c "
import json,sys
for q in json.load(sys.stdin):
    r=q.get('messages_ready',0); u=q.get('messages_unacknowledged',0)
    if r or u or 'clicker' in q['name'] or 'messages' in q['name']:
        print(f\"{q['name']:45} ready={r} unacked={u}\")
"
```

- `ready > 0` + no consumer = worker/clicker not running
- `unacknowledged > 0` = message received but not processed (clicker hanging)

---

## Step 5 — Clicker proxy check

If clicker logs show `acquire: slot=X acquired` but **nothing after** (no navigation, no form logs), the SOCKS5 proxy is down. Clicker hangs waiting for a connection that never comes.

```bash
ssh bazarai "curl -s --socks5 host.docker.internal:8889 --max-time 5 \
  https://www.bazos.cz -o /dev/null -w '%{http_code}' || echo 'PROXY UNREACHABLE'"
```

Proxy is at `socks5://host.docker.internal:8889` — it's a service running on the Docker host machine.
If unreachable: check what's supposed to serve port 8889, or bypass proxy in `.env` for testing.

---

## Publish flow — full trace

```
User clicks "Publish to X"
  → POST /market/article/{ids}/post?platform=X
  → postArticles() filters already-active submissions
  → dispatches PublishMessage → bazar-ai-worker-1 consumes
  → PublishHandler creates article_submission (status=pending)
  → dispatches ClickerCommand to AMQP → bazar-ai-clicker-1 consumes
  → BazosAdapter (or other) opens browser via proxy
  → fills form, submits
  → sends result back via HTTP to PHP
  → article_submission status → completed/failed
```

**Common failure points:**

| Symptom | Cause | Fix |
|---|---|---|
| No "Publish to X" button | User has no credentials for platform | Check `user_credential` table |
| Submission stuck `pending` | Worker down OR clicker not consuming | Steps 1 + 4 |
| Clicker silent after `slot acquired` | Proxy `host.docker.internal:8889` down | Step 5 |
| `failed` with error_data | Adapter error (form changed, auth failed, etc.) | Check `error_data` in submissions |
| "No articles found" flash | Submission already active (not failed/withdrawn) | Reset submission status or wait |
