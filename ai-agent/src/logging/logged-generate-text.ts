import { Logger } from "@nestjs/common";
import { generateText } from "ai";
import type { GenerateTextResult, LanguageModel } from "ai";

type GenerateTextParams = Parameters<typeof generateText>[0];

export type LoggedGenerateTextResult = GenerateTextResult<
  Record<string, never>,
  never
> & {
  totalTokensUsed: number;
};

const logger = new Logger("llm");

export async function loggedGenerateText(
  params: GenerateTextParams,
  operation: string,
): Promise<LoggedGenerateTextResult> {
  const start = Date.now();
  const model = (
    params.model as LanguageModel & { modelId?: string }
  ).modelId ?? "unknown";

  logger.debug(
    {
      channel: "llm",
      operation,
      model,
      promptLength:
        typeof params.prompt === "string" ? params.prompt.length : undefined,
      maxSteps: params.maxSteps,
    },
    `llm.request: ${operation}`,
  );

  try {
    const result = await generateText(params);
    const durationMs = Date.now() - start;

    logger.log(
      {
        channel: "llm",
        operation,
        model,
        durationMs,
        promptTokens: result.usage?.promptTokens,
        completionTokens: result.usage?.completionTokens,
        totalTokens: result.usage?.totalTokens,
        finishReason: result.finishReason,
        steps: result.steps?.length,
      },
      `llm.response: ${operation}`,
    );

    for (const [i, step] of (result.steps ?? []).entries()) {
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const calls = (step as any).toolCalls ?? [];
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const results = (step as any).toolResults ?? [];
      if (calls.length > 0 || results.length > 0) {
        logger.debug(
          {
            channel: "llm",
            operation,
            step: i,
            toolCalls: calls.map((tc: any) => ({
              name: tc.toolName,
              args: tc.args,
            })),
            toolResults: results.map((tr: any) => ({
              name: tr.toolName,
              result: tr.result,
            })),
          },
          `llm.tools: ${operation}`,
        );
      }
    }

    const totalTokensUsed = result.usage?.totalTokens ?? 0;
    return {
      ...(result as GenerateTextResult<Record<string, never>, never>),
      totalTokensUsed,
    };
  } catch (err) {
    const durationMs = Date.now() - start;
    logger.error(
      {
        channel: "llm",
        operation,
        model,
        durationMs,
        error: err instanceof Error ? err.message : String(err),
      },
      `llm.error: ${operation}`,
    );
    throw err;
  }
}
