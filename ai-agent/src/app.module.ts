import { Module } from "@nestjs/common";
import { LoggerModule } from "nestjs-pino";
import { MessagingModule } from "./messaging/messaging.module";
import { CommandConsumerService } from "./messaging/command-consumer.service";
import { GroupImagesService } from "./services/group-images.service";
import { PricingAgentService } from "./services/pricing-agent.service";
import { LlmChoiceService } from "./services/llm-choice.service";
import { VehicleEnrichmentService } from "./services/vehicle-enrichment.service";
import { ChoiceController } from "./choice.controller";

const lokiUrl = process.env.LOKI_URL ?? "http://loki:3100";

@Module({
  imports: [
    MessagingModule,
    LoggerModule.forRoot({
      pinoHttp: {
        level:
          process.env.LOG_LEVEL ??
          (process.env.NODE_ENV === "production" ? "info" : "debug"),
        transport: {
          targets: [
            {
              target: "pino-loki",
              options: {
                host: lokiUrl,
                labels: { service: "ai-agent" },
                silenceErrors: true,
                replaceTimestamp: false,
                batching: true,
                interval: 5,
              },
              level: "debug",
            },
            {
              target: "pino/file",
              options: { destination: 1 },
              level: process.env.LOG_LEVEL ?? "debug",
            },
          ],
        },
      },
    }),
  ],
  controllers: [ChoiceController],
  providers: [
    CommandConsumerService,
    GroupImagesService,
    PricingAgentService,
    LlmChoiceService,
    VehicleEnrichmentService,
  ],
})
export class AppModule {}
