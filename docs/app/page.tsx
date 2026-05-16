import Link from 'next/link';

export default function HomePage() {
  return (
    <main className="flex min-h-screen flex-col items-center justify-center bg-gradient-to-b from-zinc-900 to-zinc-950 px-4 text-center">
      <div className="max-w-3xl">
        <div className="mb-6 inline-flex items-center gap-2 rounded-full border border-zinc-700 bg-zinc-800/50 px-4 py-1.5 text-sm text-zinc-400">
          <span className="relative flex h-2 w-2">
            <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75" />
            <span className="relative inline-flex h-2 w-2 rounded-full bg-emerald-500" />
          </span>
          v0.4.0 — License system + distribution ready
        </div>

        <h1 className="mb-4 text-5xl font-bold tracking-tight text-white sm:text-6xl">
          The CLI companion
          <br />
          <span className="bg-gradient-to-r from-emerald-400 to-teal-400 bg-clip-text text-transparent">
            Artisan doesn&apos;t have
          </span>
        </h1>

        <p className="mb-8 text-lg text-zinc-400 sm:text-xl">
          Scaffold boilerplate instantly, enforce team conventions, check project
          health, and generate code with AI — all from one tool.
        </p>

        <div className="mb-10 flex flex-col items-center gap-3 sm:flex-row sm:justify-center">
          <div className="rounded-lg border border-zinc-700 bg-zinc-900 px-6 py-3 font-mono text-sm text-emerald-400">
            composer global require lx/lx
          </div>
        </div>

        <div className="flex flex-wrap justify-center gap-4">
          <Link
            href="/docs"
            className="rounded-lg bg-emerald-500 px-6 py-3 text-sm font-semibold text-white shadow-lg transition hover:bg-emerald-400"
          >
            Get Started
          </Link>
          <Link
            href="/docs/commands"
            className="rounded-lg border border-zinc-700 bg-zinc-800 px-6 py-3 text-sm font-semibold text-zinc-300 transition hover:border-zinc-500 hover:text-white"
          >
            Command Reference
          </Link>
          <Link
            href="/pricing"
            className="rounded-lg border border-zinc-700 bg-zinc-800 px-6 py-3 text-sm font-semibold text-zinc-300 transition hover:border-zinc-500 hover:text-white"
          >
            Pricing
          </Link>
        </div>

        <div className="mt-16 grid grid-cols-2 gap-6 sm:grid-cols-4">
          {[
            { label: 'Commands', value: '12+' },
            { label: 'Tests Passing', value: '76' },
            { label: 'PHP Support', value: '8.2+' },
            { label: 'License', value: 'MIT' },
          ].map((stat) => (
            <div
              key={stat.label}
              className="rounded-lg border border-zinc-800 bg-zinc-900 p-4"
            >
              <div className="text-2xl font-bold text-white">{stat.value}</div>
              <div className="text-xs text-zinc-500">{stat.label}</div>
            </div>
          ))}
        </div>
      </div>
    </main>
  );
}
