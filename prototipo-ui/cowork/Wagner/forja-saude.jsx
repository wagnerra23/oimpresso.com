// forja-saude.jsx — Saúde: métricas acionáveis com sparkline + regras de automação.
// Receptor vivo = team-mcp/Scorecard (Onda 7 da paridade). Extraído de forja-page.jsx.
const { useState: useStateS, useMemo: useMemoS, useEffect: useEffectS, useRef: useRefS } = React;
// ─── Saúde — métricas acionáveis com sparkline ───
function FjSaudeView({ issues, onDrill, rules, onToggleRule }) {
  const GATES = window.FORJA.GATES;
  const inferido = issues.filter(i => i.frescor === "inferido").length;
  const blocked = issues.filter(i => i.bloqueado_por.length).length;
  const p0 = issues.filter(i => i.prio === "P0").length;
  const greens = GATES.filter(g => g.estado === "green").length;
  const PHASES = window.FORJA.PHASES;
  const bl = issues.filter(i => (i.estado || "backlog") === "backlog");
  const wip = PHASES.map(p => ({ p, n: bl.filter(i => i.fase === p.id).length }));
  const wipMax = Math.max(1, ...wip.map(w => w.n));
  const aging = { fresco: 0, atencao: 0, parado: 0 };
  bl.forEach(i => { const d = i.frescor === "lido" ? 2 : i.frescor === "inferido" ? 12 : (i.frescorDias || 0); if (i.frescor === "inferido" || d > 7) aging.parado++; else if (d > 3) aging.atencao++; else aging.fresco++; });
  const throughput = window.FORJA.CHANGELOG.length;
  const metrics = [
    { label: "Não-verificados", val: inferido, lim: "meta 0", hue: inferido ? 68 : 150, st: inferido ? "warn" : "ok", spark: [3, 4, 3, 5, inferido].map(x => x / 5), drill: "inferido", nota: "issues sem ✓ lido @main nesta sessão" },
    { label: "Bloqueados", val: blocked, lim: "", hue: blocked ? 25 : 150, st: blocked ? "bad" : "ok", spark: [1, 2, 1, 2, blocked].map(x => x / 3), drill: "blocked", nota: "esperando dependência" },
    { label: "P0 abertos", val: p0, lim: "", hue: 295, st: "ok", spark: [2, 3, 2, 2, p0].map(x => x / 4), drill: "p0", nota: "prioridade máxima" },
    { label: "Gates verdes", val: greens + "/" + GATES.length, lim: "ratchet só-desce", hue: 150, st: greens === GATES.length ? "ok" : "warn", spark: [.5, .6, .7, .7, greens / GATES.length], drill: null, nota: "e2e ainda vermelho" },
  ];
  return (
    <div className="fj-saude">
      <div className="fj-mcp-intro">Semáforo do loop, alimentado pelo que já existe (memory-health · baselines de gate · frescor). <b>Cada métrica linka a uma ação</b> — nada decorativo.</div>
      <div className="fj-saude-grid">
        {metrics.map(m => (
          <div key={m.label} className={"fj-metric fj-metric-" + m.st}>
            <div className="fj-metric-top"><span className="fj-metric-lbl">{m.label}</span>{m.lim && <span className="fj-metric-lim">{m.lim}</span>}</div>
            <div className="fj-metric-mid"><span className="fj-metric-val">{m.val}</span><FjSpark data={m.spark} hue={m.hue}/></div>
            <div className="fj-metric-foot"><span>{m.nota}</span>{m.drill && <button className="fj-metric-drill" onClick={() => onDrill(m.drill)}>ver →</button>}</div>
          </div>
        ))}
      </div>
      <section className="fj-mcp-card" style={{ marginTop: 16 }}>
        <h3>Fluxo · WIP por fase</h3>
        <div className="fj-wip">{wip.map(w => (
          <div key={w.p.id} className="fj-wip-col" title={w.p.label}>
            <span className="fj-wip-n">{w.n}</span>
            <div className="fj-wip-bar" style={{ height: (6 + w.n / wipMax * 56) + "px", background: `oklch(0.58 0.13 ${w.p.hue})` }}/>
            <span className="fj-wip-lbl">{w.p.id}</span>
          </div>
        ))}</div>
        <div className="fj-flux-row">
          <div className="fj-flux-stat"><b>{throughput}</b><span>entregas (changelog)</span></div>
          <div className="fj-flux-aging">
            <span className="fj-age fj-age-ok">{aging.fresco} fresco</span>
            <span className="fj-age fj-age-warn">{aging.atencao} atenção</span>
            <span className="fj-age fj-age-bad">{aging.parado} parado</span>
          </div>
        </div>
        <p className="fj-dr-desc" style={{ marginTop: 8 }}>WIP, throughput e aging derivados do estado real. Lead/cycle time chegam com timestamps reais (round-trip git, #9).</p>
      </section>
      <section className="fj-mcp-card" style={{ marginTop: 16 }}>
        <h3>Automação</h3>
        <ul className="fj-rules">
          {FJ_RULES.map(r => (
            <li key={r.id}>
              <button className={"fj-rule-toggle" + (rules[r.id] ? " on" : "")} onClick={() => onToggleRule(r.id)} role="switch" aria-checked={!!rules[r.id]}><span className="fj-rule-knob"/></button>
              <div className="fj-rule-tx"><b>{r.label}</b><small>{r.nota}</small></div>
              {!r.live && <span className="fj-rule-dep">requer #9</span>}
            </li>
          ))}
        </ul>
      </section>
      <section className="fj-mcp-card" style={{ marginTop: 16 }}>
        <h3>Gates de CI por fase</h3>
        <ul className="fj-gate-health">
          {GATES.map(g => (
            <li key={g.id}>
              <span className={"fj-gate fj-gate-" + g.estado}><span className="fj-gate-dot"/>{g.id}</span>
              <span className="fj-gate-fase">{g.fase}</span>
              <span className="fj-gate-state">{g.estado === "green" ? "verde" : g.estado === "amber" ? "atenção" : "vermelho"}</span>
            </li>
          ))}
        </ul>
      </section>
    </div>
  );
}
window.FjSaudeView = FjSaudeView;
