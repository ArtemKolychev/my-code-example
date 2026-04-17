# Command: code-review

When the user invokes `/code-review [PR_NUMBER]`, strictly use external subprocesses via `.claude/scripts/agent_invoke.sh` to preserve main context tokens. Do NOT spawn internal sub-agents.

## Mode Detection

**If PR_NUMBER is provided** → PR review mode (fetch diff from GitHub).

**If no PR_NUMBER** → local diff mode (review uncommitted changes via `git diff HEAD`).

---

## Mode A: PR Review

**Preparation:**

```bash
gh pr diff "$PR_NUMBER" > /tmp/pr_diff_${PR_NUMBER}.txt
gh pr view "$PR_NUMBER" --json number,title,state,isDraft,author,body > /tmp/pr_meta_${PR_NUMBER}.txt
HEAD_SHA=$(gh pr view "$PR_NUMBER" --json headRefOid --jq '.headRefOid')
REPO=$(gh pr view "$PR_NUMBER" --json headRepository --jq '.headRepository.nameWithOwner')
CHANGED_FILES=$(gh pr diff "$PR_NUMBER" --name-only)
DIFF_FILE=/tmp/pr_diff_${PR_NUMBER}.txt
```

---

**Step 1: Eligibility & Context Gathering (Tier 0 - Cost 0x)**

* **Model:** `gpt-5-mini`
* **Task:** Check PR status. Gather applicable CLAUDE.md guidelines.

```bash
TASK_ID=$RANDOM
cat > /tmp/task_elig_${TASK_ID}.txt << EOF
Read /tmp/pr_meta_${PR_NUMBER}.txt.
1. Is this PR eligible for review? Output {"eligible": false, "reason": "..."} if: state is CLOSED, isDraft is true, it is a bot/automated PR, or it is trivially simple (single typo fix, version bump).
2. List paths (not contents) of applicable CLAUDE.md files: root CLAUDE.md and CLAUDE.md in directories of these changed files: ${CHANGED_FILES}
Output ONLY JSON: {"eligible": true/false, "reason": "string", "claude_md_paths": ["path1", ...]}
EOF

.claude/scripts/agent_invoke.sh gpt-5-mini /tmp/task_elig_${TASK_ID}.txt /tmp/res_elig_${TASK_ID}.txt
ELIG_RESULT=$(cat /tmp/res_elig_${TASK_ID}.txt)
```

Parse result. If `eligible` is false, stop and print the reason.

---

**Step 2: Massive Parallel Review (Tier 0 - Cost 0x)**

* **Model:** `gpt-4.1`
* **Task:** Spawn 4 parallel background processes (`&` + `wait`):

```bash
TASK_ID=$RANDOM
CLAUDE_MD_PATHS=$(echo "$ELIG_RESULT" | python3 -c "import json,sys; print(' '.join(json.loads(sys.stdin.read()).get('claude_md_paths', [])))")

# Agent A: Bug hunting & typings
cat > /tmp/task_bugs_${TASK_ID}.txt << EOF
Read ${DIFF_FILE}. Scan ONLY changed lines for obvious bugs and type errors (no nitpicks, no style, no linter catches — large bugs only). Also read these CLAUDE.md files if they exist: ${CLAUDE_MD_PATHS}
Output ONLY JSON: {"issues": [{"file": "...", "line": N, "description": "...", "type": "bug"}]}
EOF

# Agent B: Git history alignment
cat > /tmp/task_hist_${TASK_ID}.txt << EOF
Use git log to check recent history of files changed in ${DIFF_FILE} (read the diff first to get file names). Look for bugs that become apparent from historical context (e.g. reverts a previous fix, repeats a past regression).
Output ONLY JSON: {"issues": [{"file": "...", "line": N, "description": "...", "type": "historical"}]}
EOF

# Agent C: Previous PR comments context (skip in local diff mode — no PR)
cat > /tmp/task_prev_${TASK_ID}.txt << EOF
Read ${DIFF_FILE} to get changed file names. Use gh CLI to find previous pull requests that touched the same files. Check if review comments from those PRs also apply to the current diff. If no GitHub remote is available, output {"issues":[]}.
Output ONLY JSON: {"issues": [{"file": "...", "line": N, "description": "...", "type": "prev_comment"}]}
EOF

# Agent D: Code style and documentation
cat > /tmp/task_style_${TASK_ID}.txt << EOF
Read ${DIFF_FILE}. For modified files, also read inline code comments and TODOs in the full files. Check if the diff violates any documented contracts or code comments. Also check these CLAUDE.md files: ${CLAUDE_MD_PATHS}
Output ONLY JSON: {"issues": [{"file": "...", "line": N, "description": "...", "type": "style_or_comment"}]}
EOF

.claude/scripts/agent_invoke.sh gpt-4.1 /tmp/task_bugs_${TASK_ID}.txt /tmp/res_bugs_${TASK_ID}.txt &
PID_A=$!
.claude/scripts/agent_invoke.sh gpt-4.1 /tmp/task_hist_${TASK_ID}.txt /tmp/res_hist_${TASK_ID}.txt &
PID_B=$!
.claude/scripts/agent_invoke.sh gpt-4.1 /tmp/task_prev_${TASK_ID}.txt /tmp/res_prev_${TASK_ID}.txt &
PID_C=$!
.claude/scripts/agent_invoke.sh gpt-4.1 /tmp/task_style_${TASK_ID}.txt /tmp/res_style_${TASK_ID}.txt &
PID_D=$!

wait $PID_A $PID_B $PID_C $PID_D
```

Collect all issues from the 4 output files into a unified list.

---

**Step 3: Deep Logical Review (Tier 2 - Cost 1x)**

* **Model:** `claude-sonnet-4.6`
* **Task:** Spawn 1 process to analyze the complex business logic and architectural integrity of the diff.

```bash
cat > /tmp/task_arch_${TASK_ID}.txt << EOF
Read ${DIFF_FILE}. Also read these CLAUDE.md files if they exist: ${CLAUDE_MD_PATHS}
Perform a deep architectural and business logic review. Focus on: race conditions, security issues, incorrect state management, broken invariants, edge cases that will be hit in production.
Do NOT flag: style issues, missing tests, linter catches, pre-existing issues, issues on unmodified lines.
Output ONLY JSON: {"issues": [{"file": "...", "line": N, "description": "...", "type": "architectural", "confidence": 0-100}]}
Where confidence: 75=highly likely real, 100=certain.
EOF

.claude/scripts/agent_invoke.sh claude-sonnet-4.6 /tmp/task_arch_${TASK_ID}.txt /tmp/res_arch_${TASK_ID}.txt
```

---

**Step 4: Consolidation**

Main Claude reads the outputs from `/tmp/res_*.txt`:

1. Merge all issues from Steps 2 and 3 into one list.
2. Filter: remove issues with confidence < 75 (for Step 3 issues). For Step 2 issues without explicit confidence, keep only those that are clearly real bugs (not false positives, not pre-existing, not linter catches).
3. **PR mode:** post via `gh pr comment "$PR_NUMBER" --body "$COMMENT"` using the format below.
4. **Local diff mode:** print the review directly to the user (no gh comment).

In PR mode, generate full SHA links: `https://github.com/<owner>/<repo>/blob/<SHA>/<file>#L<start>-L<end>`

In local diff mode, use plain `file:line` references.

### Comment/Output Format

```
### Code review

Found N issues:

1. <brief description> (CLAUDE.md says "<rule>" / bug due to <context>)

https://github.com/<REPO>/blob/<HEAD_SHA>/<file>#L<start>-L<end>   ← PR mode
<file>:<line>                                                        ← local mode

2. ...

🤖 Generated with [Claude Code](https://claude.ai/code)

<sub>- If this code review was useful, please react with 👍. Otherwise, react with 👎.</sub>
```

Or if no issues:

```
### Code review

No issues found. Checked for bugs, git history, previous PR comments, and CLAUDE.md compliance.

🤖 Generated with [Claude Code](https://claude.ai/code)
```

---

## Mode B: Local Diff (no PR number)

**Preparation:**

```bash
DIFF_FILE=/tmp/local_diff_review.txt
git diff HEAD > "$DIFF_FILE"
CHANGED_FILES=$(git diff HEAD --name-only)
HEAD_SHA=$(git rev-parse HEAD)
```

Then proceed identically from **Step 1** with the following differences:
- Skip the eligibility PR-status check — always `eligible: true`.
- `ELIG_RESULT='{"eligible":true,"reason":"local diff","claude_md_paths":[]}'` (let agent discover CLAUDE.md paths from CHANGED_FILES as usual).
- Skip posting a gh comment — print results to the user directly.

---

## Notes

- Never attempt to build or typecheck the app — CI handles it.
- Do NOT spawn internal Claude sub-agents — use `agent_invoke.sh` only.
- Always use `$DIFF_FILE` variable (not hardcoded path) in agent prompts — works for both modes.
- Temp files: `/tmp/local_diff_review.txt`, `/tmp/pr_diff_$PR.txt`, `/tmp/task_*_$ID.txt`, `/tmp/res_*_$ID.txt`
- Always use full git sha1 in links — never `$(git rev-parse HEAD)` in the comment string itself.
