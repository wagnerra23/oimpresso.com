// PresenceStrip — live presence avatars
const PRESENCE_DATA = [
  { id:1, nome: 'Ana Carolina', iniciais: 'AC', status: 'presente', entrada: '07:55', mat: '0021' },
  { id:2, nome: 'Bruno Lima', iniciais: 'BL', status: 'presente', entrada: '08:01', mat: '0034' },
  { id:3, nome: 'Carla Mendes', iniciais: 'CM', status: 'atrasado', entrada: null, mat: '0040' },
  { id:4, nome: 'Diego Souza', iniciais: 'DS', status: 'presente', entrada: '08:10', mat: '0048' },
  { id:5, nome: 'Eliane Reis', iniciais: 'ER', status: 'saiu', entrada: '06:00', saida: '14:00', mat: '0052' },
  { id:6, nome: 'Felipe Tavares', iniciais: 'FT', status: 'presente', entrada: '08:15', mat: '0061' },
  { id:7, nome: 'Gabriela Nunes', iniciais: 'GN', status: 'presente', entrada: '08:00', mat: '0064' },
  { id:8, nome: 'Henrique Paiva', iniciais: 'HP', status: 'ausente', entrada: null, mat: '0070' },
  { id:9, nome: 'Isabel Cunha', iniciais: 'IC', status: 'presente', entrada: '07:48', mat: '0072' },
  { id:10, nome: 'João Marques', iniciais: 'JM', status: 'presente', entrada: '08:05', mat: '0081' },
  { id:11, nome: 'Karla Vidal', iniciais: 'KV', status: 'atrasado', entrada: null, mat: '0089' },
  { id:12, nome: 'Lucas Brito', iniciais: 'LB', status: 'presente', entrada: '08:00', mat: '0094' },
  { id:13, nome: 'Mariana Otto', iniciais: 'MO', status: 'presente', entrada: '07:50', mat: '0098' },
  { id:14, nome: 'Nilton Sampaio', iniciais: 'NS', status: 'saiu', entrada: '06:00', saida: '14:00', mat: '0102' },
];

function PresenceStrip() {
  const presentes = PRESENCE_DATA.filter(c => c.status === 'presente').length;
  const atrasados = PRESENCE_DATA.filter(c => c.status === 'atrasado').length;
  const ausentes = PRESENCE_DATA.filter(c => c.status === 'ausente').length;
  const total = PRESENCE_DATA.length;
  return (
    <div className="card">
      <div className="card__head">
        <div>
          <h3>Presença ao vivo</h3>
          <p className="muted">{presentes} presentes · {atrasados} atrasados · {ausentes} ausentes · {total} total</p>
        </div>
        <div className="legend">
          <span><span className="legend__dot" style={{background: 'hsl(160 84% 39%)'}}/>Presente</span>
          <span><span className="legend__dot" style={{background: 'hsl(38 92% 50%)'}}/>Atrasado</span>
          <span><span className="legend__dot" style={{background: 'hsl(215 16% 47%)'}}/>Saiu</span>
          <span><span className="legend__dot" style={{background: 'hsl(214 31% 91%)'}}/>Ausente</span>
        </div>
      </div>
      <div className="presence">
        {PRESENCE_DATA.map((c) => (
          <span key={c.id} className={"presence__avatar presence__avatar--" + c.status} title={c.nome}>
            {c.iniciais}
            <span className="presence__dot" aria-hidden/>
          </span>
        ))}
      </div>
    </div>
  );
}
window.PresenceStrip = PresenceStrip;

// ActivityFeed
const ACTIVITY = [
  { id:1, tipo:'ENTRADA', momento:'08:42', tempo:'5min', nome:'Karla Vidal', rep:'REP-P-014' },
  { id:2, tipo:'INTERVALO_FIM', momento:'08:38', tempo:'9min', nome:'Bruno Lima', rep:'REP-P-014' },
  { id:3, tipo:'INTERVALO_INICIO', momento:'08:30', tempo:'17min', nome:'Bruno Lima', rep:'REP-P-014' },
  { id:4, tipo:'ENTRADA', momento:'08:15', tempo:'32min', nome:'Felipe Tavares', rep:'REP-P-014' },
  { id:5, tipo:'ENTRADA', momento:'08:10', tempo:'37min', nome:'Diego Souza', rep:'REP-C-002' },
  { id:6, tipo:'ENTRADA', momento:'08:05', tempo:'42min', nome:'João Marques', rep:'REP-P-014' },
  { id:7, tipo:'ENTRADA', momento:'08:01', tempo:'46min', nome:'Bruno Lima', rep:'REP-P-014' },
  { id:8, tipo:'ENTRADA', momento:'08:00', tempo:'47min', nome:'Lucas Brito', rep:'REP-P-014' },
  { id:9, tipo:'SAIDA', momento:'14:00', tempo:'—', nome:'Eliane Reis', rep:'REP-P-014' },
];

const TIPO_CFG = {
  ENTRADA:          { label: 'Entrada', icon: 'log-in',  color: 'hsl(161 94% 30%)', bg: 'hsl(160 84% 39% / .12)' },
  SAIDA:            { label: 'Saída',   icon: 'log-out', color: 'hsl(215 16% 47%)', bg: 'hsl(215 16% 47% / .10)' },
  INTERVALO_INICIO: { label: 'Intervalo', icon: 'coffee', color: 'hsl(32 95% 44%)',  bg: 'hsl(38 92% 50% / .12)' },
  INTERVALO_FIM:    { label: 'Retorno', icon: 'coffee', color: 'hsl(161 94% 30%)', bg: 'hsl(160 84% 39% / .12)' },
};

function ActivityFeed() {
  return (
    <div className="card">
      <div className="card__head">
        <h3>Atividade de hoje</h3>
        <span className="eyebrow">{ACTIVITY.length} eventos</span>
      </div>
      <ol className="feed">
        <div className="feed__line" aria-hidden/>
        {ACTIVITY.map((m) => {
          const cfg = TIPO_CFG[m.tipo] || { label: m.tipo, icon: 'clock', color: 'var(--fg-2)', bg: 'var(--secondary)' };
          return (
            <li key={m.id} className="feed__item">
              <div className="feed__ico" style={{background: cfg.bg, color: cfg.color}}>
                <Icon name={cfg.icon} size={14}/>
              </div>
              <div style={{flex:1, minWidth: 0}}>
                <div className="feed__row1"><span className="feed__name">{m.nome}</span><span className="feed__time">{m.momento}</span></div>
                <div className="feed__row2"><span style={{color: cfg.color}}>{cfg.label}</span> <span className="muted">· {m.rep}</span><span className="muted">há {m.tempo}</span></div>
              </div>
            </li>
          );
        })}
      </ol>
    </div>
  );
}
window.ActivityFeed = ActivityFeed;
