#!/bin/bash
# Usage: ./agent_invoke.sh <model> <prompt_file> <output_file>
MODEL=$1
PROMPT_FILE=$2
OUTPUT_FILE=$3

# Call copilot. --allow-all-paths lets the agent read files referenced in the prompt.
OUTPUT=$(gh copilot -- --model "$MODEL" --prompt "$(cat "$PROMPT_FILE")" --allow-all-tools --allow-all-paths --silent 2>&1)

# Try to extract clean JSON if copilot wrapped output in markdown fences
EXTRACTED=$(echo "$OUTPUT" | sed -n '/^```json/,/^```/p' | sed '/^```/d')

if [ -n "$EXTRACTED" ]; then
    echo "$EXTRACTED" > "$OUTPUT_FILE"
else
    # No markdown fences — write output as-is (text or code)
    echo "$OUTPUT" > "$OUTPUT_FILE"
fi
