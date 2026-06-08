import type { JSX } from 'react';
import Link from 'next/link';

export default function HomePage(): JSX.Element {
  return (
    <main className="min-h-screen flex items-center justify-center">
      <div className="text-center">
        <h1 className="text-3xl font-bold mb-4">Core Platform</h1>
        <div className="space-x-4">
          <Link href="/login" className="underline">Login</Link>
          <Link href="/register" className="underline">Register</Link>
        </div>
      </div>
    </main>
  );
}
