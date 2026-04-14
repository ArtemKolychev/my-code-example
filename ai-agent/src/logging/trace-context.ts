import { AsyncLocalStorage } from 'node:async_hooks';
import { randomUUID } from 'node:crypto';

export interface TraceData {
  traceId: string;
  jobId?: string;
}

const storage = new AsyncLocalStorage<TraceData>();

export const traceContext = {
  run<T>(data: TraceData, fn: () => Promise<T>): Promise<T> {
    return storage.run(data, fn);
  },

  get(): TraceData | undefined {
    return storage.getStore();
  },

  getTraceId(): string | undefined {
    return storage.getStore()?.traceId;
  },

  getJobId(): string | undefined {
    return storage.getStore()?.jobId;
  },

  generateTraceId(): string {
    return randomUUID();
  },
};
