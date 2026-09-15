// SocialProof — sectors row + 3-up stats
const SETORES = [
  { label: 'Comunicação visual', emoji: '🎨' },
  { label: 'Gráficas',           emoji: '🖨️' },
  { label: 'Varejo',             emoji: '🛍️' },
  { label: 'Multi-loja',         emoji: '🏬' },
  { label: 'Serviços',           emoji: '🔧' },
];

const STATS = [
  { value: 'Dezenas',   label: 'de empresas brasileiras na base' },
  { value: 'Diariamente', label: 'NFs e ordens de produção rodando' },
  { value: '+10 anos',  label: 'de mercado e operação' },
];

function SocialProof() {
  return (
    <section className="proof" data-screen-label="Site SocialProof">
      <div className="container">
        <p className="proof__eyebrow">Quem confia no oimpresso pra rodar a operação</p>
        <div className="proof__chips">
          {SETORES.map((s) => (
            <span key={s.label} className="proof__chip">
              <span aria-hidden>{s.emoji}</span>
              {s.label}
            </span>
          ))}
        </div>
        <div className="proof__stats">
          {STATS.map((s, i) => (
            <div key={i} className="proof__stat">
              <div className="proof__stat-v">{s.value}</div>
              <div className="proof__stat-l">{s.label}</div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}
window.SocialProof = SocialProof;
