import { useState, type ReactNode } from 'react';
import SiteLayout from '@/Layouts/SiteLayout';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import PricingTiers from '@/Components/Site/PricingTiers';
import PricingFaq from '@/Components/Site/PricingFaq';
import { Headphones, ShieldCheck, Undo2 } from 'lucide-react';

interface SitePricingProps {
  packages?: any[] | null;
  permissions?: Record<string, string> | null;
}

function SitePricing({ packages }: SitePricingProps) {
  const [billing, setBilling] = useState<'monthly' | 'annual'>('annual');

  return (
    <>
      {/* Hero — design system v2 */}
      <section className="relative overflow-hidden border-b">
        <div
          aria-hidden
          className="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(ellipse_at_top,_var(--color-primary)_0%,_transparent_55%)] opacity-[0.06]"
        />
        <div className="mx-auto max-w-3xl px-6 py-24 text-center lg:py-28">
          <Badge variant="secondary" className="gap-1.5">
            <span className="h-1.5 w-1.5 rounded-full bg-primary" aria-hidden />
            Planos transparentes · Sem fidelidade
          </Badge>
          <h1
            className="mt-6 text-foreground"
            style={{
              fontSize: 'var(--text-display)',
              lineHeight: 'var(--text-display--line-height)',
              letterSpacing: 'var(--text-display--letter-spacing)',
              fontWeight: 'var(--text-display--font-weight)',
            }}
          >
            Pague só pelo que sua operação <span className="text-primary">realmente usa.</span>
          </h1>
          <p className="mx-auto mt-6 max-w-xl text-body text-muted-foreground">
            Comece grátis. Suba de plano quando o negócio pedir. Sem multa pra cancelar.
          </p>

          <div className="mt-10 inline-flex items-center rounded-full border bg-card p-1 text-small shadow-xs">
            <button
              type="button"
              onClick={() => setBilling('monthly')}
              aria-pressed={billing === 'monthly'}
              className={`rounded-full px-5 py-1.5 font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ${
                billing === 'monthly'
                  ? 'bg-primary text-primary-foreground shadow-xs'
                  : 'text-muted-foreground hover:text-foreground'
              }`}
            >
              Mensal
            </button>
            <button
              type="button"
              onClick={() => setBilling('annual')}
              aria-pressed={billing === 'annual'}
              className={`flex items-center gap-2 rounded-full px-5 py-1.5 font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ${
                billing === 'annual'
                  ? 'bg-primary text-primary-foreground shadow-xs'
                  : 'text-muted-foreground hover:text-foreground'
              }`}
            >
              Anual
              <span className="rounded-full bg-success/15 px-1.5 py-0.5 text-caption font-semibold text-success">
                −20%
              </span>
            </button>
          </div>
        </div>
      </section>

      <PricingTiers billing={billing} packages={packages} />

      {/* Confidence row — design system v2 */}
      <section className="border-y bg-surface-1 py-12">
        <div className="mx-auto grid max-w-5xl grid-cols-1 gap-8 px-6 sm:grid-cols-3">
          {[
            { Icon: Headphones, title: 'Suporte humano', desc: 'Em português, do mesmo fuso.' },
            { Icon: ShieldCheck, title: 'Seus dados, seus', desc: 'Backup diário. LGPD compliant.' },
            { Icon: Undo2, title: 'Cancela quando quiser', desc: 'Sem multa, sem fidelidade.' },
          ].map(({ Icon, title, desc }) => (
            <div key={title} className="flex flex-col items-center text-center">
              <div className="mb-4 flex size-12 items-center justify-center rounded-xl border bg-card text-primary shadow-xs">
                <Icon className="size-5" />
              </div>
              <div className="text-h4 text-foreground" style={{ fontWeight: 600 }}>{title}</div>
              <div className="mt-1.5 text-small text-muted-foreground">{desc}</div>
            </div>
          ))}
        </div>
      </section>

      <PricingFaq />

      {/* Final CTA — design system v2 */}
      <section className="border-t bg-primary py-24 text-primary-foreground">
        <div className="mx-auto max-w-3xl px-6 text-center">
          <h2
            style={{
              fontSize: 'var(--text-h1)',
              lineHeight: 'var(--text-h1--line-height)',
              letterSpacing: 'var(--text-h1--letter-spacing)',
              fontWeight: 'var(--text-h1--font-weight)',
            }}
          >
            Ainda em dúvida sobre qual plano?
          </h2>
          <p className="mx-auto mt-4 max-w-xl text-body text-primary-foreground/85">
            Conta a gente como sua operação funciona — a gente recomenda o caminho.
          </p>
          <div className="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
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

export default SitePricing;
