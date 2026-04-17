# Command: fetch-context

When the user needs main Claude to understand a large module, architecture, or multiple files, use `/fetch-context <paths>`. 

## Logic

1. Main Claude does NOT read the files directly.
2. Write a prompt to `/tmp/task_fetch_$RANDOM.txt` instructing the agent to read the specified paths and produce a high-density technical summary (max 1500 tokens). Focus on exported functions, class relationships, and DB schemas.
3. Call `.claude/scripts/agent_invoke.sh` using **`gpt-4.1` (Tier 0 - 0x cost)**.
4. Main Claude reads the resulting summary. You just compressed 50k+ tokens into a free 1.5k token summary for your main context.

## Implementation

```bash
TASK_ID=$RANDOM
PATHS="$@"  # e.g. "src/clicker/src/adapters/base.adapter.ts src/clicker/src/app.module.ts"

cat > /tmp/task_fetch_${TASK_ID}.txt << EOF
Read the following files: ${PATHS}

Produce a HIGH-DENSITY technical summary (max 1500 tokens). Include:
- All exported classes, functions, interfaces with their signatures
- Class relationships and dependencies (what imports what)
- Key business logic patterns and constraints
- DB schemas / entity shapes if present
- Anything a developer MUST know before modifying these files

Do NOT include: boilerplate, obvious code, implementation details of trivial getters/setters.
Output plain text summary (no JSON needed).
EOF

.claude/scripts/agent_invoke.sh gpt-4.1 /tmp/task_fetch_${TASK_ID}.txt /tmp/res_fetch_${TASK_ID}.txt

cat /tmp/res_fetch_${TASK_ID}.txt
```

## Usage Examples

```
/fetch-context src/clicker/src/adapters/base.adapter.ts
/fetch-context src/clicker/src/adapters/ src/clicker/src/browser/
/fetch-context src/be/src/Entity/ src/be/src/Repository/
```

## Notes

- For single files under ~200 lines, just use the Read tool directly — it is faster.
- Use this command when the total content would exceed ~500 lines or involves 3+ files.
- The summary is written to stdout so main Claude can read it inline.
- Model: `gpt-4.1` (Tier 0, free). If output is garbled, retry with `claude-haiku-4.5` (Tier 1).
