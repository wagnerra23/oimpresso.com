// forja-aprova.jsx — Mesa de Aprovações: funcionários no Claude Code (via MCP) esperando [W].
// Plano do sênior · modificação do júnior · design do artista · proposta de agente — UMA fila, artefato no centro.
const { useState: useStateA, useMemo: useMemoA, useEffect: useEffectA, useRef: useRefA } = React;

const AP_NIVEL = { senior: { lbl: "sênior", hue: 250 }, junior: { lbl: "júnior", hue: 150 }, artista: { lbl: "artista", hue: 320 }, agente: { lbl: "agente", hue: 295 } };
const AP_TIPO = {
  plano: { lbl: "Plano", hue: 250, verbo: "Aprovar plano", regra: "sênior propõe o plano ANTES de tocar código — você aprova o caminho, não o diff" },
  mod: { lbl: "Modificação", hue: 195, verbo: "Aprovar aplicação", regra: "júnior entrega o diff pronto — você aprova a aplicação; merge continua do gate" },
  design: { lbl: "Design", hue: 320, verbo: "Aprovar screenshot (F2)", regra: "artista entrega a tela — seu aprovo É o gate F2 do protocolo" },
  proposta: { lbl: "Proposta", hue: 60, verbo: "Aprovar → backlog", regra: "agente propôs — aprovar promove pro backlog, nada vira oficial sem você" },
};
function ApAvatar({ nome, nivel, size }) {
  const n = AP_NIVEL[nivel] || AP_NIVEL.agente;
  const ini = nome.split(/\s+/).map(p => p[0]).slice(0, 2).join("").toUpperCase();
  return <span className={"ap-av" + (size === "sm" ? " sm" : "")} style={{ "--ah": n.hue }} title={nome + " · " + n.lbl}>{ini}</span>;
}
function ApNivel({ nivel }) { const n = AP_NIVEL[nivel]; return <span className="ap-nivel" style={{ "--ah": n.hue }}>{n.lbl}</span>; }
function ApSpark({ data, hue }) {
  const pts = data.map((d, i) => `${i * (60 / (data.length - 1))},${17 - Math.max(0, Math.min(1, d)) * 15}`).join(" ");
  return <svg className="fj-spark" viewBox="0 0 60 18" preserveAspectRatio="none"><polyline points={pts} fill="none" stroke={`oklch(0.55 0.13 ${hue})`} strokeWidth="1.8" strokeLinejoin="round"/></svg>;
}
function GateChip({ id, estado }) { return <span className={"fj-gate fj-gate-" + estado}><span className="fj-gate-dot"/>{id}</span>; }

function AoVivo() {
  const V = window.FORJA.AO_VIVO;
  const st = { executando: { lbl: "executando", cls: "run" }, aguardando: { lbl: "espera você", cls: "wait" }, offline: { lbl: "offline", cls: "off" } };
  return (
    <div className="ap-vivo">
      <span className="ap-vivo-lbl">Ao vivo no MCP</span>
      {V.map(p => (
        <div key={p.pessoa} className={"ap-vivo-card " + st[p.status].cls} title={p.fazendo}>
          <ApAvatar nome={p.pessoa} nivel={p.nivel}/>
          <div className="ap-vivo-tx">
            <span className="ap-vivo-nome"><span className="ap-vivo-n-tx">{p.pessoa}</span><ApNivel nivel={p.nivel}/></span>
            <span className="ap-vivo-fazendo">{p.fazendo}</span>
          </div>
          <div className="ap-vivo-meta">
            <span className={"ap-vivo-st " + st[p.status].cls}><i/>{st[p.status].lbl}</span>
            <span className="ap-vivo-custo">{p.custoHoje} hoje · {p.ha}</span>
          </div>
        </div>
      ))}
    </div>
  );
}

function ArtefatoPlano({ it }) {
  return (
    <div className="ap-art">
      <p className="ap-art-obj">{it.objetivo}</p>
      <h4>Passos · {it.passos.length}</h4>
      <ol className="ap-passos">{it.passos.map((p, i) => <li key={i}><span className="ap-passo-n">{i + 1}</span><span>{p.t}</span>{p.tier0 && <span className="ap-tier0">Tier 0</span>}</li>)}</ol>
      <h4>Escopo · {it.escopo.length} arquivos</h4>
      <ul className="ap-files">{it.escopo.map(f => <li key={f} className="mono">{f}</li>)}</ul>
      <div className="ap-art-meta"><span>risco <b>{it.risco}</b></span><span>custo estimado <b>{it.custoEst}</b></span><span>sessão <b className="mono">{it.sessao}</b></span></div>
    </div>
  );
}
function ArtefatoMod({ it }) {
  const add = it.arquivos.reduce((a, f) => a + f.add, 0), del = it.arquivos.reduce((a, f) => a + f.del, 0);
  return (
    <div className="ap-art">
      <p className="ap-art-obj">{it.resumo}</p>
      <h4>Diff · {it.arquivos.length} arquivos <span className="ap-diff-n"><b className="add">+{add}</b> <b className="del">−{del}</b></span></h4>
      <ul className="ap-files">{it.arquivos.map(f => <li key={f.f}><span className="mono">{f.f}</span><span className="ap-file-d"><b className="add">+{f.add}</b> <b className="del">−{f.del}</b></span></li>)}</ul>
      <h4>Gates</h4>
      <div className="ap-gates">{Object.entries(it.gates).map(([g, e]) => <GateChip key={g} id={g} estado={e}/>)}</div>
      <div className="ap-art-meta"><span>sessão <b className="mono">{it.sessao}</b></span>{it.vinculo && <span>issue <b className="mono">{it.vinculo}</b></span>}</div>
    </div>
  );
}
function ArtefatoDesign({ it, onZoom }) {
  return (
    <div className="ap-art">
      <p className="ap-art-obj">{it.nota}</p>
      <button className="ap-shot" onClick={() => onZoom(it.img)} title="Ampliar"><img src={it.img} alt={it.tela}/></button>
      <div className="ap-art-meta"><span>tela <b>{it.tela}</b></span><span>seu aprovo = <b>gate F2</b></span></div>
    </div>
  );
}
function ArtefatoProposta({ it, onDossie }) {
  return (
    <div className="ap-art">
      <p className="ap-art-obj">{it.issue.desc}</p>
      <div className="ap-art-meta"><span>módulo <b>{it.modulo}</b></span><span>prio sugerida <b>{it.issue.prio}</b></span><span>origem <b className="mono">{it.issue.origem}</b></span></div>
      <button className="os-btn ghost" onClick={() => onDossie(it.issue.id)}>Dossiê completo do analista →</button>
    </div>
  );
}

function ForjaAprovacoes({ triagem, onTriage, onAprovarProposta, onRejeitarProposta, onDesfazerProposta, onGoHandoffs }) {
  const base = window.FORJA.APROVACOES;
  const items = useMemoA(() => [
    ...base,
    ...triagem.map(t => ({ id: t.id, tipo: "proposta", pessoa: "Claude Analista", nivel: "agente", titulo: t.titulo, modulo: t.modulo, espera: "agora", esperaMin: 0, issue: t })),
  ], [triagem]);
  const [decid, setDecid] = useStateA({});
  const fila = useMemoA(() => items.filter(i => !decid[i.id]), [items, decid]);
  const [selId, setSelId] = useStateA(null);
  const sel = fila.find(i => i.id === selId) || fila[0] || null;
  const [devolvendo, setDevolvendo] = useStateA(false);
  const [nota, setNota] = useStateA("");
  const [toast, setToast] = useStateA(null);
  const [undo, setUndo] = useStateA(null);
  const undoTimer = useRefA(null);
  const [zoom, setZoom] = useStateA(null);
  const notaRef = useRefA(null);
  useEffectA(() => { setDevolvendo(false); setNota(""); }, [sel && sel.id]);
  const decide = (acao) => {
    if (!sel) return;
    const idx = fila.indexOf(sel);
    if (sel.tipo === "proposta") { if (acao === "aprovado" && onAprovarProposta) onAprovarProposta(sel.issue.id); else if (acao === "rejeitado" && onRejeitarProposta) onRejeitarProposta(sel.issue.id); }
    setDecid(d => ({ ...d, [sel.id]: acao }));
    const msg = { aprovado: AP_TIPO[sel.tipo].verbo + " ✓ — devolvido à sessão", devolvido: "devolvido com comentário pra " + sel.pessoa, rejeitado: "rejeitado — " + sel.pessoa + " notificado" }[acao];
    setToast(sel.id + " · " + msg);
    setUndo({ item: sel, acao });
    if (undoTimer.current) clearTimeout(undoTimer.current);
    undoTimer.current = setTimeout(() => { setToast(null); setUndo(null); }, 6000);
    const next = fila[idx + 1] || fila[idx - 1];
    setSelId(next ? next.id : null);
    setDevolvendo(false); setNota("");
  };
  const desfazer = () => {
    if (!undo) return;
    const { item, acao } = undo;
    if (item.tipo === "proposta" && (acao === "aprovado" || acao === "rejeitado") && onDesfazerProposta) onDesfazerProposta(item.issue.id);
    setDecid(d => { const n = { ...d }; delete n[item.id]; return n; });
    setSelId(item.id); setToast(null); setUndo(null);
    if (undoTimer.current) clearTimeout(undoTimer.current);
  };
  useEffectA(() => {
    const onKey = (e) => {
      const t = e.target; if (t && (t.tagName === "INPUT" || t.tagName === "TEXTAREA" || t.isContentEditable)) return;
      if (zoom) { if (e.key === "Escape") setZoom(null); return; }
      if (!fila.length) return;
      const idx = sel ? fila.indexOf(sel) : 0;
      if (e.key === "j") { e.preventDefault(); setSelId(fila[Math.min(fila.length - 1, idx + 1)].id); }
      else if (e.key === "k") { e.preventDefault(); setSelId(fila[Math.max(0, idx - 1)].id); }
      else if (e.key === "a") { e.preventDefault(); decide("aprovado"); }
      else if (e.key === "x") { e.preventDefault(); decide("rejeitado"); }
      else if (e.key === "d") { e.preventDefault(); setDevolvendo(true); setTimeout(() => notaRef.current && notaRef.current.focus(), 30); }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [fila, sel, zoom]);

  const brl = (v) => "R$ " + v.toFixed(2).replace(".", ",");
  const AG = window.FORJA.AGENT_STATS;
  const semSinal = AG.filter(a => !a.hbOk).length;
  const H = window.FORJA.HANDOFFS.filter(h => h.estado === "stale" || h.gateConflito);
  const T = sel && AP_TIPO[sel.tipo];
  return (
    <div className="ap-page">
      <div className="ap-head">
        <div className="fj-hj-n"><b>{fila.length}</b><span>esperando o seu aval</span></div>
        <p className="fj-hj-sub">Sua equipe trabalha no Claude Code conectada ao MCP; nada aplica sem você. Mais antigo primeiro — <kbd>a</kbd> aprova · <kbd>d</kbd> devolve · <kbd>x</kbd> rejeita.</p>
        {H.length > 0 && <button className="ap-handoff-alert" onClick={onGoHandoffs}>{H.length} handoff{H.length > 1 ? "s" : ""} com problema →</button>}
      </div>
      <AoVivo/>
      {fila.length === 0 ? (
        <div className="fj-mcp-card"><div className="fj-hj-zero"><I.check size={22}/><p><b>Fila zerada.</b> Ninguém espera você — a equipe segue executando.</p></div></div>
      ) : (
        <div className="ap-mesa">
          <ul className="ap-fila">
            {fila.map(i => {
              const t = AP_TIPO[i.tipo];
              return (
                <li key={i.id} className={"ap-item" + (sel && sel.id === i.id ? " sel" : "")} onClick={() => setSelId(i.id)}>
                  <ApAvatar nome={i.pessoa} nivel={i.nivel} size="sm"/>
                  <div className="ap-item-tx">
                    <span className="ap-item-top"><b>{i.pessoa}</b><ApNivel nivel={i.nivel}/><span className="ap-tipo" style={{ "--ah": t.hue }}>{t.lbl}</span></span>
                    <span className="ap-item-t">{i.titulo}</span>
                  </div>
                  <span className={"ap-espera" + (i.esperaMin >= 120 ? " bad" : i.esperaMin >= 30 ? " warn" : "")} title={i.esperaMin >= 120 ? "SLA estourado: espera acima de 2h" : i.esperaMin >= 30 ? "esperando há mais de 30 min" : ""}>{i.espera}</span>
                </li>
              );
            })}
          </ul>
          {sel && (
            <section className="ap-painel">
              <header className="ap-p-head">
                <div className="ap-p-head-l">
                  <span className="ap-tipo lg" style={{ "--ah": T.hue }}>{T.lbl}</span>
                  <h2>{sel.titulo}</h2>
                  <p className="ap-p-sub"><b>{sel.pessoa}</b> · {AP_NIVEL[sel.nivel].lbl} · {sel.modulo || sel.tela} · esperando {sel.espera}</p>
                </div>
                <span className="ap-item-id mono">{sel.id}</span>
              </header>
              {sel.tipo === "plano" && <ArtefatoPlano it={sel}/>}
              {sel.tipo === "mod" && <ArtefatoMod it={sel}/>}
              {sel.tipo === "design" && <ArtefatoDesign it={sel} onZoom={setZoom}/>}
              {sel.tipo === "proposta" && <ArtefatoProposta it={sel} onDossie={onTriage}/>}
              <p className="ap-regra">{T.regra}.</p>
              {devolvendo && (
                <div className="ap-devolver">
                  <textarea ref={notaRef} rows={2} value={nota} onChange={e => setNota(e.target.value)} placeholder={"O que " + sel.pessoa + " precisa ajustar…"}/>
                  <button className="os-btn primary" disabled={!nota.trim()} onClick={() => decide("devolvido")}>Devolver</button>
                  <button className="os-btn ghost" onClick={() => setDevolvendo(false)}>Cancelar</button>
                </div>
              )}
              <footer className="ap-acoes">
                <button className="os-btn primary ap-ok" onClick={() => decide("aprovado")}>{T.verbo}<kbd>a</kbd></button>
                <button className="os-btn ghost" onClick={() => { setDevolvendo(true); setTimeout(() => notaRef.current && notaRef.current.focus(), 30); }}>Devolver c/ comentário<kbd>d</kbd></button>
                <button className="os-btn ghost ap-no" onClick={() => decide("rejeitado")}>Rejeitar<kbd>x</kbd></button>
              </footer>
            </section>
          )}
        </div>
      )}
      <section className="fj-mcp-card fj-hj-team">
        <div className="fj-hj-team-head">
          <h3>Equipe de agentes · placar</h3>
          {semSinal > 0 && <span className="fj-hj-team-alert">{semSinal} sem sinal</span>}
          <span className="fj-hj-team-note">cc_sessions + handoffs + gates — medido, nada auto-relatado</span>
        </div>
        <table className="fj-team-tbl">
          <thead><tr><th>Agente</th><th>Sinal</th><th className="num">Sessões hoje</th><th>Custo hoje / quota</th><th>Critique F1.5</th><th className="num">Retrabalho</th><th className="num">Entregas 7d</th><th></th></tr></thead>
          <tbody>
            {AG.map(a => {
              const avg = Math.round(a.critique.reduce((x, y) => x + y, 0) / a.critique.length);
              const pct = Math.min(100, Math.round(a.custoHoje / a.quotaDia * 100));
              const retPct = a.entregas ? Math.round(a.retrabalho / a.entregas * 100) : 0;
              const RB = window.FjRoleBadge;
              return (
                <tr key={a.role} className={a.hbOk ? "" : "warn"}>
                  <td><RB role={a.role} showName/></td>
                  <td><span className={"fj-hb" + (a.hbOk ? "" : " bad")}><span className="fj-hb-dot"/>{a.hbOk ? a.heartbeat : "sem sinal " + a.heartbeat.replace("há ", "")}</span></td>
                  <td className="num mono">{a.sessoesHoje}</td>
                  <td>
                    <div className="fj-quota"><span className="mono">{brl(a.custoHoje)}</span><span className="fj-quota-of">/ {brl(a.quotaDia)}</span></div>
                    <div className="fj-quota-bar"><i style={{ width: pct + "%", background: pct > 85 ? "oklch(0.58 0.18 25)" : pct > 60 ? "oklch(0.62 0.14 65)" : "var(--accent)" }}/></div>
                  </td>
                  <td><span className="fj-crit"><b className={avg < 80 ? "low" : ""}>{avg}</b><ApSpark data={a.critique.map(c => (c - 70) / 30)} hue={avg < 80 ? 55 : 150}/></span></td>
                  <td className="num mono">{a.retrabalho}{a.retrabalho > 0 && <small className="fj-ret-pct"> · {retPct}%</small>}</td>
                  <td className="num mono">{a.entregas}</td>
                  <td className="act">{!a.hbOk ? <button className="os-btn ghost" onClick={onGoHandoffs}>verificar</button> : null}</td>
                </tr>
              );
            })}
          </tbody>
        </table>
        <p className="fj-hj-team-foot">Critique = score do gate F1.5 por handoff · retrabalho = handoff <code>blocked</code> devolvido ao autor · quota BRL = a mesma da tela Equipe (reset 00:00 BRT).</p>
      </section>
      {toast && <div className="ap-toast">{toast}{undo && <button className="ap-undo" onClick={desfazer}>Desfazer</button>}</div>}
      {zoom && <div className="fj-lightbox" onClick={() => setZoom(null)}><img src={zoom} alt="screenshot"/></div>}
    </div>
  );
}
window.ForjaAprovacoes = ForjaAprovacoes;
