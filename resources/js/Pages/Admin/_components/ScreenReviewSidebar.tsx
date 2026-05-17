import * as React from 'react';
import { Layers, Folder } from 'lucide-react';
import { cn } from '@/Lib/utils';

/**
 * ScreenReviewSidebar — coluna 1 do tri-pane (port de kb/CategorySidebar)
 *
 * Wave 30 Agent B (W30-B). Lista módulos top-level (`Admin`, `Vestuario`, `Jana`)
 * com badges contagem PDCA por status. Botão "Todos" agrega tudo.
 */
export interface ModuleStatusCount {
  name: string;
  total: number;
  pending: number;
  approved: number;
  rejected: number;
  iterate: number;
}

interface Props {
  modules: ModuleStatusCount[];
  selectedModule: string | 'all';
  onSelect: (mod: string | 'all') => void;
  className?: string;
}

export default function ScreenReviewSidebar({
  modules,
  selectedModule,
  onSelect,
  className,
}: Props) {
  const totals = React.useMemo(
    () =>
      modules.reduce(
        (acc, m) => ({
          total: acc.total + m.total,
          pending: acc.pending + m.pending,
          approved: acc.approved + m.approved,
          rejected: acc.rejected + m.rejected,
          iterate: acc.iterate + m.iterate,
        }),
        { total: 0, pending: 0, approved: 0, rejected: 0, iterate: 0 },
      ),
    [modules],
  );

  return (
    <aside
      className={cn(
        'kb-side flex flex-col overflow-y-auto border-r border-border bg-card p-3 gap-4',
        className,
      )}
      aria-label="Módulos do projeto"
    >
      <section>
        <div className="mb-2 flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-muted-foreground">
          <Layers size={11} />
          Módulos do projeto
        </div>
        <ul className="space-y-1">
          <li>
            <button
              type="button"
              onClick={() => onSelect('all')}
              className={cn(
                'group flex w-full flex-col gap-1 rounded-md border px-2 py-1.5 text-left transition-colors',
                selectedModule === 'all'
                  ? 'border-primary/40 bg-primary/10'
                  : 'border-transparent hover:bg-accent',
              )}
              aria-pressed={selectedModule === 'all'}
            >
              <div className="flex items-center justify-between gap-2">
                <span className="text-[12.5px] font-medium text-foreground">
                  Todos os módulos
                </span>
                <span className="rounded-full border border-border bg-muted px-1.5 py-0.5 text-[10px] font-semibold tabular-nums text-muted-foreground">
                  {totals.total}
                </span>
              </div>
              <StatusBadges
                pending={totals.pending}
                approved={totals.approved}
                rejected={totals.rejected}
                iterate={totals.iterate}
              />
            </button>
          </li>
          {modules.map((m) => {
            const active = selectedModule === m.name;
            return (
              <li key={m.name}>
                <button
                  type="button"
                  onClick={() => onSelect(m.name)}
                  className={cn(
                    'group flex w-full flex-col gap-1 rounded-md border px-2 py-1.5 text-left transition-colors',
                    active
                      ? 'border-primary/40 bg-primary/10'
                      : 'border-transparent hover:bg-accent',
                  )}
                  aria-pressed={active}
                >
                  <div className="flex items-center justify-between gap-2">
                    <span className="flex min-w-0 items-center gap-1.5">
                      <Folder size={11} className="shrink-0 text-muted-foreground" />
                      <span className="truncate text-[12.5px] font-medium text-foreground">
                        {m.name}
                      </span>
                    </span>
                    <span
                      className={cn(
                        'shrink-0 rounded-full border px-1.5 py-0.5 text-[10px] font-semibold tabular-nums',
                        active
                          ? 'border-primary/40 bg-card text-foreground'
                          : 'border-border bg-muted text-muted-foreground',
                      )}
                    >
                      {m.total}
                    </span>
                  </div>
                  <StatusBadges
                    pending={m.pending}
                    approved={m.approved}
                    rejected={m.rejected}
                    iterate={m.iterate}
                  />
                </button>
              </li>
            );
          })}
          {modules.length === 0 && (
            <li className="px-2 py-3 text-[11px] italic text-muted-foreground">
              Nenhum módulo encontrado.
            </li>
          )}
        </ul>
      </section>
    </aside>
  );
}

function StatusBadges({
  pending,
  approved,
  rejected,
  iterate,
}: {
  pending: number;
  approved: number;
  rejected: number;
  iterate: number;
}) {
  return (
    <div className="flex flex-wrap items-center gap-1 text-[9.5px] font-medium tabular-nums">
      {pending > 0 && (
        <span
          className="rounded-md border border-border bg-muted px-1 py-0.5 text-muted-foreground"
          title="Pendentes Wagner"
        >
          {pending} pend
        </span>
      )}
      {approved > 0 && (
        <span
          className="rounded-md border border-emerald-300 bg-emerald-50 px-1 py-0.5 text-emerald-800 dark:border-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-200"
          title="Aprovadas"
        >
          {approved} apv
        </span>
      )}
      {iterate > 0 && (
        <span
          className="rounded-md border border-amber-300 bg-amber-50 px-1 py-0.5 text-amber-800 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-300"
          title="Em iteração"
        >
          {iterate} iter
        </span>
      )}
      {rejected > 0 && (
        <span
          className="rounded-md border border-destructive/40 bg-destructive/10 px-1 py-0.5 text-destructive"
          title="Rejeitadas"
        >
          {rejected} rej
        </span>
      )}
    </div>
  );
}
