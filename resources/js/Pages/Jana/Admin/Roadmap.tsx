// @memcofre
//   tela: /ia/admin/roadmap
//   module: Jana
//   ondas: Onda 5 V1 (Roadmap timeline UI)
//   adrs: 0070 (Jira-style tasks), 0093 (multi-tenant Tier 0), 0110 (Cockpit V2)
//   tests: Modules/Jana/Tests/Feature/Roadmap/RoadmapControllerTest
//   status: draft (Wagner aprova charter pra ir pra live)
//   permissao: jana.mcp.tasks.read
//
// Renderiza Gantt cronológico das tasks do cycle ativo (default) com filtros
// cycle/owner/priority/module. Click task abre Sheet com detalhe + link MCP
// `tasks-detail`. Usa @svar-ui/react-gantt MIT v2.6.x.

import AppShellV2 from '@/Layouts/AppShellV2';
import { router } from '@inertiajs/react';
import { useMemo, useState, useCallback } from 'react';
import { JanaAreaHeader } from '@/Pages/Jana/components/JanaAreaHeader';
import { GitBranch } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import { Button } from '@/Components/ui/button';
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/Components/ui/sheet';
import { Badge } from '@/Components/ui/badge';
import { Gantt } from '@svar-ui/react-gantt';
import '@svar-ui/react-gantt/style.css';

// ---------------------------------------------------------------------------
// Types — espelham o payload do RoadmapController@index
// ---------------------------------------------------------------------------

type Priority = 'p0' | 'p1' | 'p2' | 'p3';
type TaskStatus =
  | 'backlog'
  | 'todo'
  | 'doing'
  | 'review'
  | 'done'
  | 'blocked'
  | 'cancelled';

interface Cycle {
  id: number;
  key: string;
  name: string | null;
  status: 'planning' | 'active' | 'closed';
  start_date: string;
  end_date: string;
  goal: string | null;
}

interface Task {
  id: number;
  task_id: string;
  identifier: string | null;
  module: string;
  title: string;
  description: string | null;
  status: TaskStatus;
  owner: string | null;
  priority: Priority | null;
  type: string | null;
  estimate_h: number | null;
  story_points: number | null;
  parent_task_id: number | null;
  cycle_id: number | null;
  project_id: number | null;
  blocked_by: Array<string | number>;
  due_date: string | null;
  started_at: string | null;
  completed_at: string | null;
  created_at: string | null;
  updated_at: string | null;
}

interface Filters {
  cycle: number | null;
  owner: string | null;
  priority: Priority | null;
  module: string | null;
}

interface Props {
  cycles: Cycle[];
  tasks: Task[];
  filters: Filters;
  owners: string[];
  modules: string[];
  active_cycle_id: number | null;
}

// ---------------------------------------------------------------------------
// Helpers — datas + mapping para shape ITask do SVAR Gantt
// ---------------------------------------------------------------------------

function parseDate(value: string | null): Date | null {
  if (!value) return null;
  const d = new Date(value);
  return isNaN(d.getTime()) ? null : d;
}

// SVAR Gantt task shape (subset de ITask):
//   { id, text, start, end, duration, parent, type, progress, ... }
function toGanttTasks(tasks: Task[], cycles: Cycle[]) {
  // Agrupa por module → cria "summary" tasks como parents.
  // Cycles com start/end definem o eixo X mas tasks têm seus próprios start.
  const groups = new Map<string, Task[]>();
  for (const t of tasks) {
    const key = t.module || 'Outros';
    if (!groups.has(key)) groups.set(key, []);
    groups.get(key)!.push(t);
  }

  const ganttTasks: Array<Record<string, unknown>> = [];

  let groupIdx = 1;
  for (const [moduleName, list] of groups.entries()) {
    const parentId = `g-${groupIdx}`;
    // Encontra range cobrindo todas as tasks do grupo (fallback hoje + 7d).
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const dates = list
      .map((t) => parseDate(t.started_at) || parseDate(t.created_at) || today)
      .concat(
        list
          .map((t) => parseDate(t.due_date) || parseDate(t.completed_at))
          .filter((d): d is Date => d !== null),
      );

    const minDate = dates.reduce((a, b) => (a < b ? a : b), dates[0] || today);
    const maxDate = dates.reduce(
      (a, b) => (a > b ? a : b),
      new Date(today.getTime() + 7 * 86_400_000),
    );

    ganttTasks.push({
      id: parentId,
      text: moduleName,
      start: minDate,
      end: maxDate,
      type: 'summary',
      open: true,
    });

    for (const t of list) {
      const start =
        parseDate(t.started_at) || parseDate(t.created_at) || today;
      const end =
        parseDate(t.completed_at) ||
        parseDate(t.due_date) ||
        new Date(start.getTime() + 3 * 86_400_000); // default 3d

      const progress =
        t.status === 'done' ? 1 : t.status === 'doing' || t.status === 'review' ? 0.5 : 0;

      ganttTasks.push({
        id: t.id,
        text: t.identifier ? `${t.identifier} — ${t.title}` : t.title,
        start,
        end,
        progress,
        parent: parentId,
        type: 'task',
        // payload extra (preservado pelo SVAR via spread index signature)
        $payload: t,
      });
    }
    groupIdx++;
  }

  return ganttTasks;
}

// Converte blocked_by[] em links SVAR { source, target, type: 'e2s' }.
function toGanttLinks(tasks: Task[]) {
  const idByTaskId = new Map<string, number>();
  for (const t of tasks) {
    idByTaskId.set(t.task_id, t.id);
    if (t.identifier) idByTaskId.set(t.identifier, t.id);
  }

  const links: Array<Record<string, unknown>> = [];
  let linkId = 1;
  for (const t of tasks) {
    if (!t.blocked_by || t.blocked_by.length === 0) continue;
    for (const blocker of t.blocked_by) {
      const sourceId = idByTaskId.get(String(blocker));
      if (sourceId) {
        links.push({
          id: linkId++,
          source: sourceId,
          target: t.id,
          type: 'e2s', // end-to-start = blocker termina, daí desbloqueia
        });
      }
    }
  }
  return links;
}

// ---------------------------------------------------------------------------
// Sub-component: drawer com detalhe + link MCP tasks-detail
// ---------------------------------------------------------------------------

function TaskDetailDrawer({
  task,
  open,
  onClose,
}: {
  task: Task | null;
  open: boolean;
  onClose: () => void;
}) {
  if (!task) return null;

  const mcpLink = `mcp://tasks-detail?task_id=${encodeURIComponent(task.task_id)}`;
  const sourcePath = task.task_id;

  const priorityTone: Record<Priority, string> = {
    p0: 'bg-rose-500/15 text-rose-700 dark:text-rose-300',
    p1: 'bg-amber-500/15 text-amber-700 dark:text-amber-300',
    p2: 'bg-sky-500/15 text-sky-700 dark:text-sky-300',
    p3: 'bg-muted text-muted-foreground',
  };

  return (
    <Sheet open={open} onOpenChange={(v) => !v && onClose()}>
      <SheetContent side="right" className="w-full sm:max-w-lg overflow-y-auto">
        <SheetHeader>
          <SheetTitle className="text-base font-semibold">
            {task.identifier ?? task.task_id} — {task.title}
          </SheetTitle>
          <SheetDescription className="flex flex-wrap gap-2 pt-2">
            <Badge variant="outline">{task.module}</Badge>
            <Badge variant="outline">{task.status}</Badge>
            {task.priority && (
              <Badge className={priorityTone[task.priority]}>
                {task.priority.toUpperCase()}
              </Badge>
            )}
            {task.owner && <Badge variant="outline">@{task.owner}</Badge>}
          </SheetDescription>
        </SheetHeader>

        <div className="mt-6 space-y-4 text-sm">
          {task.description && (
            <section>
              <h3 className="text-xs font-medium text-muted-foreground mb-1">
                Descrição
              </h3>
              <p className="whitespace-pre-line">{task.description}</p>
            </section>
          )}

          <section className="grid grid-cols-2 gap-3 text-xs">
            <div>
              <dt className="text-muted-foreground">Estimativa</dt>
              <dd>
                {task.story_points !== null
                  ? `${task.story_points} SP`
                  : task.estimate_h !== null
                    ? `${task.estimate_h}h`
                    : '—'}
              </dd>
            </div>
            <div>
              <dt className="text-muted-foreground">Tipo</dt>
              <dd>{task.type ?? '—'}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground">Due date</dt>
              <dd>{task.due_date ?? '—'}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground">Completed</dt>
              <dd>{task.completed_at ?? '—'}</dd>
            </div>
          </section>

          {task.blocked_by.length > 0 && (
            <section>
              <h3 className="text-xs font-medium text-muted-foreground mb-1">
                Bloqueada por
              </h3>
              <ul className="list-disc ml-4">
                {task.blocked_by.map((b, i) => (
                  <li key={i} className="font-mono text-xs">
                    {String(b)}
                  </li>
                ))}
              </ul>
            </section>
          )}

          <section className="border-t pt-4">
            <h3 className="text-xs font-medium text-muted-foreground mb-2">
              Aprofundar via MCP
            </h3>
            <div className="flex flex-col gap-2">
              <code className="rounded-md bg-muted/50 px-2 py-1 text-xs font-mono break-all">
                tasks-detail task_id:{sourcePath}
              </code>
              <a
                href={mcpLink}
                className="text-xs text-primary hover:underline"
                rel="noopener noreferrer"
              >
                Abrir no Claude Code (MCP) →
              </a>
            </div>
          </section>
        </div>
      </SheetContent>
    </Sheet>
  );
}

// ---------------------------------------------------------------------------
// Page principal
// ---------------------------------------------------------------------------

function RoadmapIndex(props: Props) {
  const { cycles, tasks, filters, owners, modules, active_cycle_id } = props;

  const [selectedTask, setSelectedTask] = useState<Task | null>(null);
  const [drawerOpen, setDrawerOpen] = useState(false);

  // useMemo evita re-cálculo a cada render do shell.
  const ganttTasks = useMemo(() => toGanttTasks(tasks, cycles), [tasks, cycles]);
  const ganttLinks = useMemo(() => toGanttLinks(tasks), [tasks]);

  // Escalas Gantt — semanas + dias, default Larissa 1280px friendly.
  const scales = useMemo(
    () => [
      { unit: 'week', step: 1, format: 'w%W' },
      { unit: 'day', step: 1, format: 'd' },
    ],
    [],
  );

  const aplicarFiltro = useCallback(
    (patch: Partial<Filters>) => {
      router.get(
        '/ia/admin/roadmap',
        { ...filters, ...patch },
        { preserveState: true, preserveScroll: true, replace: true },
      );
    },
    [filters],
  );

  // Click em tarefa do Gantt → abre drawer (busca task original via id numérico).
  const handleTaskClick = useCallback(
    (ev: { action: string; data: { id?: string | number } }) => {
      if (ev.action !== 'select-task' && ev.action !== 'task-select') return;
      const id = ev.data?.id;
      if (typeof id !== 'number') return;
      const task = tasks.find((t) => t.id === id);
      if (task) {
        setSelectedTask(task);
        setDrawerOpen(true);
      }
    },
    [tasks],
  );

  const cycleAtual = cycles.find((c) => c.id === filters.cycle);

  return (
    <>
      <JanaAreaHeader active="roadmap" />

      {/* Title local da tela — preservado pós-migração JanaAreaHeader (Wagner 2026-05-25) */}
      <div className="px-6 pt-6 flex items-start justify-between gap-4">
        <div className="flex items-center gap-3">
          <GitBranch className="size-6 text-primary" />
          <div>
            <h1 className="text-xl font-semibold">Roadmap</h1>
            <p className="text-sm text-muted-foreground">
              {cycleAtual
                ? `${cycleAtual.key} — ${cycleAtual.goal ?? 'sem goal definido'}`
                : 'Visão cronológica de cycles e tasks (MCP)'}
            </p>
          </div>
        </div>
        <div className="text-xs text-muted-foreground text-right shrink-0">
          <div>
            {tasks.length} task{tasks.length === 1 ? '' : 's'} no filtro atual
          </div>
          {active_cycle_id && (
            <div className="text-[10px]">
              Cycle ativo:{' '}
              {cycles.find((c) => c.id === active_cycle_id)?.key ?? '—'}
            </div>
          )}
        </div>
      </div>

      {/* Filtros */}
      <Card className="mt-6 mb-4">
        <CardContent className="pt-6 flex flex-col md:flex-row gap-3 md:items-end flex-wrap">
          <div className="flex-1 min-w-[180px]">
            <label className="text-xs font-medium text-muted-foreground block mb-1">
              Cycle
            </label>
            <Select
              value={String(filters.cycle ?? 'current')}
              onValueChange={(v) =>
                aplicarFiltro({
                  cycle: v === 'current' ? null : Number(v) || null,
                })
              }
            >
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="current">Cycle ativo</SelectItem>
                {cycles.map((c) => (
                  <SelectItem key={c.id} value={String(c.id)}>
                    {c.key} {c.status === 'active' ? '(ativo)' : `(${c.status})`}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div className="flex-1 min-w-[140px]">
            <label className="text-xs font-medium text-muted-foreground block mb-1">
              Owner
            </label>
            <Select
              value={filters.owner ?? '__all__'}
              onValueChange={(v) =>
                aplicarFiltro({ owner: v === '__all__' ? null : v })
              }
            >
              <SelectTrigger>
                <SelectValue placeholder="Todos" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="__all__">Todos</SelectItem>
                {owners.map((o) => (
                  <SelectItem key={o} value={o}>
                    @{o}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div className="flex-1 min-w-[140px]">
            <label className="text-xs font-medium text-muted-foreground block mb-1">
              Priority
            </label>
            <Select
              value={filters.priority ?? '__all__'}
              onValueChange={(v) =>
                aplicarFiltro({
                  priority: v === '__all__' ? null : (v as Priority),
                })
              }
            >
              <SelectTrigger>
                <SelectValue placeholder="Todas" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="__all__">Todas</SelectItem>
                <SelectItem value="p0">P0 — crítico</SelectItem>
                <SelectItem value="p1">P1 — alto</SelectItem>
                <SelectItem value="p2">P2 — médio</SelectItem>
                <SelectItem value="p3">P3 — baixo</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div className="flex-1 min-w-[160px]">
            <label className="text-xs font-medium text-muted-foreground block mb-1">
              Módulo
            </label>
            <Select
              value={filters.module ?? '__all__'}
              onValueChange={(v) =>
                aplicarFiltro({ module: v === '__all__' ? null : v })
              }
            >
              <SelectTrigger>
                <SelectValue placeholder="Todos" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="__all__">Todos</SelectItem>
                {modules.map((m) => (
                  <SelectItem key={m} value={m}>
                    {m}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          {(filters.owner ||
            filters.priority ||
            filters.module ||
            filters.cycle) && (
            <Button
              type="button"
              size="sm"
              variant="ghost"
              onClick={() =>
                aplicarFiltro({
                  cycle: null,
                  owner: null,
                  priority: null,
                  module: null,
                })
              }
            >
              Limpar filtros
            </Button>
          )}
        </CardContent>
      </Card>

      {/* Gantt */}
      <Card>
        <CardHeader>
          <CardTitle className="text-sm font-semibold">
            Timeline ({ganttTasks.length} linha
            {ganttTasks.length === 1 ? '' : 's'})
          </CardTitle>
        </CardHeader>
        <CardContent className="p-0">
          {tasks.length === 0 ? (
            <div className="text-center text-sm text-muted-foreground py-16">
              Sem tasks no filtro atual.
            </div>
          ) : (
            <div
              data-testid="roadmap-gantt"
              className="wx-gantt-wrapper"
              style={{ height: '600px' }}
            >
              <Gantt
                tasks={ganttTasks as never}
                links={ganttLinks as never}
                scales={scales as never}
                readonly
                cellBorders="full"
                init={(api) => {
                  api.on('select-task', handleTaskClick);
                }}
              />
            </div>
          )}
        </CardContent>
      </Card>

      <TaskDetailDrawer
        task={selectedTask}
        open={drawerOpen}
        onClose={() => setDrawerOpen(false)}
      />
    </>
  );
}

export default function RoadmapPage(props: Props) {
  return (
    <AppShellV2
      title="Jana — Roadmap"
      breadcrumbItems={[{ label: 'Jana' }, { label: 'Admin' }, { label: 'Roadmap' }]}
    >
      <RoadmapIndex {...props} />
    </AppShellV2>
  );
}
