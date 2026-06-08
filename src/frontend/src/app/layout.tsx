import type { Metadata } from 'next';
import type { JSX, ReactNode } from 'react';
import './globals.css';

export const metadata: Metadata = {
  title: 'Core Platform',
  description: 'Modular platform',
};

export default function RootLayout({
  children,
}: Readonly<{
  children: ReactNode;
}>): JSX.Element {
  return (
    <html lang="en">
      <body>{children}</body>
    </html>
  );
}
