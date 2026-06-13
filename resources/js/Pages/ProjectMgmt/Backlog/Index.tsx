// @memcofre
//   tela: /project-mgmt/backlog
//   module: ProjectMgmt
//   stories: US-TR-202 (Backlog filtrável + bulk edit)
//   permissao: copiloto.mcp.usage.all

import AppShellV2 from '@/Layouts/AppShellV2';
import { router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { Card, CardContent } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import PageHeader from '@/Components/shared/PageHeader';
import KpiGrid from '@/Components/shared/KpiGrid';
import KpiCard from '@/Components/shared/KpiCard';
import {
  COLUMN_LABEL_PT, PRIORITY_BADGE, STATUS_BADGE, type Status, type Priority,
} from '@/Components/board/badges';
import { type BoardTask } from '@/Components/board/TaskCard';
import { AlertCircle, Calendar } from 'lucide-react';

interface EpicOption { id: number; key: string; title: string }

interface Kpis { total: number; active: number; p0: number; overdue: number; unowned: number }

interface Props {
  project: { id: number; key: string; name: string } | null;
  // tasks/kpis/epics/owners/sprints chegam via Inertia::defer (BacklogController:55-59)
  // → `undefined` no 1º paint. Tipados opcionais + default-guard no destructuring
  // pra NÃO crashar React antes do defer chegar (skill inertia-defer-default,
  // Opção B; espelha OficinaAuto/ServiceOrders/Index.tsx). Sintoma do bug:
  // tasks.length sobre undefined → tela branca.
  tasks?: BoardTask[];
  epics?: EpicOption[];
  owners?: string[];
  sprints?: string[];
  kpis?: Kpis;
  filters: {
    status: string | null; priority: string | null; owner: string | null;
    epic: number | null; cycle: number | null; sprint: string | null;
    q: string; sort: string;
  };
}

// Default-guard pro prop deferred kpis (contadores começam zerados até o defer resolver).
const EMPTY_KPIS: Kpis = { total: 0, active: 0, p0: 0, overdue: 0, unowned: 0 };

const ALL = '__all__';
const LS = {
  STATUS:   'oimpresso.backlog.status',
  PRIORITY: 'oimpresso.backlog.priority',
  OWNER:    'oimpresso.backlog.owner',
  SPRINT:   'oimpresso.backlog.sprint',
  EPIC:     'oimpresso.backlog.epic',
  SORT:     'oimpresso.backlog.sort',
  Q:        'oimpresso.backlog.q',
};

function csrf() {
  return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
}

function fmtDate(iso: string | null): string {
  if (!iso) return '—';
  return new Date(iso + 'T00:00:00').toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
}

function BacklogIndex({
  project,
  tasks = [],
  epics = [],
  owners = [],
  sprints = [],
  kpis = EMPTY_KPIS,
  filters,
}: Props) {
  const [status, setStatus] = useState(filters.status ?? localStorage.getItem(LS.STATUS) ?? ALL);
  const [priority, setPriority] = useState(filters.priority ?? localStorage.getItem(LS.PRIORITY) ?? ALL);
  const [owner, setOwner] = useState(filters.owner ?? localStorage.getItem(LS.OWNER) ?? ALL);
  const [sprint, setSprint] = useState(filters.sprint ?? localStorage.getItem(LS.SPRINT) ?? ALL);
  const [epic, setEpic] = useState<string>(filters.epic ? String(filters.epic) : (localStorage.getItem(LS.EPIC) ?? ALL));
  const [sort, setSort] = useState(filters.sort ?? localStorage.getItem(LS.SORT) ?? 'priority');
  const [q, setQ] = useState(filters.q ?? localStorage.getItem(LS.Q) ?? '');

  useEffect(() => { localStorage.setItem(LS.STATUS, status); }, [status]);
  useEffect(() => { localStorage.setItem(LS.PRIORITY, priority); }, [priority]);
  useEffect(() => { localStorage.setItem(LS.OWNER, owner); }, [owner]);
  useEffect(() => { localStorage.setItem(LS.SPRINT, sprint); }, [sprint]);
  useEffect(() => { localStorage.setItem(LS.EPIC, epic); }, [epic]);
  useEffect(() => { localStorage.setItem(LS.SORT, sort); }, [sort]);
  useEffect(() => { localStorage.setItem(LS.Q, q); }, [q]);

  function aplicar(patch: Record<string, string | null> = {}) {
    const params: Record<string, string> = {};
    const toApply = {
      status: patch.status ?? status,
      priority: patch.priority ?? priority,
      owner: patch.owner ?? owner,
      sprint: patch.sprint ?? sprint,
      epic: patch.epic ?? epic,
      sort: patch.sort ?? sort,
      q: patch.q ?? q,
    };
    Object.entries(toApply).forEach(([k, v]) => {
      if (v && v !== ALL && v !== '') params[k] = String(v);
    });
    router.get('/project-mgmt/backlog', params, { preserveScroll: true, preserveState: true, replace: true });
  }

  function limpar() {
    setStatus(ALL); setPriority(ALL); setOwner(ALL); setSprint(ALL); setEpic(ALL); setSort('priority'); setQ('');
    router.get('/project-mgmt/backlog', {}, { preserveScroll: true, preserveState: true, replace: true });
  }

  const qDebounceRef = useRef<number | null>(null);
  function onChangeQ(v: string) {
    setQ(v);
    if (qDebounceRef.current) window.clearTimeout(qDebounceRef.current);
    qDebounceRef.current = window.setTimeout(() => aplicar({ q: v }), 350);
  }

  const [selected, setSelected] = useState<Set<string>>(new Set());

  function toggle(taskId: string) {
    setSelected((prev) => {
      const next = new Set(prev);
      if (next.has(taskId)) next.delete(taskId); else next.add(taskId);
      return next;
    });
  }
  function toggleAll() {
    setSelected((prev) => prev.size === tasks.length ? new Set() : new Set(tasks.map(t => t.task_id)));
  }
  function clearSel() { setSelected(new Set()); }

  function bulk(fields: Record<string, string>) {
    if (selected.size === 0) return;
    const ids = Array.from(selected);
    fetch('/project-mgmt/backlog/bulk', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
      body: JSON.stringify({ task_ids: ids, fields }),
    }).then(r => r.json()).then(_ => {
      clearSel();
      router.reload({ only: ['tasks', 'kpis'], preserveScroll: true });
    });
  }

  const counts = useMemo(() => ({
    total: tasks.length,
    selected: selected.size,
  }), [tasks, selected]);

  return (
    <>
      <PageHeader
        icon="List"
        title={project ? `${project.name} — Backlog` : 'Backlog'}
        description={`${kpis.total} tasks · ${kpis.active} ativas · ${kpis.p0} P0 · ${kpis.overdue} atrasadas`}
        action={
          <Input
            value={q}
            onChange={(e) => onChangeQ(e.target.value)}
            placeholder="Buscar id/título/owner…"
            className="h-8 w-56 text-xs"
          />
        }
      />

      <KpiGrid cols={5} className="mt-4">
        <KpiCard icon="List" tone="default" label="Total" value={String(kpis.total)} />
        <KpiCard icon="Loader" tone="default" label="Ativas" value={String(kpis.active)} />
        <KpiCard icon="AlertCircle" tone={kpis.p0 > 0 ? 'danger' : 'success'} label="P0 abertas" value={String(kpis.p0)} />
        <KpiCard icon="AlertCircle" tone={kpis.overdue > 0 ? 'danger' : 'success'} label="Atrasadas" value={String(kpis.overdue)} />
        <KpiCard icon="UserX" tone={kpis.unowned > 0 ? 'warning' : 'default'} label="Sem owner" value={String(kpis.unowned)} />
      </KpiGrid>

      <Card className="mt-4">
        <CardContent className="py-3 flex flex-wrap items-end gap-3">
          <div className="w-36">
            <Label className="text-xs">Status</Label>
            <Select value={status} onValueChange={(v) => { setStatus(v); aplicar({ status: v }); }}>
              <SelectTrigger className="h-8"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value={ALL}>Ativos (não-cancelled)</SelectItem>
                <SelectItem value="all">Todos</SelectItem>
                <SelectItem value="backlog">Backlog</SelectItem>
                <SelectItem value="todo">A fazer</SelectItem>
                <SelectItem value="doing">Fazendo</SelectItem>
                <SelectItem value="review">Revisão</SelectItem>
                <SelectItem value="done">Concluído</SelectItem>
                <SelectItem value="blocked">Bloqueado</SelectItem>
                <SelectItem value="cancelled">Cancelado</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div className="w-28">
            <Label className="text-xs">Prio</Label>
            <Select value={priority} onValueChange={(v) => { setPriority(v); aplicar({ priority: v }); }}>
              <SelectTrigger className="h-8"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value={ALL}>Todas</SelectItem>
                <SelectItem value="p0">P0</SelectItem>
                <SelectItem value="p1">P1</SelectItem>
                <SelectItem value="p2">P2</SelectItem>
                <SelectItem value="p3">P3</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div className="w-32">
            <Label className="text-xs">Owner</Label>
            <Select value={owner} onValueChange={(v) => { setOwner(v); aplicar({ owner: v }); }}>
              <SelectTrigger className="h-8"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value={ALL}>Todos</SelectItem>
                {owners.map(o => <SelectItem key={o} value={o}>{o}</SelectItem>)}
              </SelectContent>
            </Select>
          </div>

          <div className="w-44">
            <Label className="text-xs">Epic</Label>
            <Select value={epic} onValueChange={(v) => { setEpic(v); aplicar({ epic: v }); }}>
              <SelectTrigger className="h-8"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value={ALL}>Todos</SelectItem>
                {epics.map(e => <SelectItem key={e.id} value={String(e.id)}>{e.key} — {e.title}</SelectItem>)}
              </SelectContent>
            </Select>
          </div>

          {sprints.length > 0 && (
            <div className="w-28">
              <Label className="text-xs">Sprint</Label>
              <Select value={sprint} onValueChange={(v) => { setSprint(v); aplicar({ sprint: v }); }}>
                <SelectTrigger className="h-8"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value={ALL}>Todos</SelectItem>
                  {sprints.map(s => <SelectItem key={s} value={s}>{s}</SelectItem>)}
                </SelectContent>
              </Select>
            </div>
          )}

          <div className="w-32">
            <Label className="text-xs">Sort</Label>
            <Select value={sort} onValueChange={(v) => { setSort(v); aplicar({ sort: v }); }}>
              <SelectTrigger className="h-8"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="priority">Prioridade</SelectItem>
                <SelectItem value="recent">Recentes</SelectItem>
                <SelectItem value="due">Prazo</SelectItem>
                <SelectItem value="title">Título</SelectItem>
                <SelectItem value="id">ID</SelectItem>
              </SelectContent>
            </Select>
          </div>

          {(status !== ALL || priority !== ALL || owner !== ALL || sprint !== ALL || epic !== ALL || q || sort !== 'priority') && (
            <Button variant="ghost" onClick={limpar} className="h-8 text-xs">Limpar</Button>
          )}
        </CardContent>
      </Card>

      {selected.size > 0 && (
        <div className="sticky top-2 z-20 mt-3 flex flex-wrap items-center gap-2 px-4 py-2 rounded-xl border bg-primary/5 shadow">
          <span className="text-xs font-semibold">{selected.size} selecionada{selected.size > 1 ? 's' : ''}</span>

          <span className="text-xs text-muted-foreground">Status:</span>
          <Select onValueChange={(v) => bulk({ status: v })}>
            <SelectTrigger className="h-7 w-32 text-xs"><SelectValue placeholder="Mudar pra…" /></SelectTrigger>
            <SelectContent>
              <SelectItem value="todo">A fazer</SelectItem>
              <SelectItem value="doing">Fazendo</SelectItem>
              <SelectItem value="review">Revisão</SelectItem>
              <SelectItem value="done">Concluído</SelectItem>
              <SelectItem value="blocked">Bloqueado</SelectItem>
              <SelectItem value="cancelled">Cancelado</SelectItem>
            </SelectContent>
          </Select>

          <span className="text-xs text-muted-foreground">Prio:</span>
          <Select onValueChange={(v) => bulk({ priority: v })}>
            <SelectTrigger className="h-7 w-20 text-xs"><SelectValue placeholder="—" /></SelectTrigger>
            <SelectContent>
              <SelectItem value="p0">P0</SelectItem>
              <SelectItem value="p1">P1</SelectItem>
              <SelectItem value="p2">P2</SelectItem>
              <SelectItem value="p3">P3</SelectItem>
            </SelectContent>
          </Select>

          <span className="text-xs text-muted-foreground">Owner:</span>
          <Select onValueChange={(v) => bulk({ owner: v })}>
            <SelectTrigger className="h-7 w-32 text-xs"><SelectValue placeholder="Atribuir…" /></SelectTrigger>
            <SelectContent>
              {owners.map(o => <SelectItem key={o} value={o}>{o}</SelectItem>)}
            </SelectContent>
          </Select>

          <Button variant="ghost" size="sm" className="h-7 text-xs ml-auto" onClick={clearSel}>
            limpar seleção
          </Button>
        </div>
      )}

      <Card className="mt-3">
        <CardContent className="p-0 overflow-x-auto">
          <table className="w-full text-xs">
            <thead className="border-b bg-muted/40 sticky top-0 z-10">
              <tr>
                <th className="text-left py-2 px-3 w-8">
                  <input
                    type="checkbox"
                    checked={tasks.length > 0 && selected.size === tasks.length}
                    onChange={toggleAll}
                    aria-label="selecionar todas"
                  />
                </th>
                <th className="text-left py-2 px-3">ID</th>
                <th className="text-left py-2 px-3">Título</th>
                <th className="text-left py-2 px-3">Módulo</th>
                <th className="text-left py-2 px-3">Owner</th>
                <th className="text-center py-2 px-3">Prio</th>
                <th className="text-center py-2 px-3">Status</th>
                <th className="text-center py-2 px-3">Estim.</th>
                <th className="text-center py-2 px-3">Prazo</th>
              </tr>
            </thead>
            <tbody>
              {tasks.map(t => {
                const sel = selected.has(t.task_id);
                return (
                  <tr
                    key={t.task_id}
                    className={[
                      'border-b transition-colors',
                      sel ? 'bg-primary/5' : 'hover:bg-muted/40',
                      t.is_blocked ? 'bg-destructive-soft/40' : '',
                    ].filter(Boolean).join(' ')}
                  >
                    <td className="py-1.5 px-3">
                      <input type="checkbox" checked={sel} onChange={() => toggle(t.task_id)} />
                    </td>
                    <td className="py-1.5 px-3 font-mono text-[10px] text-muted-foreground whitespace-nowrap">
                      {t.display_id}
                    </td>
                    <td className="py-1.5 px-3 max-w-[420px] truncate">{t.title}</td>
                    <td className="py-1.5 px-3">{t.module}</td>
                    <td className="py-1.5 px-3">{t.owner ? `@${t.owner}` : <span className="text-muted-foreground">—</span>}</td>
                    <td className="py-1.5 px-3 text-center">
                      <span className={`text-[10px] font-semibold px-1.5 py-0.5 rounded ${PRIORITY_BADGE[t.priority as Priority] ?? PRIORITY_BADGE.p2}`}>
                        {t.priority.toUpperCase()}
                      </span>
                    </td>
                    <td className="py-1.5 px-3 text-center">
                      <span className={`text-[10px] px-1.5 py-0.5 rounded ${STATUS_BADGE[t.status as Status] ?? ''}`}>
                        {COLUMN_LABEL_PT[t.status as Status] ?? t.status}
                      </span>
                    </td>
                    <td className="py-1.5 px-3 text-center text-muted-foreground">
                      {t.estimate_h ? `${t.estimate_h}h` : (t.story_points ? `${t.story_points}sp` : '—')}
                    </td>
                    <td className="py-1.5 px-3 text-center">
                      {t.due_date ? (
                        <span className={`inline-flex items-center gap-1 text-[10px] ${t.is_overdue ? 'text-destructive font-semibold' : 'text-muted-foreground'}`}>
                          {t.is_overdue ? <AlertCircle size={10} /> : <Calendar size={10} />}
                          {fmtDate(t.due_date)}
                        </span>
                      ) : (
                        <span className="text-muted-foreground">—</span>
                      )}
                    </td>
                  </tr>
                );
              })}
              {tasks.length === 0 && (
                <tr><td colSpan={9} className="text-center py-12 text-muted-foreground">Nenhuma task casa com os filtros.</td></tr>
              )}
            </tbody>
          </table>
        </CardContent>
      </Card>

      <p className="mt-3 text-xs text-muted-foreground">
        Mostrando {counts.total} tasks (limite 500). Bulk edit faz <code className="font-mono">tasks-bulk-update</code>{' '}
        com event audit em <code className="font-mono">mcp_task_events</code>.
      </p>
    </>
  );
}

BacklogIndex.layout = (page: ReactNode) => (
  <AppShellV2
    title="Project Mgmt — Backlog"
    breadcrumbItems={[{ label: 'Project Mgmt' }, { label: 'Backlog' }]}
  >
    {page}
  </AppShellV2>
);

export default BacklogIndex;
