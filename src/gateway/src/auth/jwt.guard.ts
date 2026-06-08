import { ExecutionContext, Inject, Injectable } from '@nestjs/common';
import { AuthGuard } from '@nestjs/passport';
import { Reflector } from '@nestjs/core';
import type * as winston from 'winston';
import { IS_PUBLIC_KEY } from './public.decorator';
import { LOGGER } from '../logger/logger.module';

@Injectable()
export class JwtGuard extends AuthGuard('jwt') {
  constructor(
    private readonly reflector: Reflector,
    @Inject(LOGGER) private readonly logger: winston.Logger,
  ) {
    super();
  }

  canActivate(context: ExecutionContext): boolean | Promise<boolean> {
    const req = context.switchToHttp().getRequest<{ path: string }>();
    const { path } = req;

    const isPublic = this.reflector.getAllAndOverride<boolean>(IS_PUBLIC_KEY, [
      context.getHandler(),
      context.getClass(),
    ]);

    if (isPublic) {
      this.logger.debug('jwt.guard.public', { path });
      return true;
    }

    this.logger.debug('jwt.guard.validate', { path });
    return super.canActivate(context) as boolean | Promise<boolean>;
  }

  handleRequest<TUser>(err: Error | null, user: TUser | false, info: { message?: string }, context: ExecutionContext): TUser {
    if (err || !user) {
      this.logger.warn('jwt.guard.rejected', { error: err?.message ?? info?.message });
      return super.handleRequest(err, user, info, context);
    }
    this.logger.debug('jwt.guard.accepted');
    return user;
  }
}
