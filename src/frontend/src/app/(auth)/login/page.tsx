'use client';

import { useState, type FormEvent, type JSX } from 'react';
import { login } from '@/lib/api-client';

export default function LoginPage(): JSX.Element {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  async function handleSubmit(e: FormEvent<HTMLFormElement>): Promise<void> {
    e.preventDefault();
    setError('');
    setLoading(true);

    try {
      const result = await login({ email, password });
      document.cookie = `access_token=${result.access_token}; path=/; max-age=${result.expires_in}`;
      document.cookie = `refresh_token=${result.refresh_token}; path=/; max-age=2592000`;
      window.location.href = '/';
    } catch {
      setError('Invalid email or password');
    } finally {
      setLoading(false);
    }
  }

  return (
    <main className="min-h-screen flex items-center justify-center">
      <form onSubmit={(e) => void handleSubmit(e)} className="w-full max-w-sm space-y-4">
        <h1 className="text-2xl font-bold">Login</h1>

        {error && <p className="text-red-500 text-sm">{error}</p>}

        <div>
          <label htmlFor="email" className="block text-sm font-medium">Email</label>
          <input
            id="email"
            type="email"
            value={email}
            onChange={(e) => { setEmail(e.target.value); }}
            required
            className="mt-1 block w-full border rounded px-3 py-2"
          />
        </div>

        <div>
          <label htmlFor="password" className="block text-sm font-medium">Password</label>
          <input
            id="password"
            type="password"
            value={password}
            onChange={(e) => { setPassword(e.target.value); }}
            required
            className="mt-1 block w-full border rounded px-3 py-2"
          />
        </div>

        <button
          type="submit"
          disabled={loading}
          className="w-full bg-blue-600 text-white py-2 rounded disabled:opacity-50"
        >
          {loading ? 'Logging in...' : 'Login'}
        </button>

        <p className="text-sm text-center">
          Don&apos;t have an account?{' '}
          <a href="/register" className="underline">Register</a>
        </p>
      </form>
    </main>
  );
}
