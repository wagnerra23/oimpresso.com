// forja-runbook.jsx — trilhas de papel: o que cada papel ([W]/[CC]/[CD]/[CL]/[CA]) faz em
// cada fase do pipeline. Extraído de forja-mcp.jsx (Onda 3).
const { useState: useStateRb, useMemo: useMemoRb, useRef: useRefRb, useEffect: useEffectRb } = React;
// ─── Trilhas de papel (runbook): o que cada papel faz em cada fase ───
function ForjaRunbook({ onClose }) {
  const RoleBadge = window.FjRoleBadge;
  const steps = [
    { id:"F0",   owner:"W",  does:"Escreve o pedido em COWORK_NOTES.md — dispara o loop.", over:null },
    { id:"F1",   owner:"CC", does:"Gera o protótipo visual (page.jsx) + COMPARISON 15 dimensões aqui no Cowork.", over:null },
    { id:"F1.5", owner:"CD", does:"design-critique → critique-score.json (≥80 passa).", over:"/design-override" },
    { id:"F2",   owner:"W2", does:"Aprovação visual síncrona do screenshot.", over:"/screenshot-override" },
    { id:"F3",   owner:"CL", does:"Traduz o protótipo aprovado pra Inertia/React real no repo.", over:null },
    { id:"F3.5", owner:"CA", does:"accessibility-review (WCAG 2.1 AA) → a11y-report.md.", over:"/a11y-override" },
    { id:"F4",   owner:"W2", does:"Merge do PR — fecha o ciclo.", over:null },
  ];
  const PHASES = window.FORJA.PHASES;
  return (
    <div className="fj-drawer-back" onClick={onClose}>
      <aside className="fj-drawer" onClick={e => e.stopPropagation()}>
        <header className="fj-dr-head">
          <div className="fj-dr-head-l"><span className="fj-dr-id">Trilhas de papel</span></div>
          <button className="icon-btn" onClick={onClose} aria-label="Fechar"><I.x size={14}/></button>
        </header>
        <div className="fj-dr-body">
          <p className="fj-dr-desc">Runbook do cowork loop — quem faz o quê em cada fase. Onboarding de papel novo (humano ou agente). Fonte: PROTOCOL.md §1–§3.</p>
          <div className="fj-dr-sec">
            <h3>6 papéis</h3>
            <ul className="fj-rb-roles">
              {Object.keys(window.FORJA.ACTORS).map(r => {
                const a = window.FORJA.ACTORS[r];
                return <li key={r}><RoleBadge role={r} showName/><span className="fj-rb-kind">{a.kind === "agent" ? "agente · " + (a.model || "") : "humano"}</span><span className="fj-rb-desc">{a.desc}</span></li>;
              })}
            </ul>
          </div>
          <div className="fj-dr-sec">
            <h3>7 fases · F0→F4</h3>
            <ul className="fj-rb-steps">
              {steps.map(s => {
                const ph = PHASES.find(p => p.id === s.id);
                return (
                  <li key={s.id} style={{ "--ph": ph ? ph.hue : 250 }}>
                    <div className="fj-rb-step-top"><span className="fj-rb-fase">{s.id} {ph?.label}</span><RoleBadge role={s.owner}/></div>
                    <p className="fj-rb-does">{s.does}</p>
                    {s.over && <span className="fj-rb-over">escape hatch: <code>{s.over}</code> (registrado)</span>}
                  </li>
                );
              })}
            </ul>
          </div>
        </div>
      </aside>
    </div>
  );
}
window.ForjaRunbook = ForjaRunbook;
