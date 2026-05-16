import { RootProvider } from 'fumadocs-ui/provider';
import type { Metadata } from 'next';
import type { ReactNode } from 'react';
import 'fumadocs-ui/style.css';

export const metadata: Metadata = {
  title: {
    default: 'lx — Laravel CLI Companion',
    template: '%s | lx',
  },
  description:
    'lx is a CLI companion for Laravel developers: scaffold boilerplate, enforce conventions, check project health, and generate code with AI.',
  openGraph: {
    siteName: 'lx docs',
    url: 'https://lx.dev',
  },
};

export default function RootLayout({ children }: { children: ReactNode }) {
  return (
    <html lang="en" suppressHydrationWarning>
      <body>
        <RootProvider>{children}</RootProvider>
      </body>
    </html>
  );
}
