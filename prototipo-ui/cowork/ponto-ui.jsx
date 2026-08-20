// ponto-ui.jsx — peças compartilhadas das telas do Ponto (tradução dos padrões AdminLTE do
// Blade pro DS vivo: label-* → pílula, small-box → KPI, callout → nota, widget → card).
// Expõe window.PontoUI. Sem estado próprio: só forma.
(() => {
const P = () => window.PONTO;
const Ic = (props) => window.JcIcon ? <window.JcIcon {...props} /> : null;

const TOM_ESTADO_INTERC = { RASCUNHO: "neutral", PENDENTE: "warn", APROVADA: "ok", REJEITADA: "danger", APLICADA: "info", CANCELADA: "neutral" };
const TOM_ESTADO_APURACAO = { PENDENTE: "neutral", CALCULADO: "info", DIVERGENCIA: "warn", AJUSTADO: "info", CONSOLIDADO: "ok", FECHADO: "ok" };
const TOM_ESTADO_IMPORT = { PENDENTE: "neutral", PROCESSANDO: "info", CONCLUIDA: "ok", CONCLUIDA_COM_ERROS: "warn", FALHOU: "danger" };

function Pill({ tom = "neutral", mono, children }) {
  return <span className={"pt-pill " + tom + (mono ? " mono" : "")}>{children}</span>;
}
const PillIntercorrencia = ({ estado }) => <Pill tom={TOM_ESTADO_INTERC[estado] || "neutral"}>{P().ESTADOS_INTERC[estado] || estado}</Pill>;
const PillApuracao = ({ estado }) => <Pill tom={TOM_ESTADO_APURACAO[estado] || "neutral"} mono>{estado}</Pill>;
const PillImportacao = ({ estado }) => <Pill tom={TOM_ESTADO_IMPORT[estado] || "neutral"} mono>{estado}</Pill>;
const PillPrioridade = ({ p }) => <Pill tom={p === "URGENTE" ? "danger" : "neutral"}>{p === "URGENTE" ? "Urgente" : "Normal"}</Pill>;
const PillSimNao = ({ v, sim = "Sim", nao = "Não" }) => <Pill tom={v ? "ok" : "neutral"}>{v ? sim : nao}</Pill>;

function Card({ icon, titulo, sub, acao, children, className, contrato }) {
  return (
    <section className={"pt-card" + (className ? " " + className : "")} data-contract={contrato}>
      {(titulo || acao) &&
        <header className="pt-card-h">
          {icon && <Ic name={icon} />}
          <b>{titulo}</b>
          {sub && <small>{sub}</small>}
          <span className="pt-sp" />
          {acao}
        </header>}
      <div className="pt-card-b">{children}</div>
    </section>
  );
}

function Kpi({ label, valor, ln, tom, onClick }) {
  const cls = "pt-kpi" + (tom ? " " + tom : "") + (onClick ? " hit" : "");
  const corpo = <><small>{label}</small><b>{valor}</b>{ln && <span className="ln">{ln}</span>}</>;
  return onClick
    ? <button type="button" className={cls} onClick={onClick}>{corpo}</button>
    : <div className={cls}>{corpo}</div>;
}

function Nota({ tom = "info", icon, titulo, children, contrato }) {
  return (
    <div className={"pt-nota " + tom} data-contract={contrato}>
      <Ic name={icon || (tom === "warn" || tom === "danger" ? "alert" : tom === "ok" ? "check" : "help")} />
      <div>{titulo && <><b>{titulo}</b><br /></>}{children}</div>
    </div>
  );
}

const Legal = ({ children }) => (
  <div className="pt-legal"><Ic name="shield" />{children || "Registros protegidos pela Portaria MTP 671/2021 — marcações são imutáveis (append-only)."}</div>
);

function Vazio({ icon = "list", children, colSpan }) {
  const corpo = <div className="pt-tbl-empty"><Ic name={icon} /><div>{children}</div></div>;
  return colSpan ? <tr><td colSpan={colSpan} style={{ padding: 0 }}>{corpo}</td></tr> : corpo;
}

function Tabela({ cols, children }) {
  return (
    <div className="pt-tblwrap">
      <table className="pt-tbl">
        <thead><tr>{cols.map((c, i) => <th key={i} className={c.num ? "num" : ""} style={c.w ? { width: c.w } : null}>{c.l}</th>)}</tr></thead>
        <tbody>{children}</tbody>
      </table>
    </div>
  );
}

const Voltar = ({ onClick, children = "Voltar" }) => (
  <button className="pt-btn" onClick={onClick}><Ic name="x" />{children}</button>
);

// Minutos assinados: verde no crédito, vermelho no débito, travessão no zero — como no Blade.
function Min({ v, sinal }) {
  const P_ = P();
  if (!v) return <span className="pt-dim">—</span>;
  const cls = v > 0 ? "pt-pos" : "pt-neg";
  return <span className={cls}>{sinal && v > 0 ? "+" : ""}{P_.fmtMin(v)}</span>;
}

// Paginação — o Blade pagina toda lista (LengthAwarePaginator); aqui o mesmo contrato:
// "N–M de T", passo por página e navegação, sem sumir com o rodapé quando cabe numa página.
function usePagina(total, porPaginaInicial = 15) {
  const [pagina, setPagina] = React.useState(1);
  const [porPagina, setPorPagina] = React.useState(porPaginaInicial);
  const ultima = Math.max(1, Math.ceil(total / porPagina));
  React.useEffect(() => { if (pagina > ultima) setPagina(1); }, [total, porPagina, ultima, pagina]);
  const fatia = (arr) => arr.slice((pagina - 1) * porPagina, pagina * porPagina);
  return { pagina, setPagina, porPagina, setPorPagina, ultima, fatia, total };
}

function Pager({ p, rotulo = "registros" }) {
  const ini = p.total === 0 ? 0 : (p.pagina - 1) * p.porPagina + 1;
  const fim = Math.min(p.pagina * p.porPagina, p.total);
  return (
    <div className="pt-pager">
      <label className="pt-pager-pp">Mostrar
        <select value={p.porPagina} onChange={(e) => { p.setPorPagina(Number(e.target.value)); p.setPagina(1); }}>
          {[10, 15, 25, 50, 100].map((n) => <option key={n} value={n}>{n}</option>)}
        </select>
        {rotulo}
      </label>
      <span className="pt-sp" />
      <span className="pt-pager-range">Mostrando <b>{ini}</b>–<b>{fim}</b> de <b>{p.total}</b></span>
      <div className="pt-group">
        <button className="pt-btn" disabled={p.pagina <= 1} onClick={() => p.setPagina(p.pagina - 1)}>Anterior</button>
        <button className="pt-btn" disabled={p.pagina >= p.ultima} onClick={() => p.setPagina(p.pagina + 1)}>Próximo</button>
      </div>
      <span className="pt-pager-cur">{p.pagina} / {p.ultima}</span>
    </div>
  );
}

window.PontoUI = { Pill, PillIntercorrencia, PillApuracao, PillImportacao, PillPrioridade, PillSimNao, Card, Kpi, Nota, Legal, Vazio, Tabela, Voltar, Min, Ic, usePagina, Pager };
})();
