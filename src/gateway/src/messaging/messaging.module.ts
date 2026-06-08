import { Module } from '@nestjs/common';
import { RabbitMQPublisherService } from './rabbitmq-publisher.service';
import { JobsModule } from '../jobs/jobs.module';

@Module({
  imports: [JobsModule],
  providers: [RabbitMQPublisherService],
  exports: [RabbitMQPublisherService],
})
export class MessagingModule {}
