// forja-atomos.jsx — fundação da Forja: escala de prioridade, rank híbrido, DSL de busca e os
// átomos compartilhados por TODAS as telas (selos de papel/fase/tipo, frescor, linha de issue).
// Extraído de forja-page.jsx (Onda 1 · 1 arquivo por tela). Publicado em window.Fj*.
const { useState: useStateFx, useMemo: useMemoFx, useEffect: useEffectFx, useRef: useRefFx } = React;
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

function FjRoleBadge({ role, showName }) {
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
function FjPhaseBadge({ fase }) {
  const p = window.FORJA.PHASES.find(x => x.id === fase);
  const hue = p ? p.hue : 250;
  return <span className="fj-phase" style={{ "--ph": hue }}>{fase} <span className="fj-phase-lbl">{p?.label}</span></span>;
}
function FjTypeChip({ tipo }) {
  const t = window.FORJA.TYPES[tipo];
  return <span className="fj-type" style={{ "--ty": t?.hue || 250 }}>{t?.label || tipo}</span>;
}

function FjVincChip({ k, v, onClick }) {
  const ic = { adr: "ADR", pr: "PR", sessao: "ses", tela: "tela", issue: "" }[k] || k;
  return <span className={"fj-vinc fj-vinc-" + k + (onClick ? " link" : "")} onClick={onClick ? (e) => { e.stopPropagation(); onClick(); } : undefined}>{ic && <span className="fj-vinc-k">{ic}</span>}{v}</span>;
}
function FjFrescorPill({ issue, full }) {
  const f = issue.frescor;
  if (f === "lido") return <span className="fj-fresco fj-fresco-lido" title="Lido @main nesta sessão"><I.check size={9}/>{full ? "lido @main" : "@main"}</span>;
  if (f === "inferido") return <span className="fj-fresco fj-fresco-inferido" title="Não verificado contra @main — pode estar stale">⚠ {full ? "não verificado" : "inferido"}</span>;
  return <span className="fj-fresco fj-fresco-sync" title={`Sincronizado há ${issue.frescorDias} dia(s)`}>sync {issue.frescorDias}d</span>;
}
const FjIcHoje = ({ size = 11 }) => <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><circle cx="12" cy="12" r="4"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M19.1 4.9L17 7M7 17l-2.1 2.1"/></svg>;
function FjStatusPill({ s }) {
  const st = window.FORJA.STATUS.find(x => x.id === (s || "backlog")) || { label: s || "—", neutral: true };
  return <span className="fj-exec" title={"Status de execução: " + st.label + " (fora do pipeline de telas)"}><i style={{ background: st.neutral ? "var(--text-mute)" : "oklch(0.6 0.14 " + st.hue + ")" }}/>{st.label}</span>;
}
const FJ_AGENTES_NOME = ["claude-cc", "claude-code", "claude-design", "claude-a11y", "claude-analista"];
function FjLockIco() { return <svg className="fj-lockico" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-label="bloqueada"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>; }
function FjOwnerSeal({ issue, showName }) {
  if (issue.assignee && window.FORJA.ACTORS[issue.assignee]) return <FjRoleBadge role={issue.assignee} showName={showName}/>;
  const o = issue.ownerNome;
  if (!o) return <span className="fj-owner vazio">—</span>;
  const ag = FJ_AGENTES_NOME.includes(o);
  return <span className={"fj-owner" + (ag ? " agente" : "")} title={(ag ? "Agente: " : "Humano: ") + o}>{ag ? <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2"><rect x="4" y="8" width="16" height="11" rx="2.5"/><path d="M12 4v4M9 13h.01M15 13h.01"/></svg> : <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2"><circle cx="12" cy="8" r="3.4"/><path d="M5 20c0-3.3 3-5.5 7-5.5s7 2.2 7 5.5"/></svg>}{o}</span>;
}
function FjStar({ on, onClick }) {
  return <button className={"fj-star" + (on ? " on" : "")} onClick={(e) => { e.stopPropagation(); onClick(); }} title={on ? "Desfavoritar" : "Favoritar"} aria-label="Favoritar">
    <svg width="13" height="13" viewBox="0 0 24 24" fill={on ? "currentColor" : "none"} stroke="currentColor" strokeWidth="1.8"><polygon points="12 2.5 15 9 22 9.6 16.5 14.2 18.2 21 12 17.3 5.8 21 7.5 14.2 2 9.6 9 9"/></svg>
  </button>;
}

function FjPin({ on, onClick }) {
  return <button className={"fj-pin" + (on ? " on" : "")} onClick={(e) => { e.stopPropagation(); onClick(); }} title={on ? "Soltar do topo" : "Fixar no topo do grupo"} aria-label="Fixar no topo" aria-pressed={on}>
    <svg width="12" height="12" viewBox="0 0 24 24" fill={on ? "currentColor" : "none"} stroke="currentColor" strokeWidth="1.8"><path d="M9 3h6l-1 6 3.5 3.5H6.5L10 9z"/><path d="M12 12.5V21"/></svg>
  </button>;
}
// Roll-up do épico (L-5): uma barra por sub-issue, colorida pela fase; n/N = quantos chegaram a F4.
function FjEpicRoll({ kids }) {
  const PH = window.FORJA.PHASES;
  const done = kids.filter(k => k.fase === "F4").length;
  return (
    <span className="fj-epic-roll" title={kids.length + " sub-issues · " + done + " em F4"}>
      <span className="fj-epic-bars">{kids.map(k => { const p = PH.find(x => x.id === k.fase); return <i key={k.id} style={{ background: `oklch(0.58 0.13 ${p ? p.hue : 250})` }} title={k.id + " · " + k.fase}/>; })}</span>
      <span className="fj-epic-n">{done}/{kids.length}</span>
    </span>
  );
}

function FjIssueRow({ issue, active, onClick, fav, onFav, selected, onSelect, pinned, onPin, rankWhy, kids, expanded, onExpand, sub }) {
  const prio = FJ_PRIO[issue.prio];
  return (
    <div className={"fj-row" + (active ? " active" : "") + (selected ? " sel" : "") + (pinned ? " pinned" : "") + (sub ? " sub" : "")} onClick={onClick}>
      <button className={"fj-rowcheck" + (selected ? " on" : "")} onClick={(e) => { e.stopPropagation(); onSelect(issue.id); }} aria-label="Selecionar">{selected && <I.check size={10}/>}</button>
      {kids && kids.length > 0
        ? <button className="fj-epic-chev" onClick={(e) => { e.stopPropagation(); onExpand(issue.id); }} aria-expanded={!!expanded} aria-label="Expandir sub-issues" style={{ transform: expanded ? "none" : "rotate(-90deg)" }}><I.chev size={11}/></button>
        : <span className={"fj-row-indent" + (sub ? " on" : "")}/>}
      <span className="fj-prio-dot" style={{ background: `oklch(0.6 0.18 ${prio.hue})` }} title={rankWhy || prio.label}/>
      <span className="fj-id">{issue.id}</span>
      <FjTypeChip tipo={issue.tipo}/>
      <span className="fj-title">{issue.titulo}</span>
      {issue.carry > 0 && <span className="fj-carry" title={"Carregado de onda encerrada ×" + issue.carry}>carry ×{issue.carry}</span>}
      {(issue.tam || issue.estimate_h) && <span className="fj-tam" title="Esforço — tamanho relativo ou horas estimadas">{issue.tam || issue.estimate_h + "h"}</span>}
      {kids && kids.length > 0 && <FjEpicRoll kids={kids}/>}
      <span className="fj-row-mid">
        {issue.vinculos.slice(0, 2).map((v, i) => <FjVincChip key={i} k={v.k} v={v.v}/>)}
        <span className="fj-mod">{issue.modulo}</span>
      </span>
      {(issue.bloqueado_por || []).length > 0 && <FjLockIco/>}
      {issue.frescor && <FjFrescorPill issue={issue}/>}
      {issue.fase ? <FjPhaseBadge fase={issue.fase}/> : <FjStatusPill s={issue.exec}/>}
      <FjOwnerSeal issue={issue}/>
      <FjPin on={pinned} onClick={() => onPin(issue.id)}/>
      <FjStar on={fav} onClick={() => onFav(issue.id)}/>
    </div>
  );
}

function FjSpark({ data, hue }) {
  const pts = data.map((d, i) => `${i * (60 / (data.length - 1))},${17 - Math.max(0, Math.min(1, d)) * 15}`).join(" ");
  return <svg className="fj-spark" viewBox="0 0 60 18" preserveAspectRatio="none"><polyline points={pts} fill="none" stroke={`oklch(0.55 0.13 ${hue})`} strokeWidth="1.8" strokeLinejoin="round"/></svg>;
}
window.FJ_PRIO = FJ_PRIO;
window.FJ_GROUPS = FJ_GROUPS;
window.FJ_PRIO_W = FJ_PRIO_W;
window.FJ_RULES = FJ_RULES;
window.fjAging = fjAging;
window.fjScore = fjScore;
window.fjWhyRank = fjWhyRank;
window.fjParseQuery = fjParseQuery;
window.FjRoleBadge = FjRoleBadge;
window.FjPhaseBadge = FjPhaseBadge;
window.FjTypeChip = FjTypeChip;
window.FjVincChip = FjVincChip;
window.FjFrescorPill = FjFrescorPill;
window.FjStatusPill = FjStatusPill;
window.FjOwnerSeal = FjOwnerSeal;
window.FjStar = FjStar;
window.FjPin = FjPin;
window.FjLockIco = FjLockIco;
window.FjIcHoje = FjIcHoje;
window.FjEpicRoll = FjEpicRoll;
window.FjIssueRow = FjIssueRow;
window.FjSpark = FjSpark;
