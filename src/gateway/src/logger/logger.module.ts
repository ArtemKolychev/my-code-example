import { Module, Global } from '@nestjs/common';
import * as winston from 'winston';
import { OpenTelemetryTransportV3 } from '@opentelemetry/winston-transport';

export const LOGGER = Symbol('LOGGER');

const logger = winston.createLogger({
  level: process.env['LOG_LEVEL'] ?? 'debug',
  transports: [
    new winston.transports.Console({
      format: winston.format.combine(
        winston.format.colorize(),
        winston.format.simple(),
      ),
    }),
    new OpenTelemetryTransportV3(),
  ],
});

@Global()
@Module({
  providers: [
    {
      provide: LOGGER,
      useValue: logger,
    },
  ],
  exports: [LOGGER],
})
export class LoggerModule {}
