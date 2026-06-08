'use client';

import { useState, useEffect, useRef, useCallback } from 'react';

export type JobStatus = 'pending' | 'done' | 'timeout' | 'error';

export interface JobState {
  status: JobStatus;
  data?: unknown;
}

const GATEWAY = process.env['NEXT_PUBLIC_GATEWAY_URL'] ?? 'http://localhost:3000';
const RETRY_INTERVAL_MS = 3000;
const MAX_RETRIES = 3;

function buildUrl(jobId: string, token: string): string {
  return `${GATEWAY}/api/jobs/${jobId}/stream?token=${encodeURIComponent(token)}`;
}

export function useJobStatus(jobId: string | null, token: string | null): JobState {
  const [jobState, setJobState] = useState<JobState>({ status: 'pending' });
  const retryCountRef = useRef(0);
  const esRef = useRef<EventSource | null>(null);
  const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const isTerminalRef = useRef(false);

  const cleanup = useCallback(() => {
    if (esRef.current) {
      esRef.current.close();
      esRef.current = null;
    }
    if (timerRef.current !== null) {
      clearTimeout(timerRef.current);
      timerRef.current = null;
    }
  }, []);

  /**
   * Opens a single EventSource session at `url`.
   * On a terminal message (done/timeout) it calls `onDone`.
   * On an error it calls `onRetry` so the caller can schedule the next attempt.
   */
  const openSession = useCallback(
    (
      url: string,
      onDone: (state: JobState) => void,
      onRetry: () => void,
    ) => {
      cleanup();

      const es = new EventSource(url);
      esRef.current = es;

      es.onmessage = (event: MessageEvent) => {
        let parsed: { status: string; data?: unknown };
        try {
          parsed = JSON.parse(event.data as string) as { status: string; data?: unknown };
        } catch {
          return;
        }

        if (parsed.status === 'done') {
          isTerminalRef.current = true;
          onDone({ status: 'done', data: parsed.data });
          cleanup();
        } else if (parsed.status === 'timeout') {
          isTerminalRef.current = true;
          onDone({ status: 'timeout' });
          cleanup();
        }
      };

      es.onerror = () => {
        cleanup();
        if (isTerminalRef.current) return;

        retryCountRef.current += 1;
        if (retryCountRef.current >= MAX_RETRIES) {
          isTerminalRef.current = true;
          onDone({ status: 'error' });
        } else {
          onRetry();
        }
      };
    },
    [cleanup],
  );

  const connect = useCallback(
    (id: string, tkn: string) => {
      if (isTerminalRef.current) return;

      openSession(
        buildUrl(id, tkn),
        (state) => setJobState(state),
        () => {
          timerRef.current = setTimeout(() => connect(id, tkn), RETRY_INTERVAL_MS);
        },
      );
    },
    [openSession],
  );

  // Polling fallback: EventSource unavailable (SSR or old browser)
  const startPolling = useCallback(
    (id: string, tkn: string) => {
      if (isTerminalRef.current) return;

      const poll = () => {
        if (isTerminalRef.current) return;

        openSession(
          buildUrl(id, tkn),
          (state) => setJobState(state),
          () => {
            timerRef.current = setTimeout(poll, RETRY_INTERVAL_MS);
          },
        );
      };

      timerRef.current = setTimeout(poll, RETRY_INTERVAL_MS);
    },
    [openSession],
  );

  useEffect(() => {
    if (!jobId || !token) {
      setJobState({ status: 'pending' });
      return;
    }

    // Reset state for new jobId
    isTerminalRef.current = false;
    retryCountRef.current = 0;
    setJobState({ status: 'pending' });

    if (typeof EventSource === 'undefined') {
      startPolling(jobId, token);
    } else {
      connect(jobId, token);
    }

    return () => {
      isTerminalRef.current = true;
      cleanup();
    };
  }, [jobId, token, connect, startPolling, cleanup]);

  return jobState;
}
