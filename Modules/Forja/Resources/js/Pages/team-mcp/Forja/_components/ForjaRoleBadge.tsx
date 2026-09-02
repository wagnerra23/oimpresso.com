// ForjaRoleBadge — porte 1:1 do `RoleBadge` de `prototipo-ui/cowork/forja-page.jsx`
// (PARIDADE §11 Onda 9 · ADR 0388 "réplica primeiro"). Mesmas classes `.fj-role`,
// `.fj-role-av`, `.fj-role-tag`, `.fj-role-name` do `cowork-forja-bundle.css`,
// mesmos dois ícones (agente = robô · humano = pessoa), mesmo `title`.
//
// O mapa FORJA_ACTORS é o do protótipo, VERBATIM (`FORJA_ACTORS` em forja-data.jsx,
// espelho provado SYNC contra o Cowork vivo em 2026-09-02): sigla, nome, kind,
// cor e descrição. Não normalizo nem traduzo — é o design.
//
// ── Papel que o protótipo NÃO conhece ────────────────────────────────────────
// O protótipo devolve `null` (some o autor da linha). Em produção o dado real traz
// siglas que o mock não cobre — `decided_by` dos ADRs mede [W] em 313 dos 393,
// mas também [E] e [F], e o time canônico (memory/regras-time.md) tem [M] e [L].
// Sumir com o autor seria perder dado real; inventar uma cor pra eles seria
// inventar design. Então renderizo SÓ a tag `[X]` em cor neutra (`var(--text-mute)`),
// sem avatar — nenhum avatar significa nenhuma afirmação sobre humano-vs-agente.
// Item declarado em memory/requisitos/Forja/INCONSISTENCIAS-replica.md.

import type { CSSProperties } from 'react';

export interface ForjaActor {
  name: string;
  kind: 'human' | 'agent';
  color: string;
  desc: string;
  model?: string;
}

// Atores do protótipo (forja-data.jsx `FORJA_ACTORS`) — copiados sem edição.
// NÃO exportado de propósito: exportar valor ao lado de um componente quebra o
// `react-refresh/only-export-components` (pego pelo eslint-baseline). Quando outra
// onda precisar do mapa, ele sai daqui pra um módulo de dados próprio.
const FORJA_ACTORS: Record<string, ForjaActor> = {
  W: { name: 'Wagner', kind: 'human', color: 'oklch(0.57 0.16 25)', desc: 'Decide · aprova screenshot e merge' },
  CC: { name: 'Claude Cowork', kind: 'agent', color: 'oklch(0.55 0.15 295)', model: 'claude-opus-4', desc: 'F1 — protótipo visual' },
  CD: { name: 'Claude Design', kind: 'agent', color: 'oklch(0.60 0.13 60)', model: 'claude-sonnet-4', desc: 'F1.5 — critique' },
  CL: { name: 'Claude Code', kind: 'agent', color: 'oklch(0.52 0.10 195)', model: 'claude-opus-4', desc: 'F3 — Inertia/React real' },
  CA: { name: 'Claude A11y', kind: 'agent', color: 'oklch(0.55 0.13 150)', model: 'claude-sonnet-4', desc: 'F3.5 — WCAG 2.1 AA' },
  AN: { name: 'Claude Analista', kind: 'agent', color: 'oklch(0.50 0.10 195)', model: 'claude-sonnet-4', desc: 'F0 — triagem & enriquecimento de ticket' },
  W2: { name: 'Wagner aprovador', kind: 'human', color: 'oklch(0.52 0.08 250)', desc: 'F2 + F4 síncronos' },
};

/** Ícone de agente do protótipo (robô). `#fff` é do design — ver ADR 0388 D-2. */
function IconeAgente() {
  return (
    <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2.4">
      <rect x="4" y="8" width="16" height="11" rx="2.5" />
      <path d="M12 4v4M9 13h.01M15 13h.01" />
    </svg>
  );
}

/** Ícone de humano do protótipo (pessoa). */
function IconeHumano() {
  return (
    <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2.4">
      <circle cx="12" cy="8" r="3.4" />
      <path d="M5 20c0-3.3 3-5.5 7-5.5s7 2.2 7 5.5" />
    </svg>
  );
}

export default function ForjaRoleBadge({ role, showName = false }: { role: string; showName?: boolean }) {
  const chave = role.trim();
  if (chave === '') {
    return null;
  }

  const a = FORJA_ACTORS[chave];

  // Papel real que o design não cobre: tag neutra, sem avatar (ver cabeçalho).
  if (!a) {
    return (
      <span className="fj-role" style={{ '--rc': 'var(--text-mute)' } as CSSProperties} data-testid="forja-role-desconhecido">
        <span className="fj-role-tag">[{chave}]</span>
      </span>
    );
  }

  const titulo = `${a.name} · ${a.kind === 'agent' ? 'agente ' + (a.model || '') : 'humano'} — ${a.desc}`;

  return (
    <span className="fj-role" title={titulo} style={{ '--rc': a.color } as CSSProperties}>
      <span className="fj-role-av" style={{ background: a.color }}>
        {a.kind === 'agent' ? <IconeAgente /> : <IconeHumano />}
      </span>
      <span className="fj-role-tag">[{chave}]</span>
      {showName && <span className="fj-role-name">{a.name}</span>}
    </span>
  );
}
