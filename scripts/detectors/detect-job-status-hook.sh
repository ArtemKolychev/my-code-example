#!/usr/bin/env bash
set -euo pipefail
REPO_ROOT="${1:-.}"  # repo root passed as first arg, default to cwd

# Use rg if available, otherwise fall back to grep -rE
if command -v rg &>/dev/null; then
  SEARCH() { rg --quiet "$1" "$2"; }
else
  SEARCH() { grep -rEq "$1" "$2"; }
fi

if SEARCH '\buseJobStatus\b' "$REPO_ROOT/src/frontend/src/"; then
  echo "OK: useJobStatus hook import found"
  exit 0
else
  echo "ERROR: useJobStatus not found — hook is not imported/used in src/frontend/src/"
  exit 1
fi
