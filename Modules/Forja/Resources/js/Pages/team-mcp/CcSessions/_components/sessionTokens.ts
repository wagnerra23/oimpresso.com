// Forja PR-2 — tokens/helpers puros da tela CcSessions (sem JSX → react-refresh friendly).
// DS v6: SEM cor crua. Bolhas/status via tokens semânticos.

// fmtRelative é canônico em @/Lib/datetime-br (reuse > recria) — re-exportado
// pra manter o import único `./sessionTokens` da tela CcSessions.
export { fmtRelative } from '@/Lib/datetime-br';

export type SessionStatus = 'active' | 'closed' | 'archived';

export interface StatusMeta { label: string; dot: string }

const STATUS_META: Record<string, StatusMeta> = {
  active:   { label: 'Ativa',      dot: 'bg-success' },
  closed:   { label: 'Fechada',    dot: 'bg-foreground/40' },
  archived: { label: 'Arquivada',  dot: 'bg-foreground/20' },
};

export function sessionStatusMeta(s: string): StatusMeta {
  return STATUS_META[s] ?? { label: s, dot: 'bg-foreground/40' };
}

export const brl = (v: number) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v ?? 0);

export const num = (v: number) => new Intl.NumberFormat('pt-BR').format(v ?? 0);

export function fmtDateTime(iso: string | null): string {
  if (!iso) return '—';
  return new Date(iso).toLocaleString('pt-BR', {
    day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit',
  });
}

export function fmtDuration(start: string | null, end: string | null): string {
  if (!start) return '—';
  const startMs = new Date(start).getTime();
  const endMs = end ? new Date(end).getTime() : Date.now();
  const sec = Math.floor((endMs - startMs) / 1000);
  if (sec < 60) return `${sec}s`;
  if (sec < 3600) return `${Math.floor(sec / 60)}m${sec % 60}s`;
  return `${Math.floor(sec / 3600)}h${Math.floor((sec % 3600) / 60)}m`;
}

/** Estilo da bolha por tipo de mensagem — tokens semânticos /opacity (DS v6, sem cor crua). */
export function msgBubbleClass(type: string): string {
  switch (type) {
    case 'user':        return 'border-primary/20 bg-primary/5';
    case 'tool_use':    return 'border-info/20 bg-info/5';
    case 'tool_result': return 'border-success/20 bg-success/5';
    case 'hook':        return 'border-warning/20 bg-warning/5';
    case 'system':      return 'border-border bg-muted/40';
    case 'attachment':  return 'border-border bg-muted/30';
    default:            return 'border-border bg-muted/30'; // assistant
  }
}
