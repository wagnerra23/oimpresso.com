// Card individual no Board (Project Mgmt) — ADR 0070.
//
// Usado por BoardColumn (Kanban) e futuramente Backlog/Roadmap.
// Mantém compatibilidade visual com /team-mcp/tasks antigo, adiciona:
//   - identifier Linear-style (display_id) — fallback pro task_id
//   - due_date com flag visual de overdue
//   - is_blocked overlay (badge vermelho dedicado)
//
// Ver memory/decisions/0070-jira-style-task-management-current-md-removed.md

import { AlertCircle, Calendar, Lock } from 'lucide-react';
import { PRIORITY_BADGE, type Priority, type Status } from './badges';

export interface BoardTask {
  task_id: string;
  identifier: string | null;
  display_id: string;
  title: string;
  module: string;
  owner: string | null;
  priority: Priority;
  status: Status;
  type: string | null;
  estimate_h: number | null;
  story_points: number | null;
  due_date: string | null;
  epic_id: number | null;
  cycle_id: number | null;
  component_id: number | null;
  blocked_by: string[];
  is_blocked: boolean;
  is_overdue: boolean;
  /** Timestamp Unix (segundos) — usado pra optimistic-lock 409 conflict (PMG-001, ADR 0100). */
  updated_at?: number;
}

interface TaskCardProps {
  task: BoardTask;
  selected?: boolean;
  onDragStart?: (id: string) => void;
  onClick?: (task: BoardTask) => void;
}

export default function TaskCard({ task, selected, onDragStart, onClick }: TaskCardProps) {
  const dueShort = task.due_date
    ? new Date(task.due_date + 'T00:00:00').toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' })
    : null;

  return (
    <div
      draggable={!!onDragStart}
      onDragStart={onDragStart ? () => onDragStart(task.task_id) : undefined}
      onClick={onClick ? () => onClick(task) : undefined}
      data-task-id={task.task_id}
      data-selected={selected ? 'true' : 'false'}
      className={[
        'bg-card border rounded-lg p-3 select-none transition-all',
        onDragStart ? 'cursor-grab active:cursor-grabbing' : '',
        onClick ? 'cursor-pointer' : '',
        'shadow-sm hover:shadow-md',
        selected ? 'ring-2 ring-blue-400 ring-offset-1' : '',
        task.is_blocked ? 'border-red-300 bg-red-50/40 dark:bg-red-900/10' : '',
      ].filter(Boolean).join(' ')}
    >
      <div className="flex items-start justify-between gap-2 mb-1">
        <span className="text-[10px] font-mono text-muted-foreground truncate">
          {task.display_id}
        </span>
        <span
          className={`text-[10px] font-semibold px-1.5 py-0.5 rounded ${
            PRIORITY_BADGE[task.priority] ?? PRIORITY_BADGE.p2
          }`}
        >
          {task.priority.toUpperCase()}
        </span>
      </div>

      <p className="text-xs font-medium leading-tight mb-2 line-clamp-3">{task.title}</p>

      <div className="flex flex-wrap items-center gap-1">
        {task.is_blocked && (
          <span className="inline-flex items-center gap-1 text-[10px] font-semibold px-1.5 py-0.5 rounded bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300">
            <Lock size={10} />
            blocked
          </span>
        )}

        <span className="text-[10px] bg-muted px-1.5 py-0.5 rounded">{task.module}</span>

        {task.owner && (
          <span className="text-[10px] bg-muted px-1.5 py-0.5 rounded">@{task.owner}</span>
        )}

        {task.estimate_h !== null && task.estimate_h > 0 && (
          <span className="text-[10px] text-muted-foreground">{task.estimate_h}h</span>
        )}

        {task.story_points !== null && task.story_points > 0 && (
          <span className="text-[10px] text-muted-foreground">{task.story_points}sp</span>
        )}

        {dueShort && (
          <span
            className={`inline-flex items-center gap-1 text-[10px] ${
              task.is_overdue ? 'text-red-600 font-semibold' : 'text-muted-foreground'
            }`}
            title={task.is_overdue ? 'Atrasada' : 'Prazo'}
          >
            {task.is_overdue ? <AlertCircle size={10} /> : <Calendar size={10} />}
            {dueShort}
          </span>
        )}

        {task.blocked_by.length > 0 && !task.is_blocked && (
          <span className="text-[10px] text-amber-600">
            ↶ {task.blocked_by.join(', ')}
          </span>
        )}
      </div>
    </div>
  );
}
