import { Injectable, Logger } from "@nestjs/common";
import { openai } from "@ai-sdk/openai";
import { loggedGenerateText } from "../logging/logged-generate-text";

export interface ChoiceOption {
  value: string;
  label: string;
}

@Injectable()
export class LlmChoiceService {
  private readonly logger = new Logger(LlmChoiceService.name);

  async choose(
    prompt: string,
    options: ChoiceOption[],
    isMultiple = false,
  ): Promise<string[]> {
    this.logger.log(
      "Choosing from %d options, isMultiple=%s",
      options.length,
      isMultiple,
    );

    const userMessage =
      prompt +
      "\n\nOptions:\n" +
      options.map((o) => `${o.value}: ${o.label}`).join("\n") +
      `\n\nSelect ${isMultiple ? "one or more" : "exactly one"}.`;

    const { text } = await loggedGenerateText({
      model: openai("gpt-5-mini"),
      system:
        "You are a classification assistant. Return ONLY a valid JSON array of chosen option values from the provided list, nothing else.",
      prompt: userMessage,
    }, "llm-choice");

    this.logger.log("LLM raw response: %s", text);

    let parsed: unknown;
    try {
      parsed = JSON.parse(text.trim());
    } catch {
      throw new Error(
        `LlmChoiceService: failed to parse JSON response: ${text}`,
      );
    }

    if (!Array.isArray(parsed)) {
      throw new Error(`LlmChoiceService: expected JSON array, got: ${text}`);
    }

    if (options.length === 0) {
      // No constraint list — return LLM's free-form choice as-is
      return (parsed as unknown[]).map((v) => String(v));
    }

    const validValues = new Set(options.map((o) => o.value));
    const result = (parsed as unknown[])
      .map((v) => String(v))
      .filter((v) => validValues.has(v));

    if (result.length === 0) {
      throw new Error(
        `LlmChoiceService: none of the returned values are valid options. Got: ${text}`,
      );
    }

    return result;
  }
}
