// Constantes compartilhadas do Board (Project Mgmt) — ADR 0070.
//
// Mantém parity com os badges usados no Kanban antigo (/team-mcp/tasks),
// mas centraliza num único local pra que BoardColumn/TaskCard/Backlog/Roadmap
// não dupliquem mapeamento de cor.

export type Status = 'backlog' | 'todo' | 'doing' | 'review' | 'done' | 'blocked' | 'cancelled';
export type Priority = 'p0' | 'p1' | 'p2' | 'p3';

export const PRIORITY_BADGE: Record<Priority, string> = {
  p0: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
  p1: 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300',
  p2: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
  p3: 'bg-slate-50 text-slate-400 dark:bg-slate-800/50 dark:text-slate-500',
};

export const STATUS_BADGE: Record<Status, string> = {
  backlog:   'bg-slate-50 text-slate-500 dark:bg-slate-900 dark:text-slate-400',
  todo:      'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
  doing:     'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
  review:    'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
  done:      'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
  blocked:   'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
  cancelled: 'bg-slate-200 text-slate-400 dark:bg-slate-800 dark:text-slate-500',
};

export const COLUMN_BORDER: Record<Status, string> = {
  backlog:   'border-slate-300',
  todo:      'border-slate-400',
  doing:     'border-blue-500',
  review:    'border-amber-500',
  done:      'border-emerald-500',
  blocked:   'border-red-500',
  cancelled: 'border-slate-200',
};

export const COLUMN_LABEL_PT: Record<Status, string> = {
  backlog:   'Backlog',
  todo:      'A fazer',
  doing:     'Fazendo',
  review:    'Revisão',
  done:      'Concluído',
  blocked:   'Bloqueado',
  cancelled: 'Cancelado',
};

/**
 * "Mover pra direita" — atalho `E`. Avança no workflow canônico.
 * Mantém a posição se já está em done/cancelled.
 */
export function nextStatus(current: Status): Status {
  const flow: Status[] = ['backlog', 'todo', 'doing', 'review', 'done'];
  const i = flow.indexOf(current);
  if (i < 0 || i === flow.length - 1) return current;
  return flow[i + 1];
}

/**
 * "Mover pra esquerda" — atalho `A`. Volta no workflow canônico.
 * Mantém a posição se já está no backlog.
 */
export function prevStatus(current: Status): Status {
  const flow: Status[] = ['backlog', 'todo', 'doing', 'review', 'done'];
  const i = flow.indexOf(current);
  if (i <= 0) return current;
  return flow[i - 1];
}
