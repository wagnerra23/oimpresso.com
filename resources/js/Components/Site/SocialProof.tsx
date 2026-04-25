/**
 * Banda de prova social — versão honesta enquanto Wagner não envia logos reais.
 * Mostra os SETORES atendidos (verificável) em vez de inventar nomes de cliente.
 * PR3: substituir por carrossel de logos reais (ex: ROTA LIVRE biz=4 desde 2024).
 */
const SETORES = [
  { label: 'Comunicação visual', emoji: '🎨' },
  { label: 'Gráficas', emoji: '🖨️' },
  { label: 'Varejo', emoji: '🛍️' },
  { label: 'Multi-loja', emoji: '🏬' },
  { label: 'Serviços', emoji: '🔧' },
];

const STATS = [
  { value: 'Dezenas', label: 'de empresas brasileiras na base' },
  { value: 'Diariamente', label: 'NFs e ordens de produção rodando' },
  { value: '+10 anos', label: 'de mercado e operação' },
];

export default function SocialProof() {
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
          {STATS.map((stat) => (
            <div key={stat.label} className="text-center">
              <div className="text-3xl font-bold tracking-tight text-foreground">{stat.value}</div>
              <div className="mt-1 text-sm text-muted-foreground">{stat.label}</div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}
