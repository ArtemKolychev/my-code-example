import {
  All,
  Controller,
  Req,
  Res,
  UseGuards,
  HttpStatus,
} from '@nestjs/common';
import type { Request, Response } from 'express';
import { match } from 'path-to-regexp';
import { JwtGuard } from '../auth/jwt.guard';
import { Public } from '../auth/public.decorator';
import { HttpProxyService } from '../proxy/http-proxy.service';
import { RabbitMQPublisherService } from '../messaging/rabbitmq-publisher.service';
import { routes, VIA_RABBITMQ } from './routing.config';

@Controller()
@UseGuards(JwtGuard)
export class RoutingController {
  constructor(
    private readonly proxy: HttpProxyService,
    private readonly publisher: RabbitMQPublisherService,
  ) {}

  @All('*splat')
  @Public()
  async handle(@Req() req: Request, @Res() res: Response): Promise<void> {
    const path = '/' + (req.params['splat'] as string | undefined ?? '');
    const method = req.method.toUpperCase();

    for (const route of routes) {
      if (route.method !== method && route.method !== '*') continue;
      const matcher = match(route.path, { decode: decodeURIComponent });
      if (!matcher(path)) continue;

      if (route.via === VIA_RABBITMQ) {
        const jobId = await this.publisher.publish(req, route);
        res.status(HttpStatus.ACCEPTED).json({ jobId });
        return;
      }

      const result = await this.proxy.forward(req, route);
      res.status(result.status).json(result.data);
      return;
    }

    res.status(HttpStatus.NOT_FOUND).json({ message: 'Route not found' });
  }
}
