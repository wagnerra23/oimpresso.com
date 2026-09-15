// FeatureGrid — 8 modules · emoji-on-primary-tinted plate (verbatim copy from FALLBACK_FEATURES)
const FEATURES = [
  { icon: '📐', title: 'Orçamento por m² (com. visual)', description: 'Cálculo automático por m² com tabelas próprias por substrato, acabamento e instalação. Adeus planilha.' },
  { icon: '🏭', title: 'Ordem de produção (OP)',          description: 'Do orçamento aprovado direto pra OP. Acompanha produção em tempo real, alerta atraso e fecha entrega.' },
  { icon: '🛒', title: 'PDV completo',                    description: 'Frente de caixa rápida, com leitor de código de barras, múltiplas formas de pagamento e impressão direta.' },
  { icon: '📦', title: 'Estoque em tempo real',           description: 'Controle multi-loja com lotes, validade, transferência entre filiais e relatórios de giro.' },
  { icon: '🧾', title: 'NF-e, NFC-e e NFS-e',             description: 'Emissão fiscal homologada para todo o Brasil. CT-e, MDF-e e devoluções incluídos.' },
  { icon: '⏱️', title: 'Ponto e RH',                      description: 'Marcação digital, espelho de ponto, escala e folha simplificada — pronto pra fiscalização.' },
  { icon: '💳', title: 'Financeiro & boletos',            description: 'Contas a pagar, a receber, conciliação bancária e geração de boletos em mais de 20 bancos.' },
  { icon: '📊', title: 'BI & dashboards',                 description: 'Veja o que importa em segundos. Vendas, margem, ticket médio, ruptura — tudo num lugar.' },
];

function FeatureGrid() {
  return (
    <section id="recursos" className="features" data-screen-label="Site Features">
      <div className="container">
        <div className="features__head">
          <span className="features__eyebrow">Tudo num lugar</span>
          <h2>Oito módulos. Uma plataforma.</h2>
          <p className="features__lede">
            Pare de pular entre 5 sistemas pra fechar o mês. Do orçamento à entrega — o oimpresso integra a operação de ponta a ponta.
          </p>
        </div>
        <div className="features__grid">
          {FEATURES.map((f) => (
            <div key={f.title} className="feature-card">
              <div className="feature-card__ico" aria-hidden>{f.icon}</div>
              <h3 className="feature-card__title">{f.title}</h3>
              <p className="feature-card__desc">{f.description}</p>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}
window.FeatureGrid = FeatureGrid;
