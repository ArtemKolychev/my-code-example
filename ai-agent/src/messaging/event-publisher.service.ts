import { Injectable, Logger } from "@nestjs/common";
import { AmqpConnection } from "@golevelup/nestjs-rabbitmq";
import type { ProgressEvent, CompletedEvent, FailedEvent, NeedsInputEvent } from "./messages";

const EXCHANGE = "clicker.events";

@Injectable()
export class EventPublisherService {
  private readonly logger = new Logger(EventPublisherService.name);

  constructor(private readonly amqp: AmqpConnection) {}

  async publishProgress(
    jobId: string,
    step: string,
    stepIndex: number,
    totalSteps: number,
  ): Promise<void> {
    const event: ProgressEvent = {
      type: "progress",
      jobId,
      step,
      stepIndex,
      totalSteps,
    };
    this.logger.debug("Publishing progress: %o", event);
    await this.amqp.publish(EXCHANGE, "event.progress", event);
  }

  async publishCompleted(
    jobId: string,
    result: Record<string, unknown>,
  ): Promise<void> {
    const event: CompletedEvent = { type: "completed", jobId, result };
    this.logger.log("Publishing completed: %o", event);
    await this.amqp.publish(EXCHANGE, "event.completed", event);
  }

  async publishFailed(
    jobId: string,
    step: string,
    error: string,
  ): Promise<void> {
    const event: FailedEvent = { type: "failed", jobId, step, error };
    this.logger.error("Publishing failed: %o", event);
    await this.amqp.publish(EXCHANGE, "event.failed", event);
  }

  async publishNeedsInput(
    jobId: string,
    articleId: number | null,
    inputType: string,
    inputPrompt: string,
    imageUrls?: string[],
  ): Promise<void> {
    const event: NeedsInputEvent = { type: "needs_input", jobId, articleId, inputType, inputPrompt, imageUrls };
    this.logger.log("Publishing needs_input: %o", event);
    await this.amqp.publish(EXCHANGE, "event.needs_input", event);
  }
}
