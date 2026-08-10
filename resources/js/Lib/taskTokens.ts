// Forja PR-1 — tokens/helpers puros da tela Tasks (sem JSX → fast-refresh friendly).
// DS v6: hues Forja → tokens semânticos.
//   P0 destructive(25°) · P1 warning(60°) · P2 primary/roxo(295°) · P3 info(250°).

export type Priority = 'p0' | 'p1' | 'p2' | 'p3';

export const PRIO_DOT: Record<Priority, string> = {
  p0: 'bg-destructive',
  p1: 'bg-warning',
  p2: 'bg-primary',
  p3: 'bg-info',
};

export const PRIO_LABEL: Record<Priority, string> = {
  p0: 'P0', p1: 'P1', p2: 'P2', p3: 'P3',
};

export interface StatusMeta { label: string; dot: string }

// Dot colorido (token de cor garantido: info/warning/success/destructive/foreground).
const STATUS_META: Record<string, StatusMeta> = {
  backlog:   { label: 'Backlog',   dot: 'bg-foreground/25' },
  todo:      { label: 'A fazer',   dot: 'bg-foreground/40' },
  doing:     { label: 'Fazendo',   dot: 'bg-info' },
  review:    { label: 'Revisão',   dot: 'bg-warning' },
  done:      { label: 'Concluído', dot: 'bg-success' },
  blocked:   { label: 'Bloqueada', dot: 'bg-destructive' },
  cancelled: { label: 'Cancelada', dot: 'bg-foreground/20' },
};

export function statusMeta(status: string): StatusMeta {
  return STATUS_META[status] ?? { label: status, dot: 'bg-foreground/40' };
}

/** Ordem canônica de status (ativos primeiro) — usada em agrupamento e sort. */
export const STATUS_ORDER = ['doing', 'review', 'todo', 'blocked', 'backlog', 'done', 'cancelled'];
