#!/usr/bin/env bash
set -euo pipefail
REPO_ROOT="${1:-.}"  # repo root passed as first arg, default to cwd

# Use rg if available, otherwise fall back to grep -rE
if command -v rg &>/dev/null; then
  SEARCH() { rg --quiet "$1" "$2"; }
else
  SEARCH() { grep -rEq "$1" "$2"; }
fi

if SEARCH "x-correlation-id" "$REPO_ROOT/src/frontend/src/lib/fetcher.ts"; then
  echo "OK: x-correlation-id header found in fetcher.ts"
  exit 0
else
  echo "ERROR: x-correlation-id not found in src/frontend/src/lib/fetcher.ts — correlation header fetcher is missing"
  exit 1
fi
