import type { ReactNode } from 'react';
import SiteLayout from '@/Layouts/SiteLayout';
import Hero from '@/Components/Site/Hero';
import SocialProof from '@/Components/Site/SocialProof';
import FeatureGrid from '@/Components/Site/FeatureGrid';
import { Button } from '@/Components/ui/button';

interface SiteHomeProps {
  page?: any;
  testimonials?: any[];
  faqs?: any[] | string | null;
  statistics?: any[] | null;
}

function SiteHome({ page, statistics }: SiteHomeProps) {
  return (
    <>
      <Hero page={page} />
      <SocialProof statistics={Array.isArray(statistics) ? statistics : null} />
      <FeatureGrid page={page} />

      {/* Final CTA */}
      <section className="relative isolate overflow-hidden border-t border-border bg-primary py-20 text-primary-foreground sm:py-24">
        <div
          aria-hidden
          className="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(ellipse_at_center,_rgba(255,255,255,0.18)_0%,_transparent_60%)]"
        />
        <div className="mx-auto max-w-3xl px-4 text-center sm:px-6 lg:px-8">
          <h2 className="text-3xl font-bold tracking-tight sm:text-4xl">
            Pronto pra ver o oimpresso rodando?
          </h2>
          <p className="mt-4 text-base text-primary-foreground/80">
            Sem cartão de crédito. Suporte em português desde o primeiro clique.
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

SiteHome.layout = (page: ReactNode) => <SiteLayout title="ERP completo pra sua empresa">{page}</SiteLayout>;

export default SiteHome;
