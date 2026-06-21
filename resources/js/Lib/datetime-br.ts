// Helpers de formatação de data/hora BR — exibição relativa.
//
// Canônico ÚNICO de `fmtRelative` (chave do ratchet reuse-index: util:fmtRelative).
// Antes vivia duplicado em `kb/_lib/helpers.ts` e `team-mcp/CcSessions/_components/sessionTokens.ts`;
// extraído pra cá pra reusar em vez de recriar (reuse > recria · MANUAL-CSS-JS #5 · ADR 0240).

/**
 * Distância relativa humana em pt-BR a partir de um ISO timestamp.
 *   "agora" · "5min atrás" · "3h atrás" · "2d atrás" · "16/06 14:30" (>7d)
 *
 * - `null`        → "—"
 * - data inválida → devolve a string original (compat seeds com valor já relativo)
 */
export function fmtRelative(iso: string | null): string {
  if (!iso) return '—';
  const d = new Date(iso).getTime();
  if (Number.isNaN(d)) return iso; // já é relativo (compat seed)
  const diffSec = (Date.now() - d) / 1000;
  if (diffSec < 60) return 'agora';
  if (diffSec < 3600) return `${Math.floor(diffSec / 60)}min atrás`;
  if (diffSec < 86_400) return `${Math.floor(diffSec / 3600)}h atrás`;
  if (diffSec < 86_400 * 7) return `${Math.floor(diffSec / 86_400)}d atrás`;
  return new Date(iso).toLocaleString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  });
}
