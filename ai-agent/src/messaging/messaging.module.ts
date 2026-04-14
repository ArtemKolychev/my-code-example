import { Module } from "@nestjs/common";
import { RabbitMQModule } from "@golevelup/nestjs-rabbitmq";
import { EventPublisherService } from "./event-publisher.service";

@Module({
  imports: [
    RabbitMQModule.forRoot({
      uri: process.env.RABBITMQ_URL || "amqp://guest:guest@rabbitmq:5672",
      exchanges: [
        { name: "clicker.actions", type: "topic" },
        { name: "clicker.events", type: "topic" },
      ],
      connectionInitOptions: { wait: true, timeout: 10_000 },
      prefetchCount: 2,
      enableControllerDiscovery: true,
    }),
  ],
  providers: [EventPublisherService],
  exports: [RabbitMQModule, EventPublisherService],
})
export class MessagingModule {}
