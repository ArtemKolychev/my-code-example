---
name: grafana-logs
description: How to query Grafana/Loki logs in this project using MCP tools. Use this skill whenever the user asks about logs, errors, debugging, tracing a job, checking what happened, LLM calls, browser events, "grafana", "loki", "logQL", "logs", "debug job", "chto v logah", "pochemu upalo", "ne rabotaet", "track job", "posmotri logi", or wants to see what a service was doing.
version: 2.0.0
---

# Grafana Logs — Bazar AI

**Services:** `php`, `clicker`, `ai-agent`
**Grafana:** http://localhost:3200 (admin/admin)
**Loki:** http://localhost:3100

---

## Method 1: Grafana MCP Tools (preferred)

The `grafana` MCP server is configured and provides direct tools. Use these whenever available.

### Step 1: Find Loki datasource UID

```
list_datasources → find the entry with type="loki" → copy its uid
```

The Loki datasource is named **"Loki"** — UID is usually `loki` or a short hash.

### Step 2: Run a log query

```
query_loki_logs(
  datasource_uid = "<uid from step 1>",
  expr = '{service="clicker"} | json | jobId="abc123"',
  start = "now-1h",   # or RFC3339: "2024-01-15T10:00:00Z"
  end = "now",
  limit = 100
)
```

### Common LogQL queries for MCP

```logql
# All logs from a service (last 1h)
{service="clicker"}
{service="php"}
{service="ai-agent"}

# Filter by keyword (fast, pre-parse)
{service=~"clicker|php|ai-agent"} |= "publish"
{service=~"clicker|php|ai-agent"} |= "error"

# Filter by structured field (after JSON parse)
{service=~"clicker|php|ai-agent"} | json | jobId="<id>"
{service=~"clicker|php|ai-agent"} | json | level>=40

# Errors only
{service=~"clicker|php|ai-agent"} | json | level=50
{service="php"} | json | level_name="ERROR"

# LLM activity
{service="ai-agent"} | json | channel="llm"

# Clicker browser events
{service="clicker"} | json | channel="clicker_events"
```

### Other useful MCP tools

```
list_loki_label_names(datasource_uid)           → see all available labels
list_loki_label_values(datasource_uid, "service") → see all service names
query_loki_stats(datasource_uid, selector)      → log volume stats
```

---

## Method 2: Loki HTTP API (fallback when MCP unavailable)

Use `Bash` tool with curl directly to Loki:

```bash
# Simple text search (last 1 hour)
curl -s -G 'http://localhost:3100/loki/api/v1/query_range' \
  --data-urlencode 'query={service=~"clicker|php|ai-agent"} |= "publish"' \
  --data-urlencode 'start='"$(date -v-1H +%s)"'000000000' \
  --data-urlencode 'end='"$(date +%s)"'000000000' \
  --data-urlencode 'limit=50' | python3 -m json.tool

# macOS note: use date -v-1H for 1 hour ago, date -v-2H for 2 hours ago

# Filter by jobId
curl -s -G 'http://localhost:3100/loki/api/v1/query_range' \
  --data-urlencode 'query={service=~"clicker|php|ai-agent"} | json | jobId="<id>"' \
  --data-urlencode 'start='"$(date -v-1H +%s)"'000000000' \
  --data-urlencode 'end='"$(date +%s)"'000000000' \
  --data-urlencode 'limit=100'
```

Parse the response — logs are in `.data.result[].values[]` as `[timestamp, logline]` pairs.

```bash
# Pretty print just the log messages:
curl -s -G 'http://localhost:3100/loki/api/v1/query_range' \
  --data-urlencode 'query={service=~"clicker|php|ai-agent"} |= "publish"' \
  --data-urlencode 'start='"$(date -v-1H +%s)"'000000000' \
  --data-urlencode 'end='"$(date +%s)"'000000000' \
  --data-urlencode 'limit=50' \
  | python3 -c "
import json, sys, datetime
data = json.load(sys.stdin)
rows = []
for stream in data['data']['result']:
  svc = stream['stream'].get('service', '?')
  for ts, line in stream['values']:
    t = datetime.datetime.fromtimestamp(int(ts[:10]))
    try:
      msg = json.loads(line)
      rows.append((t, svc, msg.get('msg') or msg.get('message', line[:120])))
    except:
      rows.append((t, svc, line[:120]))
rows.sort()
for t, svc, msg in rows:
  print(f'{t} [{svc}] {msg}')
"
```

---

## Correlation Fields

Every log entry carries:

| Field | Description | Example |
|-------|-------------|---------|
| `service` | Service name | `clicker`, `php`, `ai-agent` |
| `jobId` | Business job ID | `"bazos_1710000000.123"` |
| `traceId` | UUID per message postArticlesHandler call | `"550e8400-..."` |
| `sessionId` | Browser session within a job | `"jobId_timestamp"` |
| `channel` | Log category | `clicker_events`, `llm`, `messaging` |

---

## Workflow: Debugging a Failed Feature

1. **Start broad** — search keyword across all services:
   ```
   query_loki_logs(expr='{service=~"clicker|php|ai-agent"} |= "publish"', start="now-2h")
   ```
2. **Find the jobId** in the results
3. **Trace the job** across all services:
   ```
   query_loki_logs(expr='{service=~"clicker|php|ai-agent"} | json | jobId="<id>"')
   ```
4. **Look for errors** (`level=50` in clicker/ai-agent, `level_name=ERROR` in php)
5. **Dig into clicker events** if browser automation failed:
   ```
   query_loki_logs(expr='{service="clicker"} | json | jobId="<id>" | channel="clicker_events"')
   ```

---

## Tips

- **`|= "text"`** — fast raw text filter (before JSON parse, no overhead)
- **`| json | field="val"`** — structured filter (slower but precise)
- **No results?** — widen time range: `start="now-6h"` or `start="now-24h"`
- **pino log levels:** 10=trace, 20=debug, 30=info, 40=warn, 50=error, 60=fatal
- **php log levels:** `level_name` field = `"ERROR"`, `"WARNING"`, `"INFO"`
