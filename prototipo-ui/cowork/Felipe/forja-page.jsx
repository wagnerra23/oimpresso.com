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
  { id: "onda", label: "Onda" }, { id: "frente", label: "Frente" }, { id: "fase", label: "Fase" },
  { id: "assignee", label: "Papel" }, { id: "prio", label: "Prioridade" },
  { id: "modulo", label: "Módulo" },
];

// Rank híbrido (L-1): score automático prio × aging × bloqueio; pin manual trava no topo.
const FJ_PRIO_W = { P0: 400, P1: 300, P2: 200, P3: 100 };
function fjAging(i) { return i.frescor === "inferido" ? 14 : i.frescor === "lido" ? 1 : (i.frescorDias || 0); }
function fjScore(i, bloqueia) {
  let s = FJ_PRIO_W[i.prio] || 100;
  s += Math.min(fjAging(i), 21) * 4;
  s += (bloqueia || 0) * 25;
  if ((i.bloqueado_por || []).length) s -= 140;
  return s;
}
function fjWhyRank(i, bloqueia) {
  const p = [];
  p.push(i.prio);
  if (fjAging(i) > 7) p.push("parado " + fjAging(i) + "d");
  if (bloqueia) p.push("trava " + bloqueia);
  if ((i.bloqueado_por || []).length) p.push("bloqueado ↓");
  return "ordem automática: " + p.join(" · ");
}

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
const IcHoje = ({ size = 11 }) => <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><circle cx="12" cy="12" r="4"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M19.1 4.9L17 7M7 17l-2.1 2.1"/></svg>;
function StatusPill({ s }) {
  const st = window.FORJA.STATUS.find(x => x.id === (s || "backlog")) || { label: s || "—", neutral: true };
  return <span className="fj-exec" title={"Status de execução: " + st.label + " (fora do pipeline de telas)"}><i style={{ background: st.neutral ? "var(--text-mute)" : "oklch(0.6 0.14 " + st.hue + ")" }}/>{st.label}</span>;
}
const FJ_AGENTES_NOME = ["claude-cc", "claude-code", "claude-design", "claude-a11y", "claude-analista"];
function LockIco() { return <svg className="fj-lockico" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-label="bloqueada"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>; }
function OwnerSeal({ issue, showName }) {
  if (issue.assignee && window.FORJA.ACTORS[issue.assignee]) return <RoleBadge role={issue.assignee} showName={showName}/>;
  const o = issue.ownerNome;
  if (!o) return <span className="fj-owner vazio">—</span>;
  const ag = FJ_AGENTES_NOME.includes(o);
  return <span className={"fj-owner" + (ag ? " agente" : "")} title={(ag ? "Agente: " : "Humano: ") + o}>{ag ? <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2"><rect x="4" y="8" width="16" height="11" rx="2.5"/><path d="M12 4v4M9 13h.01M15 13h.01"/></svg> : <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2"><circle cx="12" cy="8" r="3.4"/><path d="M5 20c0-3.3 3-5.5 7-5.5s7 2.2 7 5.5"/></svg>}{o}</span>;
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
              {issue.frescor && <FrescorPill issue={issue} full/>}
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

          {issue.frescor && (
          <div className="fj-reverify">
            <FrescorPill issue={issue} full/>
            {issue.frescor !== "lido"
              ? <button className="fj-reverify-btn" onClick={() => onReverify(issue.id)}><I.check size={11}/>Conferir contra @main</button>
              : <span className="fj-reverify-ok">conferido nesta sessão · Portão 1 ✓</span>}
          </div>
          )}

          {issue.fase ? (
          <div className="fj-dr-sec">
            <h3>Fase · F0→F4 (pipeline de tela)</h3>
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
          ) : (
          <div className="fj-dr-sec">
            <h3>Execução</h3>
            <div className="fj-exec-row"><StatusPill s={issue.exec}/><span className="fj-exec-note">fora do pipeline de telas — infra, gate e ADR seguem o status canon (sem F1.5 Critique / F2 Screenshot)</span></div>
          </div>
          )}

          <div className="fj-dr-sec">
            <h3>Atribuição</h3>
            <dl className="fj-dr-meta">
              <dt>Responsável</dt><dd><OwnerSeal issue={issue} showName/></dd>
              <dt>Módulo</dt><dd>{issue.modulo}</dd>
              <dt>Esforço</dt><dd className="mono">{issue.tam || (issue.estimate_h ? issue.estimate_h + "h" : "—")}</dd>
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

function Pin({ on, onClick }) {
  return <button className={"fj-pin" + (on ? " on" : "")} onClick={(e) => { e.stopPropagation(); onClick(); }} title={on ? "Soltar do topo" : "Fixar no topo do grupo"} aria-label="Fixar no topo" aria-pressed={on}>
    <svg width="12" height="12" viewBox="0 0 24 24" fill={on ? "currentColor" : "none"} stroke="currentColor" strokeWidth="1.8"><path d="M9 3h6l-1 6 3.5 3.5H6.5L10 9z"/><path d="M12 12.5V21"/></svg>
  </button>;
}
// Roll-up do épico (L-5): uma barra por sub-issue, colorida pela fase; n/N = quantos chegaram a F4.
function EpicRoll({ kids }) {
  const PH = window.FORJA.PHASES;
  const done = kids.filter(k => k.fase === "F4").length;
  return (
    <span className="fj-epic-roll" title={kids.length + " sub-issues · " + done + " em F4"}>
      <span className="fj-epic-bars">{kids.map(k => { const p = PH.find(x => x.id === k.fase); return <i key={k.id} style={{ background: `oklch(0.58 0.13 ${p ? p.hue : 250})` }} title={k.id + " · " + k.fase}/>; })}</span>
      <span className="fj-epic-n">{done}/{kids.length}</span>
    </span>
  );
}

function IssueRow({ issue, active, onClick, fav, onFav, selected, onSelect, pinned, onPin, rankWhy, kids, expanded, onExpand, sub }) {
  const prio = FJ_PRIO[issue.prio];
  return (
    <div className={"fj-row" + (active ? " active" : "") + (selected ? " sel" : "") + (pinned ? " pinned" : "") + (sub ? " sub" : "")} onClick={onClick}>
      <button className={"fj-rowcheck" + (selected ? " on" : "")} onClick={(e) => { e.stopPropagation(); onSelect(issue.id); }} aria-label="Selecionar">{selected && <I.check size={10}/>}</button>
      {kids && kids.length > 0
        ? <button className="fj-epic-chev" onClick={(e) => { e.stopPropagation(); onExpand(issue.id); }} aria-expanded={!!expanded} aria-label="Expandir sub-issues" style={{ transform: expanded ? "none" : "rotate(-90deg)" }}><I.chev size={11}/></button>
        : <span className={"fj-row-indent" + (sub ? " on" : "")}/>}
      <span className="fj-prio-dot" style={{ background: `oklch(0.6 0.18 ${prio.hue})` }} title={rankWhy || prio.label}/>
      <span className="fj-id">{issue.id}</span>
      <TypeChip tipo={issue.tipo}/>
      <span className="fj-title">{issue.titulo}</span>
      {issue.carry > 0 && <span className="fj-carry" title={"Carregado de onda encerrada ×" + issue.carry}>carry ×{issue.carry}</span>}
      {(issue.tam || issue.estimate_h) && <span className="fj-tam" title="Esforço — tamanho relativo ou horas estimadas">{issue.tam || issue.estimate_h + "h"}</span>}
      {kids && kids.length > 0 && <EpicRoll kids={kids}/>}
      <span className="fj-row-mid">
        {issue.vinculos.slice(0, 2).map((v, i) => <VincChip key={i} k={v.k} v={v.v}/>)}
        <span className="fj-mod">{issue.modulo}</span>
      </span>
      {(issue.bloqueado_por || []).length > 0 && <LockIco/>}
      {issue.frescor && <FrescorPill issue={issue}/>}
      {issue.fase ? <PhaseBadge fase={issue.fase}/> : <StatusPill s={issue.exec}/>}
      <OwnerSeal issue={issue}/>
      <Pin on={pinned} onClick={() => onPin(issue.id)}/>
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
        <OwnerSeal issue={issue}/>
        {issue.onda && <span className="fj-onda-chip">~{issue.onda}</span>}
        <span className="fj-kc-spacer"/>
        {(issue.bloqueado_por || []).length > 0 && <LockIco/>}
        {issue.frescor && <FrescorPill issue={issue}/>}
      </div>
    </div>
  );
}
function KanbanView({ issues, eixo, onOpen, onMove, onMoveExec, fav, onFav }) {
  const [dragId, setDragId] = useStateF(null);
  const [over, setOver] = useStateF(null);
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
                {fases && <div className="fj-kcol-quem"><RoleBadge role={c.owner}/><span className="fj-kcol-faz">{c.faz}</span></div>}
                {fases && <div className="fj-kcol-sai">sai quando: <b>{c.sai}</b></div>}
              </header>
              <div className="fj-kcol-body">
                {items.map(i => <KanbanCard key={i.id} issue={i} fav={fav.has(i.id)} onFav={onFav} onDrag={onDrag} onClick={() => onOpen(i.id)}/>)}
                {items.length === 0 && <div className="fj-kcol-empty">arraste aqui</div>}
              </div>
            </section>
          );
        })}
      </div>
    </div>
  );
}

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
function GanttView({ issues, onOpen }) {
  const [shift, setShift] = useStateF({});
  const [drag, setDrag] = useStateF(null);
  const [toast, setToast] = useStateF(null);
  const hoje = new Date(); hoje.setHours(0, 0, 0, 0);
  const dias = Array.from({ length: FJ_G_DIAS }, (_, k) => { const d = new Date(hoje.getTime() + (FJ_G_INI + k) * 86400000); return { k, d, dow: d.getDay(), dd: d.getDate() }; });
  const grupos = useMemoF(() => {
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
  useEffectF(() => {
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
                        {i.bloqueado_por.length > 0 && <LockIco/>}
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
    ["p", "fixar no topo"], ["x", "selecionar"], ["→ / ←", "expandir épico"], ["v", "Lista / Quadro / Gantt"],
    ["⌘K", "paleta de comandos"], ["?", "esta ajuda"], ["Esc", "fechar"],
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
  const [view, setView] = useStateF(() => { try { const v = localStorage.getItem("oimpresso.forja.view") || "hoje"; if (v === "triagem") return "hoje"; if (v === "backlog" || v === "quadro" || v === "tarefas") return "trabalho"; return v; } catch (e) { return "hoje"; } });
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
  const [pin, setPin] = useStateF(() => { try { return new Set(JSON.parse(localStorage.getItem("oimpresso.forja.pin") || "[]")); } catch (e) { return new Set(); } });
  const [expanded, setExpanded] = useStateF(() => new Set());
  const [suggIdx, setSuggIdx] = useStateF(0);
  const [suggOff, setSuggOff] = useStateF(false);
  const searchRef = useRefF(null);
  // Fusão Backlog+Quadro+Tarefas → Trabalho ([W] 2026-08-08): mesma entidade (mcp_tasks), escopo = Frente
  const [trabFrente] = useStateF("todas"); // [W] 2026-08-08: sem filtro de frente — sempre todas; FORJA acha-se por grupo/busca
  const [trabVis, setTrabVis] = useStateF(() => { try { if (localStorage.getItem("oimpresso.forja.view") === "quadro") return "quadro"; return localStorage.getItem("oimpresso.forja.trabvis") || "lista"; } catch (e) { return "lista"; } });
  useEffectF(() => { try { localStorage.setItem("oimpresso.forja.trabvis", trabVis); } catch (e) {} }, [trabVis]);
  const isLista = view === "trabalho" && trabVis === "lista";
  const isQuadro = view === "trabalho" && trabVis === "quadro";
  const goLista = () => { setView("trabalho"); setTrabVis("lista"); };
  const [ordemBy, setOrdemBy] = useStateF({ todas: "rank" });
  const [denso, setDenso] = useStateF(false);

  useEffectF(() => { try { localStorage.setItem("oimpresso.forja.pin", JSON.stringify([...pin])); } catch (e) {} }, [pin]);
  const togglePin = (id) => setPin(s => { const n = new Set(s); n.has(id) ? n.delete(id) : n.add(id); return n; });
  const toggleExpand = (id) => setExpanded(s => { const n = new Set(s); n.has(id) ? n.delete(id) : n.add(id); return n; });

  useEffectF(() => { try { localStorage.setItem("oimpresso.forja.view", view); } catch (e) {} }, [view]);
  useEffectF(() => { try { localStorage.setItem("oimpresso.forja.fav", JSON.stringify([...fav])); } catch (e) {} }, [fav]);
  const toggleFav = (id) => setFav(s => { const n = new Set(s); n.has(id) ? n.delete(id) : n.add(id); return n; });
  useEffectF(() => { try { localStorage.setItem("oimpresso.forja.views", JSON.stringify(savedViews)); } catch (e) {} }, [savedViews]);
  const applyView = (v) => { setGroupBy(v.groupBy); setQuery(v.query || ""); setFavOnly(!!v.favOnly); setHealthFilter(v.healthFilter || null); setAssigneeFilter(v.assignee || null); goLista(); };
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
  const bulkAssign = (role) => { selected.forEach(id => patchIssue(id, { assignee: role }, { ator: "W", t: "atribuído a [" + role + "] (em massa)", quando: "agora" })); setSelected(new Set()); };
  const bulkPrio = (p) => { selected.forEach(id => patchIssue(id, { prio: p }, { ator: "W", t: "prioridade → " + p + " (em massa)", quando: "agora" })); setSelected(new Set()); };
  const bulkOnda = (o) => { selected.forEach(id => patchIssue(id, { onda: o }, { ator: "W", t: "movido p/ onda ~" + (o || "—") + " (em massa)", quando: "agora" })); setSelected(new Set()); };

  const ISSUES = useMemoF(() => {
    return [...created, ...window.FORJA.ISSUES].map(i => {
      const p = patches[i.id];
      if (!p) return i;
      return { ...i, ...p, subtarefas: p.subtarefas || i.subtarefas, atividade: [...(p.atividade || []), ...i.atividade] };
    });
  }, [created, patches]);



  const backlogIssues = useMemoF(() => ISSUES.filter(i => (i.estado || "backlog") === "backlog"), [ISSUES]);  const triagemIssues = useMemoF(() => ISSUES.filter(i => i.estado === "triagem"), [ISSUES]);
  const unifiedAll = useMemoF(() => {
    const tk = (window.FORJA.TK || []).map(t => ({ id: t.id, frente: t.module, titulo: t.title, tipo: "task", prio: t.priority, fase: null, exec: t.status, assignee: null, ownerNome: t.owner, onda: t.sprint, modulo: t.module, origem: "mcp_tasks", vinculos: [], bloqueado_por: t.blocked_by, desc: t.title, subtarefas: [], atividade: [], estimate_h: t.estimate_h, criados: "", atualizado: "mcp_tasks", ...(patches[t.id] || {}) }));
    return [...backlogIssues.map(i => ({ ...i, frente: "FORJA" })), ...tk];
  }, [backlogIssues, patches]);
  const open = useMemoF(() => ISSUES.find(i => i.id === openId) || unifiedAll.find(i => i.id === openId) || null, [openId, ISSUES, unifiedAll]);
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
  const moveExec = (id, exec) => patchIssue(id, { exec, proposto: true }, { ator: "W", t: "status → " + exec + " (proposta)", quando: "agora" });
  const resolveLink = (val) => {
    const iss = ISSUES.find(i => i.id === val);
    if (iss) { goLista(); setOpenId(val); return; }
    const log = window.FORJA.CHANGELOG.find(e => e.ref.includes(val) || val.includes(e.ref.replace(/[^0-9]/g, "")));
    if (log) { setOpenId(null); setView("changelog"); return; }
    setOpenId(null); setIaPanel({ mode: "ask" });
  };
  const drill = (kind) => { setHealthFilter(kind); setFavOnly(false); goLista(); };

  const blocksCount = useMemoF(() => { const m = {}; backlogIssues.forEach(i => (i.bloqueado_por || []).forEach(b => { m[b] = (m[b] || 0) + 1; })); return m; }, [backlogIssues]);

  const filtered = useMemoF(() => {
    const Q = fjParseQuery(query);
    const srcPool = trabFrente === "forja" ? backlogIssues.map(i => ({ ...i, frente: "FORJA" })) : unifiedAll;
    let arr = srcPool.filter(i => {
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
    else if (healthFilter === "blocked") arr = arr.filter(i => i.bloqueado_por.length || i.exec === "blocked");
    else if (healthFilter === "doing") arr = arr.filter(i => (i.exec || "backlog") === "doing");
    else if (healthFilter === "p0") arr = arr.filter(i => i.prio === "P0");
    // L-1 — pin manual no topo, resto por score automático
    const ordem = ordemBy[trabFrente] || (trabFrente === "forja" ? "rank" : "exec");
    const SO = window.FORJA.STATUS.map(s => s.id);
    arr = [...arr].sort((a, b) => {
      const pa = pin.has(a.id), pb = pin.has(b.id);
      if (pa !== pb) return pa ? -1 : 1;
      if (ordem === "exec") {
        const sa = SO.indexOf(a.exec || "backlog"), sb = SO.indexOf(b.exec || "backlog");
        if (sa !== sb) return sa - sb;
        const pd = (FJ_PRIO_W[a.prio] || 0) - (FJ_PRIO_W[b.prio] || 0);
        if (pd) return -pd;
        return a.id.localeCompare(b.id);
      }
      return fjScore(b, blocksCount[b.id]) - fjScore(a, blocksCount[a.id]);
    });
    // L-5 — sub-issue de épico visível aninha sob o pai, não duplica na lista
    const epics = new Set(arr.filter(i => i.tipo === "epico").map(i => i.id));
    if (epics.size) arr = arr.filter(i => !(i.parent && epics.has(i.parent)));
    return arr;
  }, [query, backlogIssues, unifiedAll, trabFrente, ordemBy, favOnly, fav, healthFilter, assigneeFilter, pin, blocksCount]);

  const pendencias = useMemoF(() => window.FORJA.APROVACOES.length + triagemIssues.length + window.FORJA.HANDOFFS.filter(h => h.estado === "stale" || h.gateConflito).length, [triagemIssues]);

  const kidsOf = useMemoF(() => { const m = {}; ISSUES.forEach(i => { if (i.parent) (m[i.parent] = m[i.parent] || []).push(i); }); return m; }, [ISSUES]);

  // L-8 — autocomplete da gramática de busca que já existia (is: @ ~ tipo: mod:)
  const suggests = useMemoF(() => {
    const m = (query.match(/(\S*)$/) || ["", ""])[1];
    if (!m || suggOff) return [];
    const A = window.FORJA.ACTORS, T = window.FORJA.TYPES;
    let pool = [];
    if (m[0] === "@") pool = Object.keys(A).map(r => ({ tok: "@" + r, sub: A[r].name }));
    else if (m[0] === "~") pool = window.FORJA.ONDAS.map(o => ({ tok: "~" + o.id, sub: o.nome }));
    else if (/^tipo:/i.test(m)) pool = Object.keys(T).map(t => ({ tok: "tipo:" + t, sub: T[t].label }));
    else if (/^mod:/i.test(m)) pool = [...new Set(ISSUES.map(i => i.modulo))].map(x => ({ tok: "mod:" + x.toLowerCase(), sub: "módulo" }));
    else if (/^is:/i.test(m)) pool = ["p0", "p1", "p2", "p3", "inferido", "lido", "sync"].map(x => ({ tok: "is:" + x, sub: /^p/.test(x) ? "prioridade" : "frescor" }));
    else pool = [{ tok: "is:", sub: "prioridade ou frescor" }, { tok: "tipo:", sub: "tipo de issue" }, { tok: "mod:", sub: "módulo" }];
    return pool.filter(s => s.tok.toLowerCase().startsWith(m.toLowerCase())).slice(0, 6);
  }, [query, ISSUES, suggOff]);
  useEffectF(() => { setSuggIdx(0); setSuggOff(false); }, [query]);
  const applySugg = (tok) => { setQuery(q => q.replace(/(\S*)$/, tok) + (tok.endsWith(":") ? "" : " ")); searchRef.current?.focus(); };

  const groups = useMemoF(() => {
    const key = (i) => groupBy === "onda" ? (i.onda || "Sem onda") : groupBy === "frente" ? (i.frente || "FORJA") : groupBy === "fase" ? (i.fase || "Execução (sem fase)") : groupBy === "assignee" ? i.assignee : groupBy === "prio" ? i.prio : i.modulo;
    const map = {};
    filtered.forEach(i => { (map[key(i)] = map[key(i)] || []).push(i); });
    return Object.entries(map).map(([g, items]) => [g, items.flatMap(i => {
      const kids = kidsOf[i.id];
      return (kids && kids.length && expanded.has(i.id)) ? [{ i, sub: false }, ...kids.map(k => ({ i: k, sub: true }))] : [{ i, sub: false }];
    })]);
  }, [filtered, groupBy, kidsOf, expanded]);

  const flat = useMemoF(() => groups.flatMap(([g, items]) => collapsed[g] ? [] : items.map(x => x.i)), [groups, collapsed]);
  useEffectF(() => { if (sel > flat.length - 1) setSel(Math.max(0, flat.length - 1)); }, [flat.length]);

  const commands = useMemoF(() => {
    const cmds = [];
    ISSUES.forEach(i => cmds.push({ id: "i-" + i.id, kind: "issue", kindLabel: "issue", label: i.id + " · " + i.titulo, sub: i.modulo + " · " + i.fase, tag: i.prio, run: () => { goLista(); setOpenId(i.id); } }));
    window.FORJA.ONDAS.forEach(o => cmds.push({ id: "o-" + o.id, kind: "onda", kindLabel: "onda", label: "~" + o.id + " · " + o.nome, sub: o.estado, run: () => { goLista(); setGroupBy("onda"); } }));
    window.FORJA.CHANGELOG.forEach(e => cmds.push({ id: "c-" + e.ref, kind: "log", kindLabel: "log", label: e.ref + " · " + e.resumo, sub: e.data, run: () => setView("changelog") }));
    cmds.push({ id: "a-hoje", kind: "acao", kindLabel: "ir", label: "Ir: Aprovações (mesa)", run: () => setView("hoje") });
    cmds.push({ id: "a-quadro", kind: "acao", kindLabel: "ir", label: "Ir: Trabalho (quadro)", run: () => { setView("trabalho"); setTrabVis("quadro"); } });
    cmds.push({ id: "a-gantt", kind: "acao", kindLabel: "ir", label: "Ir: Trabalho (Gantt — prazos)", run: () => { setView("trabalho"); setTrabVis("gantt"); } });
    cmds.push({ id: "a-backlog", kind: "acao", kindLabel: "ir", label: "Ir: Trabalho (lista)", run: () => goLista() });
    cmds.push({ id: "a-changelog", kind: "acao", kindLabel: "ir", label: "Ir: Changelog", run: () => setView("changelog") });
    cmds.push({ id: "a-mcp", kind: "acao", kindLabel: "ir", label: "Ir: MCP", run: () => setView("mcp") });
    cmds.push({ id: "a-saude", kind: "acao", kindLabel: "ir", label: "Ir: Saúde", run: () => setView("saude") });
    cmds.push({ id: "a-integra", kind: "acao", kindLabel: "ir", label: "Ir: Integrador (Forja ↔ TeamMcp)", run: () => setView("integra") });
    cmds.push({ id: "a-papeis", kind: "acao", kindLabel: "abrir", label: "Trilhas de papel (runbook)", run: () => setRunbook(true) });
    cmds.push({ id: "a-ask", kind: "acao", kindLabel: "IA", label: "Perguntar à memória", run: () => setIaPanel({ mode: "ask" }) });
    FJ_GROUPS.forEach(g => cmds.push({ id: "g-" + g.id, kind: "acao", kindLabel: "agrupar", label: "Agrupar por " + g.label, run: () => { goLista(); setGroupBy(g.id); } }));
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
      else if (isLista && e.key === "j") { e.preventDefault(); setSel(s => Math.min(s + 1, flat.length - 1)); }
      else if (isLista && e.key === "k") { e.preventDefault(); setSel(s => Math.max(s - 1, 0)); }
      else if (isLista && (e.key === "Enter" || e.key === "e")) { e.preventDefault(); if (flat[sel]) setOpenId(flat[sel].id); }
      else if (isLista && e.key === "x") { e.preventDefault(); if (flat[sel]) toggleSel(flat[sel].id); }
      else if (isLista && e.key === "p") { e.preventDefault(); if (flat[sel]) togglePin(flat[sel].id); }
      else if (view === "trabalho" && e.key === "v") { e.preventDefault(); setTrabVis(x => x === "lista" ? "quadro" : x === "quadro" ? "gantt" : "lista"); }
      else if (isLista && (e.key === "ArrowRight" || e.key === "ArrowLeft")) {
        const it = flat[sel]; const kids = it && kidsOf[it.id];
        if (kids && kids.length) { e.preventDefault(); setExpanded(s => { const n = new Set(s); e.key === "ArrowRight" ? n.add(it.id) : n.delete(it.id); return n; }); }
      }
      else if (e.key === "/" || e.key === "c") { e.preventDefault(); searchRef.current?.focus(); }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [flat, sel, openId, palette, cheat, view, trabFrente, trabVis, kidsOf]);

  const totals = useMemoF(() => ({
    n: filtered.length, p0: filtered.filter(i => i.prio === "P0").length,
    blocked: filtered.filter(i => i.bloqueado_por.length > 0).length,
    inferido: filtered.filter(i => i.frescor === "inferido").length,
  }), [filtered]);

  const [ondaMsg, setOndaMsg] = useStateF(null);
  const [quadroEixo, setQuadroEixo] = useStateF(() => { try { return localStorage.getItem("oimpresso.forja.quadro") || "fases"; } catch (e) { return "fases"; } });
  useEffectF(() => { try { localStorage.setItem("oimpresso.forja.quadro", quadroEixo); } catch (e) {} }, [quadroEixo]);
  const encerrarOnda = (id) => {
    const O = window.FORJA.ONDAS;
    const o = O.find(x => x.id === id); if (!o) return;
    const next = O.find(x => (x.depende || []).includes(id)) || O.find(x => x.estado === "planejada");
    const abertos = backlogIssues.filter(i => i.onda === id && i.fase !== "F4");
    abertos.forEach(i => patchIssue(i.id, { onda: next ? next.id : null, carry: (i.carry || 0) + 1 }, { ator: "W", t: "onda " + id + " encerrada → carry pra " + (next ? "~" + next.id : "sem onda"), quando: "agora" }));
    o.estado = "encerrada"; if (next) next.estado = "ativa";
    window.FORJA.CHANGELOG.unshift({ tipo: "onda", ref: id + " encerrada", resumo: abertos.length + " issue(s) não-concluído(s) → " + (next ? "~" + next.id : "sem onda") + " (carry-over) · fechamento vira insumo do ritmo", autor: "W", data: "hoje", modulos: [...new Set(abertos.map(i => i.modulo))], flags: [] });
    setOndaMsg("~" + id + " encerrada — " + abertos.length + " issue(s) carregados pra " + (next ? "~" + next.id : "sem onda"));
    setTimeout(() => setOndaMsg(null), 6000);
  };

  const groupLabel = (g) => {
    if (groupBy === "onda" && g !== "Sem onda") { const o = window.FORJA.ONDAS.find(x => x.id === g); return o ? `${g} · ${o.nome}` : g; }
    if (groupBy === "fase") { const p = window.FORJA.PHASES.find(x => x.id === g); return p ? `${g} ${p.label}` : g; }
    if (groupBy === "assignee") { const a = window.FORJA.ACTORS[g]; return a ? `[${g}] ${a.name}` : g; }
    return g;
  };
  const toggleGroup = (g) => setCollapsed(c => ({ ...c, [g]: !c[g] }));
  const hfLabel = { inferido: "não-verificados", blocked: "bloqueados", p0: "P0", doing: "fazendo" };

  const Toolbar = (
    <div className="fj-toolbar">
      <div className="fj-groupby">
        <span className="fj-groupby-lbl">{isQuadro ? "Eixo" : "Agrupar"}</span>
        {isQuadro && [["fases", "Pipeline de telas"], ["exec", "Execução (status)"]].map(([id, lbl]) => (<button key={id} className={"fj-gb-btn" + (quadroEixo === id ? " active" : "")} onClick={() => setQuadroEixo(id)}>{lbl}</button>))}
        {isLista && FJ_GROUPS.map(g => (<button key={g.id} className={"fj-gb-btn" + (groupBy === g.id ? " active" : "")} onClick={() => setGroupBy(g.id)}>{g.label}</button>))}
        {isLista && <span className="fj-groupby-lbl" style={{ marginLeft: 8 }}>Ordem</span>}
        {isLista && [["rank", "rank"], ["exec", "execução"]].map(([id, lbl]) => (<button key={id} className={"fj-gb-btn" + ((ordemBy[trabFrente] || (trabFrente === "forja" ? "rank" : "exec")) === id ? " active" : "")} onClick={() => setOrdemBy(o => ({ ...o, [trabFrente]: id }))} title={id === "rank" ? "prio × parado × destrava (pin fura)" : "status → prio → id (canon Tasks)"}>{lbl}</button>))}
        {isLista && <button className="fj-gb-btn" onClick={() => setDenso(x => !x)}>{denso ? "densidade: compacta" : "densidade: normal"}</button>}
        <button className={"fj-gb-btn fj-fav-toggle" + (favOnly ? " active" : "")} onClick={() => setFavOnly(f => !f)} title="Só favoritos"><svg className={"fj-fav-glyph" + (favOnly ? " on" : "")} width="13" height="13" viewBox="0 0 24 24" fill={favOnly ? "currentColor" : "none"} stroke="currentColor" strokeWidth="1.8"><polygon points="12 2.5 15 9 22 9.6 16.5 14.2 18.2 21 12 17.3 5.8 21 7.5 14.2 2 9.6 9 9"/></svg>favoritos</button>
        {healthFilter && <button className="fj-fchip" onClick={() => setHealthFilter(null)}>{hfLabel[healthFilter]} ✕</button>}
      </div>
      <button className="fj-ia-btn" onClick={() => setRunbook(true)} title="Trilhas de papel"><I.users size={11}/>Papéis</button>
      <button className="fj-ia-btn" onClick={() => setIaPanel({ mode: "ask" })} title="Perguntar à memória"><span className="fj-ia-spark">✦</span>Perguntar</button>
      <div className="fj-search">
        <I.search size={12}/>
        <input ref={searchRef} placeholder="Buscar…  is:p0 @CL ~FA-1 tipo:bug" value={query} onChange={e => setQuery(e.target.value)}
               onKeyDown={e => {
                 if (!suggests.length) return;
                 if (e.key === "Escape") { e.preventDefault(); e.stopPropagation(); setSuggOff(true); return; }
                 if (e.key === "ArrowDown") { e.preventDefault(); setSuggIdx(i => Math.min(i + 1, suggests.length - 1)); }
                 else if (e.key === "ArrowUp") { e.preventDefault(); setSuggIdx(i => Math.max(i - 1, 0)); }
                 else if (e.key === "Tab" || e.key === "Enter") { e.preventDefault(); applySugg(suggests[suggIdx].tok); }
               }}/>
        {suggests.length > 0 && (
          <ul className="fj-sugg">
            {suggests.map((s, i) => (
              <li key={s.tok} className={"fj-sugg-it" + (i === suggIdx ? " sel" : "")} onMouseEnter={() => setSuggIdx(i)} onMouseDown={e => { e.preventDefault(); applySugg(s.tok); }}>
                <span className="fj-sugg-tok">{s.tok}</span><span className="fj-sugg-sub">{s.sub}</span>
              </li>
            ))}
            <li className="fj-sugg-foot"><kbd>tab</kbd> completa · <kbd>↑↓</kbd> escolhe</li>
          </ul>
        )}
      </div>
    </div>
  );

  const FilterBar = (
    <div className="fj-filterbar2">
      <span className="fj-groupby-lbl">Papel</span>
      <button className={"fj-gb-btn" + (!assigneeFilter ? " active" : "")} onClick={() => setAssigneeFilter(null)}>todos</button>
      {Object.keys(window.FORJA.ACTORS).map(r => (<button key={r} className={"fj-gb-btn" + (assigneeFilter === r ? " active" : "")} onClick={() => setAssigneeFilter(a => a === r ? null : r)}>[{r}]</button>))}
      {isLista && (
        <React.Fragment>
          <span className="fj-fb-sep"/>
          <span className="fj-groupby-lbl">Visões</span>
          {savedViews.map((v, i) => (<button key={i} className="fj-view-chip" onClick={() => applyView(v)} title="aplicar visão">{v.name}<span className="fj-view-x" onClick={(e) => { e.stopPropagation(); delView(i); }}>✕</span></button>))}
          <button className="fj-view-save" onClick={saveView}>+ salvar visão</button>
        </React.Fragment>
      )}
    </div>
  );

  return (
    <div className="fj-page">
      <div className="os-page-h">
        <div className="os-page-h-l">
          <h1>Forja</h1>
          <p>Cockpit do cowork loop — aprovações da equipe, backlog, pipeline de telas F0→F3.5, tarefas de todas as frentes, changelog e atores (humano vs agente).</p>
        </div>
        <div className="os-page-h-r">
          <button className="fj-bell" onClick={() => setNotifOpen(true)} title="Minha fila">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7"><path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
            {notifCount > 0 && <span className="fj-bell-badge">{notifCount}</span>}
          </button>
          <button className="fj-kbtn" onClick={() => setPalette(true)} title="Paleta de comandos"><I.search size={11}/>Buscar<kbd>⌘K</kbd></button>
          <div className="fj-viewtabs grouped">
            {[["Trabalho", [["hoje", "Aprovações", IcHoje], ["trabalho", "Trabalho", I.list]]],
              ["Esteira", [["saude", "Saúde", I.chart], ["mcp", "MCP", I.shield]]],
              ["Histórico", [["changelog", "Changelog", I.clock], ["integra", "Integrador", I.plug]]]].map(([g, items]) => (
              <div key={g} className="fj-navgroup" role="group" aria-label={g}>
                <span className="fj-navgroup-lbl">{g}</span>
                {items.map(([id, lbl, Ic]) => (
                  <button key={id} className={view === id ? "active" : ""} onClick={() => setView(id)}><Ic size={11}/>{lbl}{id === "hoje" && pendencias > 0 && <span className="fj-tab-badge">{pendencias}</span>}</button>
                ))}
              </div>
            ))}
          </div>
          <button className="os-btn primary" onClick={() => setComposer(true)}><I.plus size={11}/>Novo issue</button>
        </div>
      </div>

      {view === "trabalho" && (
        <div className="fj-frentebar">
          <div className="fj-viewtabs tf-subnav">
            <button className={trabVis === "lista" ? "active" : ""} onClick={() => setTrabVis("lista")}><I.list size={11}/>Lista</button>
            <button className={trabVis === "quadro" ? "active" : ""} onClick={() => setTrabVis("quadro")}><I.grid size={11}/>Quadro</button>
            <button className={trabVis === "gantt" ? "active" : ""} onClick={() => setTrabVis("gantt")}><I.clock size={11}/>Gantt</button>
          </div>
          <span className="fj-frente-note"><b className="mono">{unifiedAll.length}</b> mcp_tasks numa lista só — FORJA junto das demais frentes (agrupe por Frente ou busque)</span>
        </div>
      )}
      {(isLista || isQuadro || (view === "trabalho" && trabVis === "gantt")) && (() => { const pool = trabFrente === "forja" ? backlogIssues : unifiedAll; const kp = { total: pool.length, p0: pool.filter(i => i.prio === "P0" && (i.exec || "backlog") !== "done").length, doing: pool.filter(i => (i.exec || "backlog") === "doing").length, blocked: pool.filter(i => (i.bloqueado_por || []).length || i.exec === "blocked").length }; return (
        <div className="fj-kpirow">
          <button className="tf-kpi" disabled><span className="tf-kpi-v">{kp.total}</span><span className="tf-kpi-l">Total</span></button>
          <button className={"tf-kpi click " + (kp.p0 ? "bad" : "ok") + (healthFilter === "p0" ? " on" : "")} onClick={() => setHealthFilter(h => h === "p0" ? null : "p0")}><span className="tf-kpi-v">{kp.p0}</span><span className="tf-kpi-l">P0 abertas</span></button>
          <button className={"tf-kpi click" + (healthFilter === "doing" ? " on" : "")} onClick={() => setHealthFilter(h => h === "doing" ? null : "doing")}><span className="tf-kpi-v">{kp.doing}</span><span className="tf-kpi-l">Fazendo</span></button>
          <button className={"tf-kpi click " + (kp.blocked ? "warn" : "ok") + (healthFilter === "blocked" ? " on" : "")} onClick={() => setHealthFilter(h => h === "blocked" ? null : "blocked")}><span className="tf-kpi-v">{kp.blocked}</span><span className="tf-kpi-l">Bloqueadas</span></button>
          <span className="fj-kpirow-note">clique filtra a lista e o quadro</span>
        </div>
      ); })()}
      {(isLista || isQuadro || (view === "trabalho" && trabVis === "gantt")) && Toolbar}
      {(isLista || isQuadro || (view === "trabalho" && trabVis === "gantt")) && FilterBar}

      {isLista && (
        <React.Fragment>
          {ondaMsg && <div className="fj-onda-msg">{ondaMsg}</div>}
          <div className={"fj-list" + (denso ? " compact" : "")}>
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
                    {groupBy === "onda" && g !== "Sem onda" && (() => {
                      const o = window.FORJA.ONDAS.find(x => x.id === g);
                      const carga = ["P", "M", "G", "GG"].map(t => { const n = items.filter(x => x.i.tam === t && !x.sub).length; return n ? n + t : null; }).filter(Boolean).join(" · ");
                      return (
                        <span className="fj-onda-meta">
                          {o && <span className={"fj-onda-estado " + o.estado}>{o.estado}</span>}
                          {o && <span className="fj-onda-janela">{o.janela}</span>}
                          {carga && <span className="fj-onda-carga" title="Carga da onda por tamanho (P/M/G/GG) — responde: cabe na janela?">carga {carga}</span>}
                          {o && o.estado === "ativa" && <button className="fj-onda-encerrar" onClick={() => encerrarOnda(g)} title="Encerra o ciclo: não-concluídos carregam pra próxima onda e o fechamento entra no changelog">encerrar onda</button>}
                          <button className="fj-group-ia" onClick={() => setIaPanel({ mode: "digest", onda: g })} title="Resumir onda"><span className="fj-ia-spark">✦</span>resumir</button>
                        </span>
                      );
                    })()}
                  </div>
                  {!isC && items.map(({ i: issue, sub }) => {
                    const idx = flat.indexOf(issue);
                    const kids = kidsOf[issue.id];
                    return <IssueRow key={(sub ? "s-" : "") + issue.id} issue={issue} active={idx === sel} fav={fav.has(issue.id)} onFav={toggleFav}
                      selected={selected.has(issue.id)} onSelect={toggleSel} pinned={pin.has(issue.id)} onPin={togglePin}
                      rankWhy={pin.has(issue.id) ? "fixado no topo por você" : fjWhyRank(issue, blocksCount[issue.id])}
                      kids={sub ? null : kids} expanded={expanded.has(issue.id)} onExpand={toggleExpand} sub={sub}
                      onClick={() => { setSel(idx); setOpenId(issue.id); }}/>;
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
              <span className="fj-bulk-lbl">fase</span>
              {window.FORJA.PHASES.map(p => <button key={p.id} className="fj-bulk-fase" onClick={() => bulkPhase(p.id)}>{p.id}</button>)}
              <span className="fj-bulk-lbl">papel</span>
              {Object.keys(window.FORJA.ACTORS).map(r => <button key={r} className="fj-bulk-fase" onClick={() => bulkAssign(r)} title={window.FORJA.ACTORS[r].name}>[{r}]</button>)}
              <span className="fj-bulk-lbl">prio</span>
              {["P0", "P1", "P2", "P3"].map(p => <button key={p} className="fj-bulk-fase" onClick={() => bulkPrio(p)}>{p}</button>)}
              <span className="fj-bulk-lbl">onda</span>
              {window.FORJA.ONDAS.map(o => <button key={o.id} className="fj-bulk-fase" onClick={() => bulkOnda(o.id)} title={o.nome}>~{o.id}</button>)}
              <button className="fj-bulk-fase" onClick={() => bulkOnda(null)} title="Tirar da onda">—</button>
              <span className="fj-bulk-lbl">status</span>
              {["doing", "review", "done"].map(s => <button key={s} className="fj-bulk-fase" onClick={() => { selected.forEach(id => moveExec(id, s)); setSelected(new Set()); }}>{window.FORJA.STATUS.find(x => x.id === s).label}</button>)}
              <button className="fj-bulk-act" onClick={bulkFav}>★ favoritar</button>
              <button className="fj-bulk-act" onClick={() => setSelected(new Set())}>limpar</button>
            </div>
          )}
          <div className="fj-totalbar">
            <span><b>{totals.n}</b> issues</span>
            <span><b>{totals.p0}</b> P0</span>
            <span><b>{totals.blocked}</b> bloqueados</span>
            <span className="fj-total-warn"><b>{totals.inferido}</b> não-verificados</span>
            <span className="fj-total-rank" title="Score = prioridade × tempo parado × quantos issues destrava. Bloqueado desce. Fixado fura a fila.">ordem: automática{pin.size > 0 && <b> + {pin.size} fixado{pin.size > 1 ? "s" : ""}</b>}</span>
            <span className="fj-total-hint"><kbd>j</kbd><kbd>k</kbd> navega · <kbd>↵</kbd> abre · <kbd>?</kbd> atalhos</span>
          </div>
        </React.Fragment>
      )}

      {view === "hoje" && <window.ForjaAprovacoes triagem={triagemIssues} onTriage={(id) => setDossie(id)} onAprovarProposta={approveTriagem} onRejeitarProposta={rejectTriagem} onDesfazerProposta={(id) => patchIssue(id, { estado: "triagem" }, { ator: "W", t: "decisão desfeita — volta pra fila", quando: "agora" })} onGoHandoffs={() => setView("mcp")}/>}
      {isQuadro && <KanbanView issues={filtered} eixo={quadroEixo} onOpen={setOpenId} onMove={moveFase} onMoveExec={moveExec} fav={fav} onFav={toggleFav}/>}
      {view === "trabalho" && trabVis === "gantt" && <GanttView issues={filtered} onOpen={setOpenId}/>}
      {view === "changelog" && <ChangelogFeed/>}
      {view === "mcp" && <window.ForjaMCPView/>}
      {view === "saude" && <SaudeView issues={ISSUES} onDrill={drill} rules={rules} onToggleRule={toggleRule}/>}
      {view === "integra" && <window.ForjaIntegrador/>}

      {open && <IssueDrawer issue={open} relations={relations} following={follow.has(open.id)} onFollow={toggleFollow} rules={rules} onClose={() => setOpenId(null)} onPatch={patchIssue} onReverify={reverify} onComment={addComment} onReact={react} onLink={resolveLink}/>}
      {palette && <CommandPalette commands={commands} onClose={() => setPalette(false)}/>}
      {cheat && <CheatSheet onClose={() => setCheat(false)}/>}
      {iaPanel && <window.ForjaIAPanel mode={iaPanel.mode} onda={iaPanel.onda} onClose={() => setIaPanel(null)} onHandoff={(onda) => { setIaPanel(null); setHandoff(onda); }}/>}
      {composer && <window.ForjaNewIssue onCreate={(iss) => setCreated(c => [iss, ...c])} onClose={() => setComposer(false)}/>}
      {runbook && <window.ForjaRunbook onClose={() => setRunbook(false)}/>}
      {handoff && <window.ForjaHandoff onda={handoff} onClose={() => setHandoff(null)}/>}
      {dossie && <window.ForjaDossie issue={ISSUES.find(i => i.id === dossie)} allIssues={ISSUES} onApprove={approveTriagem} onReject={rejectTriagem} onMerge={mergeDup} onClose={() => setDossie(null)}/>}
      {notifOpen && <window.ForjaNotifs notifs={notifs} seen={notifSeen} onSeen={markSeen} onMarkAll={markAllSeen} onOpen={(id) => { setNotifOpen(false); goLista(); setOpenId(id); }} onTriage={(id) => { setNotifOpen(false); setView("hoje"); setDossie(id); }} onClose={() => setNotifOpen(false)}/>}
    </div>
  );
}

window.FjRoleBadge = RoleBadge;
window.FjFrescorPill = FrescorPill;
window.ForjaPage = ForjaPage;
