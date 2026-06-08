'use client';

import { useState, useEffect, type FormEvent, type JSX, type ReactNode } from 'react';
import { register } from '@/lib/api-client';
import { registerSchema, type RegisterInput } from '@core/shared-types';
import { useJobStatus } from '@/lib/hooks/useJobStatus';

export default function RegisterPage(): JSX.Element {
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [confirmation, setConfirmation] = useState('');
  const [jobId, setJobId] = useState<string | null>(null);
  const [accessToken, setAccessToken] = useState<string | null>(null);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const [fieldErrors, setFieldErrors] = useState<Partial<Record<keyof RegisterInput, string>>>({});

  const jobState = useJobStatus(jobId, accessToken);

  useEffect(() => {
    if (!jobId) return;
    const match = document.cookie
      .split('; ')
      .find((row) => row.startsWith('access_token='));
    const tkn = match ? decodeURIComponent(match.split('=')[1] ?? '') : null;
    setAccessToken(tkn);
  }, [jobId]);

  async function handleSubmit(e: FormEvent<HTMLFormElement>): Promise<void> {
    e.preventDefault();
    setError('');
    setFieldErrors({});

    const parsed = registerSchema.safeParse({
      name,
      email,
      password,
      password_confirmation: confirmation,
    });

    if (!parsed.success) {
      const knownFields = new Set<string>(['name', 'email', 'password', 'password_confirmation']);
      const errors: Partial<Record<keyof RegisterInput, string>> = {};
      for (const issue of parsed.error.issues) {
        const field = issue.path[0];
        if (typeof field === 'string' && knownFields.has(field)) {
          const key = field as keyof RegisterInput;
          if (!errors[key]) errors[key] = issue.message;
        }
      }
      setFieldErrors(errors);
      return;
    }

    setLoading(true);
    try {
      const result = await register(parsed.data);
      setJobId(result.jobId);
    } catch {
      setError('Registration failed. Please try again.');
    } finally {
      setLoading(false);
    }
  }

  if (jobId) {
    const { status } = jobState;

    let heading: string;
    let body: ReactNode;

    if (status === 'pending') {
      heading = 'Registration submitted!';
      body = <p className="text-sm text-gray-500">Processing...</p>;
    } else if (status === 'done') {
      heading = 'Registration complete!';
      body = (
        <p className="text-sm">
          You may now <a href="/login" className="underline">login</a>.
        </p>
      );
    } else if (status === 'timeout') {
      heading = 'Timed out';
      body = (
        <p className="text-sm text-yellow-600">
          The registration is taking longer than expected. Please try again or{' '}
          <a href="/login" className="underline">login</a> later.
        </p>
      );
    } else {
      heading = 'Error';
      body = (
        <p className="text-sm text-red-500">
          Something went wrong. Please try again.
        </p>
      );
    }

    return (
      <main className="min-h-screen flex items-center justify-center">
        <div className="text-center space-y-2">
          <h1 className="text-2xl font-bold">{heading}</h1>
          {body}
          <p className="text-xs text-gray-400">
            Job ID: <code>{jobId}</code>
          </p>
        </div>
      </main>
    );
  }

  return (
    <main className="min-h-screen flex items-center justify-center">
      <form onSubmit={(e) => void handleSubmit(e)} className="w-full max-w-sm space-y-4">
        <h1 className="text-2xl font-bold">Register</h1>

        {error && <p className="text-red-500 text-sm">{error}</p>}

        <div>
          <label htmlFor="name" className="block text-sm font-medium">Name</label>
          <input
            id="name"
            type="text"
            value={name}
            onChange={(e) => { setName(e.target.value); }}
            required
            className="mt-1 block w-full border rounded px-3 py-2"
          />
          {fieldErrors.name && <p className="text-red-500 text-xs mt-1">{fieldErrors.name}</p>}
        </div>

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
          {fieldErrors.email && <p className="text-red-500 text-xs mt-1">{fieldErrors.email}</p>}
        </div>

        <div>
          <label htmlFor="password" className="block text-sm font-medium">Password</label>
          <input
            id="password"
            type="password"
            value={password}
            onChange={(e) => { setPassword(e.target.value); }}
            required
            minLength={8}
            className="mt-1 block w-full border rounded px-3 py-2"
          />
          {fieldErrors.password && <p className="text-red-500 text-xs mt-1">{fieldErrors.password}</p>}
        </div>

        <div>
          <label htmlFor="confirmation" className="block text-sm font-medium">Confirm Password</label>
          <input
            id="confirmation"
            type="password"
            value={confirmation}
            onChange={(e) => { setConfirmation(e.target.value); }}
            required
            className="mt-1 block w-full border rounded px-3 py-2"
          />
          {fieldErrors.password_confirmation && <p className="text-red-500 text-xs mt-1">{fieldErrors.password_confirmation}</p>}
        </div>

        <button
          type="submit"
          disabled={loading}
          className="w-full bg-blue-600 text-white py-2 rounded disabled:opacity-50"
        >
          {loading ? 'Registering...' : 'Register'}
        </button>

        <p className="text-sm text-center">
          Already have an account?{' '}
          <a href="/login" className="underline">Login</a>
        </p>
      </form>
    </main>
  );
}
