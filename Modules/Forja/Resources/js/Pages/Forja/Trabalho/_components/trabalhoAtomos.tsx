// Átomos da linha do Trabalho — RÉPLICA do `forja-page.jsx` (PARIDADE §11 Onda 4).
//
// Cada componente daqui é a cópia do átomo homônimo do protótipo, com o MESMO
// nome de classe do bundle `cowork-forja-bundle.css` (Onda 1). Nada foi
// redesenhado: onde este arquivo diverge do protótipo, é bug meu, não escolha.
// A lei é a ADR 0388 ("réplica primeiro": o protótipo é o contrato de layout).
//
// Os mapas de vocabulário (hue por prioridade/tipo/fase, papel, status) moram em
// `trabalhoTokens.ts` — arquivo separado porque exportar constante ao lado de
// componente quebra o Fast Refresh do Vite.
//
// ── POR QUE `oklch()` INLINE, e por que isso não é descuido ──────────────
// O protótipo pinta prioridade, tipo, fase e status por HUE calculado
// (`oklch(0.6 0.18 <hue>)`). Não há token do DS para "a cor da fase F1.5" — a
// escala é contínua e mora na fonte de design. Pela ADR 0388 D-2 isso vira item
// na lista de inconsistências (`INCONSISTENCIAS-replica.md`), não motivo pra
// mudar o layout. Trocar por token seria inventar um desenho que ninguém pediu.
//
// ── O QUE NÃO ESTÁ AQUI, e por quê ────────────────────────────────
// `FrescorPill` e o chip `carry` do protótipo NÃO existem neste arquivo: são
// campos que `mcp_tasks` não tem (`frescor`, `frescorDias`, `carry`). No
// protótipo os dois são condicionais (`issue.carry > 0 &&`), então a ausência
// do dado já os apaga lá — renderizar um valor fixo seria dado fantasma. Ver
// o charter §"Diferenças declaradas".

import type { CSSProperties } from 'react';
import { FASE_HUE, PAPEIS, PRIO_HUE, STATUS_FJ, TIPOS } from './trabalhoTokens';

/* ─── Átomos ─────────────────────────────────────────────────────────────── */

export function PrioDot({ prio, title }: { prio: string; title?: string }) {
  const hue = PRIO_HUE[prio] ?? 250;
  return <span className="fj-prio-dot" style={{ background: `oklch(0.6 0.18 ${hue})` }} title={title ?? prio.toUpperCase()} />;
}

export function TypeChip({ tipo }: { tipo: string }) {
  const t = TIPOS[tipo];
  return <span className="fj-type" style={{ '--ty': t?.hue ?? 250 } as CSSProperties}>{t?.label ?? tipo}</span>;
}

export function PhaseBadge({ fase, label }: { fase: string; label?: string }) {
  return (
    <span className="fj-phase" style={{ '--ph': FASE_HUE[fase] ?? 250 } as CSSProperties}>
      {fase} <span className="fj-phase-lbl">{label}</span>
    </span>
  );
}

export function StatusPill({ s }: { s: string }) {
  const st = STATUS_FJ[s || 'backlog'] ?? { label: s || '—', hue: 250, neutral: true };
  return (
    <span className="fj-exec" title={`Status de execução: ${st.label} (fora do pipeline de telas)`}>
      <i style={{ background: st.neutral ? 'var(--text-mute)' : `oklch(0.6 0.14 ${st.hue})` }} />
      {st.label}
    </span>
  );
}

const IcAgente = (
  <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" aria-hidden>
    <rect x="4" y="8" width="16" height="11" rx="2.5" /><path d="M12 4v4M9 13h.01M15 13h.01" />
  </svg>
);
const IcHumano = (
  <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" aria-hidden>
    <circle cx="12" cy="8" r="3.4" /><path d="M5 20c0-3.3 3-5.5 7-5.5s7 2.2 7 5.5" />
  </svg>
);

/** `RoleBadge` — avatar quadrado + `[SIGLA]`, quando a task declara papel. */
export function RoleBadge({ papel, showName }: { papel: string; showName?: boolean }) {
  const a = PAPEIS[papel];
  if (!a) return null;
  return (
    <span className="fj-role" title={`${a.nome} · ${a.agente ? 'agente' : 'humano'} — ${a.desc}`} style={{ '--rc': a.cor } as CSSProperties}>
      {/* Glifo BRANCO por atributo, como no protótipo (`stroke="#fff"` — aqui em
          oklch, a notação que o conformance-gate aceita, mesmo desvio declarado
          da Onda 2 no `.os-btn.primary`). O avatar tem fundo de cor cheia nos
          dois temas, então o branco é fixo de propósito: não é token de tema. */}
      <span className="fj-role-av" style={{ background: a.cor }}>
        {a.agente
          ? <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="oklch(1 0 0)" strokeWidth="2.4" aria-hidden><rect x="4" y="8" width="16" height="11" rx="2.5" /><path d="M12 4v4M9 13h.01M15 13h.01" /></svg>
          : <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="oklch(1 0 0)" strokeWidth="2.4" aria-hidden><circle cx="12" cy="8" r="3.4" /><path d="M5 20c0-3.3 3-5.5 7-5.5s7 2.2 7 5.5" /></svg>}
      </span>
      <span className="fj-role-tag">[{papel}]</span>
      {showName && <span className="fj-role-name">{a.nome}</span>}
    </span>
  );
}

/**
 * `OwnerSeal` — papel se houver; senão o dono, marcado agente ou humano.
 *
 * ⚠️ "É agente?" vem da ALLOWLIST do backend (`TrabalhoService::agentes()` lê
 * `mcp_actors` com `type=ai_agent` não revogado), NUNCA de padrão no nome. O
 * charter proíbe a heurística com todas as letras: `claude*` erra, e o selo
 * mente sobre quem fez o trabalho.
 */
export function OwnerSeal({ papel, owner, agents }: { papel: string | null; owner: string | null; agents: string[] }) {
  if (papel && PAPEIS[papel]) return <RoleBadge papel={papel} />;
  if (!owner) return <span className="fj-owner vazio">—</span>;
  const ag = agents.includes(owner.toLowerCase());
  return (
    <span className={'fj-owner' + (ag ? ' agente' : '')} title={(ag ? 'Agente: ' : 'Humano: ') + owner}>
      {ag ? IcAgente : IcHumano}{owner}
    </span>
  );
}

export function LockIco() {
  return (
    <svg className="fj-lockico" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-label="bloqueada" role="img">
      <rect x="5" y="11" width="14" height="9" rx="2" /><path d="M8 11V7a4 4 0 0 1 8 0v4" />
    </svg>
  );
}

/**
 * `VincChip` — vínculo do issue. O protótipo tem quatro tipos (adr/pr/sessão/
 * tela) vindos do mock; `mcp_tasks` só carrega UM vínculo real hoje, o
 * `blocked_by`, então é o único que a tela projeta (`k="issue"`, sem prefixo —
 * a mesma forma que o protótipo usa pra vínculo entre issues).
 */
export function VincChip({ k, v, title }: { k: string; v: string; title?: string }) {
  const prefixo: Record<string, string> = { adr: 'ADR', pr: 'PR', sessao: 'ses', tela: 'tela', issue: '' };
  const ic = prefixo[k] ?? k;
  return (
    <span className={`fj-vinc fj-vinc-${k}`} title={title}>
      {ic && <span className="fj-vinc-k">{ic}</span>}{v}
    </span>
  );
}

export function Star({ on, onClick }: { on: boolean; onClick: () => void }) {
  return (
    <button type="button" className={'fj-star' + (on ? ' on' : '')} aria-pressed={on}
      onClick={(e) => { e.stopPropagation(); onClick(); }} title={on ? 'Desfavoritar' : 'Favoritar'} aria-label="Favoritar">
      <svg width="13" height="13" viewBox="0 0 24 24" fill={on ? 'currentColor' : 'none'} stroke="currentColor" strokeWidth="1.8" aria-hidden>
        <polygon points="12 2.5 15 9 22 9.6 16.5 14.2 18.2 21 12 17.3 5.8 21 7.5 14.2 2 9.6 9 9" />
      </svg>
    </button>
  );
}

export function Pin({ on, onClick }: { on: boolean; onClick: () => void }) {
  return (
    <button type="button" className={'fj-pin' + (on ? ' on' : '')} aria-pressed={on}
      onClick={(e) => { e.stopPropagation(); onClick(); }} title={on ? 'Soltar do topo' : 'Fixar no topo do grupo'} aria-label="Fixar no topo">
      <svg width="12" height="12" viewBox="0 0 24 24" fill={on ? 'currentColor' : 'none'} stroke="currentColor" strokeWidth="1.8" aria-hidden>
        <path d="M9 3h6l-1 6 3.5 3.5H6.5L10 9z" /><path d="M12 12.5V21" />
      </svg>
    </button>
  );
}

/**
 * `EpicRoll` — uma barra por sub-issue, colorida pela FASE; `n/N` = quantas
 * chegaram a F4. Só aparece em quem tem filhos (`epic_id` apontando pra cá).
 */
export function EpicRoll({ kids }: { kids: { task_id: string; display_id: string; forja_fase: string | null }[] }) {
  const done = kids.filter((k) => k.forja_fase === 'F4').length;
  return (
    <span className="fj-epic-roll" title={`${kids.length} sub-issues · ${done} em F4`}>
      <span className="fj-epic-bars">
        {kids.map((k) => (
          <i key={k.task_id} style={{ background: `oklch(0.58 0.13 ${FASE_HUE[k.forja_fase ?? ''] ?? 250})` }} title={`${k.display_id} · ${k.forja_fase ?? 'sem fase'}`} />
        ))}
      </span>
      <span className="fj-epic-n">{done}/{kids.length}</span>
    </span>
  );
}

/** Chevron do cabeçalho de grupo — gira quando o grupo colapsa. */
export function GroupChevron({ colapsado }: { colapsado: boolean }) {
  return (
    <span className="fj-group-chev" style={{ transform: colapsado ? 'rotate(-90deg)' : 'none' }}>
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden><path d="M6 9l6 6 6-6" /></svg>
    </span>
  );
}
