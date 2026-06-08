import {
  Injectable,
  NestInterceptor,
  ExecutionContext,
  CallHandler,
  Inject,
} from '@nestjs/common';
import type { Observable } from 'rxjs';
import { tap } from 'rxjs/operators';
import type * as winston from 'winston';
import type { Request } from 'express';
import { LOGGER } from '../logger/logger.module';

@Injectable()
export class LoggingInterceptor implements NestInterceptor {
  constructor(
    @Inject(LOGGER) private readonly logger: winston.Logger,
  ) {}

  intercept(context: ExecutionContext, next: CallHandler): Observable<unknown> {
    const req = context.switchToHttp().getRequest<Request>();
    const { method, path } = req;
    const requestId = req.headers['x-request-id'] as string | undefined;

    this.logger.info('request.received', { method, path, requestId });

    return next.handle().pipe(
      tap({
        next: () => {
          const res = context.switchToHttp().getResponse<{ statusCode: number }>();
          this.logger.info('request.done', {
            method,
            path,
            requestId,
            statusCode: res.statusCode,
          });
        },
        error: (err: { status?: number; message?: string }) => {
          this.logger.error('request.error', {
            method,
            path,
            requestId,
            statusCode: err.status ?? 500,
            error: err.message,
          });
        },
      }),
    );
  }
}
