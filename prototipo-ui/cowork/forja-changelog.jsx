// forja-changelog.jsx — Changelog: feed do git (PRs · ADRs · sessões · ondas) com filtro.
// Tela da Onda 9 da paridade. Extraído de forja-page.jsx.
const { useState: useStateC, useMemo: useMemoC, useEffect: useEffectC, useRef: useRefC } = React;
function FjChangelogFeed() {
  const [filter, setFilter] = useStateC("all");
  const LOG = window.FORJA.CHANGELOG;
  const dot = { pr: "oklch(0.52 0.10 195)", adr: "oklch(0.55 0.16 270)", sessao: "oklch(0.60 0.13 60)", onda: "oklch(0.55 0.13 150)" };
  const filtered = filter === "all" ? LOG : LOG.filter(e => e.tipo === filter);
  const tabs = [["all","Tudo"],["pr","PRs"],["adr","ADRs"],["sessao","Sessões"],["onda","Ondas"]];
  return (
    <div className="fj-changelog">
      <div className="fj-clog-tabs">{tabs.map(([k, l]) => (<button key={k} className={"fj-clog-tab" + (filter === k ? " active" : "")} onClick={() => setFilter(k)}>{l}</button>))}</div>
      <ul className="fj-feed">
        {filtered.map((e, i) => (
          <li key={i} className="fj-feed-item">
            <span className="fj-feed-dot" style={{ background: dot[e.tipo] }}/>
            <div className="fj-feed-body">
              <div className="fj-feed-top"><span className="fj-feed-ref">{e.ref}</span>{e.flags.map(f => <span key={f} className={"fj-flag fj-flag-" + f}>{f}</span>)}<span className="fj-feed-when">{e.data}</span></div>
              <p className="fj-feed-resumo">{e.resumo}</p>
              <div className="fj-feed-meta"><FjRoleBadge role={e.autor}/>{e.modulos.map(m => <span key={m} className="fj-mod sm">{m}</span>)}</div>
            </div>
          </li>
        ))}
      </ul>
    </div>
  );
}
window.FjChangelogFeed = FjChangelogFeed;
