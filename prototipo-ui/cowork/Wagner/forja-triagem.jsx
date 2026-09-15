// forja-triagem.jsx — Triagem (F0): fila de propostas com nav J/K, linha focada e empty-state.
// Receptor vivo é o tipo Proposta dentro de Aprovações (§6-bis da paridade); a view fica aqui,
// isolada, para não voltar a viver dentro do shell. Extraído de forja-page.jsx (Onda 1).
const { useState: useStateTg, useMemo: useMemoTg, useEffect: useEffectTg, useRef: useRefTg } = React;
// Triagem (F0) — alinhada à tela real shippada (ForjaTriage.tsx): nav J/K + linha
// focada, empty-state com ícone, rodapé explicando a fila. Agente propõe, [W] aprova.
function FjTriagemView({ issues, onOpen }) {
  const [sel, setSel] = useStateTg(issues[0]?.id ?? null);
  useEffectTg(() => {
    if (!issues.length) { setSel(null); return; }
    if (!issues.find(i => i.id === sel)) setSel(issues[0].id);
  }, [issues, sel]);
  useEffectTg(() => {
    const onKey = (e) => {
      const t = e.target;
      if (t && (t.tagName === "INPUT" || t.tagName === "TEXTAREA" || t.isContentEditable)) return;
      if (!issues.length) return;
      const idx = sel ? issues.findIndex(i => i.id === sel) : -1;
      if (e.key === "j" || e.key === "J") { e.preventDefault(); setSel(issues[Math.min(issues.length - 1, idx < 0 ? 0 : idx + 1)].id); }
      else if (e.key === "k" || e.key === "K") { e.preventDefault(); setSel(issues[idx <= 0 ? 0 : idx - 1].id); }
      else if (e.key === "Enter" && idx >= 0) { e.preventDefault(); onOpen(issues[idx].id); }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [issues, sel, onOpen]);
  return (
    <div className="fj-triagem">
      <div className="fj-mcp-intro">Tickets propostos aguardando o analista <b>[AN]</b> enriquecer e <b>sua aprovação</b>. Entram no backlog só depois — é o F0 do protocolo, formalizado.</div>
      {issues.length === 0 ? (
        <div className="fj-triagem-empty">
          <I.check size={26}/>
          <p className="fj-te-h">Nada pra triar</p>
          <p className="fj-te-s">Nenhuma proposta aguardando enriquecimento e aprovação.</p>
        </div>
      ) : (
        <ul className="fj-triagem-list">
          {issues.map(i => (
            <li key={i.id} className={"fj-triagem-item" + (i.id === sel ? " sel" : "")}
                aria-current={i.id === sel ? "true" : undefined}
                onMouseEnter={() => setSel(i.id)} onClick={() => onOpen(i.id)}>
              <span className="fj-prio-dot" style={{ background: `oklch(0.6 0.18 ${FJ_PRIO[i.prio].hue})` }}/>
              <span className="fj-id">{i.id}</span>
              <FjTypeChip tipo={i.tipo}/>
              <span className="fj-title">{i.titulo}</span>
              <span className="fj-mod">{i.modulo}</span>
              <FjRoleBadge role={i.assignee}/>
              <button className="os-btn primary" onClick={(e) => { e.stopPropagation(); onOpen(i.id); }}><I.search size={12}/>Analisar</button>
            </li>
          ))}
        </ul>
      )}
      <p className="fj-triagem-foot"><I.inbox size={12}/>Fila = <code>mcp_tasks</code> project=FORJA em triagem (sem dono · sem prioridade · ou backlog). Aprovar promove pro backlog; rejeitar cancela. <b>Nada vira oficial sem você confirmar.</b> <span className="fj-jk">J/K navega · Enter abre</span></p>
    </div>
  );
}
window.FjTriagemView = FjTriagemView;
