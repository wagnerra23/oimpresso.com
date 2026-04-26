import { motion, useReducedMotion } from 'framer-motion';

interface Statistic {
  value?: string;
  label?: string;
  title?: string;
  number?: string;
}

interface SocialProofProps {
  statistics?: Statistic[] | null;
}

const SETORES = [
  { label: 'Comunicação visual', emoji: '🎨' },
  { label: 'Gráficas', emoji: '🖨️' },
  { label: 'Varejo', emoji: '🛍️' },
  { label: 'Multi-loja', emoji: '🏬' },
  { label: 'Serviços', emoji: '🔧' },
];

const FALLBACK_STATS = [
  { value: 'Dezenas', label: 'de empresas brasileiras na base' },
  { value: 'Diariamente', label: 'NFs e ordens de produção rodando' },
  { value: '+10 anos', label: 'de mercado e operação' },
];

function normalizeStats(input: SocialProofProps['statistics']): { value: string; label: string }[] {
  if (!input || !Array.isArray(input) || input.length === 0) {
    return FALLBACK_STATS;
  }

  return input
    .map((stat) => ({
      value: (stat.value ?? stat.number ?? '').toString(),
      label: (stat.label ?? stat.title ?? '').toString(),
    }))
    .filter((stat) => stat.value || stat.label);
}

export default function SocialProof({ statistics }: SocialProofProps) {
  const reduceMotion = useReducedMotion();
  const stats = normalizeStats(statistics);
  const list = stats.length > 0 ? stats : FALLBACK_STATS;

  return (
    <section className="border-b border-border bg-muted/20 py-14">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <p className="text-center text-xs font-medium uppercase tracking-wider text-muted-foreground">
          Quem confia no oimpresso pra rodar a operação
        </p>
        <div className="mt-6 flex flex-wrap items-center justify-center gap-3">
          {SETORES.map((setor) => (
            <span
              key={setor.label}
              className="inline-flex items-center gap-2 rounded-full border border-border bg-card px-4 py-1.5 text-sm font-medium text-foreground"
            >
              <span aria-hidden>{setor.emoji}</span>
              {setor.label}
            </span>
          ))}
        </div>

        <div className="mt-12 grid grid-cols-1 gap-6 border-t border-border pt-10 sm:grid-cols-3">
          {list.map((stat, idx) => (
            <motion.div
              key={`${stat.label}-${idx}`}
              initial={{ opacity: 0, y: 12 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true, amount: 0.3 }}
              transition={{ duration: reduceMotion ? 0 : 0.4, delay: reduceMotion ? 0 : idx * 0.1 }}
              className="text-center"
            >
              <div className="text-3xl font-bold tracking-tight text-foreground">{stat.value}</div>
              <div className="mt-1 text-sm text-muted-foreground">{stat.label}</div>
            </motion.div>
          ))}
        </div>
      </div>
    </section>
  );
}
