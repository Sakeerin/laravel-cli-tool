import Link from 'next/link';
import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'Pricing',
  description: 'lx Free is open source. lx Pro unlocks AI commands.',
};

const tiers = [
  {
    name: 'Free',
    price: '$0',
    period: 'forever',
    description: 'Everything you need for day-to-day Laravel scaffolding.',
    cta: { label: 'Install Free', href: '/docs/getting-started', primary: false },
    features: [
      'make:service, make:repository, make:dto, make:action',
      'make:module (full module scaffold)',
      'lx lint — PSR-12 + custom convention rules',
      'lx check — health check for .env, security, queue',
      'config:init wizard + config:pull from URL',
      '.lxconfig.yml support',
      'MIT licensed — use in commercial projects',
      'GitHub Actions CI template',
    ],
  },
  {
    name: 'Pro',
    price: '$9',
    period: '/dev / month',
    description: 'AI-powered commands that save hours on every project.',
    cta: { label: 'Get Pro', href: 'https://lx.lemonsqueezy.com/buy/pro', primary: true },
    badge: 'Most Popular',
    features: [
      'Everything in Free',
      'ai:migration — generate migrations from plain text',
      'ai:fix — diagnose errors from message or log',
      'ai:test — generate Pest / PHPUnit tests',
      'ai:review — Claude reviews your staged diff',
      'ai:usage — monthly token & cost report',
      'Local rate limiting (10 req/min)',
      '7-day offline grace period',
      'Priority email support',
    ],
  },
  {
    name: 'Team',
    price: '$29',
    period: '/ 5 devs / month',
    description: 'Pro for your whole team with shared configuration.',
    cta: { label: 'Get Team', href: 'https://lx.lemonsqueezy.com/buy/team', primary: false },
    features: [
      'Everything in Pro',
      'Up to 5 developer seats',
      'Shared .lxconfig.yml hosting',
      'Team usage analytics dashboard',
      'Annual billing option ($249/yr)',
      'Slack support channel',
    ],
  },
];

const faq = [
  {
    q: 'Do I need the Pro license to use lx at all?',
    a: 'No. All scaffolding (make:*), linting, health checks, and config commands are free and open source. Pro only unlocks the AI commands.',
  },
  {
    q: 'What happens if I lose internet access?',
    a: 'AI commands continue working for up to 7 days after the last successful license validation — useful for offline conferences or travel.',
  },
  {
    q: 'Does lx send my code to a server?',
    a: 'Snippets are sent to Anthropic\'s Claude API (same as the Claude web app) only when you run an AI command. No code is sent for non-AI commands. Usage stats are stored locally in ~/.lx/usage.json.',
  },
  {
    q: 'Can I use my own Anthropic API key?',
    a: 'Yes — set ANTHROPIC_API_KEY in your environment. lx uses your key and your account\'s quota. The Pro license only unlocks the commands; it does not provide API credits.',
  },
  {
    q: 'Is there an annual discount?',
    a: 'Annual Pro is $79/year (saves $29). Annual Team is $249/year. Both are available at checkout.',
  },
];

export default function PricingPage() {
  return (
    <div className="min-h-screen bg-zinc-950 px-4 py-20">
      <div className="mx-auto max-w-5xl">
        {/* Header */}
        <div className="mb-16 text-center">
          <h1 className="mb-4 text-4xl font-bold text-white sm:text-5xl">
            Simple, honest pricing
          </h1>
          <p className="text-lg text-zinc-400">
            Free forever for core commands. Pay only for AI.
          </p>
        </div>

        {/* Tiers */}
        <div className="mb-20 grid gap-6 sm:grid-cols-3">
          {tiers.map((tier) => (
            <div
              key={tier.name}
              className={`relative flex flex-col rounded-2xl border p-8 ${
                tier.badge
                  ? 'border-emerald-500 bg-emerald-950/20'
                  : 'border-zinc-800 bg-zinc-900'
              }`}
            >
              {tier.badge && (
                <div className="absolute -top-3 left-1/2 -translate-x-1/2">
                  <span className="rounded-full bg-emerald-500 px-3 py-1 text-xs font-semibold text-white">
                    {tier.badge}
                  </span>
                </div>
              )}

              <div className="mb-6">
                <h2 className="mb-2 text-lg font-semibold text-white">{tier.name}</h2>
                <div className="mb-1 flex items-end gap-1">
                  <span className="text-4xl font-bold text-white">{tier.price}</span>
                  <span className="mb-1 text-sm text-zinc-400">{tier.period}</span>
                </div>
                <p className="text-sm text-zinc-400">{tier.description}</p>
              </div>

              <ul className="mb-8 flex-1 space-y-3">
                {tier.features.map((feature) => (
                  <li key={feature} className="flex items-start gap-2 text-sm text-zinc-300">
                    <span className="mt-0.5 flex-shrink-0 text-emerald-400">✓</span>
                    {feature}
                  </li>
                ))}
              </ul>

              <Link
                href={tier.cta.href}
                className={`rounded-lg px-6 py-3 text-center text-sm font-semibold transition ${
                  tier.cta.primary
                    ? 'bg-emerald-500 text-white hover:bg-emerald-400'
                    : 'border border-zinc-700 text-zinc-300 hover:border-zinc-500 hover:text-white'
                }`}
              >
                {tier.cta.label}
              </Link>
            </div>
          ))}
        </div>

        {/* FAQ */}
        <div className="mx-auto max-w-2xl">
          <h2 className="mb-8 text-center text-2xl font-bold text-white">
            Frequently Asked Questions
          </h2>
          <div className="space-y-6">
            {faq.map((item) => (
              <div key={item.q} className="rounded-lg border border-zinc-800 p-6">
                <h3 className="mb-2 font-semibold text-white">{item.q}</h3>
                <p className="text-sm text-zinc-400">{item.a}</p>
              </div>
            ))}
          </div>
        </div>

        {/* Footer note */}
        <p className="mt-12 text-center text-xs text-zinc-600">
          Payments processed by LemonSqueezy. EU VAT handled automatically.{' '}
          <Link href="/docs" className="underline hover:text-zinc-400">
            Docs
          </Link>{' '}
          ·{' '}
          <a
            href="https://github.com/your-username/lx"
            className="underline hover:text-zinc-400"
          >
            GitHub
          </a>
        </p>
      </div>
    </div>
  );
}
