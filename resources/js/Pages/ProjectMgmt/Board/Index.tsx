// @memcofre
//   tela: /project-mgmt/board
//   module: ProjectMgmt
//   stories: US-TR-201 (Kanban Page)
//   adrs: 0070 (Jira-style PM), 0039 (cockpit como layout-mae do ERP)
//   tests: Modules/ProjectMgmt/Tests/Feature/BoardControllerTest (TBD)
//   status: implementada (PR1)
//   permissao: copiloto.mcp.usage.all
//
// Atalhos (DESIGN.md §13):
//   J / K    navegar cards (próximo / anterior)
//   E        avançar status do card selecionado (todo→doing→review→done)
//   A        voltar status do card selecionado
//   /        focar busca da lista
//
// Persistência (DESIGN.md §12 — prefixo oimpresso.):
//   oimpresso.board.cycle    cycle id em foco
//   oimpresso.board.epic     epic id filtrado
//   oimpresso.board.owner    owner filtrado
//   oimpresso.board.search   texto de busca

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
import BoardColumn from '@/Components/board/BoardColumn';
import { type BoardTask } from '@/Components/board/TaskCard';
import { nextStatus, prevStatus, type Status } from '@/Components/board/badges';
import DetailSheet from './DetailSheet';

interface CycleHeader {
  id: number;
  key: string;
  name: string | null;
  goal: string | null;
  start_date: string | null;
  end_date: string | null;
  status: 'planning' | 'active' | 'closed';
  days_remaining: number;
  progress_percent: number;
}

interface CycleOption {
  id: number;
  key: string;
  name: string | null;
  status: 'planning' | 'active' | 'closed';
  is_active: boolean;
}

interface EpicOption { id: number; key: string; title: string }

interface Kpis {
  total: number;
  doing: number;
  review: number;
  blocked: number;
  p0_aberto: number;
}

interface Props {
  project: { id: number; key: string; name: string } | null;
  cycle: CycleHeader | null;
  kanban: Record<string, BoardTask[]>;
  kpis: Kpis;
  columns: Status[];
  // epics/cycles/owners chegam via Inertia::defer (BoardController:109-111) →
  // `undefined` no 1º paint. Tipados opcionais + default-guard no destructuring
  // pra NÃO crashar React antes do defer chegar (skill inertia-defer-default,
  // Opção B; espelha OficinaAuto/ServiceOrders/Index.tsx). Sintoma do bug:
  // cycles.map() sobre undefined → tela branca. kanban/kpis/columns/cycle ficam
  // eager (rollback PR #963 — atalhos J/K exigem cards já no initial render).
  epics?: EpicOption[];
  cycles?: CycleOption[];
  owners?: string[];
  filters: {
    project: string | null;
    cycle: number | null;
    epic: number | null;
    component: number | null;
    owner: string | null;
  };
}

const LS = {
  CYCLE:  'oimpresso.board.cycle',
  EPIC:   'oimpresso.board.epic',
  OWNER:  'oimpresso.board.owner',
  SEARCH: 'oimpresso.board.search',
} as const;

const ALL = '__all__';

function BoardIndex({ project, cycle, kanban, kpis, columns, epics = [], cycles = [], owners = [], filters }: Props) {
  // ── Filtros (controlled, com fallback pro localStorage)
  const [cycleId, setCycleId] = useState<string>(() => {
    if (filters.cycle) return String(filters.cycle);
    if (typeof window !== 'undefined') return localStorage.getItem(LS.CYCLE) ?? ALL;
    return ALL;
  });
  const [epicId, setEpicId] = useState<string>(() => {
    if (filters.epic) return String(filters.epic);
    if (typeof window !== 'undefined') return localStorage.getItem(LS.EPIC) ?? ALL;
    return ALL;
  });
  const [owner, setOwner] = useState<string>(() => {
    if (filters.owner) return filters.owner;
    if (typeof window !== 'undefined') return localStorage.getItem(LS.OWNER) ?? ALL;
    return ALL;
  });
  const [search, setSearch] = useState<string>(() => {
    if (typeof window !== 'undefined') return localStorage.getItem(LS.SEARCH) ?? '';
    return '';
  });

  // ── Persistência localStorage
  useEffect(() => { localStorage.setItem(LS.CYCLE, cycleId); }, [cycleId]);
  useEffect(() => { localStorage.setItem(LS.EPIC, epicId); }, [epicId]);
  useEffect(() => { localStorage.setItem(LS.OWNER, owner); }, [owner]);
  useEffect(() => { localStorage.setItem(LS.SEARCH, search); }, [search]);

  // ── Drag-drop optimistic
  const dragId = useRef<string | null>(null);
  const [optimistic, setOptimistic] = useState<Record<string, Status>>({});
  // PMG-001 (ADR 0100) — banner inline pra 409 conflict / 403 / outros erros
  const [conflictMessage, setConflictMessage] = useState<string | null>(null);

  // ── Detail Sheet (PMG-004, ADR 0100) — open task via URL ?task=ID
  const [openTaskId, setOpenTaskId] = useState<string | null>(() => {
    if (typeof window === 'undefined') return null;
    const params = new URLSearchParams(window.location.search);
    return params.get('task');
  });

  function openDetail(taskId: string) {
    setOpenTaskId(taskId);
    // Atualiza URL preservando outros params; sem reload do board (preserveState).
    const url = new URL(window.location.href);
    url.searchParams.set('task', taskId);
    window.history.replaceState({}, '', url.toString());
  }

  function closeDetail() {
    setOpenTaskId(null);
    const url = new URL(window.location.href);
    url.searchParams.delete('task');
    window.history.replaceState({}, '', url.toString());
  }

  // Auto-dismiss conflictMessage após 5s
  useEffect(() => {
    if (!conflictMessage) return;
    const tid = setTimeout(() => setConflictMessage(null), 5000);
    return () => clearTimeout(tid);
  }, [conflictMessage]);

  function patchStatus(taskId: string, status: Status) {
    // Encontra updated_at atual da task (pra optimistic-lock)
    let expectedUpdatedAt: number | undefined;
    for (const list of Object.values(kanban)) {
      const t = list.find((x) => x.task_id === taskId);
      if (t && typeof t.updated_at === 'number') {
        expectedUpdatedAt = t.updated_at;
        break;
      }
    }

    setOptimistic((prev) => ({ ...prev, [taskId]: status }));

    const csrfToken =
      (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';

    fetch(`/project-mgmt/board/${taskId}/status`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
      body: JSON.stringify({
        status,
        ...(expectedUpdatedAt !== undefined ? { expected_updated_at: expectedUpdatedAt } : {}),
      }),
    })
      .then(async (r) => {
        if (r.ok) {
          // Sucesso: pede reload parcial pra reconciliar com servidor
          router.reload({ only: ['kanban', 'kpis'], preserveScroll: true });
          return;
        }

        // Reverte otimismo
        setOptimistic((prev) => {
          const n = { ...prev };
          delete n[taskId];
          return n;
        });

        // R-PMG-005 — 409 Conflict: refetch silencioso + toast informativo
        if (r.status === 409) {
          try {
            const data = await r.json();
            const cur = data?.current?.status ? ` (agora: ${data.current.status})` : '';
            setConflictMessage(`Task atualizada por outro usuário${cur}. Board sincronizado.`);
          } catch {
            setConflictMessage('Task atualizada por outro usuário. Board sincronizado.');
          }
          router.reload({ only: ['kanban', 'kpis'], preserveScroll: true });
          return;
        }

        if (r.status === 403) {
          setConflictMessage('Sem permissão para mover tasks.');
          return;
        }

        setConflictMessage('Erro ao atualizar task. Tenta novamente.');
      })
      .catch(() => {
        setOptimistic((prev) => {
          const n = { ...prev };
          delete n[taskId];
          return n;
        });
        setConflictMessage('Erro de rede. Tenta novamente.');
      });
  }

  // ── Build effective list aplicando optimistic + busca client-side
  const searchLower = search.trim().toLowerCase();

  const effective = useMemo(() => {
    const out: Record<Status, BoardTask[]> = {
      backlog: [], todo: [], doing: [], review: [], done: [], blocked: [], cancelled: [],
    };
    for (const col of columns) out[col] = [];

    // Coleta tasks de TODAS as colunas (inclui blocked já mergeado em todo pelo backend)
    const seen = new Set<string>();
    Object.values(kanban).forEach((list) => {
      list.forEach((t) => {
        if (seen.has(t.task_id)) return;
        seen.add(t.task_id);

        if (searchLower) {
          const haystack = `${t.display_id} ${t.title} ${t.owner ?? ''} ${t.module}`.toLowerCase();
          if (!haystack.includes(searchLower)) return;
        }

        const status = optimistic[t.task_id] ?? t.status;
        const target = (out[status as Status] ?? out.todo);
        target.push({ ...t, status: status as Status });
      });
    });
    return out;
  }, [kanban, optimistic, searchLower, columns]);

  // ── Lista linear pra navegação J/K
  const linearTasks = useMemo(() => {
    const list: BoardTask[] = [];
    for (const col of columns) list.push(...(effective[col] ?? []));
    return list;
  }, [effective, columns]);

  const [selectedId, setSelectedId] = useState<string | null>(null);

  // Quando lista muda e o selecionado some, escolhe o primeiro
  useEffect(() => {
    if (!linearTasks.length) {
      setSelectedId(null);
      return;
    }
    if (selectedId && !linearTasks.find((t) => t.task_id === selectedId)) {
      setSelectedId(linearTasks[0]?.task_id ?? null);
    }
  }, [linearTasks, selectedId]);

  // ── Atalhos J/K/E/A + /
  const searchInputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    function onKey(e: KeyboardEvent) {
      const target = e.target as HTMLElement | null;
      const isTyping = target && (
        target.tagName === 'INPUT' ||
        target.tagName === 'TEXTAREA' ||
        target.isContentEditable
      );

      // "/" sempre foca busca, mesmo digitando? não — só quando NÃO está digitando
      if (e.key === '/' && !isTyping) {
        e.preventDefault();
        searchInputRef.current?.focus();
        return;
      }

      if (isTyping) return;

      const idx = selectedId ? linearTasks.findIndex((t) => t.task_id === selectedId) : -1;
      const current = idx >= 0 ? linearTasks[idx] : null;

      if (e.key === 'j' || e.key === 'J') {
        e.preventDefault();
        const next = idx < 0 ? 0 : Math.min(linearTasks.length - 1, idx + 1);
        setSelectedId(linearTasks[next]?.task_id ?? null);
      } else if (e.key === 'k' || e.key === 'K') {
        e.preventDefault();
        const prev = idx <= 0 ? 0 : idx - 1;
        setSelectedId(linearTasks[prev]?.task_id ?? null);
      } else if (e.key === 'e' || e.key === 'E') {
        if (!current) return;
        e.preventDefault();
        const ns = nextStatus(current.status);
        if (ns !== current.status) patchStatus(current.task_id, ns);
      } else if (e.key === 'a' || e.key === 'A') {
        if (!current) return;
        e.preventDefault();
        const ps = prevStatus(current.status);
        if (ps !== current.status) patchStatus(current.task_id, ps);
      }
    }

    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [linearTasks, selectedId]);

  // ── Polling 10s + on-focus
  useEffect(() => {
    const reload = () => router.reload({ only: ['kanban', 'kpis', 'cycle'], preserveScroll: true });
    const interval = setInterval(reload, 10_000);
    window.addEventListener('focus', reload);
    return () => {
      clearInterval(interval);
      window.removeEventListener('focus', reload);
    };
  }, []);

  // ── Filtros aplicar (server-side)
  function aplicar(patch: Partial<{ cycle: string; epic: string; owner: string }>) {
    const params: Record<string, string> = {};
    const c = patch.cycle ?? cycleId;
    const e = patch.epic ?? epicId;
    const o = patch.owner ?? owner;
    if (c !== ALL) params.cycle = c;
    if (e !== ALL) params.epic = e;
    if (o !== ALL) params.owner = o;
    router.get('/project-mgmt/board', params, { preserveScroll: true, preserveState: true, replace: true });
  }

  function limpar() {
    setCycleId(ALL);
    setEpicId(ALL);
    setOwner(ALL);
    router.get('/project-mgmt/board', {}, { preserveScroll: true, preserveState: true, replace: true });
  }

  const cycleAtivoBadge = cycle && cycle.status === 'active'
    ? `${cycle.key}${cycle.name ? ' — ' + cycle.name : ''} · ${cycle.days_remaining}d restantes · ${cycle.progress_percent}% decorrido`
    : null;

  return (
    <>
      <PageHeader
        icon="LayoutKanban"
        title={project ? `${project.name} — Board` : 'Board'}
        description={
          cycleAtivoBadge
            ? cycleAtivoBadge
            : 'Nenhum cycle ativo neste projeto. Use `cycles-create` via MCP pra começar.'
        }
        action={
          <div className="flex items-center gap-2">
            <Input
              ref={searchInputRef}
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Buscar (/)…"
              className="h-8 w-48 text-xs"
            />
          </div>
        }
      />

      {conflictMessage && (
        <div
          role="alert"
          className="mt-4 flex items-center justify-between rounded-md border border-warning/20 bg-warning-soft px-3 py-2 text-sm text-warning-fg"
        >
          <span>{conflictMessage}</span>
          <button
            type="button"
            onClick={() => setConflictMessage(null)}
            className="text-xs font-medium underline-offset-2 hover:underline"
          >
            ok
          </button>
        </div>
      )}

      {cycle?.goal && (
        <Card className="mt-4 border-l-4 border-l-blue-500">
          <CardContent className="py-3 px-4">
            <p className="text-xs text-muted-foreground mb-0.5">Goal do cycle</p>
            <p className="text-sm">{cycle.goal}</p>
          </CardContent>
        </Card>
      )}

      <KpiGrid cols={4} className="mt-4">
        <KpiCard icon="list" tone="default" label="Total filtrado" value={String(kpis.total)} />
        <KpiCard
          icon="alert-circle"
          tone={kpis.p0_aberto > 0 ? 'danger' : 'success'}
          label="P0 abertas"
          value={String(kpis.p0_aberto)}
        />
        <KpiCard icon="loader" tone="default" label="Doing" value={String(kpis.doing)} />
        <KpiCard
          icon="lock"
          tone={kpis.blocked > 0 ? 'warning' : 'success'}
          label="Bloqueadas"
          value={String(kpis.blocked)}
        />
      </KpiGrid>

      <Card className="mt-4">
        <CardContent className="py-3 flex flex-wrap items-end gap-3">
          <div className="w-48">
            <Label className="text-xs">Cycle</Label>
            <Select value={cycleId} onValueChange={(v) => { setCycleId(v); aplicar({ cycle: v }); }}>
              <SelectTrigger className="h-8"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value={ALL}>Todos os cycles</SelectItem>
                {cycles.map((c) => (
                  <SelectItem key={c.id} value={String(c.id)}>
                    {c.key}{c.name ? ' — ' + c.name : ''}{c.is_active ? ' (ativo)' : ''}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div className="w-44">
            <Label className="text-xs">Epic</Label>
            <Select value={epicId} onValueChange={(v) => { setEpicId(v); aplicar({ epic: v }); }}>
              <SelectTrigger className="h-8"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value={ALL}>Todos os epics</SelectItem>
                {epics.map((e) => (
                  <SelectItem key={e.id} value={String(e.id)}>
                    {e.key} — {e.title}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div className="w-36">
            <Label className="text-xs">Owner</Label>
            <Select value={owner} onValueChange={(v) => { setOwner(v); aplicar({ owner: v }); }}>
              <SelectTrigger className="h-8"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value={ALL}>Todos</SelectItem>
                {owners.map((o) => (
                  <SelectItem key={o} value={o}>{o}</SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          {(cycleId !== ALL || epicId !== ALL || owner !== ALL || search) && (
            <Button variant="ghost" onClick={() => { setSearch(''); limpar(); }} className="h-8 text-xs">
              Limpar
            </Button>
          )}

          <div className="ml-auto text-[11px] text-muted-foreground">
            <kbd className="px-1 py-0.5 rounded bg-muted">J</kbd>{' '}
            <kbd className="px-1 py-0.5 rounded bg-muted">K</kbd> navegar ·{' '}
            <kbd className="px-1 py-0.5 rounded bg-muted">E</kbd> avançar ·{' '}
            <kbd className="px-1 py-0.5 rounded bg-muted">A</kbd> voltar ·{' '}
            <kbd className="px-1 py-0.5 rounded bg-muted">/</kbd> buscar
          </div>
        </CardContent>
      </Card>

      <div
        className="grid gap-3 mt-4"
        style={{ gridTemplateColumns: `repeat(${columns.length}, minmax(0, 1fr))` }}
      >
        {columns.map((col) => (
          <BoardColumn
            key={col}
            status={col}
            tasks={effective[col] ?? []}
            selectedTaskId={selectedId}
            onDragStart={(id) => { dragId.current = id; setSelectedId(id); }}
            onDrop={(target) => {
              const id = dragId.current;
              dragId.current = null;
              if (!id) return;
              patchStatus(id, target);
            }}
            onCardClick={(t) => { setSelectedId(t.task_id); openDetail(t.task_id); }}
          />
        ))}
      </div>

      <p className="mt-4 text-xs text-muted-foreground">
        Drag-drop atualiza status e registra evento em <code className="font-mono">mcp_task_events</code>.
        Click no card abre Detail Sheet (PMG-004). Edição inline de campos: PMG-005+.
      </p>

      {/* PMG-004 — Detail Sheet abre via URL ?task=ID */}
      <DetailSheet taskId={openTaskId} onClose={closeDetail} />
    </>
  );
}

BoardIndex.layout = (page: ReactNode) => (
  <AppShellV2
    title="Project Mgmt — Board"
    breadcrumbItems={[{ label: 'Project Mgmt' }, { label: 'Board' }]}
  >
    {page}
  </AppShellV2>
);

export default BoardIndex;
