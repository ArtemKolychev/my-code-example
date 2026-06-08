import { Module, NestModule, MiddlewareConsumer } from '@nestjs/common';
import { APP_INTERCEPTOR } from '@nestjs/core';
import { AuthModule } from './auth/auth.module';
import { RoutingModule } from './routing/routing.module';
import { ProxyModule } from './proxy/proxy.module';
import { MessagingModule } from './messaging/messaging.module';
import { JobsModule } from './jobs/jobs.module';
import { LoggerModule } from './logger/logger.module';
import { CorrelationIdMiddleware } from './middleware/correlation-id.middleware';
import { LoggingInterceptor } from './interceptors/logging.interceptor';

@Module({
  imports: [
    LoggerModule,
    AuthModule,
    MessagingModule,
    ProxyModule,
    JobsModule,
    RoutingModule,
  ],
  providers: [
    { provide: APP_INTERCEPTOR, useClass: LoggingInterceptor },
  ],
})
export class AppModule implements NestModule {
  configure(consumer: MiddlewareConsumer): void {
    consumer.apply(CorrelationIdMiddleware).forRoutes('*splat');
  }
}
