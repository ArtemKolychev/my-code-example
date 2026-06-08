type FetchArgs = Parameters<typeof fetch>;

export async function fetcher(input: FetchArgs[0], init?: FetchArgs[1]): Promise<Response> {
  const extraHeaders: Record<string, string> = {
    'x-correlation-id': crypto.randomUUID(),
  };

  if (typeof window === 'undefined') {
    const { headers: nextHeaders } = await import('next/headers');
    const incoming = await nextHeaders();
    const traceparent = incoming.get('traceparent');
    if (traceparent !== null) {
      extraHeaders['traceparent'] = traceparent;
    }
  }

  const merged: RequestInit = {
    ...init,
    headers: {
      ...extraHeaders,
      ...(init?.headers as Record<string, string> | undefined),
    },
  };

  return fetch(input, merged);
}
