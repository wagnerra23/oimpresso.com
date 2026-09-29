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

/**
 * Data-hora absoluta BR: "2026-08-19 21:30" → "19/08/2026 21:30".
 *
 * Parse por STRING, sem `new Date()`: o servidor já entrega o horário no fuso da empresa
 * (ex.: `format('Y-m-d H:i')`), e passar por `Date` reaplicaria o fuso do NAVEGADOR —
 * outro relógio na tela de quem está em outro fuso. Aceita separador espaço ou `T`
 * e ignora segundos/fuso depois dos minutos.
 *
 * - `null`/vazio   → "—"
 * - fora do padrão → devolve a string original (não inventa data)
 */
export function fmtDataHoraBr(ymdHi: string | null | undefined): string {
  if (!ymdHi) return '—';
  const m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/.exec(ymdHi);
  if (!m) return ymdHi;
  const [, y, mo, d, h, mi] = m;
  return `${d}/${mo}/${y} ${h}:${mi}`;
}

/**
 * Data BR: "2026-08-17" → "17/08/2026". Mesmo contrato do `fmtDataHoraBr` (parse por
 * string, sem `Date`, sem fuso): `null`/vazio → "—"; fora do padrão → a string original.
 */
export function fmtDataBr(ymd: string | null | undefined): string {
  if (!ymd) return '—';
  const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(ymd);
  if (!m) return ymd;
  const [, y, mo, d] = m;
  return `${d}/${mo}/${y}`;
}
