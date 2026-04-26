import { motion, useReducedMotion } from 'framer-motion';

interface Feature {
  title: string;
  description: string;
  icon: string;
}

interface CmsPageMeta {
  meta_key?: string;
  meta_value?: string;
}

interface CmsPage {
  pageMeta?: CmsPageMeta[] | null;
  page_meta?: CmsPageMeta[] | null;
  content?: string | null;
}

interface FeatureGridProps {
  page?: CmsPage | null;
}

const FALLBACK_FEATURES: Feature[] = [
  {
    icon: '📐',
    title: 'Orçamento por m² (com. visual)',
    description:
      'Cálculo automático por m² com tabelas próprias por substrato, acabamento e instalação. Adeus planilha.',
  },
  {
    icon: '🏭',
    title: 'Ordem de produção (OP)',
    description:
      'Do orçamento aprovado direto pra OP. Acompanha produção em tempo real, alerta atraso e fecha entrega.',
  },
  {
    icon: '🛒',
    title: 'PDV completo',
    description:
      'Frente de caixa rápida, com leitor de código de barras, múltiplas formas de pagamento e impressão direta.',
  },
  {
    icon: '📦',
    title: 'Estoque em tempo real',
    description:
      'Controle multi-loja com lotes, validade, transferência entre filiais e relatórios de giro.',
  },
  {
    icon: '🧾',
    title: 'NF-e, NFC-e e NFS-e',
    description:
      'Emissão fiscal homologada para todo o Brasil. CT-e, MDF-e e devoluções incluídos.',
  },
  {
    icon: '⏱️',
    title: 'Ponto e RH',
    description:
      'Marcação digital, espelho de ponto, escala e folha simplificada — pronto pra fiscalização.',
  },
  {
    icon: '💳',
    title: 'Financeiro & boletos',
    description:
      'Contas a pagar, a receber, conciliação bancária e geração de boletos em mais de 20 bancos.',
  },
  {
    icon: '📊',
    title: 'BI & dashboards',
    description:
      'Veja o que importa em segundos. Vendas, margem, ticket médio, ruptura — tudo num lugar.',
  },
];

function extractFeaturesFromMeta(page?: CmsPage | null): Feature[] | null {
  if (!page) return null;
  const meta = page.pageMeta ?? page.page_meta ?? null;
  if (!meta || meta.length === 0) return null;

  const features: Feature[] = [];
  for (const item of meta) {
    if (!item?.meta_key) continue;
    const match = /^feature_(\d+)_(title|description|icon)$/.exec(item.meta_key);
    if (!match || !match[1] || !match[2]) continue;
    const idx = parseInt(match[1], 10);
    const field = match[2] as 'title' | 'description' | 'icon';
    features[idx] = features[idx] ?? { title: '', description: '', icon: '✨' };
    (features[idx] as any)[field] = item.meta_value ?? '';
  }
  const compact = features.filter((f) => f && f.title);
  return compact.length > 0 ? compact : null;
}

export default function FeatureGrid({ page }: FeatureGridProps) {
  const reduceMotion = useReducedMotion();
  const features = extractFeaturesFromMeta(page) ?? FALLBACK_FEATURES;

  return (
    <section id="recursos" className="py-20 sm:py-28">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="mx-auto max-w-2xl text-center">
          <span className="text-xs font-semibold uppercase tracking-wider text-primary">
            Tudo num lugar
          </span>
          <h2 className="mt-3 text-3xl font-bold tracking-tight text-foreground sm:text-4xl">
            Oito módulos. Uma plataforma.
          </h2>
          <p className="mt-4 text-base text-muted-foreground">
            Pare de pular entre 5 sistemas pra fechar o mês. Do orçamento à entrega — o oimpresso integra a operação de ponta a ponta.
          </p>
        </div>

        <div className="mt-14 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
          {features.map((feature, idx) => (
            <motion.div
              key={feature.title}
              initial={{ opacity: 0, y: 20 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true, amount: 0.2 }}
              transition={{
                duration: reduceMotion ? 0 : 0.45,
                delay: reduceMotion ? 0 : idx * 0.06,
              }}
              className="group relative rounded-xl border border-border bg-card p-6 transition-all hover:border-primary/50 hover:shadow-lg"
            >
              <div
                className="flex h-11 w-11 items-center justify-center rounded-lg bg-primary/10 text-2xl"
                aria-hidden
              >
                {feature.icon}
              </div>
              <h3 className="mt-4 text-base font-semibold text-foreground">{feature.title}</h3>
              <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
                {feature.description}
              </p>
            </motion.div>
          ))}
        </div>
      </div>
    </section>
  );
}
