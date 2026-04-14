import { tool } from "ai";
import { z } from "zod";
import type { EventPublisherService } from "../messaging/event-publisher.service";

export function createAskUserTool(
  eventPublisher: EventPublisherService,
  jobId: string,
  articleId: number | null,
) {
  return tool({
    description: "Ask the user for missing information needed to complete the task.",
    parameters: z.object({
      prompt: z.string().describe("The question shown in the UI as a label"),
      inputType: z.string().describe('The type of input expected, e.g. "vin_or_spz"'),
      imageUrls: z
        .array(z.string())
        .nullable()
        .describe(
          "Relative URLs of the specific images that triggered this question (e.g. [\"/uploads/images/foo.jpg\"]). Include only the relevant images, not all images.",
        ),
    }),
    execute: async ({ prompt, inputType, imageUrls }) => {
      await eventPublisher.publishNeedsInput(jobId, articleId, inputType, prompt, imageUrls);
      return { suspended: true };
    },
  });
}
