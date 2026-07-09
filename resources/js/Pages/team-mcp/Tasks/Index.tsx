// @memcofre
//   tela: /team-mcp/tasks
//   module: TeamMcp (split do Copiloto) — TaskRegistry F2 (US-TR-007)
//   forja: PR-1 re-skin DS v6 — visual-comparison em
//          memory/requisitos/TeamMcp/tasks-visual-comparison.md (approved [W] 2026-06-16)
//   permissao: copiloto.mcp.usage.all
//
// Atalhos (PT-01):
//   J / K    navegar linha (próxima / anterior)
//   Enter    abre drawer da linha selecionada
//   X        marca/desmarca seleção em lote
//   /        foca a busca local
//   Esc      fecha drawer / limpa seleção
//   ⌘K       command palette global (AppShellV2 — não re-montar aqui)
//
// Persistência localStorage (prefixo oimpresso.teammcp.tasks.*):
//   groupBy · tab · density · search · collapsed

import AppShellV2 from '@/Layouts/AppShellV2';
import { router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { ChevronRight, LayoutGrid, ListTree, Lock } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import PageHeader from '@/Components/shared/PageHeader';
import KpiGrid from '@/Components/shared/KpiGrid';
import KpiCard from '@/Components/shared/KpiCard';
import BulkActionBar from '@/Components/shared/BulkActionBar';
import EmptyState from '@/Components/shared/EmptyState';
import { Checkbox } from '@/Components/ui/checkbox';
import { cn } from '@/Lib/utils';
import ForjaHub from '@/Pages/team-mcp/Forja/_components/ForjaHub';
import TaskDrawer from './_components/TaskDrawer';
import { ActorSeal, PriorityDot, TaskStatusPill } from './_components/taskBadges';
import { PRIO_LABEL, STATUS_ORDER, statusMeta, type Priority } from './_components/taskTokens';

interface Task {
  task_id: string;
  display_id: string;
  title: string;
  module: string;
  owner: string | null;
  sprint: string | null;
  priority: Priority;
  type: string | null;
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
  // Props deferidas (Inertia::defer) → undefined no 1º paint. Default-guard no
  // destructuring evita tela branca (skill inertia-defer-default, espelha Board).
  kanban?: Record<string, Task[]>;
  backlog?: Task[];
  kpis?: Kpis;
  modulos?: string[];
  owners?: string[];
  sprints?: string[];
  agents?: string[];
  filters: { module: string | null; owner: string | null; sprint: string | null };
}

const ALL = '__all__';
const NONE = '__none__';

const LS = {
  GROUP: 'oimpresso.teammcp.tasks.groupBy',
  TAB: 'oimpresso.teammcp.tasks.tab',
  DENSITY: 'oimpresso.teammcp.tasks.density',
  SEARCH: 'oimpresso.teammcp.tasks.search',
  COLLAPSED: 'oimpresso.teammcp.tasks.collapsed',
} as const;

type GroupKey = 'sprint' | 'status' | 'owner' | 'priority' | 'module';
type Tab = 'backlog' | 'quadro';
type Density = 'compact' | 'normal';

const GROUP_OPTIONS: { key: GroupKey; label: string }[] = [
  { key: 'sprint', label: 'Onda' },
  { key: 'status', label: 'Fase' },
  { key: 'owner', label: 'Papel' },
  { key: 'priority', label: 'Prioridade' },
  { key: 'module', label: 'Módulo' },
];

const KANBAN_COLS: { key: string; label: string }[] = [
  { key: 'todo', label: 'A fazer' },
  { key: 'doing', label: 'Fazendo' },
  { key: 'review', label: 'Revisão' },
  { key: 'done', label: 'Concluído' },
];

const PRIO_ORDER: Record<string, number> = { p0: 0, p1: 1, p2: 2, p3: 3 };

function lsGet(key: string, fallback: string): string {
  if (typeof window === 'undefined') return fallback;
  return localStorage.getItem(key) ?? fallback;
}

function groupOf(t: Task, key: GroupKey): string {
  if (key === 'priority') return t.priority ?? 'p2';
  if (key === 'status') return t.status;
  const v = key === 'sprint' ? t.sprint : key === 'owner' ? t.owner : t.module;
  return v && v.length ? v : NONE;
}

function groupLabel(key: GroupKey, val: string): string {
  if (val === NONE) {
    return key === 'sprint' ? '— sem onda —' : key === 'owner' ? '— sem dono —' : '— sem módulo —';
  }
  if (key === 'priority') return PRIO_LABEL[val as Priority] ?? val;
  if (key === 'status') return statusMeta(val).label;
  return val;
}

function groupSortValue(key: GroupKey, val: string): number | string {
  if (key === 'priority') return PRIO_ORDER[val] ?? 9;
  if (key === 'status') { const i = STATUS_ORDER.indexOf(val); return i < 0 ? 99 : i; }
  return val === NONE ? '￿' : val.toLowerCase();
}

function sortTasks(a: Task, b: Task): number {
  const sa = STATUS_ORDER.indexOf(a.status);
  const sb = STATUS_ORDER.indexOf(b.status);
  if (sa !== sb) return (sa < 0 ? 99 : sa) - (sb < 0 ? 99 : sb);
  const pa = PRIO_ORDER[a.priority] ?? 9;
  const pb = PRIO_ORDER[b.priority] ?? 9;
  if (pa !== pb) return pa - pb;
  return a.display_id.localeCompare(b.display_id);
}

function TasksIndex({
  kanban,
  backlog,
  kpis,
  modulos = [],
  owners = [],
  sprints = [],
  agents = [],
  filters,
}: Props) {
  const isLoading = kpis === undefined || backlog === undefined;
  const k: Kpis = kpis ?? { total: 0, p0: 0, doing: 0, blocked: 0, done: 0, cancelled: 0, total_h: 0 };

  // ── UI state (persistido)
  const [tab, setTab] = useState<Tab>(() => (lsGet(LS.TAB, 'backlog') === 'quadro' ? 'quadro' : 'backlog'));
  const [groupBy, setGroupBy] = useState<GroupKey>(() => lsGet(LS.GROUP, 'sprint') as GroupKey);
  const [density, setDensity] = useState<Density>(() => (lsGet(LS.DENSITY, 'normal') === 'compact' ? 'compact' : 'normal'));
  const [search, setSearch] = useState<string>(() => lsGet(LS.SEARCH, ''));
  const [collapsed, setCollapsed] = useState<Set<string>>(() => {
    try { return new Set<string>(JSON.parse(lsGet(LS.COLLAPSED, '[]'))); }
    catch { return new Set<string>(); }
  });

  useEffect(() => { localStorage.setItem(LS.TAB, tab); }, [tab]);
  useEffect(() => { localStorage.setItem(LS.GROUP, groupBy); }, [groupBy]);
  useEffect(() => { localStorage.setItem(LS.DENSITY, density); }, [density]);
  useEffect(() => { localStorage.setItem(LS.SEARCH, search); }, [search]);
  useEffect(() => { localStorage.setItem(LS.COLLAPSED, JSON.stringify(Array.from(collapsed))); }, [collapsed]);

  // ── Filtros server-side (module/owner/sprint)
  const [modulo, setModulo] = useState(filters.module ?? ALL);
  const [owner, setOwner] = useState(filters.owner ?? ALL);
  const [sprint, setSprint] = useState(filters.sprint ?? ALL);

  // ── Seleção / navegação
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [selected, setSelected] = useState<Set<string>>(new Set());
  const searchRef = useRef<HTMLInputElement>(null);

  // ── Drag-drop otimista
  const dragId = useRef<string | null>(null);
  const [dragOver, setDragOver] = useState<string | null>(null);
  const [optimistic, setOptimistic] = useState<Record<string, string>>({});
  const [flash, setFlash] = useState<string | null>(null);

  // ── Drawer via URL ?task=ID
  const [openId, setOpenId] = useState<string | null>(() => {
    if (typeof window === 'undefined') return null;
    return new URLSearchParams(window.location.search).get('task');
  });

  function openDetail(id: string) {
    setOpenId(id);
    const url = new URL(window.location.href);
    url.searchParams.set('task', id);
    window.history.replaceState({}, '', url.toString());
  }
  function closeDetail() {
    setOpenId(null);
    const url = new URL(window.location.href);
    url.searchParams.delete('task');
    window.history.replaceState({}, '', url.toString());
  }

  useEffect(() => {
    if (!flash) return;
    const tid = setTimeout(() => setFlash(null), 5000);
    return () => clearTimeout(tid);
  }, [flash]);

  function patchStatus(taskId: string, status: string) {
    setOptimistic((p) => ({ ...p, [taskId]: status }));
    const csrf = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
    fetch(`/team-mcp/tasks/${taskId}/status`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
      body: JSON.stringify({ status, author: 'wagner' }),
    })
      .then((r) => {
        if (r.ok) {
          // Limpa o otimismo só após o reload reconciliar: evita flicker E evita
          // mascarar mudança real posterior de outro ator (review PR-1).
          router.reload({
            only: ['kanban', 'backlog', 'kpis'],
            onFinish: () => setOptimistic((p) => { const n = { ...p }; delete n[taskId]; return n; }),
          });
          return;
        }
        setOptimistic((p) => { const n = { ...p }; delete n[taskId]; return n; });
        setFlash(r.status === 403 ? 'Sem permissão para mover tasks.' : 'Falha ao atualizar status.');
      })
      .catch(() => {
        setOptimistic((p) => { const n = { ...p }; delete n[taskId]; return n; });
        setFlash('Erro de rede. Tenta novamente.');
      });
  }

  function toggleSelect(id: string) {
    setSelected((prev) => { const n = new Set(prev); if (n.has(id)) n.delete(id); else n.add(id); return n; });
  }
  function toggleCollapse(g: string) {
    setCollapsed((prev) => { const n = new Set(prev); if (n.has(g)) n.delete(g); else n.add(g); return n; });
  }
  function bulkSetStatus(status: string) {
    selected.forEach((id) => patchStatus(id, status));
    setSelected(new Set());
  }

  // ── Backlog efetivo (otimista + busca + sort)
  const searchLower = search.trim().toLowerCase();
  const visibleBacklog = useMemo(() => {
    return (backlog ?? [])
      .map((t) => ({ ...t, status: optimistic[t.task_id] ?? t.status }))
      .filter((t) => !searchLower || `${t.display_id} ${t.title} ${t.owner ?? ''} ${t.module}`.toLowerCase().includes(searchLower))
      .sort(sortTasks);
  }, [backlog, optimistic, searchLower]);

  const grouped = useMemo(() => {
    const map = new Map<string, Task[]>();
    for (const t of visibleBacklog) {
      const g = groupOf(t, groupBy);
      if (!map.has(g)) map.set(g, []);
      map.get(g)!.push(t);
    }
    return Array.from(map.entries()).sort((a, b) => {
      const av = groupSortValue(groupBy, a[0]);
      const bv = groupSortValue(groupBy, b[0]);
      if (typeof av === 'number' && typeof bv === 'number') return av - bv;
      return String(av).localeCompare(String(bv));
    });
  }, [visibleBacklog, groupBy]);

  const flatBacklog = useMemo(() => {
    const out: Task[] = [];
    for (const [g, items] of grouped) if (!collapsed.has(g)) out.push(...items);
    return out;
  }, [grouped, collapsed]);

  // ── Kanban efetivo (otimista)
  const effectiveKanban = useMemo(() => {
    const out: Record<string, Task[]> = {};
    KANBAN_COLS.forEach((c) => { out[c.key] = []; });
    Object.values(kanban ?? {}).forEach((list) => {
      (list ?? []).forEach((t) => {
        const st = optimistic[t.task_id] ?? t.status;
        if (out[st]) out[st].push({ ...t, status: st });
      });
    });
    return out;
  }, [kanban, optimistic]);

  const flatKanban = useMemo(() => {
    const o: Task[] = [];
    KANBAN_COLS.forEach((c) => o.push(...(effectiveKanban[c.key] ?? [])));
    return o;
  }, [effectiveKanban]);

  const flatVisible = tab === 'quadro' ? flatKanban : flatBacklog;

  useEffect(() => {
    if (selectedId && !flatVisible.find((t) => t.task_id === selectedId)) setSelectedId(null);
  }, [flatVisible, selectedId]);

  // ── Atalhos
  useEffect(() => {
    function onKey(e: KeyboardEvent) {
      const tgt = e.target as HTMLElement | null;
      const typing = !!tgt && (tgt.tagName === 'INPUT' || tgt.tagName === 'TEXTAREA' || tgt.isContentEditable);
      if (e.key === '/' && !typing) { e.preventDefault(); searchRef.current?.focus(); return; }
      if (e.key === 'Escape') {
        if (openId) closeDetail();
        else if (selected.size) setSelected(new Set());
        return;
      }
      if (typing) return;
      const idx = selectedId ? flatVisible.findIndex((t) => t.task_id === selectedId) : -1;
      const cur = idx >= 0 ? flatVisible[idx] : null;
      if (e.key === 'j' || e.key === 'J') {
        e.preventDefault();
        const n = idx < 0 ? 0 : Math.min(flatVisible.length - 1, idx + 1);
        setSelectedId(flatVisible[n]?.task_id ?? null);
      } else if (e.key === 'k' || e.key === 'K') {
        e.preventDefault();
        const p = idx <= 0 ? 0 : idx - 1;
        setSelectedId(flatVisible[p]?.task_id ?? null);
      } else if (e.key === 'Enter') {
        if (cur) { e.preventDefault(); openDetail(cur.task_id); }
      } else if (e.key === 'x' || e.key === 'X') {
        if (cur) { e.preventDefault(); toggleSelect(cur.task_id); }
      }
    }
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [flatVisible, selectedId, openId, selected]);

  // ── Polling 10s + on-focus
  useEffect(() => {
    const reload = () => router.reload({ only: ['kanban', 'backlog', 'kpis'] });
    const interval = setInterval(reload, 10_000);
    window.addEventListener('focus', reload);
    return () => { clearInterval(interval); window.removeEventListener('focus', reload); };
  }, []);

  function applyFilter(patch: Partial<{ module: string; owner: string; sprint: string }>) {
    const m = patch.module ?? modulo;
    const o = patch.owner ?? owner;
    const s = patch.sprint ?? sprint;
    const params: Record<string, string> = {};
    if (m !== ALL) params.module = m;
    if (o !== ALL) params.owner = o;
    if (s !== ALL) params.sprint = s;
    // D-14: partial reload — só re-busca o que muda com filtro
    // (modulos/owners/sprints/agents são defer por business — pulam no partial).
    router.get('/team-mcp/tasks', params, { preserveScroll: true, preserveState: true, replace: true, only: ['kanban', 'backlog', 'kpis', 'filters'] });
  }
  function clearFilter() {
    setModulo(ALL); setOwner(ALL); setSprint(ALL);
    // D-14: partial reload — idem applyFilter
    router.get('/team-mcp/tasks', {}, { preserveScroll: true, preserveState: true, replace: true, only: ['kanban', 'backlog', 'kpis', 'filters'] });
  }

  const hasServerFilter = modulo !== ALL || owner !== ALL || sprint !== ALL;
  const rowH = density === 'compact' ? 'h-8' : 'h-9';

  return (
    <>
      <ForjaHub active="tarefas" />
      <PageHeader
        icon="layout-kanban"
        title="Tasks"
        description={`${isLoading ? '—' : k.total} tasks · ${isLoading ? '—' : k.total_h.toFixed(0)}h estimadas · ${isLoading ? '—' : k.doing} fazendo`}
        action={
          <div className="inline-flex rounded-lg border bg-muted/40 p-0.5" role="tablist" aria-label="Visão">
            <button
              type="button"
              role="tab"
              aria-selected={tab === 'backlog'}
              data-testid="view-backlog"
              onClick={() => setTab('backlog')}
              className={cn('inline-flex items-center gap-1.5 rounded-md px-3 py-1 text-xs font-medium transition-colors',
                tab === 'backlog' ? 'bg-background text-primary shadow-sm' : 'text-muted-foreground hover:text-foreground')}
            >
              <ListTree size={14} /> Backlog
            </button>
            <button
              type="button"
              role="tab"
              aria-selected={tab === 'quadro'}
              data-testid="view-quadro"
              onClick={() => setTab('quadro')}
              className={cn('inline-flex items-center gap-1.5 rounded-md px-3 py-1 text-xs font-medium transition-colors',
                tab === 'quadro' ? 'bg-background text-primary shadow-sm' : 'text-muted-foreground hover:text-foreground')}
            >
              <LayoutGrid size={14} /> Quadro
            </button>
          </div>
        }
      />

      {flash && (
        <div role="alert" className="mt-4 inline-flex w-full items-center justify-between rounded-md border border-warning/20 bg-warning-soft px-3 py-2 text-sm text-warning-fg">
          <span>{flash}</span>
          <button type="button" onClick={() => setFlash(null)} className="text-xs font-medium underline-offset-2 hover:underline">ok</button>
        </div>
      )}

      <KpiGrid cols={4} className="mt-4">
        <KpiCard icon="list" tone="default" label="Total" value={isLoading ? '—' : String(k.total)} />
        <KpiCard icon="alert-circle" tone={k.p0 > 0 ? 'danger' : 'success'} label="P0 abertas" value={isLoading ? '—' : String(k.p0)} />
        <KpiCard icon="loader" tone="default" label="Fazendo" value={isLoading ? '—' : String(k.doing)} />
        <KpiCard icon="lock" tone={k.blocked > 0 ? 'warning' : 'success'} label="Bloqueadas" value={isLoading ? '—' : String(k.blocked)} />
      </KpiGrid>

      {/* Toolbar */}
      <div className="mt-4 inline-flex w-full flex-wrap items-end gap-3 rounded-lg border bg-card px-3 py-3">
        {tab === 'backlog' && (
          <div>
            <Label className="text-xs">Agrupar por</Label>
            <div className="mt-1 inline-flex rounded-md border bg-muted/40 p-0.5" role="group" data-testid="groupby">
              {GROUP_OPTIONS.map((g) => (
                <button
                  key={g.key}
                  type="button"
                  onClick={() => setGroupBy(g.key)}
                  className={cn('rounded px-2 py-1 text-xs font-medium transition-colors',
                    groupBy === g.key ? 'bg-background text-primary shadow-sm' : 'text-muted-foreground hover:text-foreground')}
                >
                  {g.label}
                </button>
              ))}
            </div>
          </div>
        )}

        <div className="w-36">
          <Label className="text-xs">Módulo</Label>
          <Select value={modulo} onValueChange={(v) => { setModulo(v); applyFilter({ module: v }); }}>
            <SelectTrigger className="h-8"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value={ALL}>Todos</SelectItem>
              {modulos.map((m) => <SafeSelectItem key={m} value={m}>{m}</SafeSelectItem>)}
            </SelectContent>
          </Select>
        </div>
        <div className="w-32">
          <Label className="text-xs">Owner</Label>
          <Select value={owner} onValueChange={(v) => { setOwner(v); applyFilter({ owner: v }); }}>
            <SelectTrigger className="h-8"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value={ALL}>Todos</SelectItem>
              {owners.map((o) => <SafeSelectItem key={o} value={o}>{o}</SafeSelectItem>)}
            </SelectContent>
          </Select>
        </div>
        <div className="w-28">
          <Label className="text-xs">Onda</Label>
          <Select value={sprint} onValueChange={(v) => { setSprint(v); applyFilter({ sprint: v }); }}>
            <SelectTrigger className="h-8"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value={ALL}>Todas</SelectItem>
              {sprints.map((s) => <SafeSelectItem key={s} value={s}>{s}</SafeSelectItem>)}
            </SelectContent>
          </Select>
        </div>

        <div className="w-48">
          <Label className="text-xs">Buscar</Label>
          <Input
            ref={searchRef}
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Buscar (/)…"
            className="h-8 text-xs"
            data-testid="search"
          />
        </div>

        {(hasServerFilter || search) && (
          <Button variant="ghost" onClick={() => { setSearch(''); clearFilter(); }} className="h-8 text-xs">Limpar</Button>
        )}

        <div className="ml-auto inline-flex items-center gap-2">
          {tab === 'backlog' && (
            <button
              type="button"
              onClick={() => setDensity(density === 'compact' ? 'normal' : 'compact')}
              className="rounded-md border px-2 py-1 text-[11px] text-muted-foreground hover:text-foreground"
              data-testid="density"
            >
              {density === 'compact' ? 'Densidade: compacta' : 'Densidade: normal'}
            </button>
          )}
          <span className="hidden text-[11px] text-muted-foreground lg:inline">
            <kbd className="rounded bg-muted px-1 py-0.5">J</kbd>/<kbd className="rounded bg-muted px-1 py-0.5">K</kbd> navegar ·{' '}
            <kbd className="rounded bg-muted px-1 py-0.5">↵</kbd> abrir ·{' '}
            <kbd className="rounded bg-muted px-1 py-0.5">X</kbd> marcar ·{' '}
            <kbd className="rounded bg-muted px-1 py-0.5">⌘K</kbd> palette
          </span>
        </div>
      </div>

      {/* Conteúdo */}
      {isLoading ? (
        <div className="mt-4 space-y-2" data-testid="tasks-skeleton">
          {Array.from({ length: 8 }).map((_, i) => (
            <div key={i} className="h-9 animate-pulse rounded-md bg-muted/50" />
          ))}
        </div>
      ) : tab === 'quadro' ? (
        <div className="mt-4 inline-grid w-full gap-3" style={{ gridTemplateColumns: `repeat(${KANBAN_COLS.length}, minmax(0, 1fr))` }} data-testid="quadro">
          {KANBAN_COLS.map((col) => {
            const items = effectiveKanban[col.key] ?? [];
            return (
              <div
                key={col.key}
                onDragOver={(e) => { e.preventDefault(); setDragOver(col.key); }}
                onDragLeave={() => setDragOver(null)}
                onDrop={() => { const id = dragId.current; dragId.current = null; setDragOver(null); if (id) patchStatus(id, col.key); }}
                className={cn('min-h-[280px] rounded-lg border bg-muted/30 p-2', dragOver === col.key && 'bg-muted/60 ring-2 ring-primary/50')}
                data-testid={`kanban-col-${col.key}`}
              >
                <div className="mb-2 inline-flex w-full items-center justify-between px-1">
                  <TaskStatusPill status={col.key} />
                  <span className="text-xs tabular-nums text-muted-foreground">{items.length}</span>
                </div>
                <div className="inline-flex w-full flex-col gap-2">
                  {items.map((t) => (
                    <div
                      key={t.task_id}
                      role="button"
                      tabIndex={0}
                      draggable
                      onDragStart={() => { dragId.current = t.task_id; setSelectedId(t.task_id); }}
                      onClick={() => { setSelectedId(t.task_id); openDetail(t.task_id); }}
                      onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); setSelectedId(t.task_id); openDetail(t.task_id); } }}
                      className={cn('cursor-grab select-none rounded-lg border bg-card p-2.5 shadow-sm transition-shadow hover:shadow-md active:cursor-grabbing',
                        selectedId === t.task_id && 'ring-1 ring-primary')}
                      data-testid="kanban-card"
                    >
                      <div className="mb-1 inline-flex w-full items-center gap-2">
                        <PriorityDot priority={t.priority} />
                        <span className="font-mono text-[10px] text-muted-foreground">{t.display_id}</span>
                        {t.blocked_by.length > 0 && <Lock size={11} className="ml-auto text-destructive" aria-label="bloqueada" />}
                      </div>
                      <p className="mb-2 line-clamp-2 text-xs font-medium leading-tight">{t.title}</p>
                      <div className="inline-flex w-full items-center gap-2">
                        <span className="rounded bg-muted px-1.5 py-0.5 text-[10px] text-muted-foreground">{t.module}</span>
                        <ActorSeal owner={t.owner} agents={agents} className="ml-auto" />
                      </div>
                    </div>
                  ))}
                  {items.length === 0 && (
                    <div className="rounded-lg border-2 border-dashed border-border py-6 text-center text-xs text-muted-foreground">vazio</div>
                  )}
                </div>
              </div>
            );
          })}
        </div>
      ) : flatBacklog.length === 0 && grouped.length === 0 ? (
        <div className="mt-4">
          <EmptyState
            icon={search ? 'search' : 'inbox'}
            variant={search ? 'search' : 'default'}
            title={search ? `Nada pra "${search}"` : 'Nenhuma task encontrada'}
            description={search ? 'Tenta ajustar a busca ou os filtros.' : 'Rode mcp:tasks:sync ou crie via tasks-create no MCP.'}
          />
        </div>
      ) : (
        <div className="mt-4 overflow-hidden rounded-lg border bg-card" data-testid="backlog">
          {grouped.map(([g, items]) => {
            const isCol = collapsed.has(g);
            return (
              <div key={g}>
                <button
                  type="button"
                  onClick={() => toggleCollapse(g)}
                  className="inline-flex w-full items-center gap-2 border-b border-border/60 bg-muted/40 px-3 py-1.5 text-left text-xs font-medium"
                  data-testid="group-head"
                >
                  <ChevronRight size={13} className={cn('shrink-0 transition-transform', !isCol && 'rotate-90')} />
                  <span>{groupLabel(groupBy, g)}</span>
                  <span className="rounded-full bg-background px-1.5 text-[10px] tabular-nums text-muted-foreground">{items.length}</span>
                </button>
                {!isCol && items.map((t) => {
                  const sel = selectedId === t.task_id;
                  const checked = selected.has(t.task_id);
                  return (
                    <div
                      key={t.task_id}
                      role="button"
                      tabIndex={0}
                      data-testid="task-row"
                      onClick={() => { setSelectedId(t.task_id); openDetail(t.task_id); }}
                      onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); setSelectedId(t.task_id); openDetail(t.task_id); } }}
                      className={cn('inline-flex w-full cursor-pointer items-center gap-3 border-b border-border/60 px-3 text-sm last:border-b-0', rowH,
                        sel ? 'bg-primary/10' : 'hover:bg-muted/50', checked && !sel && 'bg-primary/5')}
                    >
                      <Checkbox
                        checked={checked}
                        onClick={(e) => e.stopPropagation()}
                        onCheckedChange={() => toggleSelect(t.task_id)}
                        className="shrink-0"
                        aria-label={`Selecionar ${t.display_id}`}
                        data-testid="row-check"
                      />
                      <PriorityDot priority={t.priority} />
                      <span className="w-20 shrink-0 truncate font-mono text-[11px] text-muted-foreground">{t.display_id}</span>
                      <span className="min-w-0 flex-1 truncate">{t.title}</span>
                      {t.blocked_by.length > 0 && <Lock size={12} className="shrink-0 text-destructive" aria-label="bloqueada" />}
                      <span className="hidden shrink-0 rounded bg-muted px-1.5 py-0.5 text-[10px] text-muted-foreground sm:inline">{t.module}</span>
                      <span className="hidden w-28 shrink-0 sm:block"><ActorSeal owner={t.owner} agents={agents} /></span>
                      <span className="hidden w-12 shrink-0 text-right text-xs tabular-nums text-muted-foreground md:block">{t.estimate_h ? `${t.estimate_h}h` : ''}</span>
                      <TaskStatusPill status={t.status} className="hidden w-24 shrink-0 md:inline-flex" />
                    </div>
                  );
                })}
              </div>
            );
          })}
        </div>
      )}

      {/* Totalbar */}
      {!isLoading && (
        <div className="mt-3 inline-flex w-full flex-wrap items-center justify-between gap-2 text-[11px] text-muted-foreground" data-testid="totalbar">
          <span className="tabular-nums">
            {tab === 'quadro' ? `${flatKanban.length} no quadro` : `${flatBacklog.length} de ${(backlog ?? []).length} tasks`}
            {' · '}{k.done} concluídas · {k.cancelled} canceladas
          </span>
          <span>
            Drag no Quadro atualiza status (registra <code className="font-mono">mcp_task_events</code>). Edição:{' '}
            <code className="font-mono">tasks-update</code> via MCP.
          </span>
        </div>
      )}

      <BulkActionBar selectedCount={selected.size} onClear={() => setSelected(new Set())} label="tasks">
        <Button size="sm" variant="ghost" onClick={() => bulkSetStatus('doing')}>→ Fazendo</Button>
        <Button size="sm" variant="ghost" onClick={() => bulkSetStatus('review')}>→ Revisão</Button>
        <Button size="sm" variant="ghost" onClick={() => bulkSetStatus('done')}>→ Concluído</Button>
      </BulkActionBar>

      <TaskDrawer taskId={openId} agents={agents} onClose={closeDetail} />
    </>
  );
}

TasksIndex.layout = (page: ReactNode) => (
  <AppShellV2 title="Tarefas — Forja" breadcrumbItems={[{ label: 'Forja' }, { label: 'Tarefas' }]}>
    {page}
  </AppShellV2>
);

export default TasksIndex;
