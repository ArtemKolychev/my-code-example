import { Module } from '@nestjs/common';
import { PassportModule } from '@nestjs/passport';
import { JwtStrategy } from './jwt.strategy';
import { JwtGuard } from './jwt.guard';
import { Reflector } from '@nestjs/core';

@Module({
  imports: [PassportModule],
  providers: [JwtStrategy, JwtGuard, Reflector],
  exports: [JwtGuard],
})
export class AuthModule {}
