import { Injectable, OnModuleInit } from '@nestjs/common';
import { Redis } from 'ioredis';
import { Observable, Subject } from 'rxjs';

const TTL_SECONDS = 86400; // 24h

interface JobResult {
  status: 'pending' | 'done' | 'timeout';
  data?: unknown;
}

@Injectable()
export class JobsService implements OnModuleInit {
  private redis!: Redis;
  private readonly subjects = new Map<string, Subject<JobResult>>();

  onModuleInit(): void {
    this.redis = new Redis({
      host: process.env['REDIS_HOST'] ?? 'localhost',
      port: parseInt(process.env['REDIS_PORT'] ?? '6379', 10),
      password: process.env['REDIS_PASSWORD'] ?? undefined,
    });
  }

  async setPending(jobId: string): Promise<void> {
    await this.redis.setex(`jobs:${jobId}`, TTL_SECONDS, JSON.stringify({ status: 'pending' }));
  }

  async setDone(jobId: string, data: unknown): Promise<void> {
    const result: JobResult = { status: 'done', data };
    await this.redis.setex(`jobs:${jobId}`, TTL_SECONDS, JSON.stringify(result));
    this.subjects.get(jobId)?.next(result);
    this.subjects.get(jobId)?.complete();
    this.subjects.delete(jobId);
  }

  subscribe(jobId: string): Observable<JobResult> {
    const subject = new Subject<JobResult>();
    this.subjects.set(jobId, subject);

    void this.redis.get(`jobs:${jobId}`).then((raw) => {
      if (!raw) return;
      const stored = JSON.parse(raw) as JobResult;
      if (stored.status === 'done') {
        subject.next(stored);
        subject.complete();
        this.subjects.delete(jobId);
      }
    });

    return subject.asObservable();
  }

  cleanup(jobId: string): void {
    this.subjects.get(jobId)?.complete();
    this.subjects.delete(jobId);
  }
}
