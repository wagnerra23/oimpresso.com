// @memcofre
//   tela: /forja/roadmap-gantt
//   module: Forja
//   charter: resources/js/Pages/Forja/Roadmap/Gantt.charter.md
//   runbook: memory/requisitos/Forja/RUNBOOK-gantt.md
//   adrs: 0070 (Jira-style tasks), 0087 (URL/permission congeladas), 0093 (multi-tenant
//         Tier 0), 0110 (Cockpit V2), 0253 (primitivos de layout), 0366 §D-B + 0367 D4
//         (fronteira Jana→Forja)
//   tests: Modules/Forja/Tests/Feature/Roadmap/RoadmapGanttControllerTest
//   status: draft ([W] aprova o charter pra ir pra live)
//   permissao: jana.mcp.tasks.read (leitura) · jana.mcp.tasks.write (reagendar prazo)
//
// Gantt cronológico das tasks do cycle (default = ativo) com filtros cycle/owner/
// priority/module. Click na barra abre Sheet com detalhe + snippet MCP `tasks-detail`.
// Com write, arrastar a barra reagenda o PRAZO. Usa @svar-ui/react-gantt MIT v2.6.x.
//
// ⚠️ NÃO é o quarter view. `Forja/Roadmap/Index` (/project-mgmt/roadmap) agrupa EPICS
// por trimestre e continua vivo por decisão [W] (ADR 0367 D7) — por isso esta tela é
// `Gantt.tsx` e não `Index.tsx`. Recibo da não-duplicação em
// memory/sessions/2026-08-05-duplicacao-roadmap-forja.md.

import AppShellV2 from '@/Layouts/AppShellV2';
import ForjaHub from '../../team-mcp/Forja/_components/ForjaHub';
import { router } from '@inertiajs/react';
import { useMemo, useState, useCallback } from 'react';
import { PageHeader } from '@/Components/PageHeader';
import { Box, Grid, Inline, Stack, Text } from '@/Components/layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
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
// Types — espelham o payload do RoadmapGanttController@index
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
  /** CLOSURE no controller (nunca defer) — chega sempre no load cheio. Ver RUNBOOK §3. */
  owners: string[];
  /** idem `owners`. */
  modules: string[];
  active_cycle_id: number | null;
  /** US-COPI-111 B2: habilita drag-drop reschedule (só com jana.mcp.tasks.write). */
  can_edit?: boolean;
}

const ROTA = '/forja/roadmap-gantt';
const SENTINELA_TODOS = '__all__';
const UM_DIA_MS = 86_400_000;

/** Tons de prioridade por TOKEN semântico do DS (nunca palette cru — ds/no-raw-palette-color). */
const PRIORITY_VARIANT: Record<Priority, 'danger' | 'warning' | 'info' | 'neutral'> = {
  p0: 'danger',
  p1: 'warning',
  p2: 'info',
  p3: 'neutral',
};

// ---------------------------------------------------------------------------
// Helpers — datas + mapping para o shape ITask do SVAR Gantt
// ---------------------------------------------------------------------------

function parseDate(value: string | null): Date | null {
  if (!value) return null;
  const d = new Date(value);
  return isNaN(d.getTime()) ? null : d;
}

/** Formata Date → 'YYYY-MM-DD' (o backend valida `date`; evita ambiguidade de fuso). */
function toIsoDate(d: Date): string {
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}

// SVAR Gantt task shape (subset de ITask):
//   { id, text, start, end, duration, parent, type, progress, ... }
// Agrupa por module → cria "summary" tasks como parents com id STRING ('g-N'), o que
// é justamente o que distingue parent de task real no handler de reschedule.
function toGanttTasks(tasks: Task[]) {
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
    // Range cobrindo todas as tasks do grupo (fallback hoje + 7d).
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
      new Date(today.getTime() + 7 * UM_DIA_MS),
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
      const start = parseDate(t.started_at) || parseDate(t.created_at) || today;
      const end =
        parseDate(t.completed_at) ||
        parseDate(t.due_date) ||
        new Date(start.getTime() + 3 * UM_DIA_MS); // default 3d

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

/** Converte blocked_by[] em links SVAR { source, target, type: 'e2s' }. */
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
// Sub-component: drawer com detalhe + snippet MCP tasks-detail
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

  return (
    <Sheet open={open} onOpenChange={(v) => !v && onClose()}>
      <SheetContent side="right" className="w-full sm:max-w-lg overflow-y-auto">
        <SheetHeader>
          <SheetTitle className="text-base font-semibold">
            {task.identifier ?? task.task_id} — {task.title}
          </SheetTitle>
          <SheetDescription>Detalhe da tarefa no roadmap do time.</SheetDescription>
        </SheetHeader>

        <Stack gap={4} className="px-4 pb-6 text-sm">
          <Inline gap={2} wrap>
            <Badge variant="outline">{task.module}</Badge>
            <Badge variant="outline">{task.status}</Badge>
            {task.priority && (
              <Badge variant={PRIORITY_VARIANT[task.priority]}>
                {task.priority.toUpperCase()}
              </Badge>
            )}
            {task.owner && <Badge variant="outline">@{task.owner}</Badge>}
          </Inline>

          {task.description && (
            <Stack gap={1} asChild>
              <section>
                <Text as="h3" size="xs" weight="medium" tone="muted">
                  Descrição
                </Text>
                <Text as="p" size="sm" className="whitespace-pre-line">
                  {task.description}
                </Text>
              </section>
            </Stack>
          )}

          <Grid cols={2} gap={3}>
            <Stack gap={0}>
              <Text as="span" size="xs" tone="muted">
                Estimativa
              </Text>
              <Text as="span" size="xs" numeric="tabular">
                {task.story_points !== null
                  ? `${task.story_points} SP`
                  : task.estimate_h !== null
                    ? `${task.estimate_h}h`
                    : '—'}
              </Text>
            </Stack>
            <Stack gap={0}>
              <Text as="span" size="xs" tone="muted">
                Tipo
              </Text>
              <Text as="span" size="xs">
                {task.type ?? '—'}
              </Text>
            </Stack>
            <Stack gap={0}>
              <Text as="span" size="xs" tone="muted">
                Prazo
              </Text>
              <Text as="span" size="xs" numeric="tabular">
                {task.due_date ?? '—'}
              </Text>
            </Stack>
            <Stack gap={0}>
              <Text as="span" size="xs" tone="muted">
                Concluída em
              </Text>
              <Text as="span" size="xs" numeric="tabular">
                {task.completed_at ?? '—'}
              </Text>
            </Stack>
          </Grid>

          {task.blocked_by.length > 0 && (
            <Stack gap={1} asChild>
              <section>
                <Text as="h3" size="xs" weight="medium" tone="muted">
                  Bloqueada por
                </Text>
                <ul className="list-disc ml-4">
                  {task.blocked_by.map((b, i) => (
                    <li key={i} className="font-mono text-xs">
                      {String(b)}
                    </li>
                  ))}
                </ul>
              </section>
            </Stack>
          )}

          <Stack gap={2} asChild>
            <section className="border-t pt-4">
              <Text as="h3" size="xs" weight="medium" tone="muted">
                Aprofundar via MCP
              </Text>
              <code className="rounded-md bg-muted/50 px-2 py-1 text-xs font-mono break-all">
                tasks-detail task_id:{task.task_id}
              </code>
            </section>
          </Stack>
        </Stack>
      </SheetContent>
    </Sheet>
  );
}

// ---------------------------------------------------------------------------
// Page principal
// ---------------------------------------------------------------------------

function RoadmapGantt(props: Props) {
  const { cycles, tasks, filters, owners, modules, active_cycle_id, can_edit = false } = props;

  const [selectedTask, setSelectedTask] = useState<Task | null>(null);
  const [drawerOpen, setDrawerOpen] = useState(false);

  // useMemo evita re-cálculo a cada render do shell.
  const ganttTasks = useMemo(() => toGanttTasks(tasks), [tasks]);
  const ganttLinks = useMemo(() => toGanttLinks(tasks), [tasks]);

  // Escalas Gantt — semanas + dias, default 1280px friendly.
  const scales = useMemo(
    () => [
      { unit: 'week', step: 1, format: 'w%W' },
      { unit: 'day', step: 1, format: 'd' },
    ],
    [],
  );

  const aplicarFiltro = useCallback(
    (patch: Partial<Filters>) => {
      // D-14: partial reload — só re-busca o que muda com filtro (ref PR PR 3889).
      // cycles/owners/modules (fontes dos dropdowns) não trafegam de novo.
      router.get(
        ROTA,
        { ...filters, ...patch },
        {
          preserveState: true,
          preserveScroll: true,
          replace: true,
          only: ['tasks', 'filters'],
        },
      );
    },
    [filters],
  );

  // Click em tarefa do Gantt → abre drawer (busca task original via id numérico).
  //
  // O handler aceita AS DUAS formas de payload: `{ action, data: { id } }` (a que o
  // handler de origem no Jana lia) e `{ id }` (a que os tipos do SVAR v2 declaram pro
  // callback de `select-task` — o tipo da lib não tem `action`/`data`, e tipar só a
  // primeira forma quebra o `tsc`). Superset do comportamento antigo: nada que abria
  // antes deixa de abrir. `api.on('select-task')` só dispara nesse evento, então
  // aceitar o payload sem `action` não amplia o gatilho.
  const handleTaskClick = useCallback(
    (ev: { id?: string | number; action?: string; data?: { id?: string | number } }) => {
      if (ev.action && ev.action !== 'select-task' && ev.action !== 'task-select') return;
      const id = ev.data?.id ?? ev.id;
      if (typeof id !== 'number') return;
      const task = tasks.find((t) => t.id === id);
      if (task) {
        setSelectedTask(task);
        setDrawerOpen(true);
      }
    },
    [tasks],
  );

  // US-COPI-111 B2: drag/resize da barra → reagenda o PRAZO.
  // SVAR dispara `update-task` no commit do drag com a task já com as datas novas.
  // Persistimos APENAS `due_date` (a ponta editável — started_at é lifecycle-managed
  // no backend). Summary parents têm id string ('g-N') → ignorados. Partial reload
  // (only: tasks) re-renderiza o Gantt com a data persistida.
  const handleReschedule = useCallback(
    (ev: { id?: string | number; task?: { end?: Date | string } }) => {
      const id = ev.id;
      if (typeof id !== 'number') return; // ignora summary parents ('g-N')
      const task = tasks.find((t) => t.id === id);
      const end = ev.task?.end;
      if (!task || !end) return;
      const dueDate = end instanceof Date ? toIsoDate(end) : toIsoDate(new Date(end));
      router.patch(
        `${ROTA}/tasks/${encodeURIComponent(task.task_id)}/schedule`,
        { due_date: dueDate },
        { preserveState: true, preserveScroll: true, only: ['tasks'] },
      );
    },
    [tasks],
  );

  const cycleAtual = cycles.find((c) => c.id === filters.cycle);
  const cycleAtivoKey = cycles.find((c) => c.id === active_cycle_id)?.key ?? '—';
  const temFiltro = Boolean(
    filters.owner || filters.priority || filters.module || filters.cycle,
  );

  return (
    <>
      <PageHeader
        title="Roadmap"
        suffix=" · Gantt"
        subtitle={
          <>
            {cycleAtual
              ? `${cycleAtual.key} — ${cycleAtual.goal ?? 'sem goal definido'}`
              : 'Visão cronológica de cycles e tarefas do time'}
            {' · '}
            {tasks.length} tarefa{tasks.length === 1 ? '' : 's'} no filtro
            {active_cycle_id ? ` · cycle ativo ${cycleAtivoKey}` : ''}
          </>
        }
      />

      <Box px={6} py={6}>
        <Stack gap={4}>
          {/* Filtros */}
          <Card>
            <CardContent>
              <Inline gap={3} align="end" wrap>
                <Box className="min-w-[180px]">
                  <label
                    htmlFor="rg-cycle"
                    className="text-xs font-medium text-muted-foreground block mb-1"
                  >
                    Cycle
                  </label>
                  <Select
                    value={String(filters.cycle ?? 'current')}
                    onValueChange={(v) =>
                      aplicarFiltro({ cycle: v === 'current' ? null : Number(v) || null })
                    }
                  >
                    <SelectTrigger id="rg-cycle">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="current">Cycle ativo</SelectItem>
                      {cycles.map((c) => (
                        <SafeSelectItem key={c.id} value={String(c.id)}>
                          {c.key} {c.status === 'active' ? '(ativo)' : `(${c.status})`}
                        </SafeSelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </Box>

                <Box className="min-w-[160px]">
                  <label
                    htmlFor="rg-owner"
                    className="text-xs font-medium text-muted-foreground block mb-1"
                  >
                    Responsável
                  </label>
                  <Select
                    value={filters.owner ?? SENTINELA_TODOS}
                    onValueChange={(v) =>
                      aplicarFiltro({ owner: v === SENTINELA_TODOS ? null : v })
                    }
                  >
                    <SelectTrigger id="rg-owner">
                      <SelectValue placeholder="Todos" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value={SENTINELA_TODOS}>Todos</SelectItem>
                      {owners.map((o) => (
                        <SafeSelectItem key={o} value={o}>
                          @{o}
                        </SafeSelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </Box>

                <Box className="min-w-[160px]">
                  <label
                    htmlFor="rg-priority"
                    className="text-xs font-medium text-muted-foreground block mb-1"
                  >
                    Prioridade
                  </label>
                  <Select
                    value={filters.priority ?? SENTINELA_TODOS}
                    onValueChange={(v) =>
                      aplicarFiltro({
                        priority: v === SENTINELA_TODOS ? null : (v as Priority),
                      })
                    }
                  >
                    <SelectTrigger id="rg-priority">
                      <SelectValue placeholder="Todas" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value={SENTINELA_TODOS}>Todas</SelectItem>
                      <SelectItem value="p0">P0 — crítico</SelectItem>
                      <SelectItem value="p1">P1 — alto</SelectItem>
                      <SelectItem value="p2">P2 — médio</SelectItem>
                      <SelectItem value="p3">P3 — baixo</SelectItem>
                    </SelectContent>
                  </Select>
                </Box>

                <Box className="min-w-[180px]">
                  <label
                    htmlFor="rg-module"
                    className="text-xs font-medium text-muted-foreground block mb-1"
                  >
                    Módulo
                  </label>
                  <Select
                    value={filters.module ?? SENTINELA_TODOS}
                    onValueChange={(v) =>
                      aplicarFiltro({ module: v === SENTINELA_TODOS ? null : v })
                    }
                  >
                    <SelectTrigger id="rg-module">
                      <SelectValue placeholder="Todos" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value={SENTINELA_TODOS}>Todos</SelectItem>
                      {modules.map((m) => (
                        <SafeSelectItem key={m} value={m}>
                          {m}
                        </SafeSelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </Box>

                {temFiltro && (
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
              </Inline>
            </CardContent>
          </Card>

          {/* Gantt */}
          <Card>
            <CardHeader>
              <CardTitle className="text-sm font-semibold">
                Timeline ({ganttTasks.length} linha{ganttTasks.length === 1 ? '' : 's'})
                {can_edit && (
                  <Badge variant="secondary" className="ml-2 font-normal align-middle">
                    arraste a barra p/ reagendar o prazo
                  </Badge>
                )}
              </CardTitle>
            </CardHeader>
            <CardContent className="p-0">
              {tasks.length === 0 ? (
                <Text as="p" size="sm" tone="muted" align="center" className="py-16">
                  Sem tarefas no filtro atual.
                </Text>
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
                    readonly={!can_edit}
                    cellBorders="full"
                    init={(api) => {
                      api.on('select-task', handleTaskClick);
                      // B2: só escuta reschedule quando editável (readonly nem dispara drag).
                      if (can_edit) {
                        api.on('update-task', handleReschedule);
                      }
                    }}
                  />
                </div>
              )}
            </CardContent>
          </Card>
        </Stack>
      </Box>

      <TaskDetailDrawer
        task={selectedTask}
        open={drawerOpen}
        onClose={() => setDrawerOpen(false)}
      />
    </>
  );
}

export default function RoadmapGanttPage(props: Props) {
  return (
    <AppShellV2
      title="Forja — Roadmap (Gantt)"
      breadcrumbItems={[{ label: 'Forja' }, { label: 'Roadmap' }, { label: 'Gantt' }]}
    >
      {/* Faixa do hub. Sem isto a tela abre SOLTA — foi o defeito que [W] viu em
          produção. O `AppShellV2` não desenha topnav aqui: o hub Forja esconde a
          barra do shell e renderiza a própria (`ForjaHub`), então toda Page sob
          /forja/* precisa montá-la explicitamente. Registrar a aba em
          `config/core_topnavs.php` (PR 5339) alimenta `shell.topnavs`, que ESTA tela
          não consome — provado em runtime: 10 itens no shell, 0 `.topnav-chip` no DOM. */}
      <ForjaHub active="roadmap-gantt" />
      <RoadmapGantt {...props} />
    </AppShellV2>
  );
}
