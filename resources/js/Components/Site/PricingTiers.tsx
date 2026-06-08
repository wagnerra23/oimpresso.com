import { Button } from '@/Components/ui/button';
import { motion, useReducedMotion } from 'framer-motion';

type Billing = 'monthly' | 'annual';

interface Tier {
  name: string;
  tagline: string;
  price: { monthly: string; annual: string };
  priceSuffix: string;
  cta: { label: string; href: string };
  highlighted?: boolean;
  features: string[];
  ctaVariant?: 'default' | 'outline' | 'secondary';
}

interface PackageRow {
  id: number | string;
  name: string;
  description?: string | null;
  price?: number | string | null;
  interval?: string | null;
  interval_count?: number | null;
  trial_days?: number | null;
  location_count?: number | null;
  user_count?: number | null;
  product_count?: number | null;
  invoice_count?: number | null;
  custom_permissions?: Record<string, any> | null;
  sort_order?: number | null;
}

interface PricingTiersProps {
  billing: Billing;
  packages?: PackageRow[] | null;
}

const FALLBACK_TIERS: Tier[] = [
  {
    name: 'Essencial',
    tagline: 'Pra começar a operar com o básico bem feito.',
    price: { monthly: 'R$ 149', annual: 'R$ 119' },
    priceSuffix: '/mês',
    cta: { label: 'Começar grátis', href: '/login' },
    ctaVariant: 'outline',
    features: [
      'PDV completo (1 caixa)',
      'Estoque + cadastro de produtos',
      'NF-e e NFC-e',
      'Até 2 usuários',
      'Suporte por chat',
    ],
  },
  {
    name: 'Profissional',
    tagline: 'Pra gráfica, varejo ou serviço que precisa de tudo, sem gambiarra.',
    price: { monthly: 'R$ 349', annual: 'R$ 279' },
    priceSuffix: '/mês',
    cta: { label: 'Começar 14 dias grátis', href: '/login' },
    highlighted: true,
    features: [
      'Tudo do Essencial',
      'Cálculo automático por m² (gráficas e com. visual)',
      'Ordem de produção em tempo real',
      'Multi-loja + transferência entre filiais',
      'Financeiro, boletos e conciliação',
      'Ponto eletrônico + folha simplificada',
      'BI e dashboards',
      'Até 10 usuários',
      'Suporte prioritário',
    ],
  },
  {
    name: 'Enterprise',
    tagline: 'Pra operações grandes ou com regras específicas.',
    price: { monthly: 'Sob consulta', annual: 'Sob consulta' },
    priceSuffix: '',
    cta: { label: 'Falar com o time', href: '/c/contact-us' },
    ctaVariant: 'outline',
    features: [
      'Tudo do Profissional',
      'Usuários ilimitados',
      'Integrações sob medida (ERP, CRM, e-commerce)',
      'SLA dedicado e onboarding guiado',
      'Treinamento da equipe',
      'Gerente de conta dedicado',
    ],
  },
];

function formatPrice(value: number | string | null | undefined): string {
  if (value === null || value === undefined || value === '') return 'Sob consulta';
  const num = typeof value === 'string' ? parseFloat(value) : value;
  if (!Number.isFinite(num) || num === 0) return 'Grátis';
  return new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  }).format(num);
}

function tiersFromPackages(packages: PackageRow[]): Tier[] {
  return packages.map((pkg, idx) => {
    const priceMonthly = formatPrice(pkg.price);
    const features: string[] = [];

    if (pkg.location_count) {
      features.push(
        pkg.location_count === 0
          ? 'Lojas ilimitadas'
          : `Até ${pkg.location_count} ${pkg.location_count === 1 ? 'loja' : 'lojas'}`,
      );
    }
    if (pkg.user_count) {
      features.push(
        pkg.user_count === 0 ? 'Usuários ilimitados' : `Até ${pkg.user_count} usuários`,
      );
    }
    if (pkg.product_count) {
      features.push(
        pkg.product_count === 0 ? 'Produtos ilimitados' : `Até ${pkg.product_count} produtos`,
      );
    }
    if (pkg.invoice_count) {
      features.push(
        pkg.invoice_count === 0
          ? 'NF-e ilimitadas'
          : `Até ${pkg.invoice_count} NF-e/mês`,
      );
    }

    if (pkg.custom_permissions && typeof pkg.custom_permissions === 'object') {
      for (const key of Object.keys(pkg.custom_permissions)) {
        if (pkg.custom_permissions[key]) {
          features.push(prettifyPermissionKey(key));
        }
      }
    }

    if (pkg.trial_days && pkg.trial_days > 0) {
      features.unshift(`${pkg.trial_days} dias grátis pra testar`);
    }

    if (features.length === 0 && pkg.description) {
      features.push(...pkg.description.split('\n').map((s) => s.trim()).filter(Boolean));
    }

    const interval = pkg.interval ?? 'months';
    const suffix = interval === 'months' ? '/mês' : interval === 'years' ? '/ano' : '/dia';

    return {
      name: pkg.name,
      tagline: (pkg.description ?? '').toString().split('\n')[0] || '',
      price: { monthly: priceMonthly, annual: priceMonthly },
      priceSuffix: priceMonthly === 'Grátis' || priceMonthly === 'Sob consulta' ? '' : suffix,
      cta: { label: 'Começar agora', href: '/register' },
      highlighted: idx === 1 && packages.length >= 3,
      features: features.length > 0 ? features : ['Recursos sob medida'],
      ctaVariant: idx === 1 && packages.length >= 3 ? 'default' : 'outline',
    };
  });
}

function prettifyPermissionKey(key: string): string {
  return key
    .replace(/_module$/, '')
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (c) => c.toUpperCase());
}

export default function PricingTiers({ billing, packages }: PricingTiersProps) {
  const reduceMotion = useReducedMotion();
  const tiers =
    packages && Array.isArray(packages) && packages.length > 0
      ? tiersFromPackages(packages)
      : FALLBACK_TIERS;

  return (
    <section className="py-16 sm:py-20">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="grid gap-6 lg:grid-cols-3">
          {tiers.map((tier, idx) => {
            const isHighlighted = tier.highlighted;
            return (
              <motion.div
                key={tier.name}
                initial={{ opacity: 0, y: 20 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true, amount: 0.2 }}
                transition={{
                  duration: reduceMotion ? 0 : 0.45,
                  delay: reduceMotion ? 0 : idx * 0.08,
                }}
                whileHover={reduceMotion ? undefined : { scale: 1.02 }}
                className={`relative flex flex-col rounded-2xl border p-7 transition-all ${
                  isHighlighted
                    ? 'border-primary bg-card shadow-2xl shadow-primary/10 lg:-mt-4 lg:mb-4'
                    : 'border-border bg-card hover:border-primary/30 hover:shadow-lg'
                }`}
              >
                {isHighlighted && (
                  <span className="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-primary px-3 py-1 text-[11px] font-semibold uppercase tracking-wider text-primary-foreground">
                    Mais escolhido
                  </span>
                )}

                <div>
                  <h3 className="text-lg font-bold text-foreground">{tier.name}</h3>
                  {tier.tagline && (
                    <p className="mt-2 text-sm text-muted-foreground">{tier.tagline}</p>
                  )}
                </div>

                <div className="mt-6 flex items-baseline gap-2">
                  <span className="text-4xl font-bold tracking-tight text-foreground">
                    {tier.price[billing]}
                  </span>
                  {tier.priceSuffix && (
                    <span className="text-sm text-muted-foreground">{tier.priceSuffix}</span>
                  )}
                </div>
                {billing === 'annual' && tier.priceSuffix && (
                  <p className="mt-1 text-xs text-muted-foreground">cobrado anualmente</p>
                )}

                <ul role="list" className="mt-7 space-y-3 text-sm">
                  {tier.features.map((feature) => (
                    <li key={feature} className="flex items-start gap-3">
                      <svg
                        className={`mt-0.5 h-4 w-4 shrink-0 ${
                          isHighlighted ? 'text-primary' : 'text-muted-foreground'
                        }`}
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        aria-hidden
                      >
                        <path
                          fillRule="evenodd"
                          d="M16.704 5.29a1 1 0 010 1.42l-7.79 7.793a1 1 0 01-1.414 0L3.296 10.3a1 1 0 011.414-1.414l3.498 3.497 7.083-7.092a1 1 0 011.413 0z"
                          clipRule="evenodd"
                        />
                      </svg>
                      <span className="text-foreground">{feature}</span>
                    </li>
                  ))}
                </ul>

                <div className="mt-8 pt-6 border-t border-border">
                  <Button
                    asChild
                    variant={tier.ctaVariant ?? (isHighlighted ? 'default' : 'outline')}
                    size="lg"
                    className="w-full"
                  >
                    <a href={tier.cta.href}>{tier.cta.label}</a>
                  </Button>
                </div>
              </motion.div>
            );
          })}
        </div>

        <p className="mt-8 text-center text-xs text-muted-foreground">
          Preços em reais (R$). Não cobramos setup nem fidelidade. Cancele quando quiser.
        </p>
      </div>
    </section>
  );
}
