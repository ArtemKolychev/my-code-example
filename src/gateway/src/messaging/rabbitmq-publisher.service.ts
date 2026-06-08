import { Injectable, OnModuleInit, OnModuleDestroy } from '@nestjs/common';
import { randomUUID } from 'crypto';
import * as amqp from 'amqplib';
import type { Request } from 'express';
import type { RouteConfig } from '../routing/routing.config';
import { JobsService } from '../jobs/jobs.service';

const EXCHANGE = 'core.commands';

const VERB_MAP: Record<string, string> = {
  POST: 'create',
  PUT: 'update',
  PATCH: 'update',
  DELETE: 'delete',
};

@Injectable()
export class RabbitMQPublisherService implements OnModuleInit, OnModuleDestroy {
  private connection: amqp.ChannelModel | null = null;
  private channel: amqp.Channel | null = null;

  constructor(private readonly jobsService: JobsService) {}

  async onModuleInit(): Promise<void> {
    const url = process.env['RABBITMQ_URL'] ?? 'amqp://guest:guest@localhost:5672';
    this.connection = await amqp.connect(url);
    this.channel = await this.connection.createChannel();
    await this.channel.assertExchange(EXCHANGE, 'topic', { durable: true });

    await this.subscribeToEvents();
  }

  async onModuleDestroy(): Promise<void> {
    await this.channel?.close();
    await this.connection?.close();
  }

  async publish(req: Request, route: RouteConfig): Promise<string> {
    if (!this.channel) throw new Error('RabbitMQ channel not initialized');

    const jobId = randomUUID();
    const verb = VERB_MAP[req.method.toUpperCase()] ?? 'action';
    const routingKey = `core.commands.${route.module}.${verb}.v1`;

    const payload = JSON.stringify({
      jobId,
      ...(req.body as object),
    });

    this.channel.publish(EXCHANGE, routingKey, Buffer.from(payload), {
      persistent: true,
      headers: {
        jobId,
      },
    });

    await this.jobsService.setPending(jobId);
    return jobId;
  }

  private async subscribeToEvents(): Promise<void> {
    if (!this.channel) return;

    const queue = await this.channel.assertQueue('gateway.events', { exclusive: false, durable: true });
    await this.channel.bindQueue(queue.queue, 'core.events', 'core.events.#');

    await this.channel.consume(queue.queue, (msg) => {
      if (!msg) return;
      try {
        const payload = JSON.parse(msg.content.toString()) as { jobId?: string };
        if (payload.jobId) {
          void this.jobsService.setDone(payload.jobId, payload);
        }
        this.channel?.ack(msg);
      } catch {
        this.channel?.nack(msg, false, false);
      }
    });
  }
}
