import { Button } from '@/Components/ui/button';

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

/**
 * Preços PLACEHOLDER — Wagner ainda não definiu valores oficiais.
 * Memória: project_meta_5mi_ano + meta de receita pendente.
 * Substituir pelos valores reais quando definidos (vinda do DB de packages).
 */
const TIERS: Tier[] = [
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
    tagline: 'Pra quem precisa de tudo, sem gambiarra.',
    price: { monthly: 'R$ 349', annual: 'R$ 279' },
    priceSuffix: '/mês',
    cta: { label: 'Começar 14 dias grátis', href: '/login' },
    highlighted: true,
    features: [
      'Tudo do Essencial',
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

export default function PricingTiers({ billing }: { billing: Billing }) {
  return (
    <section className="py-16 sm:py-20">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="grid gap-6 lg:grid-cols-3">
          {TIERS.map((tier) => {
            const isHighlighted = tier.highlighted;
            return (
              <div
                key={tier.name}
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
                  <p className="mt-2 text-sm text-muted-foreground">{tier.tagline}</p>
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
              </div>
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
