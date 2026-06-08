// Coluna do Kanban (Project Mgmt) — ADR 0070.
//
// Container drop-target. A página dona faz a chamada PATCH otimista.

import { useState, type ReactNode } from 'react';
import TaskCard, { type BoardTask } from './TaskCard';
import { COLUMN_BORDER, COLUMN_LABEL_PT, type Status } from './badges';

interface BoardColumnProps {
  status: Status;
  tasks: BoardTask[];
  selectedTaskId?: string | null;
  onDrop: (status: Status) => void;
  onDragStart: (taskId: string) => void;
  onCardClick?: (task: BoardTask) => void;
  /** Render slot extra acima dos cards (botões, contador animado, etc) */
  header?: ReactNode;
}

export default function BoardColumn({
  status,
  tasks,
  selectedTaskId,
  onDrop,
  onDragStart,
  onCardClick,
  header,
}: BoardColumnProps) {
  const [draggingOver, setDraggingOver] = useState(false);

  return (
    <div
      data-column-status={status}
      className={[
        'rounded-xl border-t-4 bg-muted/30 p-3 min-h-[300px] transition-colors',
        COLUMN_BORDER[status] ?? 'border-slate-300',
        draggingOver ? 'bg-muted/60 ring-2 ring-blue-400' : '',
      ].filter(Boolean).join(' ')}
      onDragOver={(e) => {
        e.preventDefault();
        setDraggingOver(true);
      }}
      onDragLeave={() => setDraggingOver(false)}
      onDrop={() => {
        setDraggingOver(false);
        onDrop(status);
      }}
    >
      <div className="flex items-center justify-between mb-3">
        <span className="text-xs font-semibold uppercase tracking-wide">
          {COLUMN_LABEL_PT[status] ?? status}
        </span>
        <span className="text-xs text-muted-foreground font-mono">{tasks.length}</span>
      </div>

      {header}

      <div className="flex flex-col gap-2">
        {tasks.map((t) => (
          <TaskCard
            key={t.task_id}
            task={t}
            selected={t.task_id === selectedTaskId}
            onDragStart={onDragStart}
            onClick={onCardClick}
          />
        ))}
        {tasks.length === 0 && (
          <div className="text-xs text-muted-foreground text-center py-8 border-2 border-dashed rounded-lg">
            vazio
          </div>
        )}
      </div>
    </div>
  );
}
