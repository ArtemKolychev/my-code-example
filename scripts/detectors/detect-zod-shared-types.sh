#!/usr/bin/env bash
set -euo pipefail
REPO_ROOT="${1:-.}"  # repo root passed as first arg, default to cwd

# Use rg if available, otherwise fall back to grep -rE
if command -v rg &>/dev/null; then
  SEARCH() { rg --quiet "$1" "$2"; }
else
  SEARCH() { grep -rEq "$1" "$2"; }
fi

if SEARCH "(registerSchema|loginSchema)" "$REPO_ROOT/src/frontend/src/"; then
  echo "OK: @core/shared-types schema import found (registerSchema or loginSchema)"
  exit 0
else
  echo "ERROR: neither registerSchema nor loginSchema found in src/frontend/src/ — @core/shared-types Zod schemas are not imported"
  exit 1
fi
