#!/usr/bin/env bash
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

PASS=0
FAIL=0

run_detector() {
  local name="$1"
  local script="$2"
  if bash "$script" "$REPO_ROOT"; then
    echo "PASS: $name"
    PASS=$((PASS + 1))
  else
    echo "FAIL: $name"
    FAIL=$((FAIL + 1))
  fi
}

run_detector "job-status-hook" "$SCRIPT_DIR/detect-job-status-hook.sh"
run_detector "fetch-wrapper" "$SCRIPT_DIR/detect-fetch-wrapper.sh"
run_detector "zod-shared-types" "$SCRIPT_DIR/detect-zod-shared-types.sh"

echo ""
echo "Results: $PASS passed, $FAIL failed"
[ "$FAIL" -eq 0 ]
