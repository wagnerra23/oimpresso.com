// forja-gantt.jsx — Trabalho · Gantt (prazos): barras por módulo, progresso por status,
// prazo arrastável e linha do hoje. Tela da Onda 6 da paridade (Forja/Roadmap/Gantt).
const { useState: useStateG, useMemo: useMemoG, useEffect: useEffectG, useRef: useRefG } = React;
// ─── Gantt (cópia do conceito Forja/Roadmap/Gantt.tsx @main): barras por módulo,
// progresso por status (done=100 · doing/review=50), prazo arrastável (só o prazo — B2),
// bloqueio sinalizado; clique abre o drawer único. Janela: −7d..+14d, linha do hoje. ───
const FJ_G_DIAS = 22, FJ_G_INI = -7;
function fjGanttRange(i, shift) {
  const hash = String(i.id).split("").reduce((a, c) => a + c.charCodeAt(0), 0);
  const j = hash % 3;
  const st = i.exec || "backlog";
  let s, e;
  if (st === "done") { s = -7 + j; e = -2; }
  else if (st === "doing") { s = -3 - j; e = 2 + j; }
  else if (st === "review") { s = -4; e = -1 + j; }
  else if (st === "blocked") { s = -2; e = 4 + j; }
  else if (st === "todo") { s = 1 + j; e = 5 + j; }
  else { s = 5 + j; e = 9 + j; }
  return { s, e: e + (shift || 0) };
}
function FjGanttView({ issues, onOpen }) {
  const [shift, setShift] = useStateG({});
  const [drag, setDrag] = useStateG(null);
  const [toast, setToast] = useStateG(null);
  const hoje = new Date(); hoje.setHours(0, 0, 0, 0);
  const dias = Array.from({ length: FJ_G_DIAS }, (_, k) => { const d = new Date(hoje.getTime() + (FJ_G_INI + k) * 86400000); return { k, d, dow: d.getDay(), dd: d.getDate() }; });
  const grupos = useMemoG(() => {
    const m = {};
    issues.forEach(i => { (m[i.modulo] = m[i.modulo] || []).push(i); });
    const ent = Object.entries(m).map(([mod, list]) => {
      const sorted = [...list].sort((a, b) => fjGanttRange(a, shift[a.id] || 0).s - fjGanttRange(b, shift[b.id] || 0).s);
      const prox = Math.min(...list.filter(i => (i.exec || "backlog") !== "done").map(i => fjGanttRange(i, shift[i.id] || 0).e).concat([99]));
      return [mod, sorted, prox];
    });
    return ent.sort((a, b) => a[2] - b[2]);
  }, [issues, shift]);
  const fds = dias.filter(x => x.dow === 0 || x.dow === 6);
  const FdsSpans = () => fds.map(x => <span key={x.k} className="fj-g-fds" style={{ left: (x.k / FJ_G_DIAS * 100) + "%", width: (100 / FJ_G_DIAS) + "%" }}/>);
  const pct = (v) => ((v - FJ_G_INI) / FJ_G_DIAS * 100);
  const fmt = (d) => String(d.getDate()).padStart(2, "0") + "/" + String(d.getMonth() + 1).padStart(2, "0");
  const onDown = (e, i) => { e.preventDefault(); const track = e.currentTarget.closest(".fj-g-track"); setDrag({ id: i.id, x0: e.clientX, pxDia: track.clientWidth / FJ_G_DIAS, d: 0 }); };
  useEffectG(() => {
    if (!drag) return;
    const mv = (e) => setDrag(g => g && ({ ...g, d: Math.round((e.clientX - g.x0) / g.pxDia) }));
    const up = () => { setDrag(g => { if (g && g.d) { setShift(s => ({ ...s, [g.id]: (s[g.id] || 0) + g.d })); const base = issues.find(x => x.id === g.id) || { id: g.id }; const r = fjGanttRange(base, (shift[g.id] || 0) + g.d); const nd = new Date(hoje.getTime() + r.e * 86400000); setToast(g.id + " · prazo → " + fmt(nd) + " (proposta — registra mcp_task_events)"); setTimeout(() => setToast(null), 3500); } return null; }); };
    window.addEventListener("mousemove", mv); window.addEventListener("mouseup", up);
    return () => { window.removeEventListener("mousemove", mv); window.removeEventListener("mouseup", up); };
  }, [drag, issues, shift]);
  return (
    <div className="fj-gantt">
      <p className="fj-quadro-ancora"><b>O que vence esta semana e o que está bloqueando o quê.</b> Barras por módulo, progresso pelo status; arraste a barra pra reagendar <b>o prazo</b> (só o prazo — início é do ciclo de vida). Clique abre o detalhe.</p>
      <div className="fj-g-scale"><span className="fj-g-lbl"/><div className="fj-g-days">{dias.map(x => <span key={x.k} className={"fj-g-day" + (x.dow === 0 || x.dow === 6 ? " fds" : "") + (x.dow === 1 ? " seg" : "") + (x.k === -FJ_G_INI ? " hoje" : "")} title={x.d.toLocaleDateString("pt-BR")}>{x.dd}</span>)}</div></div>
      <div className="fj-g-body">
        {grupos.map(([mod, list]) => {
          const rs = list.map(i => fjGanttRange(i, shift[i.id] || 0));
          const mn = Math.min(...rs.map(r => r.s)), mx = Math.max(...rs.map(r => r.e));
          return (
            <div key={mod} className="fj-g-grupo">
              <div className="fj-g-row sum"><span className="fj-g-lbl">{mod}<b className="fj-g-n">{list.length}</b></span><div className="fj-g-track"><FdsSpans/><span className="fj-g-hoje" style={{ left: pct(0) + "%" }}/><div className="fj-g-bar sum" style={{ left: pct(Math.max(mn, FJ_G_INI)) + "%", width: (Math.min(mx, FJ_G_INI + FJ_G_DIAS) - Math.max(mn, FJ_G_INI)) / FJ_G_DIAS * 100 + "%" }}/></div></div>
              {list.map(i => {
                const r = fjGanttRange(i, (shift[i.id] || 0) + (drag && drag.id === i.id ? drag.d : 0));
                const st = i.exec || "backlog";
                const prog = st === "done" ? 100 : (st === "doing" || st === "review") ? 50 : 0;
                const atras = r.e < 0 && st !== "done";
                return (
                  <div key={i.id} className="fj-g-row">
                    <span className="fj-g-lbl" title={i.titulo}><span className="fj-prio-dot" style={{ background: "oklch(0.6 0.18 " + FJ_PRIO[i.prio].hue + ")" }}/><span className="fj-id">{i.id}</span><span className="fj-g-t">{i.titulo}</span></span>
                    <div className="fj-g-track">
                      <FdsSpans/>
                      <span className="fj-g-hoje" style={{ left: pct(0) + "%" }}/>
                      {drag && drag.id === i.id && <span className="fj-g-drag-tip" style={{ left: Math.min(92, pct(Math.min(r.e, FJ_G_INI + FJ_G_DIAS))) + "%" }}>prazo → {fmt(new Date(hoje.getTime() + r.e * 86400000))}</span>}
                      <button className={"fj-g-bar" + (atras ? " atrasada" : "") + (drag && drag.id === i.id ? " dragging" : "")} style={{ left: pct(Math.max(r.s, FJ_G_INI)) + "%", width: Math.max(2.2, (Math.min(r.e, FJ_G_INI + FJ_G_DIAS) - Math.max(r.s, FJ_G_INI)) / FJ_G_DIAS * 100) + "%", "--gh": FJ_PRIO[i.prio].hue }}
                        onMouseDown={(e) => onDown(e, i)} onClick={() => { if (!drag) onOpen(i.id); }}
                        title={i.id + " · " + i.titulo + " · prazo " + fmt(new Date(hoje.getTime() + r.e * 86400000)) + (i.bloqueado_por.length ? " · bloqueada por " + i.bloqueado_por.join(", ") : "")}>
                        <i style={{ width: prog + "%" }}/>
                        {i.bloqueado_por.length > 0 && <FjLockIco/>}
                        <span className="fj-g-bar-t">{i.titulo}</span>
                      </button>
                    </div>
                  </div>
                );
              })}
            </div>
          );
        })}
        {issues.length === 0 && <div className="fj-empty"><p>Sem tarefas no filtro atual.</p></div>}
      </div>
      <div className="fj-totalbar fj-g-foot">
        <span><b>{issues.length}</b> tarefas</span>
        <span className="fj-total-warn"><b>{issues.filter(i => { const r = fjGanttRange(i, shift[i.id] || 0); return r.e < 0 && (i.exec || "backlog") !== "done"; }).length}</b> com prazo vencido</span>
        <span className="fj-g-leg"><i className="lg-prog"/>progresso</span>
        <span className="fj-g-leg"><i className="lg-atr"/>prazo vencido</span>
        <span className="fj-g-leg"><i className="lg-hoje"/>hoje</span>
        <span className="fj-total-hint">arraste a barra = reagendar prazo · clique = detalhe</span>
      </div>
      {toast && <div className="ap-toast">{toast}</div>}
    </div>
  );
}
window.FjGanttView = FjGanttView;
window.fjGanttRange = fjGanttRange;
window.FJ_G_DIAS = FJ_G_DIAS;
window.FJ_G_INI = FJ_G_INI;
