import { fetcher } from './fetcher';

const GATEWAY = process.env['NEXT_PUBLIC_GATEWAY_URL'] ?? 'http://localhost:3000';

export interface RegisterPayload {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
}

export interface LoginPayload {
  email: string;
  password: string;
}

export interface RegisterResult {
  jobId: string;
}

export interface LoginResult {
  access_token: string;
  refresh_token: string;
  expires_in: number;
  token_type: string;
}

async function request<T>(path: string, body: unknown): Promise<T> {
  const res = await fetcher(`${GATEWAY}/api${path}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });

  if (!res.ok) {
    throw new Error(`HTTP ${res.status}`);
  }

  const data = (await res.json()) as T;

  return data;
}

export async function register(payload: RegisterPayload): Promise<RegisterResult> {
  return request<RegisterResult>('/auth/register', payload);
}

export async function login(payload: LoginPayload): Promise<LoginResult> {
  return request<LoginResult>('/auth/login', payload);
}
