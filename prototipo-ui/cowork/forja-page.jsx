// forja-page.jsx — Forja (Etapa 1 + Refinos #1..#4)
// #1 ⌘K·tree·frescor·cheat  #2 MCP·IA RAG·resumir·auto-sugest  #3 editor·re-verificar·comentários·cross-link·trilhas
// #4 Quadro (kanban drag→fase)·favoritos·handoff/release notes·saúde sparkline
// Chrome = roxo canon. Teal --dev só em selos. Tela = projeção do git; escrita = proposta.
const { useState: useStateF, useMemo: useMemoF, useEffect: useEffectF, useRef: useRefF } = React;

const FJ_PRIO = {
  P0: { hue: 25,  label: "P0" }, P1: { hue: 60,  label: "P1" },
  P2: { hue: 295, label: "P2" }, P3: { hue: 250, label: "P3" },
};
const FJ_GROUPS = [
  { id: "onda", label: "Onda" }, { id: "fase", label: "Fase" },
  { id: "assignee", label: "Papel" }, { id: "prio", label: "Prioridade" },
  { id: "modulo", label: "Módulo" },
];

// Filtro DSL: is:p0 · @CL · ~FA-1 · tipo:bug · mod:financeiro · is:inferido
function fjParseQuery(q) {
  const out = { text: [], prio: null, assignee: null, onda: null, tipo: null, modulo: null, fresco: null };
  (q || "").split(/\s+/).forEach(tok => {
    if (!tok) return;
    let m;
    if (m = tok.match(/^is:(p[0-3])$/i)) out.prio = m[1].toUpperCase();
    else if (m = tok.match(/^is:(inferido|lido|sync)$/i)) out.fresco = m[1].toLowerCase();
    else if (m = tok.match(/^@(\w+)$/)) out.assignee = m[1].toUpperCase();
    else if (m = tok.match(/^~(.+)$/)) out.onda = m[1];
    else if (m = tok.match(/^tipo:(\w+)$/i)) out.tipo = m[1].toLowerCase();
    else if (m = tok.match(/^mod:(\w+)$/i)) out.modulo = m[1].toLowerCase();
    else out.text.push(tok.toLowerCase());
  });
  return out;
}

// Regras de automação (toggle persistido) — gateBlock e reverifyF1 têm efeito vivo no avanço
const FJ_RULES = [
  { id: "gateBlock",  label: "Gate vermelho trava o avanço de fase", nota: "e2e/a11y vermelho bloqueia F3.5→F4", live: true },
  { id: "reverifyF1", label: "F1 exige ✓ lido @main antes de avançar", nota: "mecaniza o Portão 1 (Regra 6)", live: true },
  { id: "prMergeF4",  label: "PR merged → move issue p/ F4 (auto)",   nota: "via webhook do round-trip git", live: false },
];

function RoleBadge({ role, showName }) {
  const a = window.FORJA.ACTORS[role];
  if (!a) return null;
  return (
    <span className="fj-role" title={`${a.name} · ${a.kind === "agent" ? "agente " + (a.model||"") : "humano"} — ${a.desc}`}
          style={{ "--rc": a.color }}>
      <span className="fj-role-av" style={{ background: a.color }}>
        {a.kind === "agent"
          ? <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2.4"><rect x="4" y="8" width="16" height="11" rx="2.5"/><path d="M12 4v4M9 13h.01M15 13h.01"/></svg>
          : <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2.4"><circle cx="12" cy="8" r="3.4"/><path d="M5 20c0-3.3 3-5.5 7-5.5s7 2.2 7 5.5"/></svg>}
      </span>
      <span className="fj-role-tag">[{role}]</span>
      {showName && <span className="fj-role-name">{a.name}</span>}
    </span>
  );
}
function PhaseBadge({ fase }) {
  const p = window.FORJA.PHASES.find(x => x.id === fase);
  const hue = p ? p.hue : 250;
  return <span className="fj-phase" style={{ "--ph": hue }}>{fase} <span className="fj-phase-lbl">{p?.label}</span></span>;
}
function TypeChip({ tipo }) {
  const t = window.FORJA.TYPES[tipo];
  return <span className="fj-type" style={{ "--ty": t?.hue || 250 }}>{t?.label || tipo}</span>;
}
// Triagem (F0) — alinhada à tela real shippada (ForjaTriage.tsx): nav J/K + linha
// focada, empty-state com ícone, rodapé explicando a fila. Agente propõe, [W] aprova.
function TriagemView({ issues, onOpen }) {
  const [sel, setSel] = useStateF(issues[0]?.id ?? null);
  useEffectF(() => {
    if (!issues.length) { setSel(null); return; }
    if (!issues.find(i => i.id === sel)) setSel(issues[0].id);
  }, [issues, sel]);
  useEffectF(() => {
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
              <TypeChip tipo={i.tipo}/>
              <span className="fj-title">{i.titulo}</span>
              <span className="fj-mod">{i.modulo}</span>
              <RoleBadge role={i.assignee}/>
              <button className="os-btn primary" onClick={(e) => { e.stopPropagation(); onOpen(i.id); }}><I.search size={12}/>Analisar</button>
            </li>
          ))}
        </ul>
      )}
      <p className="fj-triagem-foot"><I.inbox size={12}/>Fila = <code>mcp_tasks</code> project=FORJA em triagem (sem dono · sem prioridade · ou backlog). Aprovar promove pro backlog; rejeitar cancela. <b>Nada vira oficial sem você confirmar.</b> <span className="fj-jk">J/K navega · Enter abre</span></p>
    </div>
  );
}
function VincChip({ k, v, onClick }) {
  const ic = { adr: "ADR", pr: "PR", sessao: "ses", tela: "tela", issue: "" }[k] || k;
  return <span className={"fj-vinc fj-vinc-" + k + (onClick ? " link" : "")} onClick={onClick ? (e) => { e.stopPropagation(); onClick(); } : undefined}>{ic && <span className="fj-vinc-k">{ic}</span>}{v}</span>;
}
function FrescorPill({ issue, full }) {
  const f = issue.frescor;
  if (f === "lido") return <span className="fj-fresco fj-fresco-lido" title="Lido @main nesta sessão"><I.check size={9}/>{full ? "lido @main" : "@main"}</span>;
  if (f === "inferido") return <span className="fj-fresco fj-fresco-inferido" title="Não verificado contra @main — pode estar stale">⚠ {full ? "não verificado" : "inferido"}</span>;
  return <span className="fj-fresco fj-fresco-sync" title={`Sincronizado há ${issue.frescorDias} dia(s)`}>sync {issue.frescorDias}d</span>;
}
function Star({ on, onClick }) {
  return <button className={"fj-star" + (on ? " on" : "")} onClick={(e) => { e.stopPropagation(); onClick(); }} title={on ? "Desfavoritar" : "Favoritar"} aria-label="Favoritar">
    <svg width="13" height="13" viewBox="0 0 24 24" fill={on ? "currentColor" : "none"} stroke="currentColor" strokeWidth="1.8"><polygon points="12 2.5 15 9 22 9.6 16.5 14.2 18.2 21 12 17.3 5.8 21 7.5 14.2 2 9.6 9 9"/></svg>
  </button>;
}

// ─── Drawer do issue ───
function IssueDrawer({ issue, relations, following, onFollow, rules, onClose, onPatch, onReverify, onComment, onReact, onLink }) {
  const PHASES = window.FORJA.PHASES, GATES = window.FORJA.GATES;
  const curIdx = PHASES.findIndex(p => p.id === issue.fase);
  const prio = FJ_PRIO[issue.prio];
  const nextPhase = PHASES[curIdx + 1];
  const phaseGates = GATES.filter(g => g.fase === issue.fase);
  const redGate = phaseGates.some(g => g.estado === "red");
  const needReverify = rules && rules.reverifyF1 && issue.fase === "F1" && issue.frescor !== "lido";
  const advBlock = (rules && rules.gateBlock && redGate) || needReverify;
  const advWhy = (rules && rules.gateBlock && redGate) ? "gate vermelho" : needReverify ? "exige ✓ lido @main" : null;
  const [editing, setEditing] = useStateF(false);
  const [draft, setDraft] = useStateF(issue.desc);
  const [comment, setComment] = useStateF("");
  const [lightbox, setLightbox] = useStateF(null);
  const [replyTo, setReplyTo] = useStateF(null);
  const [replyText, setReplyText] = useStateF("");
  const [reacted, setReacted] = useStateF(() => new Set());
  useEffectF(() => { setEditing(false); setDraft(issue.desc); setReplyTo(null); setLightbox(null); }, [issue.id]);

  const saveDesc = () => { onPatch(issue.id, { desc: draft, proposto: true }, { ator: "W", t: "descrição editada (proposta)", quando: "agora" }); setEditing(false); };
  const toggleSub = (i) => { const subs = issue.subtarefas.map((s, j) => j === i ? { ...s, done: !s.done } : s); onPatch(issue.id, { subtarefas: subs, proposto: true }); };
  const submitComment = () => { if (!comment.trim()) return; onComment(issue.id, comment.trim()); setComment(""); };
  const submitReply = () => { if (!replyText.trim()) return; onComment(issue.id, replyText.trim(), replyTo); setReplyText(""); setReplyTo(null); };
  const react = (cid) => { if (!cid || reacted.has(cid)) return; setReacted(s => new Set(s).add(cid)); onReact(issue.id, cid); };
  const addAnexo = (name, url) => onPatch(issue.id, { anexos: [...(issue.anexos || []), { name, url }] }, { ator: "W", t: "anexou " + name, quando: "agora" });
  const onFile = (e) => { const f = e.target.files && e.target.files[0]; if (!f) return; const r = new FileReader(); r.onload = () => addAnexo(f.name, r.result); r.readAsDataURL(f); e.target.value = ""; };
  const onPaste = (e) => { const items = (e.clipboardData && e.clipboardData.items) || []; for (const it of items) { if (it.type && it.type.indexOf("image") === 0) { const f = it.getAsFile(); const r = new FileReader(); r.onload = () => addAnexo("colado-" + Date.now() + ".png", r.result); r.readAsDataURL(f); } } };
  const renderMentions = (t) => (t || "").split(/(@[A-Za-z0-9]+|\[[A-Za-z0-9]+\])/).map((part, i) => {
    const m = part.match(/^@([A-Za-z0-9]+)$/) || part.match(/^\[([A-Za-z0-9]+)\]$/);
    const role = m && m[1].toUpperCase();
    if (role && window.FORJA.ACTORS[role]) return <span key={i} className="fj-mention">[{role}]</span>;
    return part;
  });
  const comments = issue.atividade.filter(a => a.comment);
  const nonComments = issue.atividade.filter(a => !a.comment);
  const roots = comments.filter(c => !c.replyTo);
  const repliesOf = (cid) => comments.filter(c => c.replyTo === cid);
  const rx = issue.reactions || {};

  return (
    <div className="fj-drawer-back" onClick={onClose}>
      <aside className="fj-drawer" onClick={(e) => e.stopPropagation()} onPaste={onPaste}>
        <header className="fj-dr-head">
          <div className="fj-dr-head-l">
            <span className="fj-dr-id">{issue.id}</span>
            <div className="fj-dr-chips">
              <TypeChip tipo={issue.tipo}/>
              <span className="fj-prio-chip" style={{ "--pc": prio.hue }}>{prio.label}</span>
              {issue.onda && <span className="fj-onda-chip">~{issue.onda}</span>}
              <FrescorPill issue={issue} full/>
            </div>
          </div>
          <div className="fj-dr-head-r">
            <button className={"fj-follow" + (following ? " on" : "")} onClick={() => onFollow(issue.id)} title={following ? "Seguindo" : "Seguir"}>{following ? "seguindo" : "+ seguir"}</button>
            <button className="icon-btn" onClick={onClose} aria-label="Fechar"><I.x size={14}/></button>
          </div>
        </header>

        <div className="fj-dr-body">
          {issue.proposto && <div className="fj-proposto-banner">⚠ Alterações não-salvas = <b>proposta</b>. Vira patch espelho + transporte zero-toque ([W] aplica). A tela nunca grava no git.</div>}
          <h2 className="fj-dr-title">{issue.titulo}</h2>

          {!editing ? (
            <div className="fj-dr-desc-row">
              <p className="fj-dr-desc">{issue.desc}</p>
              <button className="fj-mini-edit" onClick={() => setEditing(true)} title="Editar descrição"><I.pencil size={11}/></button>
            </div>
          ) : (
            <div className="fj-dr-edit">
              <textarea value={draft} onChange={e => setDraft(e.target.value)} rows={4}/>
              <div className="fj-dr-edit-act">
                <button className="os-btn ghost" onClick={() => { setEditing(false); setDraft(issue.desc); }}>Cancelar</button>
                <button className="os-btn primary" onClick={saveDesc}>Salvar (proposta)</button>
              </div>
            </div>
          )}

          <div className="fj-reverify">
            <FrescorPill issue={issue} full/>
            {issue.frescor !== "lido"
              ? <button className="fj-reverify-btn" onClick={() => onReverify(issue.id)}><I.check size={11}/>Conferir contra @main</button>
              : <span className="fj-reverify-ok">conferido nesta sessão · Portão 1 ✓</span>}
          </div>

          <div className="fj-dr-sec">
            <h3>Fase · F0→F4</h3>
            <div className="fj-phasebar">
              {PHASES.map((p, i) => (
                <div key={p.id} className={"fj-phstep" + (i < curIdx ? " done" : i === curIdx ? " cur" : "")} style={{ "--ph": p.hue }} title={`${p.id} ${p.label} · ${p.owner}`}>
                  <span className="fj-phstep-dot">{i < curIdx ? <I.check size={9}/> : null}</span>
                  <span className="fj-phstep-lbl">{p.id}</span>
                </div>
              ))}
            </div>
            {nextPhase && (
              <div className="fj-transition">
                <div className="fj-trans-gates">
                  {phaseGates.length === 0 && <span className="fj-trans-none">sem gate nesta fase</span>}
                  {phaseGates.map(g => (<span key={g.id} className={"fj-gate fj-gate-" + g.estado}><span className="fj-gate-dot"/>{g.id}</span>))}
                </div>
                <div className="fj-trans-adv">
                  <button className="os-btn primary" disabled={advBlock} title={advWhy || ""}>Avançar p/ {nextPhase.id} →</button>
                  {advBlock && <span className="fj-block-why">trava: {advWhy}</span>}
                </div>
              </div>
            )}
          </div>

          <div className="fj-dr-sec">
            <h3>Atribuição</h3>
            <dl className="fj-dr-meta">
              <dt>Responsável</dt><dd><RoleBadge role={issue.assignee} showName/></dd>
              <dt>Módulo</dt><dd>{issue.modulo}</dd>
              <dt>Origem</dt><dd className="mono">{issue.origem}</dd>
              <dt>Atualizado</dt><dd className="mono">{issue.atualizado}</dd>
              {issue.bloqueado_por.length > 0 && (
                <React.Fragment><dt>Bloqueado por</dt><dd>{issue.bloqueado_por.map((b, i) => <VincChip key={i} k="issue" v={b} onClick={() => onLink(b)}/>)}</dd></React.Fragment>
              )}
            </dl>
          </div>

          {issue.subtarefas.length > 0 && (
            <div className="fj-dr-sec">
              <h3>Subtarefas · {issue.subtarefas.filter(s => s.done).length}/{issue.subtarefas.length}</h3>
              <ul className="fj-subtasks">
                {issue.subtarefas.map((s, i) => (
                  <li key={i} className={s.done ? "done" : ""}><button className="fj-check-box" onClick={() => toggleSub(i)}>{s.done && <I.check size={10}/>}</button>{s.t}</li>
                ))}
              </ul>
            </div>
          )}

          {issue.vinculos.length > 0 && (
            <div className="fj-dr-sec">
              <h3>Vínculos</h3>
              <div className="fj-vinc-row">{issue.vinculos.map((v, i) => <VincChip key={i} k={v.k} v={v.v} onClick={() => onLink(v.v)}/>)}</div>
            </div>
          )}

          {relations && (relations.parent || relations.children.length > 0 || relations.bloqueia.length > 0 || relations.relacionados.length > 0 || issue.bloqueado_por.length > 0) && (
            <div className="fj-dr-sec">
              <h3>Relações</h3>
              {relations.parent && <div className="fj-rel-row"><span className="fj-rel-lbl">épico</span><VincChip k="issue" v={relations.parent} onClick={() => onLink(relations.parent)}/></div>}
              {relations.children.length > 0 && (
                <div className="fj-rel-row fj-rel-kids-row"><span className="fj-rel-lbl">sub-issues</span>
                  <div className="fj-rel-kids">{relations.children.map(c => <button key={c.id} className="fj-kid" onClick={() => onLink(c.id)}><span className="fj-id">{c.id}</span><span className="fj-kid-t">{c.titulo}</span><PhaseBadge fase={c.fase}/></button>)}</div>
                </div>
              )}
              {(issue.bloqueado_por.length > 0 || relations.bloqueia.length > 0) && (
                <div className="fj-depgraph">
                  <div className="fj-dep-col">{issue.bloqueado_por.length ? issue.bloqueado_por.map(id => <button key={id} className="fj-dep-node" onClick={() => onLink(id)}>{id}</button>) : <span className="fj-dep-none">—</span>}</div>
                  <span className="fj-dep-arrow">→</span>
                  <div className="fj-dep-node self">{issue.id}</div>
                  <span className="fj-dep-arrow">→</span>
                  <div className="fj-dep-col">{relations.bloqueia.length ? relations.bloqueia.map(id => <button key={id} className="fj-dep-node" onClick={() => onLink(id)}>{id}</button>) : <span className="fj-dep-none">—</span>}</div>
                </div>
              )}
              {relations.relacionados.length > 0 && <div className="fj-rel-row"><span className="fj-rel-lbl">relacionados</span>{relations.relacionados.map(id => <VincChip key={id} k="issue" v={id} onClick={() => onLink(id)}/>)}</div>}
            </div>
          )}

          <div className="fj-dr-sec">
            <h3>Anexos · {(issue.anexos || []).length}</h3>
            <div className="fj-anexos">
              {(issue.anexos || []).map((a, i) => (<button key={i} className="fj-anexo" onClick={() => setLightbox(a.url)} title={a.name}><img src={a.url} alt={a.name}/></button>))}
              <label className="fj-anexo-add"><input type="file" accept="image/*" style={{ display: "none" }} onChange={onFile}/><I.plus size={13}/>anexar</label>
            </div>
            <p className="fj-dr-desc fj-anexo-hint">cole uma imagem (⌘/Ctrl V) ou clique em anexar</p>
          </div>

          {nonComments.length > 0 && (
            <div className="fj-dr-sec">
              <h3>Atividade</h3>
              <ul className="fj-activity">
                {nonComments.map((a, i) => (<li key={i}><RoleBadge role={a.ator}/><span className="fj-act-t">{a.t}</span><span className="fj-act-when">{a.quando}</span></li>))}
              </ul>
            </div>
          )}

          <div className="fj-dr-sec">
            <h3>Comentários · {comments.length}</h3>
            <ul className="fj-comments">
              {roots.map((c, ci) => (
                <li key={c.cid || ci}>
                  <div className="fj-cm"><RoleBadge role={c.ator}/>
                    <div className="fj-cm-body">
                      <span className="fj-cm-t">{renderMentions(c.t)}</span>
                      <div className="fj-cm-foot">
                        <button className="fj-cm-react" onClick={() => react(c.cid)}>▲{rx[c.cid] ? " " + rx[c.cid] : ""}</button>
                        <button className="fj-cm-reply" onClick={() => { setReplyTo(c.cid); setReplyText(""); }}>responder</button>
                        <span className="fj-act-when">{c.quando}</span>
                      </div>
                      {repliesOf(c.cid).map((r, j) => (
                        <div key={j} className="fj-cm reply"><RoleBadge role={r.ator}/><div className="fj-cm-body"><span className="fj-cm-t">{renderMentions(r.t)}</span><span className="fj-act-when">{r.quando}</span></div></div>
                      ))}
                      {replyTo === c.cid && (
                        <div className="fj-comment-box reply"><input autoFocus value={replyText} onChange={e => setReplyText(e.target.value)} onKeyDown={e => e.key === "Enter" && submitReply()} placeholder="Responder…"/><button className="os-btn ghost" onClick={submitReply} disabled={!replyText.trim()}><I.send size={11}/></button></div>
                      )}
                    </div>
                  </div>
                </li>
              ))}
            </ul>
            <div className="fj-comment-box">
              <input value={comment} onChange={e => setComment(e.target.value)} onKeyDown={e => e.key === "Enter" && submitComment()} placeholder="Comentar… use @CC @W pra mencionar"/>
              <button className="os-btn ghost" onClick={submitComment} disabled={!comment.trim()}><I.send size={11}/></button>
            </div>
          </div>
        </div>

        <footer className="fj-dr-foot">
          <button className="os-btn ghost"><I.paperclip size={11}/>Anexar</button>
          <span className="fj-foot-spacer"/>
          <span className="fj-foot-note">projeção do git · escrita = proposta</span>
        </footer>
        {lightbox && <div className="fj-lightbox" onClick={(e) => { e.stopPropagation(); setLightbox(null); }}><img src={lightbox} alt="anexo"/></div>}
      </aside>
    </div>
  );
}

function IssueRow({ issue, active, onClick, fav, onFav, selected, onSelect }) {
  const prio = FJ_PRIO[issue.prio];
  return (
    <div className={"fj-row" + (active ? " active" : "") + (selected ? " sel" : "")} onClick={onClick}>
      <button className={"fj-rowcheck" + (selected ? " on" : "")} onClick={(e) => { e.stopPropagation(); onSelect(issue.id); }} aria-label="Selecionar">{selected && <I.check size={10}/>}</button>
      <span className="fj-prio-dot" style={{ background: `oklch(0.6 0.18 ${prio.hue})` }} title={prio.label}/>
      <span className="fj-id">{issue.id}</span>
      <TypeChip tipo={issue.tipo}/>
      <span className="fj-title">{issue.titulo}</span>
      <span className="fj-row-mid">
        {issue.vinculos.slice(0, 2).map((v, i) => <VincChip key={i} k={v.k} v={v.v}/>)}
        <span className="fj-mod">{issue.modulo}</span>
      </span>
      <FrescorPill issue={issue}/>
      <PhaseBadge fase={issue.fase}/>
      <RoleBadge role={issue.assignee}/>
      <Star on={fav} onClick={() => onFav(issue.id)}/>
    </div>
  );
}

// ─── Quadro (Kanban) — colunas por fase · drag = mover fase (proposta) ───
function KanbanCard({ issue, onClick, fav, onFav, onDrag }) {
  const prio = FJ_PRIO[issue.prio];
  return (
    <div className="fj-kc" draggable onDragStart={(e) => onDrag(e, issue.id)} onClick={onClick}>
      <div className="fj-kc-top">
        <span className="fj-prio-dot" style={{ background: `oklch(0.6 0.18 ${prio.hue})` }}/>
        <span className="fj-id">{issue.id}</span>
        <TypeChip tipo={issue.tipo}/>
        <span className="fj-kc-spacer"/>
        <Star on={fav} onClick={() => onFav(issue.id)}/>
      </div>
      <div className="fj-kc-title">{issue.titulo}</div>
      <div className="fj-kc-foot">
        <RoleBadge role={issue.assignee}/>
        {issue.onda && <span className="fj-onda-chip">~{issue.onda}</span>}
        <span className="fj-kc-spacer"/>
        <FrescorPill issue={issue}/>
      </div>
    </div>
  );
}
function KanbanView({ issues, onOpen, onMove, fav, onFav }) {
  const PHASES = window.FORJA.PHASES;
  const [dragId, setDragId] = useStateF(null);
  const [over, setOver] = useStateF(null);
  const onDrag = (e, id) => { e.dataTransfer.effectAllowed = "move"; setDragId(id); };
  return (
    <div className="fj-kanban">
      {PHASES.map(p => {
        const items = issues.filter(i => i.fase === p.id);
        return (
          <section key={p.id} className={"fj-kcol" + (over === p.id ? " over" : "")} style={{ "--ph": p.hue }}
                   onDragOver={(e) => { e.preventDefault(); setOver(p.id); }}
                   onDragLeave={() => setOver(o => o === p.id ? null : o)}
                   onDrop={(e) => { e.preventDefault(); if (dragId) onMove(dragId, p.id); setDragId(null); setOver(null); }}>
            <header className="fj-kcol-head">
              <span className="fj-kcol-dot"/><b>{p.id}</b><span className="fj-kcol-lbl">{p.label}</span><span className="fj-kcol-count">{items.length}</span>
            </header>
            <div className="fj-kcol-body">
              {items.map(i => <KanbanCard key={i.id} issue={i} fav={fav.has(i.id)} onFav={onFav} onDrag={onDrag} onClick={() => onOpen(i.id)}/>)}
              {items.length === 0 && <div className="fj-kcol-empty">arraste aqui</div>}
            </div>
          </section>
        );
      })}
    </div>
  );
}

// ─── Saúde — métricas acionáveis com sparkline ───
function Spark({ data, hue }) {
  const pts = data.map((d, i) => `${i * (60 / (data.length - 1))},${17 - Math.max(0, Math.min(1, d)) * 15}`).join(" ");
  return <svg className="fj-spark" viewBox="0 0 60 18" preserveAspectRatio="none"><polyline points={pts} fill="none" stroke={`oklch(0.55 0.13 ${hue})`} strokeWidth="1.8" strokeLinejoin="round"/></svg>;
}
function SaudeView({ issues, onDrill, rules, onToggleRule }) {
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
            <div className="fj-metric-mid"><span className="fj-metric-val">{m.val}</span><Spark data={m.spark} hue={m.hue}/></div>
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

function CommandPalette({ commands, onClose }) {
  const [q, setQ] = useStateF("");
  const [i, setI] = useStateF(0);
  const [stack, setStack] = useStateF([]);
  const inputRef = useRefF(null);
  useEffectF(() => { inputRef.current?.focus(); }, []);
  const list = stack.length ? stack[stack.length - 1].children : commands;
  const filtered = useMemoF(() => { const t = q.trim().toLowerCase(); return !t ? list : list.filter(c => (c.label + " " + (c.sub || "")).toLowerCase().includes(t)); }, [q, list]);
  useEffectF(() => { setI(0); }, [q, stack]);
  const pick = (c) => { if (!c) return; if (c.children) { setStack(s => [...s, c]); setQ(""); } else { c.run(); onClose(); } };
  const onKey = (e) => {
    if (e.key === "ArrowDown") { e.preventDefault(); setI(x => Math.min(x + 1, filtered.length - 1)); }
    else if (e.key === "ArrowUp") { e.preventDefault(); setI(x => Math.max(x - 1, 0)); }
    else if (e.key === "Enter") { e.preventDefault(); pick(filtered[i]); }
    else if (e.key === "Backspace" && !q && stack.length) { e.preventDefault(); setStack(s => s.slice(0, -1)); }
    else if (e.key === "Escape") { e.preventDefault(); if (stack.length) setStack(s => s.slice(0, -1)); else onClose(); }
  };
  return (
    <div className="fj-pal-back" onClick={onClose}>
      <div className="fj-pal" onClick={e => e.stopPropagation()}>
        <div className="fj-pal-in">
          <I.search size={14}/>
          {stack.length > 0 && <span className="fj-pal-crumb">{stack[stack.length - 1].label} ›</span>}
          <input ref={inputRef} value={q} onChange={e => setQ(e.target.value)} onKeyDown={onKey} placeholder={stack.length ? "escolha…" : "Buscar issue, ADR, PR, onda — ou uma ação…"}/>
          <kbd>esc</kbd>
        </div>
        <ul className="fj-pal-list">
          {filtered.length === 0 && <li className="fj-pal-empty">Nada encontrado.</li>}
          {filtered.map((c, idx) => (
            <li key={c.id} className={"fj-pal-it" + (idx === i ? " sel" : "")} onMouseEnter={() => setI(idx)} onClick={() => pick(c)}>
              <span className={"fj-pal-kind fj-pal-kind-" + c.kind}>{c.kindLabel}</span>
              <span className="fj-pal-tx"><b>{c.label}</b>{c.sub && <small>{c.sub}</small>}</span>
              {c.children ? <span className="fj-pal-tag">›</span> : (c.tag && <span className="fj-pal-tag">{c.tag}</span>)}
            </li>
          ))}
        </ul>
      </div>
    </div>
  );
}

function CheatSheet({ onClose }) {
  const rows = [
    ["J / K", "navegar issues"], ["↵ / e", "abrir issue"], ["/ ou c", "buscar"],
    ["⌘K", "paleta de comandos"], ["←/→ no grupo", "colapsar / expandir"], ["?", "esta ajuda"], ["Esc", "fechar"],
  ];
  return (
    <div className="fj-pal-back" onClick={onClose}>
      <div className="fj-cheat" onClick={e => e.stopPropagation()}>
        <div className="fj-cheat-head"><b>Atalhos</b><button className="icon-btn" onClick={onClose}><I.x size={13}/></button></div>
        <ul className="fj-cheat-list">
          {rows.map(([k, l], idx) => (<li key={idx}><span className="fj-cheat-keys">{k.split(" ").map((p, j) => p === "/" || p === "ou" ? <span key={j} className="fj-cheat-or">{p}</span> : <kbd key={j}>{p}</kbd>)}</span><span>{l}</span></li>))}
        </ul>
      </div>
    </div>
  );
}

function ChangelogFeed() {
  const [filter, setFilter] = useStateF("all");
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
              <div className="fj-feed-meta"><RoleBadge role={e.autor}/>{e.modulos.map(m => <span key={m} className="fj-mod sm">{m}</span>)}</div>
            </div>
          </li>
        ))}
      </ul>
    </div>
  );
}

function ForjaPage() {
  const [view, setView] = useStateF(() => { try { return localStorage.getItem("oimpresso.forja.view") || "backlog"; } catch (e) { return "backlog"; } });
  const [groupBy, setGroupBy] = useStateF("onda");
  const [query, setQuery] = useStateF("");
  const [sel, setSel] = useStateF(0);
  const [openId, setOpenId] = useStateF(null);
  const [collapsed, setCollapsed] = useStateF({});
  const [palette, setPalette] = useStateF(false);
  const [cheat, setCheat] = useStateF(false);
  const [created, setCreated] = useStateF([]);
  const [patches, setPatches] = useStateF({});
  const [iaPanel, setIaPanel] = useStateF(null);
  const [composer, setComposer] = useStateF(false);
  const [runbook, setRunbook] = useStateF(false);
  const [handoff, setHandoff] = useStateF(null);
  const [favOnly, setFavOnly] = useStateF(false);
  const [healthFilter, setHealthFilter] = useStateF(null);
  const [fav, setFav] = useStateF(() => { try { return new Set(JSON.parse(localStorage.getItem("oimpresso.forja.fav") || "[]")); } catch (e) { return new Set(); } });
  const [dossie, setDossie] = useStateF(null);
  const [notifOpen, setNotifOpen] = useStateF(false);
  const [assigneeFilter, setAssigneeFilter] = useStateF(null);
  const [savedViews, setSavedViews] = useStateF(() => { try { return JSON.parse(localStorage.getItem("oimpresso.forja.views") || "[]"); } catch (e) { return []; } });
  const [follow, setFollow] = useStateF(() => { try { return new Set(JSON.parse(localStorage.getItem("oimpresso.forja.follow") || "[]")); } catch (e) { return new Set(); } });
  const [notifSeen, setNotifSeen] = useStateF(() => { try { return new Set(JSON.parse(localStorage.getItem("oimpresso.forja.seen") || "[]")); } catch (e) { return new Set(); } });
  const [rules, setRules] = useStateF(() => { try { return JSON.parse(localStorage.getItem("oimpresso.forja.rules") || "null") || { gateBlock: true, reverifyF1: true, prMergeF4: false }; } catch (e) { return { gateBlock: true, reverifyF1: true, prMergeF4: false }; } });
  const [selected, setSelected] = useStateF(() => new Set());
  const searchRef = useRefF(null);

  useEffectF(() => { try { localStorage.setItem("oimpresso.forja.view", view); } catch (e) {} }, [view]);
  useEffectF(() => { try { localStorage.setItem("oimpresso.forja.fav", JSON.stringify([...fav])); } catch (e) {} }, [fav]);
  const toggleFav = (id) => setFav(s => { const n = new Set(s); n.has(id) ? n.delete(id) : n.add(id); return n; });
  useEffectF(() => { try { localStorage.setItem("oimpresso.forja.views", JSON.stringify(savedViews)); } catch (e) {} }, [savedViews]);
  const applyView = (v) => { setGroupBy(v.groupBy); setQuery(v.query || ""); setFavOnly(!!v.favOnly); setHealthFilter(v.healthFilter || null); setAssigneeFilter(v.assignee || null); setView("backlog"); };
  const saveView = () => {
    const parts = [{ onda: "por onda", fase: "por fase", assignee: "por papel", prio: "por prio", modulo: "por módulo" }[groupBy]];
    if (favOnly) parts.push("★"); if (assigneeFilter) parts.push("[" + assigneeFilter + "]"); if (healthFilter) parts.push(healthFilter); if (query) parts.push('"' + query + '"');
    setSavedViews(vs => [...vs, { name: parts.join(" · "), groupBy, query, favOnly, healthFilter, assignee: assigneeFilter }]);
  };
  const delView = (idx) => setSavedViews(vs => vs.filter((_, i) => i !== idx));
  useEffectF(() => { try { localStorage.setItem("oimpresso.forja.follow", JSON.stringify([...follow])); } catch (e) {} }, [follow]);
  useEffectF(() => { try { localStorage.setItem("oimpresso.forja.seen", JSON.stringify([...notifSeen])); } catch (e) {} }, [notifSeen]);
  const toggleFollow = (id) => setFollow(s => { const n = new Set(s); n.has(id) ? n.delete(id) : n.add(id); return n; });
  const markSeen = (key) => setNotifSeen(s => new Set(s).add(key));
  useEffectF(() => { try { localStorage.setItem("oimpresso.forja.rules", JSON.stringify(rules)); } catch (e) {} }, [rules]);
  const toggleRule = (id) => setRules(r => ({ ...r, [id]: !r[id] }));
  const toggleSel = (id) => setSelected(s => { const n = new Set(s); n.has(id) ? n.delete(id) : n.add(id); return n; });
  const bulkPhase = (fase) => { selected.forEach(id => moveFase(id, fase)); setSelected(new Set()); };
  const bulkFav = () => { setFav(s => { const n = new Set(s); selected.forEach(id => n.add(id)); return n; }); setSelected(new Set()); };

  const ISSUES = useMemoF(() => {
    return [...created, ...window.FORJA.ISSUES].map(i => {
      const p = patches[i.id];
      if (!p) return i;
      return { ...i, ...p, subtarefas: p.subtarefas || i.subtarefas, atividade: [...(p.atividade || []), ...i.atividade] };
    });
  }, [created, patches]);

  const open = useMemoF(() => ISSUES.find(i => i.id === openId) || null, [openId, ISSUES]);

  const backlogIssues = useMemoF(() => ISSUES.filter(i => (i.estado || "backlog") === "backlog"), [ISSUES]);  const triagemIssues = useMemoF(() => ISSUES.filter(i => i.estado === "triagem"), [ISSUES]);
  const relations = useMemoF(() => {
    if (!open) return null;
    const bloqueia = ISSUES.filter(i => (i.bloqueado_por || []).includes(open.id)).map(i => i.id);
    const relacionados = ISSUES.filter(i => i.id !== open.id && i.onda && i.onda === open.onda && !(open.bloqueado_por || []).includes(i.id) && !bloqueia.includes(i.id)).map(i => i.id).slice(0, 4);
    const children = (open.children || []).map(id => ISSUES.find(i => i.id === id)).filter(Boolean);
    return { bloqueia, relacionados, parent: open.parent || null, children };
  }, [open, ISSUES]);
  const approveTriagem = (id, prio) => { const part = { estado: "backlog" }; if (prio) part.prio = prio; patchIssue(id, part, { ator: "AN", t: "triado → aprovado p/ backlog" + (prio ? " (" + prio + ")" : ""), quando: "agora" }); setDossie(null); };
  const rejectTriagem = (id) => { patchIssue(id, { estado: "rejeitado" }, { ator: "W", t: "rejeitado na triagem", quando: "agora" }); setDossie(null); };
  const mergeDup = (id, into) => { patchIssue(id, { estado: "merged" }, { ator: "AN", t: "fundido em " + into, quando: "agora" }); setDossie(null); };
  const notifs = useMemoF(() => ({
    triagem: triagemIssues,
    mine: backlogIssues.filter(i => i.assignee === "W"),
    follow: backlogIssues.filter(i => follow.has(i.id)),
    comments: ISSUES.filter(i => i.atividade.some(a => a.comment)).slice(0, 5),
    inferido: backlogIssues.filter(i => i.frescor === "inferido"),
  }), [ISSUES, triagemIssues, backlogIssues, follow]);
  const notifCount = useMemoF(() => {
    let n = 0;
    ["triagem", "mine", "follow", "comments", "inferido"].forEach(sec => notifs[sec].forEach(i => { if (!notifSeen.has(sec + ":" + i.id)) n++; }));
    return n;
  }, [notifs, notifSeen]);
  const markAllSeen = () => { const keys = []; ["triagem", "mine", "follow", "comments", "inferido"].forEach(sec => notifs[sec].forEach(i => keys.push(sec + ":" + i.id))); setNotifSeen(s => { const n = new Set(s); keys.forEach(k => n.add(k)); return n; }); };

  const patchIssue = (id, partial, act) => setPatches(p => { const cur = p[id] || {}; const next = { ...cur, ...partial }; if (act) next.atividade = [act, ...(cur.atividade || [])]; return { ...p, [id]: next }; });
  const reverify = (id) => patchIssue(id, { frescor: "lido" }, { ator: "W", t: "✓ conferido contra @main nesta sessão", quando: "agora" });
  const addComment = (id, text, replyTo) => patchIssue(id, {}, { ator: "W", t: text, quando: "agora", comment: true, cid: "c" + Date.now() + Math.floor(Math.random() * 999), replyTo: replyTo || null });
  const react = (id, cid) => setPatches(p => { const cur = p[id] || {}; const rxn = { ...(cur.reactions || {}) }; rxn[cid] = (rxn[cid] || 0) + 1; return { ...p, [id]: { ...cur, reactions: rxn } }; });
  const moveFase = (id, fase) => patchIssue(id, { fase, proposto: true }, { ator: "W", t: "movido p/ " + fase + " (proposta)", quando: "agora" });
  const resolveLink = (val) => {
    const iss = ISSUES.find(i => i.id === val);
    if (iss) { setView("backlog"); setOpenId(val); return; }
    const log = window.FORJA.CHANGELOG.find(e => e.ref.includes(val) || val.includes(e.ref.replace(/[^0-9]/g, "")));
    if (log) { setOpenId(null); setView("changelog"); return; }
    setOpenId(null); setIaPanel({ mode: "ask" });
  };
  const drill = (kind) => { setHealthFilter(kind); setFavOnly(false); setView("backlog"); };

  const filtered = useMemoF(() => {
    const Q = fjParseQuery(query);
    let arr = backlogIssues.filter(i => {
      if (Q.prio && i.prio !== Q.prio) return false;
      if (Q.assignee && i.assignee !== Q.assignee) return false;
      if (Q.onda && (i.onda || "") !== Q.onda) return false;
      if (Q.tipo && i.tipo !== Q.tipo) return false;
      if (Q.fresco && i.frescor !== Q.fresco) return false;
      if (Q.modulo && !i.modulo.toLowerCase().includes(Q.modulo)) return false;
      if (Q.text.length) { const hay = (i.titulo + " " + i.id + " " + i.modulo).toLowerCase(); if (!Q.text.every(t => hay.includes(t))) return false; }
      return true;
    });
    if (assigneeFilter) arr = arr.filter(i => i.assignee === assigneeFilter);
    if (favOnly) arr = arr.filter(i => fav.has(i.id));
    if (healthFilter === "inferido") arr = arr.filter(i => i.frescor === "inferido");
    else if (healthFilter === "blocked") arr = arr.filter(i => i.bloqueado_por.length);
    else if (healthFilter === "p0") arr = arr.filter(i => i.prio === "P0");
    return arr;
  }, [query, backlogIssues, favOnly, fav, healthFilter, assigneeFilter]);

  const groups = useMemoF(() => {
    const key = (i) => groupBy === "onda" ? (i.onda || "Sem onda") : groupBy === "fase" ? i.fase : groupBy === "assignee" ? i.assignee : groupBy === "prio" ? i.prio : i.modulo;
    const map = {};
    filtered.forEach(i => { (map[key(i)] = map[key(i)] || []).push(i); });
    return Object.entries(map);
  }, [filtered, groupBy]);

  const flat = useMemoF(() => groups.flatMap(([g, items]) => collapsed[g] ? [] : items), [groups, collapsed]);
  useEffectF(() => { if (sel > flat.length - 1) setSel(Math.max(0, flat.length - 1)); }, [flat.length]);

  const commands = useMemoF(() => {
    const cmds = [];
    ISSUES.forEach(i => cmds.push({ id: "i-" + i.id, kind: "issue", kindLabel: "issue", label: i.id + " · " + i.titulo, sub: i.modulo + " · " + i.fase, tag: i.prio, run: () => { setView("backlog"); setOpenId(i.id); } }));
    window.FORJA.ONDAS.forEach(o => cmds.push({ id: "o-" + o.id, kind: "onda", kindLabel: "onda", label: "~" + o.id + " · " + o.nome, sub: o.estado, run: () => { setView("backlog"); setGroupBy("onda"); } }));
    window.FORJA.CHANGELOG.forEach(e => cmds.push({ id: "c-" + e.ref, kind: "log", kindLabel: "log", label: e.ref + " · " + e.resumo, sub: e.data, run: () => setView("changelog") }));
    cmds.push({ id: "a-quadro", kind: "acao", kindLabel: "ir", label: "Ir: Quadro (kanban)", run: () => setView("quadro") });
    cmds.push({ id: "a-backlog", kind: "acao", kindLabel: "ir", label: "Ir: Backlog", run: () => setView("backlog") });
    cmds.push({ id: "a-changelog", kind: "acao", kindLabel: "ir", label: "Ir: Changelog", run: () => setView("changelog") });
    cmds.push({ id: "a-mcp", kind: "acao", kindLabel: "ir", label: "Ir: MCP", run: () => setView("mcp") });
    cmds.push({ id: "a-saude", kind: "acao", kindLabel: "ir", label: "Ir: Saúde", run: () => setView("saude") });
    cmds.push({ id: "a-integra", kind: "acao", kindLabel: "ir", label: "Ir: Integrador (Forja ↔ TeamMcp)", run: () => setView("integra") });
    cmds.push({ id: "a-papeis", kind: "acao", kindLabel: "abrir", label: "Trilhas de papel (runbook)", run: () => setRunbook(true) });
    cmds.push({ id: "a-ask", kind: "acao", kindLabel: "IA", label: "Perguntar à memória", run: () => setIaPanel({ mode: "ask" }) });
    FJ_GROUPS.forEach(g => cmds.push({ id: "g-" + g.id, kind: "acao", kindLabel: "agrupar", label: "Agrupar por " + g.label, run: () => { setView("backlog"); setGroupBy(g.id); } }));
    if (open) {
      cmds.push({ id: "ch-fase", kind: "acao", kindLabel: "mover", label: "Mover " + open.id + " p/ fase…", children: window.FORJA.PHASES.map(p => ({ id: "chf-" + p.id, kind: "acao", kindLabel: p.id, label: p.id + " · " + p.label, run: () => moveFase(open.id, p.id) })) });
      cmds.push({ id: "ch-assign", kind: "acao", kindLabel: "atribuir", label: "Atribuir " + open.id + " a…", children: Object.keys(window.FORJA.ACTORS).map(r => ({ id: "cha-" + r, kind: "acao", kindLabel: r, label: "[" + r + "] " + window.FORJA.ACTORS[r].name, run: () => patchIssue(open.id, { assignee: r }, { ator: "W", t: "atribuído a [" + r + "]", quando: "agora" }) })) });
    }
    cmds.push({ id: "ch-filter", kind: "acao", kindLabel: "filtrar", label: "Filtrar por papel…", children: Object.keys(window.FORJA.ACTORS).map(r => ({ id: "chx-" + r, kind: "acao", kindLabel: r, label: "[" + r + "]", run: () => { setView("backlog"); setAssigneeFilter(r); } })) });
    return cmds;
  }, [ISSUES, open]);

  useEffectF(() => {
    const onKey = (e) => {
      const mod = e.metaKey || e.ctrlKey;
      if (mod && e.key.toLowerCase() === "k") { e.preventDefault(); setPalette(p => !p); return; }
      const typing = /^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement?.tagName || "");
      if (palette || cheat) return;
      if (e.key === "Escape") { if (openId) { setOpenId(null); return; } }
      if (typing) { if (e.key === "Escape") e.target.blur(); return; }
      if (e.key === "?") { e.preventDefault(); setCheat(true); }
      else if (view === "backlog" && e.key === "j") { e.preventDefault(); setSel(s => Math.min(s + 1, flat.length - 1)); }
      else if (view === "backlog" && e.key === "k") { e.preventDefault(); setSel(s => Math.max(s - 1, 0)); }
      else if (view === "backlog" && (e.key === "Enter" || e.key === "e")) { e.preventDefault(); if (flat[sel]) setOpenId(flat[sel].id); }
      else if (view === "backlog" && e.key === "x") { e.preventDefault(); if (flat[sel]) toggleSel(flat[sel].id); }
      else if (e.key === "/" || e.key === "c") { e.preventDefault(); searchRef.current?.focus(); }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [flat, sel, openId, palette, cheat, view]);

  const totals = useMemoF(() => ({
    n: filtered.length, p0: filtered.filter(i => i.prio === "P0").length,
    blocked: filtered.filter(i => i.bloqueado_por.length > 0).length,
    inferido: filtered.filter(i => i.frescor === "inferido").length,
  }), [filtered]);

  const groupLabel = (g) => {
    if (groupBy === "onda" && g !== "Sem onda") { const o = window.FORJA.ONDAS.find(x => x.id === g); return o ? `${g} · ${o.nome}` : g; }
    if (groupBy === "fase") { const p = window.FORJA.PHASES.find(x => x.id === g); return p ? `${g} ${p.label}` : g; }
    if (groupBy === "assignee") { const a = window.FORJA.ACTORS[g]; return a ? `[${g}] ${a.name}` : g; }
    return g;
  };
  const toggleGroup = (g) => setCollapsed(c => ({ ...c, [g]: !c[g] }));
  const hfLabel = { inferido: "não-verificados", blocked: "bloqueados", p0: "P0" };

  const Toolbar = (
    <div className="fj-toolbar">
      <div className="fj-groupby">
        <span className="fj-groupby-lbl">{view === "quadro" ? "Filtra" : "Agrupar"}</span>
        {view === "backlog" && FJ_GROUPS.map(g => (<button key={g.id} className={"fj-gb-btn" + (groupBy === g.id ? " active" : "")} onClick={() => setGroupBy(g.id)}>{g.label}</button>))}
        <button className={"fj-gb-btn fj-fav-toggle" + (favOnly ? " active" : "")} onClick={() => setFavOnly(f => !f)} title="Só favoritos"><svg className={"fj-fav-glyph" + (favOnly ? " on" : "")} width="13" height="13" viewBox="0 0 24 24" fill={favOnly ? "currentColor" : "none"} stroke="currentColor" strokeWidth="1.8"><polygon points="12 2.5 15 9 22 9.6 16.5 14.2 18.2 21 12 17.3 5.8 21 7.5 14.2 2 9.6 9 9"/></svg>favoritos</button>
        {healthFilter && <button className="fj-fchip" onClick={() => setHealthFilter(null)}>{hfLabel[healthFilter]} ✕</button>}
      </div>
      <button className="fj-ia-btn" onClick={() => setRunbook(true)} title="Trilhas de papel"><I.users size={11}/>Papéis</button>
      <button className="fj-ia-btn" onClick={() => setIaPanel({ mode: "ask" })} title="Perguntar à memória"><span className="fj-ia-spark">✦</span>Perguntar</button>
      <div className="fj-search">
        <I.search size={12}/>
        <input ref={searchRef} placeholder="Buscar…  is:p0 @CL ~FA-1 tipo:bug" value={query} onChange={e => setQuery(e.target.value)}/>
      </div>
    </div>
  );

  const FilterBar = (
    <div className="fj-filterbar2">
      <span className="fj-groupby-lbl">Papel</span>
      <button className={"fj-gb-btn" + (!assigneeFilter ? " active" : "")} onClick={() => setAssigneeFilter(null)}>todos</button>
      {Object.keys(window.FORJA.ACTORS).map(r => (<button key={r} className={"fj-gb-btn" + (assigneeFilter === r ? " active" : "")} onClick={() => setAssigneeFilter(a => a === r ? null : r)}>[{r}]</button>))}
      <span className="fj-fb-sep"/>
      <span className="fj-groupby-lbl">Visões</span>
      {savedViews.map((v, i) => (<button key={i} className="fj-view-chip" onClick={() => applyView(v)} title="aplicar visão">{v.name}<span className="fj-view-x" onClick={(e) => { e.stopPropagation(); delView(i); }}>✕</span></button>))}
      <button className="fj-view-save" onClick={saveView}>+ salvar visão</button>
    </div>
  );

  return (
    <div className="fj-page">
      <div className="os-page-h">
        <div className="os-page-h-l">
          <h1>Forja</h1>
          <p>Cockpit do cowork loop — backlog, quadro F0→F4, changelog e atores (humano vs agente).</p>
        </div>
        <div className="os-page-h-r">
          <button className="fj-bell" onClick={() => setNotifOpen(true)} title="Minha fila">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
            {notifCount > 0 && <span className="fj-bell-badge">{notifCount}</span>}
          </button>
          <button className="fj-kbtn" onClick={() => setPalette(true)} title="Paleta de comandos"><I.search size={11}/>Buscar<kbd>⌘K</kbd></button>
          <div className="fj-viewtabs">
            <button className={view === "triagem" ? "active" : ""} onClick={() => setView("triagem")}><I.inbox size={11}/>Triagem{triagemIssues.length > 0 && <span className="fj-tab-badge">{triagemIssues.length}</span>}</button>
            <button className={view === "backlog" ? "active" : ""} onClick={() => setView("backlog")}><I.list size={11}/>Backlog</button>
            <button className={view === "quadro" ? "active" : ""} onClick={() => setView("quadro")}><I.grid size={11}/>Quadro</button>
            <button className={view === "changelog" ? "active" : ""} onClick={() => setView("changelog")}><I.clock size={11}/>Changelog</button>
            <button className={view === "mcp" ? "active" : ""} onClick={() => setView("mcp")}><I.shield size={11}/>MCP</button>
            <button className={view === "saude" ? "active" : ""} onClick={() => setView("saude")}><I.chart size={11}/>Saúde</button>
            <button className={view === "integra" ? "active" : ""} onClick={() => setView("integra")}><I.plug size={11}/>Integrador</button>
          </div>
          <button className="os-btn primary" onClick={() => setComposer(true)}><I.plus size={11}/>Novo issue</button>
        </div>
      </div>

      {(view === "backlog" || view === "quadro") && Toolbar}
      {view === "backlog" && FilterBar}

      {view === "backlog" && (
        <React.Fragment>
          <div className="fj-list">
            {groups.map(([g, items]) => {
              const isC = !!collapsed[g];
              return (
                <div key={g} className={"fj-group" + (isC ? " collapsed" : "")}>
                  <div className="fj-group-head">
                    <button className="fj-group-toggle" onClick={() => toggleGroup(g)}>
                      <span className="fj-group-chev" style={{ transform: isC ? "rotate(-90deg)" : "none" }}><I.chev size={12}/></span>
                      <span className="fj-group-title">{groupLabel(g)}</span>
                      <span className="fj-group-count">{items.length}</span>
                    </button>
                    {groupBy === "onda" && g !== "Sem onda" && (
                      <button className="fj-group-ia" onClick={() => setIaPanel({ mode: "digest", onda: g })} title="Resumir onda"><span className="fj-ia-spark">✦</span>resumir</button>
                    )}
                  </div>
                  {!isC && items.map(issue => {
                    const idx = flat.indexOf(issue);
                    return <IssueRow key={issue.id} issue={issue} active={idx === sel} fav={fav.has(issue.id)} onFav={toggleFav} selected={selected.has(issue.id)} onSelect={toggleSel} onClick={() => { setSel(idx); setOpenId(issue.id); }}/>;
                  })}
                </div>
              );
            })}
            {filtered.length === 0 && (
              <div className="fj-empty">
                <p>Nenhum issue casa com o filtro.</p>
                <button className="os-btn ghost" onClick={() => setIaPanel({ mode: "ask" })}><span className="fj-ia-spark">✦</span>Perguntar à memória sobre isso</button>
              </div>
            )}
          </div>
          {selected.size > 0 && (
            <div className="fj-bulkbar">
              <span className="fj-bulk-n"><b>{selected.size}</b> selecionados</span>
              <span className="fj-bulk-lbl">mover p/</span>
              {window.FORJA.PHASES.map(p => <button key={p.id} className="fj-bulk-fase" onClick={() => bulkPhase(p.id)}>{p.id}</button>)}
              <button className="fj-bulk-act" onClick={bulkFav}>★ favoritar</button>
              <button className="fj-bulk-act" onClick={() => setSelected(new Set())}>limpar</button>
            </div>
          )}
          <div className="fj-totalbar">
            <span><b>{totals.n}</b> issues</span>
            <span><b>{totals.p0}</b> P0</span>
            <span><b>{totals.blocked}</b> bloqueados</span>
            <span className="fj-total-warn"><b>{totals.inferido}</b> não-verificados</span>
            <span className="fj-total-hint"><kbd>j</kbd><kbd>k</kbd> navega · <kbd>↵</kbd> abre · <kbd>?</kbd> atalhos</span>
          </div>
        </React.Fragment>
      )}

      {view === "quadro" && <KanbanView issues={filtered} onOpen={setOpenId} onMove={moveFase} fav={fav} onFav={toggleFav}/>}
      {view === "changelog" && <ChangelogFeed/>}
      {view === "mcp" && <window.ForjaMCPView/>}
      {view === "saude" && <SaudeView issues={ISSUES} onDrill={drill} rules={rules} onToggleRule={toggleRule}/>}
      {view === "integra" && <window.ForjaIntegrador/>}
      {view === "triagem" && <TriagemView issues={triagemIssues} onOpen={setDossie}/>}

      {open && <IssueDrawer issue={open} relations={relations} following={follow.has(open.id)} onFollow={toggleFollow} rules={rules} onClose={() => setOpenId(null)} onPatch={patchIssue} onReverify={reverify} onComment={addComment} onReact={react} onLink={resolveLink}/>}
      {palette && <CommandPalette commands={commands} onClose={() => setPalette(false)}/>}
      {cheat && <CheatSheet onClose={() => setCheat(false)}/>}
      {iaPanel && <window.ForjaIAPanel mode={iaPanel.mode} onda={iaPanel.onda} onClose={() => setIaPanel(null)} onHandoff={(onda) => { setIaPanel(null); setHandoff(onda); }}/>}
      {composer && <window.ForjaNewIssue onCreate={(iss) => setCreated(c => [iss, ...c])} onClose={() => setComposer(false)}/>}
      {runbook && <window.ForjaRunbook onClose={() => setRunbook(false)}/>}
      {handoff && <window.ForjaHandoff onda={handoff} onClose={() => setHandoff(null)}/>}
      {dossie && <window.ForjaDossie issue={ISSUES.find(i => i.id === dossie)} allIssues={ISSUES} onApprove={approveTriagem} onReject={rejectTriagem} onMerge={mergeDup} onClose={() => setDossie(null)}/>}
      {notifOpen && <window.ForjaNotifs notifs={notifs} seen={notifSeen} onSeen={markSeen} onMarkAll={markAllSeen} onOpen={(id) => { setNotifOpen(false); setView("backlog"); setOpenId(id); }} onTriage={(id) => { setNotifOpen(false); setView("triagem"); setDossie(id); }} onClose={() => setNotifOpen(false)}/>}
    </div>
  );
}

window.FjRoleBadge = RoleBadge;
window.FjFrescorPill = FrescorPill;
window.ForjaPage = ForjaPage;
