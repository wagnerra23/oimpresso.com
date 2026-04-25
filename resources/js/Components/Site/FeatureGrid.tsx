interface Feature {
  title: string;
  description: string;
  icon: string;
}

const FEATURES: Feature[] = [
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

export default function FeatureGrid() {
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
          {FEATURES.map((feature) => (
            <div
              key={feature.title}
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
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}
