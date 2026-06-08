import { Controller, Get, Param, Req, Res, UseGuards } from '@nestjs/common';
import type { Request, Response } from 'express';
import { JwtGuard } from '../auth/jwt.guard';
import { JobsService } from './jobs.service';

const SSE_TIMEOUT_MS = 60_000;

@Controller('jobs')
@UseGuards(JwtGuard)
export class JobsController {
  constructor(private readonly jobsService: JobsService) {}

  @Get(':jobId/stream')
  stream(@Param('jobId') jobId: string, @Req() req: Request, @Res() res: Response): void {
    res.setHeader('Content-Type', 'text/event-stream');
    res.setHeader('Cache-Control', 'no-cache');
    res.setHeader('Connection', 'keep-alive');
    res.flushHeaders();

    const timeout = setTimeout(() => {
      res.write('data: {"status":"timeout"}\n\n');
      res.end();
      this.jobsService.cleanup(jobId);
    }, SSE_TIMEOUT_MS);

    const subscription = this.jobsService.subscribe(jobId).subscribe({
      next: (data) => {
        res.write(`data: ${JSON.stringify(data)}\n\n`);
        clearTimeout(timeout);
        res.end();
      },
      error: () => {
        clearTimeout(timeout);
        res.end();
      },
      complete: () => {
        clearTimeout(timeout);
      },
    });

    req.on('close', () => {
      clearTimeout(timeout);
      subscription.unsubscribe();
      this.jobsService.cleanup(jobId);
    });
  }
}
