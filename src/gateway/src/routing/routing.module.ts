import { Module } from '@nestjs/common';
import { RoutingController } from './routing.controller';
import { ProxyModule } from '../proxy/proxy.module';
import { MessagingModule } from '../messaging/messaging.module';
import { AuthModule } from '../auth/auth.module';

@Module({
  imports: [AuthModule, ProxyModule, MessagingModule],
  controllers: [RoutingController],
})
export class RoutingModule {}
