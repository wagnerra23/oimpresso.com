// tela: /copiloto/admin/tasks
// module: Copiloto — TaskRegistry F2 (US-TR-007)
// permissao: copiloto.mcp.usage.all

import AppShellV2 from '@/Layouts/AppShellV2';
import { router } from '@inertiajs/react';
import { useRef, useState, type ReactNode } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Label } from '@/Components/ui/label';
import { ScrollArea } from '@/Components/ui/scroll-area';
import PageHeader from '@/Components/shared/PageHeader';
import KpiGrid from '@/Components/shared/KpiGrid';
import KpiCard from '@/Components/shared/KpiCard';

interface Task {
  task_id: string;
  title: string;
  module: string;
  owner: string | null;
  sprint: string | null;
  priority: 'p0' | 'p1' | 'p2' | 'p3';
  estimate_h: number | null;
  blocked_by: string[];
  status: string;
}

interface Kpis {
  total: number;
  p0: number;
  doing: number;
  blocked: number;
  done: number;
  cancelled: number;
  total_h: number;
}

interface Props {
  kanban: Record<string, Task[]>;
  backlog: Task[];
  kpis: Kpis;
  modulos: string[];
  owners: string[];
  sprints: string[];
  filters: { module: string | null; owner: string | null; sprint: string | null };
}

const COLUNAS: { key: string; label: string; color: string }[] = [
  { key: 'todo',   label: 'A fazer',   color: 'border-slate-400' },
  { key: 'doing',  label: 'Fazendo',   color: 'border-blue-500' },
  { key: 'review', label: 'Revisão',   color: 'border-amber-500' },
  { key: 'done',   label: 'Concluído', color: 'border-emerald-500' },
];

const PRIORITY_BADGE: Record<string, string> = {
  p0: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
  p1: 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300',
  p2: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
  p3: 'bg-slate-50 text-slate-400 dark:bg-slate-800/50 dark:text-slate-500',
};

const STATUS_BADGE: Record<string, string> = {
  todo:      'bg-slate-100 text-slate-600',
  doing:     'bg-blue-100 text-blue-700',
  review:    'bg-amber-100 text-amber-700',
  done:      'bg-emerald-100 text-emerald-700',
  blocked:   'bg-red-100 text-red-700',
  cancelled: 'bg-slate-200 text-slate-400',
};

function TaskCard({ task, onDragStart }: { task: Task; onDragStart: (id: string) => void }) {
  return (
    <div
      draggable
      onDragStart={() => onDragStart(task.task_id)}
      className="bg-card border rounded-lg p-3 cursor-grab active:cursor-grabbing shadow-sm hover:shadow-md transition-shadow select-none"
    >
      <div className="flex items-start justify-between gap-2 mb-1">
        <span className="text-[10px] font-mono text-muted-foreground">{task.task_id}</span>
        <span className={`text-[10px] font-semibold px-1.5 py-0.5 rounded ${PRIORITY_BADGE[task.priority] ?? PRIORITY_BADGE.p2}`}>
          {task.priority.toUpperCase()}
        </span>
      </div>
      <p className="text-xs font-medium leading-tight mb-2">{task.title}</p>
      <div className="flex flex-wrap gap-1">
        <span className="text-[10px] bg-muted px-1.5 py-0.5 rounded">{task.module}</span>
        {task.owner && <span className="text-[10px] bg-muted px-1.5 py-0.5 rounded">{task.owner}</span>}
        {task.estimate_h && <span className="text-[10px] text-muted-foreground">{task.estimate_h}h</span>}
        {task.blocked_by.length > 0 && (
          <span className="text-[10px] text-red-500">blk:{task.blocked_by.join(',')}</span>
        )}
      </div>
    </div>
  );
}

function KanbanView({ kanban }: { kanban: Record<string, Task[]> }) {
  const dragId = useRef<string | null>(null);
  const [draggingOver, setDraggingOver] = useState<string | null>(null);
  const [optimistic, setOptimistic] = useState<Record<string, string>>({});

  function handleDrop(targetStatus: string) {
    const id = dragId.current;
    if (!id) return;
    setDraggingOver(null);
    dragId.current = null;
    setOptimistic(prev => ({ ...prev, [id]: targetStatus }));
    router.patch(`/copiloto/admin/tasks/${id}/status`, { status: targetStatus, author: 'wagner' }, {
      preserveScroll: true,
      preserveState: true,
      onError: () => setOptimistic(prev => { const n = { ...prev }; delete n[id]; return n; }),
      onSuccess: () => setOptimistic(prev => { const n = { ...prev }; delete n[id]; return n; }),
    });
  }

  // Build effective task lists applying optimistic updates
  const effective: Record<string, Task[]> = {};
  COLUNAS.forEach(c => { effective[c.key] = []; });
  COLUNAS.forEach(c => {
    (kanban[c.key] ?? []).forEach(t => {
      const status = optimistic[t.task_id] ?? t.status;
      if (effective[status]) effective[status].push({ ...t, status });
    });
  });

  return (
    <div className="grid grid-cols-4 gap-4 mt-4">
      {COLUNAS.map(col => (
        <div
          key={col.key}
          className={`rounded-xl border-t-4 ${col.color} bg-muted/30 p-3 min-h-[300px] transition-colors ${draggingOver === col.key ? 'bg-muted/60 ring-2 ring-blue-400' : ''}`}
          onDragOver={e => { e.preventDefault(); setDraggingOver(col.key); }}
          onDragLeave={() => setDraggingOver(null)}
          onDrop={() => handleDrop(col.key)}
        >
          <div className="flex items-center justify-between mb-3">
            <span className="text-xs font-semibold uppercase tracking-wide">{col.label}</span>
            <span className="text-xs text-muted-foreground">{effective[col.key]?.length ?? 0}</span>
          </div>
          <div className="flex flex-col gap-2">
            {(effective[col.key] ?? []).map(t => (
              <TaskCard key={t.task_id} task={t} onDragStart={id => { dragId.current = id; }} />
            ))}
            {(effective[col.key] ?? []).length === 0 && (
              <div className="text-xs text-muted-foreground text-center py-8 border-2 border-dashed rounded-lg">
                vazio
              </div>
            )}
          </div>
        </div>
      ))}
    </div>
  );
}

function BacklogView({ backlog }: { backlog: Task[] }) {
  return (
    <Card className="mt-4">
      <CardContent className="p-0">
        <ScrollArea className="max-h-[600px]">
          <table className="w-full text-xs">
            <thead className="sticky top-0 bg-background z-10 border-b">
              <tr>
                <th className="text-left py-2 px-3 font-medium">ID</th>
                <th className="text-left py-2 px-3 font-medium">Título</th>
                <th className="text-left py-2 px-3 font-medium">Módulo</th>
                <th className="text-left py-2 px-3 font-medium">Owner</th>
                <th className="text-left py-2 px-3 font-medium">Sprint</th>
                <th className="text-center py-2 px-3 font-medium">Prio</th>
                <th className="text-center py-2 px-3 font-medium">Est.</th>
                <th className="text-center py-2 px-3 font-medium">Status</th>
              </tr>
            </thead>
            <tbody>
              {backlog.map(t => (
                <tr key={t.task_id} className="border-b hover:bg-muted/40">
                  <td className="py-1.5 px-3 font-mono text-[10px] text-muted-foreground whitespace-nowrap">{t.task_id}</td>
                  <td className="py-1.5 px-3 max-w-[300px] truncate">{t.title}</td>
                  <td className="py-1.5 px-3">{t.module}</td>
                  <td className="py-1.5 px-3">{t.owner ?? '—'}</td>
                  <td className="py-1.5 px-3">{t.sprint ?? '—'}</td>
                  <td className="py-1.5 px-3 text-center">
                    <span className={`text-[10px] font-semibold px-1.5 py-0.5 rounded ${PRIORITY_BADGE[t.priority] ?? PRIORITY_BADGE.p2}`}>
                      {t.priority.toUpperCase()}
                    </span>
                  </td>
                  <td className="py-1.5 px-3 text-center text-muted-foreground">{t.estimate_h ? `${t.estimate_h}h` : '—'}</td>
                  <td className="py-1.5 px-3 text-center">
                    <span className={`text-[10px] px-1.5 py-0.5 rounded ${STATUS_BADGE[t.status] ?? ''}`}>
                      {t.status}
                    </span>
                  </td>
                </tr>
              ))}
              {backlog.length === 0 && (
                <tr>
                  <td colSpan={8} className="text-center py-12 text-muted-foreground">
                    Nenhuma task encontrada. Rode <code className="font-mono">php artisan mcp:tasks:sync</code>.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </ScrollArea>
      </CardContent>
    </Card>
  );
}

function TasksIndex({ kanban, backlog, kpis, modulos, owners, sprints, filters }: Props) {
  const [tab, setTab] = useState<'kanban' | 'backlog'>('kanban');
  const [modulo, setModulo] = useState(filters.module ?? '__all__');
  const [owner,  setOwner]  = useState(filters.owner  ?? '__all__');
  const [sprint, setSprint] = useState(filters.sprint  ?? '__all__');

  function applyFilter() {
    const params: Record<string, string> = {};
    if (modulo !== '__all__') params.module = modulo;
    if (owner  !== '__all__') params.owner  = owner;
    if (sprint !== '__all__') params.sprint = sprint;
    router.get('/copiloto/admin/tasks', params, { preserveScroll: true, preserveState: true });
  }

  function clearFilter() {
    setModulo('__all__'); setOwner('__all__'); setSprint('__all__');
    router.get('/copiloto/admin/tasks', {}, { preserveScroll: true, preserveState: true });
  }

  return (
    <>
      <PageHeader
        icon="layout-kanban"
        title="Task Board"
        description={`${kpis.total} tasks · ${kpis.total_h.toFixed(0)}h estimadas · TaskRegistry F2 (US-TR-007)`}
      />

      {/* KPIs */}
      <KpiGrid cols={4} className="mt-4">
        <KpiCard icon="list" tone="default"     label="Total"    value={String(kpis.total)} />
        <KpiCard icon="alert-circle" tone={kpis.p0 > 0 ? 'danger' : 'success'} label="P0 abertas" value={String(kpis.p0)} />
        <KpiCard icon="loader" tone="default"   label="Doing"    value={String(kpis.doing)} />
        <KpiCard icon="lock" tone={kpis.blocked > 0 ? 'warning' : 'success'} label="Bloqueadas" value={String(kpis.blocked)} />
      </KpiGrid>

      {/* Filtros */}
      <Card className="mt-4">
        <CardContent className="py-3 flex flex-wrap items-end gap-3">
          <div className="w-36">
            <Label className="text-xs">Módulo</Label>
            <Select value={modulo} onValueChange={setModulo}>
              <SelectTrigger className="h-8"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="__all__">Todos</SelectItem>
                {modulos.map(m => <SelectItem key={m} value={m}>{m}</SelectItem>)}
              </SelectContent>
            </Select>
          </div>
          <div className="w-32">
            <Label className="text-xs">Owner</Label>
            <Select value={owner} onValueChange={setOwner}>
              <SelectTrigger className="h-8"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="__all__">Todos</SelectItem>
                {owners.map(o => <SelectItem key={o} value={o}>{o}</SelectItem>)}
              </SelectContent>
            </Select>
          </div>
          <div className="w-28">
            <Label className="text-xs">Sprint</Label>
            <Select value={sprint} onValueChange={setSprint}>
              <SelectTrigger className="h-8"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="__all__">Todos</SelectItem>
                {sprints.map(s => <SelectItem key={s} value={s}>{s}</SelectItem>)}
              </SelectContent>
            </Select>
          </div>
          <Button onClick={applyFilter} className="h-8 text-xs">Filtrar</Button>
          {(filters.module || filters.owner || filters.sprint) && (
            <Button variant="ghost" onClick={clearFilter} className="h-8 text-xs">Limpar</Button>
          )}

          {/* Tab switcher */}
          <div className="ml-auto flex gap-1">
            <Button
              variant={tab === 'kanban' ? 'default' : 'ghost'}
              className="h-8 text-xs"
              onClick={() => setTab('kanban')}
            >
              Kanban
            </Button>
            <Button
              variant={tab === 'backlog' ? 'default' : 'ghost'}
              className="h-8 text-xs"
              onClick={() => setTab('backlog')}
            >
              Backlog ({backlog.length})
            </Button>
          </div>
        </CardContent>
      </Card>

      {tab === 'kanban' ? (
        <KanbanView kanban={kanban} />
      ) : (
        <BacklogView backlog={backlog} />
      )}

      <div className="mt-4 text-xs text-muted-foreground">
        Drag-drop atualiza status e registra evento em <code className="font-mono">mcp_task_events</code>.
        Editar campos detalhados: <code className="font-mono">tasks-update</code> via MCP ou edite o SPEC e rode{' '}
        <code className="font-mono">php artisan mcp:tasks:sync</code>.
      </div>
    </>
  );
}

TasksIndex.layout = (page: ReactNode) => (
  <AppShellV2 title="Task Board — Copiloto" breadcrumbItems={[{ label: 'Copiloto' }, { label: 'Tasks' }]}>
    {page}
  </AppShellV2>
);

export default TasksIndex;
