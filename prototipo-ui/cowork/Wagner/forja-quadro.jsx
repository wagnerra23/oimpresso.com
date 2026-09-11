// forja-quadro.jsx — Trabalho · Quadro (Kanban, 2 eixos: pipeline de telas × execução).
// Tela da Onda 5 da paridade (_components/TrabalhoQuadro). Extraído de forja-page.jsx.
const { useState: useStateQ, useMemo: useMemoQ, useEffect: useEffectQ, useRef: useRefQ } = React;
// ─── Quadro (Kanban) — colunas por fase · drag = mover fase (proposta) ───
function FjKanbanCard({ issue, onClick, fav, onFav, onDrag }) {
  const prio = FJ_PRIO[issue.prio];
  return (
    <div className="fj-kc" draggable onDragStart={(e) => onDrag(e, issue.id)} onClick={onClick}>
      <div className="fj-kc-top">
        <span className="fj-prio-dot" style={{ background: `oklch(0.6 0.18 ${prio.hue})` }}/>
        <span className="fj-id">{issue.id}</span>
        <FjTypeChip tipo={issue.tipo}/>
        <span className="fj-kc-spacer"/>
        <FjStar on={fav} onClick={() => onFav(issue.id)}/>
      </div>
      <div className="fj-kc-title">{issue.titulo}</div>
      <div className="fj-kc-foot">
        <FjOwnerSeal issue={issue}/>
        {issue.onda && <span className="fj-onda-chip">~{issue.onda}</span>}
        <span className="fj-kc-spacer"/>
        {(issue.bloqueado_por || []).length > 0 && <FjLockIco/>}
        {issue.frescor && <FjFrescorPill issue={issue}/>}
      </div>
    </div>
  );
}
function FjKanbanView({ issues, eixo, onOpen, onMove, onMoveExec, fav, onFav }) {
  const [dragId, setDragId] = useStateQ(null);
  const [over, setOver] = useStateQ(null);
  const onDrag = (e, id) => { e.dataTransfer.effectAllowed = "move"; setDragId(id); };
  const fases = eixo !== "exec";
  const BOARD = ["todo", "doing", "review", "done"];
  const cols = fases ? window.FORJA.PHASES.filter(p => p.id !== "F4") : BOARD.map(id => window.FORJA.STATUS.find(s => s.id === id));
  const pool = fases ? issues.filter(i => i.fase) : issues;
  const fora = fases ? issues.length - pool.length : 0;
  return (
    <div className="fj-quadro-wrap">
      <p className="fj-quadro-ancora">{fases
        ? <React.Fragment><b>O ciclo de vida de cada tela, do brief à acessibilidade.</b> Cada card avança da esquerda pra direita conforme o protocolo formaliza a fase (F0 → F3.5); no merge (F4) ele sai do quadro e vira entrada no changelog.{fora > 0 && <span> <b>{fora}</b> issue(s) sem fase (infra · gate · ADR) vivem no eixo Execução.</span>}</React.Fragment>
        : <React.Fragment><b>Execução de todo o trabalho — visual ou não.</b> As 4 colunas ativas do canon (A fazer → Fazendo → Revisão → Concluído); Backlog e Bloqueada não são colunas — ficam na Lista, via KPI-filtro. Arraste = muda status (registra mcp_task_events).</React.Fragment>}</p>
      <div className="fj-kanban">
        {cols.map(c => {
          const items = pool.filter(i => fases ? i.fase === c.id : (i.exec || "backlog") === c.id);
          return (
            <section key={c.id} className={"fj-kcol" + (over === c.id ? " over" : "")} style={{ "--ph": c.hue }}
                     onDragOver={(e) => { e.preventDefault(); setOver(c.id); }}
                     onDragLeave={() => setOver(o => o === c.id ? null : o)}
                     onDrop={(e) => { e.preventDefault(); if (dragId) (fases ? onMove(dragId, c.id) : onMoveExec(dragId, c.id)); setDragId(null); setOver(null); }}>
              <header className="fj-kcol-head">
                <div className="fj-kcol-top"><span className="fj-kcol-dot"/><b>{fases ? c.id : c.label}</b>{fases && <span className="fj-kcol-lbl">{c.label}</span>}<span className="fj-kcol-count">{items.length}</span></div>
                {fases && <div className="fj-kcol-quem"><FjRoleBadge role={c.owner}/><span className="fj-kcol-faz">{c.faz}</span></div>}
                {fases && <div className="fj-kcol-sai">sai quando: <b>{c.sai}</b></div>}
              </header>
              <div className="fj-kcol-body">
                {items.map(i => <FjKanbanCard key={i.id} issue={i} fav={fav.has(i.id)} onFav={onFav} onDrag={onDrag} onClick={() => onOpen(i.id)}/>)}
                {items.length === 0 && <div className="fj-kcol-empty">arraste aqui</div>}
              </div>
            </section>
          );
        })}
      </div>
    </div>
  );
}
window.FjKanbanCard = FjKanbanCard;
window.FjKanbanView = FjKanbanView;
