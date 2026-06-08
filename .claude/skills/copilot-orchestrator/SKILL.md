---
name: copilot-orchestrator
description: "Master pattern for delegating ANY sub-task (code review, context gathering, test generation, log parsing) to gh copilot CLI. Keywords: external agent, delegate, save tokens, fetch context, background task"
---

# Copilot Delegation Pattern & Routing Strategy

You are the Tech Lead / Orchestrator. Delegate routine work to `.claude/scripts/agent_invoke.sh`. Tasks are always passed via temp files `/tmp/task_*.txt`; results are read from `/tmp/res_*.txt`.

## Economic Routing Matrix (STRICT RULES)

1. **Tier 0: "Free Army" (Cost: 0x) — USE BY DEFAULT**
   * **Models:** `gpt-4.1`, `gpt-5-mini`
   * **Rule:** 90% of tasks go here.
   * **gpt-4.1:** Parallel file reading (fetch-context), writing unit tests, boilerplate generation, basic code review (bug hunting, style checks). Run in parallel background processes (`&`).
   * **gpt-5-mini:** Triage, routing decisions, log parsing, formatting output as JSON.

2. **Tier 1: "Light Fallback" (Cost: 0.33x) — USE ONLY AS FALLBACK**
   * **Models:** `claude-haiku-4.5`, `gpt-5.4-mini`
   * **Rule:** Use ONLY if Tier 0 failed or produced garbled output format.

3. **Tier 2: "Heavy Artillery" (Cost: 1x) — TARGETED USE ONLY**
   * **Models:** `claude-sonnet-4.6`, `gpt-5.4`
   * **Rule:**
     * **claude-sonnet-4.6:** Deep architectural PR analysis, complex logical vulnerability detection.
     * **gpt-5.4:** Generating complex non-trivial code.

4. **Tier 3: "Forbidden Zone" (Cost: 3x)**
   * **Models:** `claude-opus-4.5`, `claude-opus-4.6`
   * **Rule:** Strictly FORBIDDEN for background tasks.

## Data Pipeline Best Practices

Use unique IDs for temp files (e.g. `$RANDOM` or timestamp) to avoid race conditions when running parallel `&` processes with `wait`.

### Parallel execution example

```bash
TASK_ID=$RANDOM

# Write prompts
cat > /tmp/task_a_${TASK_ID}.txt << 'EOF'
Read /path/to/file.ts and find bugs. Output ONLY JSON: {"bugs": [{"line": N, "description": "..."}]}
EOF

cat > /tmp/task_b_${TASK_ID}.txt << 'EOF'
Read /path/to/other.ts and check style. Output ONLY JSON: {"issues": [...]}
EOF

# Launch in parallel
.claude/scripts/agent_invoke.sh gpt-4.1 /tmp/task_a_${TASK_ID}.txt /tmp/res_a_${TASK_ID}.txt &
PID_A=$!
.claude/scripts/agent_invoke.sh gpt-4.1 /tmp/task_b_${TASK_ID}.txt /tmp/res_b_${TASK_ID}.txt &
PID_B=$!

wait $PID_A $PID_B

RESULT_A=$(cat /tmp/res_a_${TASK_ID}.txt)
RESULT_B=$(cat /tmp/res_b_${TASK_ID}.txt)
```

### Passing large files to an agent

Always write diffs and large files to temp files and pass paths in the prompt — `--allow-all-paths` lets the agent read them directly:

```bash
gh pr diff "$PR_NUMBER" > /tmp/pr_diff_${PR_NUMBER}.txt

cat > /tmp/task_${TASK_ID}.txt << EOF
Read /tmp/pr_diff_${PR_NUMBER}.txt and find all obvious bugs.
Output ONLY JSON: {"issues": [{"file": "...", "line": N, "description": "..."}]}
EOF
```

### Structured output

Always include a JSON schema in the prompt — reduces likelihood of markdown wrapping:

```
Output ONLY valid JSON matching this schema, no prose, no markdown:
{"result": "..."}
```

---

## Tiered Pipeline (cheap first, expensive only if needed)

```bash
TASK_ID=$RANDOM

# Tier 0: cheap routing/eligibility
cat > /tmp/task_route_${TASK_ID}.txt << 'EOF'
<routing task — classify or check eligibility>
Output ONLY JSON: {"proceed": true/false, "reason": "..."}
EOF
.claude/scripts/agent_invoke.sh gpt-5-mini /tmp/task_route_${TASK_ID}.txt /tmp/res_route_${TASK_ID}.txt
ROUTE=$(cat /tmp/res_route_${TASK_ID}.txt)

# Parse ROUTE — if proceed=false, stop
# Tier 1: parallel medium-weight analysis with gpt-4.1 (&)
# Tier 2: single deep review (claude-sonnet-4.6) only if Tier 1 found issues
```

---

## Prompt Writing Rules

1. **Always specify output format** — `Output ONLY JSON: {...}` or `Output ONLY plain text.`
2. **Reference files by path** — agents use `--allow-all-paths`, so `Read /path/to/file` works
3. **No ambiguity** — be explicit: "Scan ONLY changed lines", "Skip pre-existing issues"
4. **Keep prompts focused** — one concern per agent, not "do everything"
5. **Use `$TASK_ID`** in all temp file names to avoid collisions between parallel runs

---

## Temp File Conventions

```
/tmp/task_<label>_<TASK_ID>.txt   ← prompt input
/tmp/res_<label>_<TASK_ID>.txt    ← agent output
/tmp/<domain>_<id>.txt            ← named artifacts (diffs, meta)
```

Clean up with `rm /tmp/*_${TASK_ID}.txt` after consolidation.

---

## Anti-Patterns

- **DO NOT** spawn internal Claude sub-agents (via `Agent` tool) for tasks this script can handle
- **DO NOT** read large diffs (>200 lines) into main context — save to file and delegate
- **DO NOT** use hardcoded `/tmp/` paths without `$TASK_ID` — parallel runs will collide
- **DO NOT** pass secrets or credentials in prompt files
