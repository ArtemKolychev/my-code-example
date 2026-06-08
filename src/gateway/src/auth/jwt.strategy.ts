import { Injectable } from '@nestjs/common';
import { PassportStrategy } from '@nestjs/passport';
import { ExtractJwt, Strategy } from 'passport-jwt';
import { readFileSync } from 'fs';

interface JwtPayload {
  sub: string;
  tenantId?: string;
  roles?: string[];
}

export interface AuthUser {
  userId: string;
  tenantId: string | undefined;
  roles: string[];
}

@Injectable()
export class JwtStrategy extends PassportStrategy(Strategy) {
  constructor() {
    const keyPath = process.env['JWT_PUBLIC_KEY'];
    const secretOrKey = keyPath
      ? readFileSync(keyPath, 'utf8')
      : (process.env['JWT_PUBLIC_KEY_INLINE'] ?? '');

    super({
      jwtFromRequest: ExtractJwt.fromAuthHeaderAsBearerToken(),
      ignoreExpiration: false,
      secretOrKey,
      algorithms: ['RS256'],
    });
  }

  validate(payload: JwtPayload): AuthUser {
    return {
      userId: payload.sub,
      tenantId: payload.tenantId,
      roles: payload.roles ?? [],
    };
  }
}
