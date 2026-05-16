import { DocsLayout } from 'fumadocs-ui/layouts/docs';
import type { ReactNode } from 'react';
import { source } from '@/lib/source';

export default function Layout({ children }: { children: ReactNode }) {
  return (
    <DocsLayout
      tree={source.pageTree}
      nav={{
        title: (
          <span className="flex items-center gap-2 font-bold">
            <span className="rounded bg-emerald-500 px-1.5 py-0.5 text-xs text-white">
              lx
            </span>
            docs
          </span>
        ),
        links: [
          { text: 'Home', url: '/' },
          { text: 'Pricing', url: '/pricing' },
          {
            text: 'GitHub',
            url: 'https://github.com/your-username/lx',
            external: true,
          },
        ],
      }}
      sidebar={{
        banner: (
          <div className="rounded-lg border border-emerald-900 bg-emerald-950/50 p-3 text-xs text-emerald-400">
            <strong>AI Commands (Pro)</strong>
            <br />
            Unlock{' '}
            <a href="/pricing" className="underline">
              ai:migration, ai:fix, ai:test, ai:review
            </a>{' '}
            with a Pro license.
          </div>
        ),
      }}
    >
      {children}
    </DocsLayout>
  );
}
