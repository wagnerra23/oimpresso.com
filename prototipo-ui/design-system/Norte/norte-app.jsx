// norte-app.jsx — North Star: spine de fluxo + cena + costura. Lê window.NORTE.
(() => {
const { useState, useEffect, useCallback } = React;
const { SCENES, SPINE } = window.NORTE;

const Arrow = ({ s = 18 }) => <svg width={s} height={s} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>;
const Bolt = ({ s = 15 }) => <svg width={s} height={s} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M13 2L4.5 13.5H11l-1 8.5L19.5 10H13l0-8Z"/></svg>;
const Doc = ({ s = 15 }) => <svg width={s} height={s} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Z"/><path d="M14 3v6h6"/><path d="M9 14l2 2 4-4"/></svg>;
const Lock = ({ s = 15 }) => <svg width={s} height={s} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>;
const Check = ({ s = 13 }) => <svg width={s} height={s} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round"><path d="M5 13l4 4 10-10"/></svg>;

// ── widget renderer ──
function W({ w }) {
  switch (w.t) {
    case "head": return (
      <div className="w-head">
        <div className="w-plate"><div className="pt">BR · MERCOSUL</div><div className="pn">{w.plate}</div></div>
        <div className="w-head-meta"><b>{w.veh}</b><small>{w.sub}</small></div>
        <span className="w-os">{w.os}</span>
      </div>
    );
    case "sec":  return <div className="w-sec">{w.v}</div>;
    case "text": return <p style={{ margin: "0 0 6px", fontSize: 13, lineHeight: 1.5, color: "var(--text-2)" }}>{w.v}</p>;
    case "fields": return (
      <dl className="w-fields">{w.rows.map((r, i) => <React.Fragment key={i}><dt>{r[0]}</dt><dd className={r[2] || ""}>{r[1]}</dd></React.Fragment>)}</dl>
    );
    case "dvi": return (
      <div className="w-dvi">{w.rows.map((r, i) => (
        <div key={i} className={"w-dvi-row " + r.s}><span className="w-dvi-dot"/><div><b>{r.b}</b><small>{r.n}</small></div><span className={"w-tag " + r.tag[0]}>{r.tag[1]}</span></div>
      ))}</div>
    );
    case "kpis": return (
      <div className="w-kpis">{w.items.map((k, i) => <div key={i} className={"w-kpi " + (k[2] || "")}><small>{k[0]}</small><b>{k[1]}</b></div>)}</div>
    );
    case "items": return (
      <div className="w-items">{w.rows.map((r, i) => (
        <div key={i} className="w-item"><span className={"w-item-ic " + r.ic}>{r.ic === "serv" ? "🔧" : "⬡"}</span><b>{r.b}</b><span className="q">{r.q}</span><span className="v">{r.v}</span></div>
      ))}</div>
    );
    case "msg": return (
      <div className={"w-msg " + w.side}><div className="w-bub">{w.text}<small>{w.when}{w.side === "me" ? " ✓✓" : ""}</small></div></div>
    );
    case "fiscal": return (
      <div className="w-fiscal">{w.rows.map((r, i) => (
        <div key={i} className="w-fiscal-row"><span className="w-fiscal-ic"><Doc/></span><div><b>{r.tipo}</b><small>{r.num}</small></div><span className="w-fiscal-ok"><Check/> autorizada</span></div>
      ))}</div>
    );
    case "progress": return (
      <div style={{ margin: "2px 0 4px" }}>
        <div style={{ height: 8, borderRadius: 6, background: "var(--sunken)", overflow: "hidden", border: "1px solid var(--border-2)" }}>
          <div style={{ height: "100%", width: w.pct + "%", borderRadius: 6, background: "linear-gradient(90deg, var(--stage-emerald), var(--stage-green))" }}/>
        </div>
        <div style={{ fontSize: 11, color: "var(--text-3)", marginTop: 6, fontFamily: "var(--mono)" }}>{w.label}</div>
      </div>
    );
    case "note": {
      const ic = w.tone === "lock" ? <Lock s={16}/> : w.tone === "pos" ? <Check s={15}/> : <Bolt s={15}/>;
      const cls = w.tone === "lock" ? "w-note" : "w-note " + w.tone;
      const style = w.tone === "lock" ? { background: "var(--neg-soft)", border: "1px solid color-mix(in oklch,var(--neg) 40%,var(--border))" } : undefined;
      const icColor = w.tone === "lock" ? "var(--neg)" : w.tone === "pos" ? "var(--pos)" : "var(--accent)";
      return <div className={cls} style={style}><span style={{ color: icColor, flex: "0 0 auto", marginTop: 1 }}>{ic}</span><span>{w.text}</span></div>;
    }
    default: return null;
  }
}

function App() {
  const [i, setI] = useState(() => {
    const s = parseInt(localStorage.getItem("norte.step") || "0", 10);
    return isNaN(s) ? 0 : Math.max(0, Math.min(SCENES.length - 1, s));
  });
  useEffect(() => { localStorage.setItem("norte.step", String(i)); }, [i]);

  const go = useCallback((n) => setI(p => Math.max(0, Math.min(SCENES.length - 1, n))), []);
  useEffect(() => {
    const k = (e) => { if (e.key === "ArrowRight") go(i + 1); else if (e.key === "ArrowLeft") go(i - 1); };
    window.addEventListener("keydown", k);
    return () => window.removeEventListener("keydown", k);
  }, [i, go]);

  const sc = SCENES[i];

  return (
    <React.Fragment>
      <div className="nx-top">
        <div className="nx-brand">
          <div className="nx-logo">Oi</div>
          <div><b>Oimpresso ERP</b><small>Norte — o caminhão de ponta a ponta</small></div>
        </div>
        <div className="nx-kicker">Fluxo &gt; Módulo</div>
      </div>

      <div className="nx-spine">
        {SCENES.map((s, idx) => (
          <button key={s.id} className={"nx-node" + (idx === i ? " active" : "") + (idx < i ? " done" : "")}
            style={{ "--stage": s.stage }} onClick={() => go(idx)}>
            <span className={"nx-node-line" + (idx < i ? " done" : "")}/>
            <span className="nx-dot"/>
            <span className="nx-node-n">{String(idx + 1).padStart(2, "0")}</span>
            <span className="nx-node-lbl">{SPINE[idx]}</span>
          </button>
        ))}
      </div>

      <div className="nx-stage" style={{ "--stage": sc.stage }}>
        <div className="nx-scene" key={sc.id}>
          <div className="nx-scene-head">
            <div className="nx-eyebrow">
              <span className="nx-mod">{sc.mod}</span>
              <span className="nx-persona">{sc.persona[0]}<b>{sc.persona[1]}</b></span>
            </div>
            <h1>{sc.title}</h1>
            <p className="nx-felt">{sc.felt}</p>
          </div>
          <div className="nx-screen">
            <div className="nx-screen-bar"><span className="nx-tl"/><span className="nx-tl"/><span className="nx-tl"/><span>{sc.screen.bar}</span></div>
            <div className="nx-screen-body">{sc.screen.body.map((w, k) => <W key={k} w={w}/>)}</div>
          </div>
        </div>

        <div className="nx-seam" key={sc.id + "-seam"}>
          <div className="nx-seam-card">
            <span className="nx-seam-tag"><Bolt s={12}/>{sc.seam.tag}</span>
            <div className="nx-seam-flow">
              <span className="nx-seam-chip">{sc.seam.from}</span>
              <span className="nx-seam-arrow"><Arrow s={20}/></span>
              <span className="nx-seam-chip">{sc.seam.to}</span>
            </div>
            <h2>{sc.seam.h2}</h2>
            <p>{sc.seam.body}</p>
            <div className="nx-seam-auto">
              <span className="nx-seam-auto-ic"><Bolt s={15}/></span>
              <div><b>{sc.seam.auto[0]}</b><small>{sc.seam.auto[1]}</small></div>
            </div>
          </div>
        </div>
      </div>

      <div className="nx-foot">
        <button className="nx-btn" onClick={() => go(i - 1)} disabled={i === 0}><span style={{ display: "inline-flex", transform: "scaleX(-1)" }}><Arrow s={16}/></span>Anterior</button>
        <span className="nx-count">{String(i + 1).padStart(2, "0")} / {String(SCENES.length).padStart(2, "0")}</span>
        <div className="nx-progress"><div className="nx-progress-fill" style={{ width: ((i + 1) / SCENES.length * 100) + "%" }}/></div>
        <span className="nx-hint">navegue <kbd>←</kbd> <kbd>→</kbd></span>
        {i < SCENES.length - 1
          ? <button className="nx-btn primary" onClick={() => go(i + 1)}>Próxima costura <Arrow s={16}/></button>
          : <button className="nx-btn primary" onClick={() => go(0)}>Recomeçar o ciclo <Arrow s={16}/></button>}
      </div>
    </React.Fragment>
  );
}

ReactDOM.createRoot(document.getElementById("app")).render(<App/>);
})();
