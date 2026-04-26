import { Button } from '@/Components/ui/button';
import DashboardMockup from '@/Components/Site/DashboardMockup';
import { motion, useReducedMotion } from 'framer-motion';

interface HeroPage {
  title?: string | null;
  content?: string | null;
}

interface HeroProps {
  page?: HeroPage | null;
}

export default function Hero({ page }: HeroProps) {
  const reduceMotion = useReducedMotion();
  const baseTransition = reduceMotion ? { duration: 0 } : { duration: 0.6 };

  const cmsTitle = page?.title?.trim();
  const cmsContent = page?.content?.trim();

  return (
    <section className="relative overflow-hidden">
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
            Comunicação visual · Varejo · Serviços · Multi-loja
          </span>

          <motion.h1
            initial={{ opacity: 0, y: 16 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ ...baseTransition, delay: 0 }}
            className="mt-5 text-4xl font-bold leading-[1.05] tracking-tight text-foreground sm:text-5xl lg:text-[3.75rem]"
          >
            {cmsTitle ? (
              <span className="block text-foreground">{cmsTitle}</span>
            ) : (
              <>
                <span className="block text-foreground">O ERP pra quem</span>
                <span className="block text-primary">orça, imprime, monta</span>
                <span className="block text-primary">e entrega.</span>
              </>
            )}
          </motion.h1>

          <motion.p
            initial={{ opacity: 0, y: 12 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ ...baseTransition, delay: reduceMotion ? 0 : 0.15 }}
            className="mt-6 max-w-xl text-lg leading-relaxed text-muted-foreground"
          >
            {cmsContent ? (
              <span dangerouslySetInnerHTML={{ __html: cmsContent }} />
            ) : (
              <>
                Cálculo automático por <strong className="font-semibold text-foreground">m²</strong>,
                ordem de produção em tempo real e fechamento fiscal sem retrabalho.
                PDV, NF-e, estoque, ponto, financeiro e BI integrados — em uma plataforma só.
              </>
            )}
          </motion.p>

          <motion.div
            initial={{ opacity: 0, y: 12 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ ...baseTransition, delay: reduceMotion ? 0 : 0.3 }}
            className="mt-8 flex flex-col gap-3 sm:flex-row"
          >
            <Button size="lg" asChild>
              <a href="/login">Começar grátis</a>
            </Button>
            <Button size="lg" variant="outline" asChild>
              <a href="#recursos">Ver recursos</a>
            </Button>
          </motion.div>

          <div className="mt-4 flex flex-col gap-1 text-xs text-muted-foreground sm:flex-row sm:items-center sm:gap-3">
            <span>Sem cartão de crédito · Suporte humano em português.</span>
            <a
              href="/c/contact-us"
              className="inline-flex items-center gap-1 font-medium text-primary hover:underline"
            >
              Não sabe qual plano? Me ajuda a escolher
              <span aria-hidden>→</span>
            </a>
          </div>
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
