import { useState, type ReactNode } from 'react';
import SiteLayout from '@/Layouts/SiteLayout';
import { Button } from '@/Components/ui/button';
import PricingTiers from '@/Components/Site/PricingTiers';
import PricingFaq from '@/Components/Site/PricingFaq';

export default function SitePricing() {
  const [billing, setBilling] = useState<'monthly' | 'annual'>('annual');

  return (
    <>
      {/* Hero compact */}
      <section className="relative overflow-hidden border-b border-border">
        <div
          aria-hidden
          className="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(ellipse_at_top,_var(--color-primary)_0%,_transparent_50%)] opacity-[0.06]"
        />
        <div className="mx-auto max-w-3xl px-4 py-20 text-center sm:px-6 lg:px-8 lg:py-24">
          <span className="inline-flex w-fit items-center gap-2 rounded-full border border-border bg-background/60 px-3 py-1 text-xs font-medium text-muted-foreground backdrop-blur">
            <span className="h-1.5 w-1.5 rounded-full bg-primary" aria-hidden />
            Planos transparentes · Sem fidelidade
          </span>
          <h1 className="mt-5 text-4xl font-bold tracking-tight text-foreground sm:text-5xl">
            Pague só pelo que sua operação <span className="text-primary">realmente usa.</span>
          </h1>
          <p className="mt-5 text-base text-muted-foreground sm:text-lg">
            Comece grátis. Suba de plano quando o negócio pedir. Sem multa pra cancelar.
          </p>

          <div className="mt-8 inline-flex items-center rounded-full border border-border bg-card p-1 text-sm">
            <button
              type="button"
              onClick={() => setBilling('monthly')}
              className={`rounded-full px-4 py-1.5 font-medium transition-colors ${
                billing === 'monthly'
                  ? 'bg-primary text-primary-foreground'
                  : 'text-muted-foreground hover:text-foreground'
              }`}
            >
              Mensal
            </button>
            <button
              type="button"
              onClick={() => setBilling('annual')}
              className={`rounded-full px-4 py-1.5 font-medium transition-colors ${
                billing === 'annual'
                  ? 'bg-primary text-primary-foreground'
                  : 'text-muted-foreground hover:text-foreground'
              }`}
            >
              Anual
              <span className="ml-2 rounded-full bg-green-500/20 px-1.5 py-0.5 text-[10px] font-semibold text-green-600">
                −20%
              </span>
            </button>
          </div>
        </div>
      </section>

      <PricingTiers billing={billing} />

      {/* Confidence row */}
      <section className="border-y border-border bg-muted/20 py-10">
        <div className="mx-auto grid max-w-5xl grid-cols-1 gap-6 px-4 text-center sm:grid-cols-3 sm:px-6 lg:px-8">
          {[
            { icon: '🇧🇷', title: 'Suporte humano', desc: 'Em português, do mesmo fuso.' },
            { icon: '🔒', title: 'Seus dados, seus', desc: 'Backup diário. LGPD compliant.' },
            { icon: '↩️', title: 'Cancela quando quiser', desc: 'Sem multa, sem fidelidade.' },
          ].map((item) => (
            <div key={item.title}>
              <div className="text-2xl" aria-hidden>{item.icon}</div>
              <div className="mt-2 text-sm font-semibold text-foreground">{item.title}</div>
              <div className="mt-1 text-xs text-muted-foreground">{item.desc}</div>
            </div>
          ))}
        </div>
      </section>

      <PricingFaq />

      {/* Final CTA */}
      <section className="border-t border-border bg-primary py-20 text-primary-foreground">
        <div className="mx-auto max-w-3xl px-4 text-center sm:px-6 lg:px-8">
          <h2 className="text-3xl font-bold tracking-tight sm:text-4xl">
            Ainda em dúvida sobre qual plano?
          </h2>
          <p className="mt-3 text-primary-foreground/80">
            Conta a gente como sua operação funciona — a gente recomenda o caminho.
          </p>
          <div className="mt-7 flex flex-col items-center justify-center gap-3 sm:flex-row">
            <Button size="lg" variant="secondary" asChild>
              <a href="/login">Começar grátis</a>
            </Button>
            <Button
              size="lg"
              variant="outline"
              asChild
              className="border-primary-foreground/30 bg-transparent text-primary-foreground hover:bg-primary-foreground/10 hover:text-primary-foreground"
            >
              <a href="/c/contact-us">Falar com o time</a>
            </Button>
          </div>
        </div>
      </section>
    </>
  );
}

SitePricing.layout = (page: ReactNode) => <SiteLayout title="Preços">{page}</SiteLayout>;
