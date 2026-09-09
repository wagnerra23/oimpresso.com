// PricingTiers — 3 tiers, billing toggle, Profissional highlighted (lifted from FALLBACK_TIERS)
const TIERS = [
  {
    name: 'Essencial',
    tagline: 'Pra começar a operar com o básico bem feito.',
    price: { monthly: 'R$ 149', annual: 'R$ 119' },
    priceSuffix: '/mês',
    cta: { label: 'Começar grátis' },
    ctaVariant: 'outline',
    features: ['PDV completo (1 caixa)', 'Estoque + cadastro de produtos', 'NF-e e NFC-e', 'Até 2 usuários', 'Suporte por chat'],
  },
  {
    name: 'Profissional',
    tagline: 'Pra gráfica, varejo ou serviço que precisa de tudo, sem gambiarra.',
    price: { monthly: 'R$ 349', annual: 'R$ 279' },
    priceSuffix: '/mês',
    cta: { label: 'Começar 14 dias grátis' },
    ctaVariant: 'primary',
    highlighted: true,
    features: ['Tudo do Essencial', 'Cálculo automático por m² (gráficas e com. visual)', 'Ordem de produção em tempo real', 'Multi-loja + transferência entre filiais', 'Financeiro, boletos e conciliação', 'Ponto eletrônico + folha simplificada', 'BI e dashboards', 'Até 10 usuários', 'Suporte prioritário'],
  },
  {
    name: 'Enterprise',
    tagline: 'Pra operações grandes ou com regras específicas.',
    price: { monthly: 'Sob consulta', annual: 'Sob consulta' },
    priceSuffix: '',
    cta: { label: 'Falar com o time' },
    ctaVariant: 'outline',
    features: ['Tudo do Profissional', 'Usuários ilimitados', 'Integrações sob medida (ERP, CRM, e-commerce)', 'SLA dedicado e onboarding guiado', 'Treinamento da equipe', 'Gerente de conta dedicado'],
  },
];

function CheckIcon({ highlighted }) {
  return (
    <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden style={{ color: highlighted ? 'var(--primary)' : 'var(--fg-2)' }}>
      <path fillRule="evenodd" clipRule="evenodd" d="M16.704 5.29a1 1 0 010 1.42l-7.79 7.793a1 1 0 01-1.414 0L3.296 10.3a1 1 0 011.414-1.414l3.498 3.497 7.083-7.092a1 1 0 011.413 0z"/>
    </svg>
  );
}

function PricingTiers() {
  const [billing, setBilling] = React.useState('monthly');
  return (
    <section id="precos" className="pricing" data-screen-label="Site Pricing">
      <div className="container">
        <div className="pricing__head">
          <h2>Escolha o plano que cabe na operação.</h2>
          <p>Sem setup, sem fidelidade. Cancele quando quiser.</p>
          <div className="billing-toggle" role="tablist" aria-label="Periodicidade de cobrança">
            <button role="tab" aria-selected={billing==='monthly'} className={billing==='monthly' ? 'is-active' : ''} onClick={() => setBilling('monthly')}>Mensal</button>
            <button role="tab" aria-selected={billing==='annual'} className={billing==='annual' ? 'is-active' : ''} onClick={() => setBilling('annual')}>
              Anual <span className="billing-toggle__save">−20%</span>
            </button>
          </div>
        </div>
        <div className="tiers">
          {TIERS.map((t) => (
            <div key={t.name} className={'tier' + (t.highlighted ? ' tier--highlighted' : '')}>
              {t.highlighted && <span className="tier__ribbon">Mais escolhido</span>}
              <div>
                <h3 className="tier__name">{t.name}</h3>
                <p className="tier__tagline">{t.tagline}</p>
              </div>
              <div className="tier__price-row">
                <span className="tier__price">{t.price[billing]}</span>
                {t.priceSuffix && <span className="tier__suffix">{t.priceSuffix}</span>}
              </div>
              {billing === 'annual' && t.priceSuffix && <p className="tier__annual-note">cobrado anualmente</p>}
              <ul className="tier__features">
                {t.features.map((f) => (
                  <li key={f} className="tier__feature">
                    <CheckIcon highlighted={t.highlighted}/>
                    <span>{f}</span>
                  </li>
                ))}
              </ul>
              <div className="tier__cta-wrap">
                <a href="#" className={'btn btn--lg ' + (t.ctaVariant === 'primary' ? 'btn--primary' : 'btn--outline')}>
                  {t.cta.label}
                </a>
              </div>
            </div>
          ))}
        </div>
        <p className="pricing__finefoot">Preços em reais (R$). Não cobramos setup nem fidelidade. Cancele quando quiser.</p>
      </div>
    </section>
  );
}
window.PricingTiers = PricingTiers;
