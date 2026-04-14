import { Injectable, Logger } from "@nestjs/common";
import { RabbitSubscribe } from "@golevelup/nestjs-rabbitmq";
import { openai } from "@ai-sdk/openai";
import { EventPublisherService } from "./event-publisher.service";
import { GroupImagesService, NeedsInputError } from "../services/group-images.service";
import { PricingAgentService } from "../services/pricing-agent.service";
import { VehicleEnrichmentService } from "../services/vehicle-enrichment.service";
import { traceContext } from "../logging/trace-context";
import { loggedGenerateText } from "../logging/logged-generate-text";
import { createAskUserTool } from "../tools/ask-user.tool";
import { createGetCarDataTool } from "../tools/get-car-data.tool";
import type { GroupImagesCommand, SuggestPriceCommand, EnrichVehicleCommand } from "./messages";

@Injectable()
export class CommandConsumerService {
  private readonly logger = new Logger(CommandConsumerService.name);

  constructor(
    private readonly eventPublisher: EventPublisherService,
    private readonly groupImagesService: GroupImagesService,
    private readonly pricingAgentService: PricingAgentService,
    private readonly vehicleEnrichmentService: VehicleEnrichmentService,
  ) {}

  @RabbitSubscribe({
    exchange: "clicker.actions",
    routingKey: "action.group_images",
    queue: "ai-agent.group_images",
  })
  async handleGroupImages(msg: GroupImagesCommand): Promise<void> {
    return traceContext.run(
      { traceId: traceContext.generateTraceId(), jobId: msg.jobId },
      async () => {
        this.logger.log(
          "Received group_images command: jobId=%s, images=%d",
          msg.jobId,
          msg.articleImages.length,
        );

        try {
          await this.eventPublisher.publishProgress(msg.jobId, "group_images", 0, 1);
          const { groups, tokensUsed } = await this.groupImagesService.groupAndDescription(
            msg.articleImages,
            msg.jobId,
            null, // no articleId in group_images context
            msg.vehicleIdentifier,
            msg.condition,
          );
          await this.eventPublisher.publishCompleted(msg.jobId, {
            action: "group_images",
            batchId: msg.batchId,
            groups,
            tokensUsed,
          });
        } catch (err) {
          if (err instanceof NeedsInputError) {
            // Suspended — needs_input event already published by ask_user tool
            // BE will save pendingInput to batch; job resumes when user provides identifier
            this.logger.log("group_images suspended: waiting for vehicle identifier from user (jobId=%s)", msg.jobId);
            return;
          }
          const error = err instanceof Error ? err.message : String(err);
          this.logger.error("group_images failed: %s", error);
          await this.eventPublisher.publishFailed(msg.jobId, "group_images", error);
        }
      },
    );
  }

  @RabbitSubscribe({
    exchange: "clicker.actions",
    routingKey: "action.suggest_price",
    queue: "ai-agent.suggest_price",
  })
  async handleSuggestPrice(msg: SuggestPriceCommand): Promise<void> {
    return traceContext.run(
      { traceId: traceContext.generateTraceId(), jobId: msg.jobId },
      async () => {
        this.logger.log(
          "Received suggest_price command: jobId=%s, articleId=%d",
          msg.jobId,
          msg.articleId,
        );

        try {
          await this.eventPublisher.publishProgress(msg.jobId, "suggest_price", 0, 1);
          const result = await this.pricingAgentService.suggestPrice(msg.title, msg.description, msg.condition);
          await this.eventPublisher.publishCompleted(msg.jobId, {
            action: "suggest_price",
            articleId: msg.articleId,
            price: result.price,
            reasoning: result.reasoning,
            sources: result.sources,
            tokensUsed: result.tokensUsed,
          });
        } catch (err) {
          const error = err instanceof Error ? err.message : String(err);
          this.logger.error("suggest_price failed: %s", error);
          await this.eventPublisher.publishFailed(msg.jobId, "suggest_price", error);
        }
      },
    );
  }

  @RabbitSubscribe({
    exchange: "clicker.actions",
    routingKey: "action.enrich_vehicle",
    queue: "ai-agent.enrich_vehicle",
  })
  async handleEnrichVehicle(msg: EnrichVehicleCommand): Promise<void> {
    return traceContext.run(
      { traceId: traceContext.generateTraceId(), jobId: msg.jobId },
      async () => {
        this.logger.log(
          "Received enrich_vehicle command: jobId=%s, articleId=%d, vin=%s, spz=%s",
          msg.jobId,
          msg.articleId,
          msg.vin ?? "",
          msg.spz ?? "",
        );

        try {
          await this.eventPublisher.publishProgress(msg.jobId, "enrich_vehicle", 0, 1);

          let vehicleData = null;

          if (msg.vin) {
            vehicleData = await this.vehicleEnrichmentService.enrichByVin(msg.vin);
          } else if (msg.spz) {
            vehicleData = await this.vehicleEnrichmentService.enrichBySpz(msg.spz);
          } else {
            // No vin/spz — use LLM with images to extract SPZ/VIN or ask user
            const askUserTool = createAskUserTool(this.eventPublisher, msg.jobId, msg.articleId);
            const getCarDataTool = createGetCarDataTool(this.vehicleEnrichmentService);

            const imageContent: Array<
              | { type: "text"; text: string }
              | { type: "image"; image: string; mimeType: string }
            > = [];
            for (const img of msg.articleImages ?? []) {
              const res = await fetch(img.url, { redirect: "follow" });
              if (res.ok) {
                const base64 = Buffer.from(await res.arrayBuffer()).toString("base64");
                imageContent.push({ type: "image", image: base64, mimeType: "image/jpeg" });
              }
            }
            if (imageContent.length === 0) {
              imageContent.push({ type: "text", text: "No images available." });
            }

            const result = await loggedGenerateText(
              {
                model: openai("gpt-5-mini"),
                system: `You are processing a vehicle listing.
1. Look for a license plate (SPZ) or VIN in the provided images.
2. If found, call get_car_data to fetch technical data.
3. If not found in images, call ask_user to request it from the user.`,
                messages: [{ role: "user", content: imageContent }],
                tools: { ask_user: askUserTool, get_car_data: getCarDataTool },
                maxSteps: 3,
              },
              "enrich_vehicle.extract_from_images",
            );

            const wasAsked = result.steps?.some((s) =>
              s.toolCalls?.some((tc) => tc.toolName === "ask_user"),
            );
            if (wasAsked) return; // suspended — wait for user input

            // Extract vehicleData from get_car_data tool result
            // eslint-disable-next-line @typescript-eslint/no-explicit-any
            const allToolResults: any[] = result.steps?.flatMap((s) => (s as any).toolResults ?? []) ?? [];
            const carDataResult = allToolResults.find((tr) => tr.toolName === "get_car_data")?.result;
            vehicleData = carDataResult?.vehicleData ?? null;
          }

          await this.eventPublisher.publishCompleted(msg.jobId, {
            action: "enrich_vehicle",
            articleId: msg.articleId,
            vehicleData: vehicleData ?? {},
            found: vehicleData !== null,
          });
        } catch (err) {
          const error = err instanceof Error ? err.message : String(err);
          this.logger.error("enrich_vehicle failed: %s", error);
          await this.eventPublisher.publishFailed(msg.jobId, "enrich_vehicle", error);
        }
      },
    );
  }
}
