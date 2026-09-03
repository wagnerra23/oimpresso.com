// ForjaRoleBadge — o selo de ator do protótipo (`window.FjRoleBadge`), como componente real.
//
// @memcofre
//   module: Forja — peça compartilhada da réplica (PARIDADE §11 Onda 8)
//   fonte: prototipo-ui/cowork/forja-page.jsx `function RoleBadge` + forja-data.jsx `FORJA_ACTORS`
//   adrs: 0388 (réplica primeiro) · UI-0013
//
// POR QUE NASCE AGORA: a view `mcp` do protótipo usa o selo em DOIS lugares (tokens
// ativos e auditoria) e produção não tinha equivalente — o `ForjaChangelog` desenha
// uma pílula mono própria, que não é este componente. As classes (`.fj-role`,
// `.fj-role-av`, `.fj-role-tag`, `.fj-role-name`) JÁ descem no bundle da Onda 1
// (linhas 127-130 do `cowork-forja-bundle.css`); faltava só quem as usasse.
//
// As cores são as do protótipo, VERBATIM de `FORJA_ACTORS`. Elas entram por
// `style={{ '--rc': … }}` porque é assim que o CSS do bundle as consome
// (`.fj-role-tag{ color: var(--rc) }`) — a cor é DADO do ator, não token de tema.
// O `ds-guard` classifica cor crua como desvio: pela ADR 0388 isso é item da lista
// de inconsistências, não motivo pra divergir do protótipo.

import { type CSSProperties } from 'react';

export type ForjaRole = 'W' | 'W2' | 'CC' | 'CD' | 'CL' | 'CA' | 'AN';

interface Actor {
  name: string;
  kind: 'human' | 'agent';
  color: string;
  model?: string;
  desc: string;
}

// VERBATIM de prototipo-ui/cowork/forja-data.jsx `FORJA_ACTORS` (espelho provado
// SYNC contra o Cowork vivo em 2026-09-02, sha e4339537969d do forja-page.jsx).
const ACTORS: Record<ForjaRole, Actor> = {
  W: { name: 'Wagner', kind: 'human', color: 'oklch(0.57 0.16 25)', desc: 'Decide · aprova screenshot e merge' },
  CC: { name: 'Claude Cowork', kind: 'agent', color: 'oklch(0.55 0.15 295)', model: 'claude-opus-4', desc: 'F1 — protótipo visual' },
  CD: { name: 'Claude Design', kind: 'agent', color: 'oklch(0.60 0.13 60)', model: 'claude-sonnet-4', desc: 'F1.5 — critique' },
  CL: { name: 'Claude Code', kind: 'agent', color: 'oklch(0.52 0.10 195)', model: 'claude-opus-4', desc: 'F3 — Inertia/React real' },
  CA: { name: 'Claude A11y', kind: 'agent', color: 'oklch(0.55 0.13 150)', model: 'claude-sonnet-4', desc: 'F3.5 — WCAG 2.1 AA' },
  AN: { name: 'Claude Analista', kind: 'agent', color: 'oklch(0.50 0.10 195)', model: 'claude-sonnet-4', desc: 'F0 — triagem & enriquecimento de ticket' },
  W2: { name: 'Wagner aprovador', kind: 'human', color: 'oklch(0.52 0.08 250)', desc: 'F2 + F4 síncronos' },
};

/** `role` desconhecido devolve `null` — igual ao protótipo (`if (!a) return null`).
 *  NÃO exportada de propósito: este arquivo só pode exportar o componente, senão o
 *  `react-refresh/only-export-components` acusa (medido: +1 no eslint-baseline). */
function isForjaRole(role: string): role is ForjaRole {
  return Object.prototype.hasOwnProperty.call(ACTORS, role);
}

export default function ForjaRoleBadge({ role, showName }: { role: string; showName?: boolean }) {
  if (!isForjaRole(role)) return null;
  const a = ACTORS[role];
  const kindLabel = a.kind === 'agent' ? `agente ${a.model ?? ''}`.trim() : 'humano';

  return (
    <span
      className="fj-role"
      title={`${a.name} · ${kindLabel} — ${a.desc}`}
      style={{ '--rc': a.color } as CSSProperties}
      data-testid="forja-role"
      data-role={role}
    >
      <span className="fj-role-av" style={{ background: a.color }}>
        {a.kind === 'agent' ? (
          <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2.4" aria-hidden="true">
            <rect x="4" y="8" width="16" height="11" rx="2.5" />
            <path d="M12 4v4M9 13h.01M15 13h.01" />
          </svg>
        ) : (
          <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2.4" aria-hidden="true">
            <circle cx="12" cy="8" r="3.4" />
            <path d="M5 20c0-3.3 3-5.5 7-5.5s7 2.2 7 5.5" />
          </svg>
        )}
      </span>
      <span className="fj-role-tag">[{role}]</span>
      {showName && <span className="fj-role-name">{a.name}</span>}
    </span>
  );
}
