// norte-board.jsx — Board interativo: 7 frames reais + costuras como setas, pan/zoom/foco.
// Lê window.NORTE (norte-data.jsx). Cor-por-módulo da identidade Norte.
(() => {
const { useState, useEffect, useCallback, useRef } = React;
const { SCENES } = window.NORTE;

// ── ícones (compartilhados com o percurso) ──
const Doc = ({ s = 15 }) => <svg width={s} height={s} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Z"/><path d="M14 3v6h6"/><path d="M9 14l2 2 4-4"/></svg>;
const Lock = ({ s = 15 }) => <svg width={s} height={s} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>;
const Check = ({ s = 13 }) => <svg width={s} height={s} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round"><path d="M5 13l4 4 10-10"/></svg>;
const Bolt = ({ s = 15 }) => <svg width={s} height={s} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M13 2L4.5 13.5H11l-1 8.5L19.5 10H13l0-8Z"/></svg>;

// ── renderer dos widgets (mesma fonte do percurso) ──
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
        <div style={{ height: 8, borderRadius: 6, background: "var(--surface)", overflow: "hidden", border: "1px solid var(--border-2)" }}>
          <div style={{ height: "100%", width: w.pct + "%", borderRadius: 6, background: "linear-gradient(90deg, var(--pos), oklch(0.74 0.15 145))" }}/>
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

// ── geometria do board ──
const CW = 2040, CH = 1170, FW = 360, FH = 440;
const FP = [
  { x: 60, y: 60 }, { x: 580, y: 60 }, { x: 1100, y: 60 }, { x: 1620, y: 60 },
  { x: 1620, y: 670 }, { x: 1100, y: 670 }, { x: 580, y: 670 },
];
// cor-por-módulo por cena (identidade Norte)
const MOD = ["var(--m-oficina)", "var(--m-oficina)", "var(--m-inbox)", "var(--m-oficina)", "var(--m-vendas)", "var(--m-fiscal)", "var(--m-financeiro)"];

// conectores: costuras como setas. cor = módulo de destino.
const CONN = [
  { to: 1, d: "M420 280 L580 280",   lx: 500,  ly: 252, label: "abre o diagnóstico" },
  { to: 2, d: "M940 280 L1100 280",  lx: 1020, ly: 252, label: "orçamento → cliente" },
  { to: 3, d: "M1460 280 L1620 280", lx: 1540, ly: 252, label: "aprovou · destrava" },
  { to: 4, d: "M1800 500 L1800 670", lx: 1882, ly: 585, label: "OS pronta → venda" },
  { to: 5, d: "M1620 890 L1460 890", lx: 1540, ly: 862, label: "emite as 2 notas" },
  { to: 6, d: "M1100 890 L940 890",  lx: 1020, ly: 862, label: "baixa no caixa" },
  { to: 0, d: "M580 890 C 170 980 100 560 240 502", lx: 250, ly: 720, label: "histórico → próxima OS", loop: true },
];

function Board() {
  const vpRef = useRef(null);
  const [view, setView] = useState({ x: 0, y: 0, s: 1 });
  const [focus, setFocus] = useState(-1);
  const drag = useRef(null);

  const fit = useCallback(() => {
    const el = vpRef.current; if (!el) return;
    const w = el.clientWidth, h = el.clientHeight;
    if (!w || !h) return; // ainda sem layout — o poller tenta de novo
    const s = Math.min(w / CW, h / CH) * 0.92;
    setView({ s, x: (w - CW * s) / 2, y: (h - CH * s) / 2 });
    setFocus(-1);
  }, []);
  useEffect(() => {
    let tries = 0;
    const attempt = () => {
      const el = vpRef.current;
      if (el && el.clientWidth && el.clientHeight) { fit(); return; }
      if (tries++ < 80) setTimeout(attempt, 30); // timer dispara mesmo sem paint
    };
    attempt();
    const el = vpRef.current;
    let ro;
    if (window.ResizeObserver && el) { ro = new ResizeObserver(() => fit()); ro.observe(el); }
    return () => { ro && ro.disconnect(); };
  }, [fit]);

  // wheel zoom (non-passive)
  useEffect(() => {
    const el = vpRef.current; if (!el) return;
    const onWheel = (e) => {
      e.preventDefault();
      const r = el.getBoundingClientRect();
      const mx = e.clientX - r.left, my = e.clientY - r.top;
      setView(v => {
        const ns = Math.max(0.22, Math.min(2.2, v.s * Math.exp(-e.deltaY * 0.0015)));
        const k = ns / v.s;
        return { s: ns, x: mx - (mx - v.x) * k, y: my - (my - v.y) * k };
      });
    };
    el.addEventListener("wheel", onWheel, { passive: false });
    return () => el.removeEventListener("wheel", onWheel);
  }, []);

  const onDown = (e) => {
    if (e.target.closest(".bframe2")) return; // frames lidam com o próprio clique
    drag.current = { sx: e.clientX, sy: e.clientY, ox: view.x, oy: view.y };
    vpRef.current.classList.add("grab");
  };
  const onMove = (e) => {
    if (!drag.current) return;
    setView(v => ({ ...v, x: drag.current.ox + (e.clientX - drag.current.sx), y: drag.current.oy + (e.clientY - drag.current.sy) }));
  };
  const onUp = () => { drag.current = null; vpRef.current && vpRef.current.classList.remove("grab"); };

  const zoomAt = (f) => setView(v => {
    const el = vpRef.current, w = el.clientWidth, h = el.clientHeight;
    const ns = Math.max(0.22, Math.min(2.2, v.s * f)), k = ns / v.s;
    return { s: ns, x: w / 2 - (w / 2 - v.x) * k, y: h / 2 - (h / 2 - v.y) * k };
  });

  const focusFrame = (idx) => {
    const el = vpRef.current, w = el.clientWidth, h = el.clientHeight;
    const cx = FP[idx].x + FW / 2, cy = FP[idx].y + FH / 2;
    const s = Math.min((h * 0.82) / FH, 0.95);
    setView({ s, x: w / 2 - cx * s, y: h / 2 - cy * s });
    setFocus(idx);
  };

  return (
    <React.Fragment>
      <div className="bd-top">
        <div className="bd-brand">
          <div className="bd-logo">Oi</div>
          <div><b>Oimpresso · Norte</b><small>Board — o caminhão de ponta a ponta</small></div>
        </div>
        <div className="bd-top-r">
          <a className="bd-link" href="Norte — Identidade Oimpresso.html">← Identidade</a>
        </div>
      </div>

      <div className="bd-main">
        <div className="bd-vp" ref={vpRef} onPointerDown={onDown} onPointerMove={onMove} onPointerUp={onUp} onPointerLeave={onUp}>
          <div className="bd-canvas" style={{ transform: `translate(${view.x}px,${view.y}px) scale(${view.s})`, width: CW, height: CH }}>
            <svg className="bd-links" width={CW} height={CH} viewBox={`0 0 ${CW} ${CH}`}>
              <defs>
                {CONN.map((c, i) => (
                  <marker key={i} id={`arr${i}`} markerWidth="10" markerHeight="10" refX="7" refY="5" orient="auto" markerUnits="userSpaceOnUse">
                    <path d="M0 0 L9 5 L0 10 z" style={{ fill: MOD[c.to] }}/>
                  </marker>
                ))}
              </defs>
              {CONN.map((c, i) => (
                <path key={i} d={c.d} fill="none" style={{ stroke: MOD[c.to] }} strokeWidth="2.5"
                  strokeDasharray={c.loop ? "8 7" : undefined} strokeLinecap="round" markerEnd={`url(#arr${i})`} opacity="0.92"/>
              ))}
            </svg>

            {CONN.map((c, i) => (
              <div key={i} className={"bd-llbl" + (c.loop ? " loop" : "")} style={{ left: c.lx, top: c.ly, "--lc": MOD[c.to] }}>{c.label}</div>
            ))}

            {SCENES.map((sc, idx) => (
              <button key={sc.id} className={"bframe2" + (focus === idx ? " focus" : "")}
                style={{ left: FP[idx].x, top: FP[idx].y, "--c": MOD[idx] }}
                onClick={() => focusFrame(idx)}>
                <div className="bf-head">
                  <span className="bf-dot"/>
                  <span className="bf-n">{String(idx + 1).padStart(2, "0")}</span>
                  <span className="bf-mod">{sc.mod}</span>
                  <span className="bf-persona">{sc.persona[0]}<b>{sc.persona[1]}</b></span>
                </div>
                <div className="bf-title">{sc.title}</div>
                <div className="bf-screen">
                  <div className="bf-bar"><i/><i/><i/><span>{sc.screen.bar}</span></div>
                  <div className="bf-body">{sc.screen.body.map((w, k) => <W key={k} w={w}/>)}</div>
                </div>
              </button>
            ))}
          </div>

          <div className="bd-cap">
            <b>O produto inteiro, como um circuito.</b>
            <span>Cada tela é um frame, cada costura é uma seta — e o ciclo se fecha. Arraste pra navegar, clique num quadro pra dar zoom.</span>
          </div>

          <div className="bd-hint">arraste · <kbd>scroll</kbd> zoom · clique = foco</div>

          <div className="bd-zoom">
            <button className="bd-zbtn" onClick={() => zoomAt(1 / 1.25)} title="menos zoom">−</button>
            <span className="bd-zlabel">{Math.round(view.s * 100)}%</span>
            <button className="bd-zbtn" onClick={() => zoomAt(1.25)} title="mais zoom">+</button>
            <button className="bd-zbtn wide" onClick={fit}>Ver tudo</button>
          </div>
        </div>
      </div>
    </React.Fragment>
  );
}

ReactDOM.createRoot(document.getElementById("app")).render(<Board/>);
})();
