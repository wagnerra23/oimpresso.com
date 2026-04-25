import { Button } from '@/Components/ui/button';
import DashboardMockup from '@/Components/Site/DashboardMockup';

export default function Hero() {
  return (
    <section className="relative overflow-hidden">
      {/* Subtle gradient backdrop */}
      <div
        aria-hidden
        className="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(ellipse_at_top_right,_var(--color-primary)_0%,_transparent_55%)] opacity-[0.08]"
      />
      <div
        aria-hidden
        className="pointer-events-none absolute inset-x-0 top-0 -z-10 h-px bg-gradient-to-r from-transparent via-border to-transparent"
      />

      <div className="mx-auto grid max-w-7xl gap-12 px-4 py-20 sm:px-6 lg:grid-cols-2 lg:gap-16 lg:px-8 lg:py-28">
        <div className="flex flex-col justify-center">
          <span className="inline-flex w-fit items-center gap-2 rounded-full border border-border bg-background/60 px-3 py-1 text-xs font-medium text-muted-foreground backdrop-blur">
            <span className="h-1.5 w-1.5 rounded-full bg-primary" aria-hidden />
            ERP brasileiro · 100% em português
          </span>

          <h1 className="mt-5 text-4xl font-bold leading-[1.05] tracking-tight text-foreground sm:text-5xl lg:text-6xl">
            <span className="block">O ERP completo</span>
            <span className="block text-primary">pra sua empresa.</span>
          </h1>

          <p className="mt-6 max-w-xl text-lg leading-relaxed text-muted-foreground">
            Do orçamento à NF-e, do ponto ao financeiro — em uma plataforma só.
            PDV, estoque, fiscal, RH, cobrança e BI integrados.
          </p>

          <div className="mt-8 flex flex-col gap-3 sm:flex-row">
            <Button size="lg" asChild>
              <a href="/login">Começar grátis</a>
            </Button>
            <Button size="lg" variant="outline" asChild>
              <a href="/c/page/recursos">Ver recursos</a>
            </Button>
          </div>

          <p className="mt-4 text-xs text-muted-foreground">
            Sem cartão de crédito. Suporte humano em português.
          </p>
        </div>

        <div className="relative flex items-center justify-center">
          <div
            aria-hidden
            className="absolute inset-0 -z-10 rounded-3xl bg-gradient-to-br from-primary/15 via-primary/5 to-transparent blur-2xl"
          />
          <DashboardMockup />
        </div>
      </div>
    </section>
  );
}
