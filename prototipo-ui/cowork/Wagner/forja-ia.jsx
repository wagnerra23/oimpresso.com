// forja-ia.jsx — painel de IA (slide-over): modo ask (RAG citando fonte) ou digest (resumir onda).
// IA propõe, [W] decide (P4). Extraído de forja-mcp.jsx (Onda 3).
const { useState: useStateIa, useMemo: useMemoIa, useRef: useRefIa, useEffect: useEffectIa } = React;
// ─── IA Panel (slide-over): modo ask (RAG) ou digest (resumir onda) ───
function ForjaIAPanel({ mode, onda, onClose, onHandoff }) {
  const [q, setQ] = useStateIa("");
  const [asked, setAsked] = useStateIa(null);
  const inRef = useRefIa(null);
  useEffectIa(() => { if (mode === "ask") inRef.current?.focus(); }, [mode]);

  const digest = useMemoIa(() => {
    if (mode !== "digest") return null;
    const F = window.FORJA;
    const o = F.ONDAS.find(x => x.id === onda);
    const issues = F.ISSUES.filter(i => i.onda === onda);
    const byFase = {}; issues.forEach(i => byFase[i.fase] = (byFase[i.fase] || 0) + 1);
    const blockers = issues.filter(i => i.bloqueado_por.length);
    const shipped = F.CHANGELOG.filter(e => e.ref.includes(onda) || (o && e.resumo.toLowerCase().includes((o.milestone||"").toLowerCase().split(" ")[0])));
    return { o, issues, byFase, blockers, shipped };
  }, [mode, onda]);

  const ask = () => setAsked(forjaRag(q));
  const onKey = (e) => { if (e.key === "Enter") { e.preventDefault(); ask(); } if (e.key === "Escape") onClose(); };
  const KindBadge = ({ k }) => <span className={"fj-rag-kind fj-rag-kind-" + k}>{ {adr:"ADR", log:"shippou", issue:"issue", onda:"onda"}[k] || k }</span>;

  return (
    <div className="fj-drawer-back" onClick={onClose}>
      <aside className="fj-drawer fj-ia" onClick={e => e.stopPropagation()}>
        <header className="fj-dr-head">
          <div className="fj-dr-head-l">
            <span className="fj-ia-spark">✦</span>
            <span className="fj-dr-id">{mode === "ask" ? "Perguntar à memória" : "Resumir onda " + onda}</span>
          </div>
          <button className="icon-btn" onClick={onClose} aria-label="Fechar"><I.x size={14}/></button>
        </header>
        <div className="fj-dr-body">
          {mode === "ask" && (
            <React.Fragment>
              <p className="fj-dr-desc">IA grounded no corpus (ADRs · changelog · backlog). Cita fonte, nunca inventa — anti-reinvenção (Regra 7).</p>
              <div className="fj-ia-ask">
                <I.search size={13}/>
                <input ref={inRef} value={q} onChange={e => setQ(e.target.value)} onKeyDown={onKey}
                       placeholder="Ex.: já decidimos a cor do accent? gate de cor crua existe?"/>
                <button className="os-btn primary" onClick={ask}>Perguntar</button>
              </div>
              {asked && (
                <div className="fj-dr-sec" style={{ marginTop: 8 }}>
                  <div className={"fj-rag-verdict fj-rag-" + asked.kind}>{asked.verdict}</div>
                  {asked.sources.length > 0 && (
                    <React.Fragment>
                      <h3>Fontes ({asked.sources.length})</h3>
                      <ul className="fj-rag-src">
                        {asked.sources.map((s, i) => (
                          <li key={i}><KindBadge k={s.kind}/><span className="fj-rag-ref">{s.ref}</span><span className="fj-rag-lbl">{s.label}</span><span className="fj-rag-when">{s.when}</span></li>
                        ))}
                      </ul>
                    </React.Fragment>
                  )}
                </div>
              )}
            </React.Fragment>
          )}

          {mode === "digest" && digest && (
            <React.Fragment>
              <p className="fj-dr-desc">{digest.o ? digest.o.nome : onda} · {digest.o?.estado} · janela {digest.o?.janela}. Resumo computado do backlog real (não inventado).</p>
              <div className="fj-dr-sec">
                <h3>Progresso · {digest.issues.length} issues</h3>
                <div className="fj-digest-fases">
                  {window.FORJA.PHASES.map(p => digest.byFase[p.id] ? (
                    <span key={p.id} className="fj-digest-fase" style={{ "--ph": p.hue }}>{p.id} <b>{digest.byFase[p.id]}</b></span>
                  ) : null)}
                </div>
              </div>
              <div className="fj-dr-sec">
                <h3>Bloqueios · {digest.blockers.length}</h3>
                {digest.blockers.length === 0 ? <p className="fj-dr-desc">Sem bloqueios nesta onda.</p> :
                  <ul className="fj-subtasks">{digest.blockers.map(b => <li key={b.id}><span className="fj-check-box" style={{borderColor:"var(--neg, oklch(0.58 0.21 25))"}}/>{b.id} — bloqueado por {b.bloqueado_por.join(", ")}</li>)}</ul>}
              </div>
              <div className="fj-dr-sec">
                <h3>Já shippou ({digest.shipped.length})</h3>
                {digest.shipped.length === 0 ? <p className="fj-dr-desc">Nada fechado ainda nesta onda.</p> :
                  <ul className="fj-rag-src">{digest.shipped.map((e,i) => <li key={i}><span className="fj-rag-ref">{e.ref}</span><span className="fj-rag-lbl">{e.resumo}</span><span className="fj-rag-when">{e.data}</span></li>)}</ul>}
              </div>
              <button className="fj-ia-foot fj-ia-foot-btn" onClick={() => onHandoff && onHandoff(onda)}><span className="fj-ia-spark">✦</span>Gerar release notes + handoff desta onda →</button>
            </React.Fragment>
          )}
        </div>
      </aside>
    </div>
  );
}
window.ForjaIAPanel = ForjaIAPanel;
