import { Injectable, Inject } from '@nestjs/common';
import axios from 'axios';
import type { Request } from 'express';
import type * as winston from 'winston';
import type { RouteConfig } from '../routing/routing.config';
import { LOGGER } from '../logger/logger.module';

interface ProxyResult {
  status: number;
  data: unknown;
}

const BACKEND_URL = process.env['BACKEND_URL'] ?? 'http://localhost:8001';

@Injectable()
export class HttpProxyService {
  constructor(@Inject(LOGGER) private readonly logger: winston.Logger) {}

  async forward(req: Request, _route: RouteConfig): Promise<ProxyResult> {
    const baseUrl = BACKEND_URL;
    const queryString = req.url.includes('?') ? req.url.slice(req.url.indexOf('?')) : '';
    const url = `${baseUrl}/api${req.path}${queryString}`;
    const requestId = req.headers['x-request-id'] as string | undefined;

    this.logger.debug('proxy.forwarding', { method: req.method, url, requestId });

    const response = await axios({
      method: req.method.toLowerCase(),
      url,
      data: req.body as unknown,
      headers: {
        'content-type': req.headers['content-type'] ?? 'application/json',
        ...(req.headers['authorization'] ? { authorization: req.headers['authorization'] } : {}),
        ...(req.headers['traceparent'] ? { traceparent: req.headers['traceparent'] } : {}),
        ...(requestId ? { 'x-request-id': requestId } : {}),
      },
      validateStatus: () => true,
    });

    this.logger.info('proxy.forwarded', { method: req.method, url, requestId, status: response.status });

    return { status: response.status, data: response.data };
  }
}
