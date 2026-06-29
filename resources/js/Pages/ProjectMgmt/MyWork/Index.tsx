// @memcofre
//   tela: /project-mgmt/my-work
//   module: ProjectMgmt
//   stories: US-TR-204 (My Work + Inbox)
//   adrs: 0070 (Jira-style PM), 0039 (cockpit)
//   permissao: copiloto.mcp.usage.all
//
// Atalhos:
//   J / K       navegar (My Work em foco)
//   E           avançar status do card selecionado (My Work)
//   R           marcar inbox item lida (Inbox em foco)
//   Tab         alternar foco My Work ↔ Inbox
//   Shift+R     marcar TODAS notifs como lidas

import AppShellV2 from '@/Layouts/AppShellV2';
import { router } from '@inertiajs/react';
import { useEffect, useMemo, useState, type ReactNode } from 'react';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import {
  AlertCircle, AtSign, BellOff, CheckCircle2, CheckSquare, Clock,
  Flame, Inbox as InboxIcon, MessageSquare, RefreshCw, Send, Target, UserPlus,
} from 'lucide-react';
import PageHeader from '@/Components/shared/PageHeader';
import KpiGrid from '@/Components/shared/KpiGrid';
import KpiCard from '@/Components/shared/KpiCard';
import TaskCard, { type BoardTask } from '@/Components/board/TaskCard';
import { nextStatus } from '@/Components/board/badges';

interface CycleHeader {
  id?: number;
  key: string;
  label: string;
  goal?: string | null;
  is_active: boolean;
  days_remaining: number | null;
}

interface MyWorkBucket { header: CycleHeader; tasks: BoardTask[] }

interface InboxItem {
  id: number;
  type: 'mention' | 'assigned' | 'review_requested' | 'status_changed' | 'commented' | 'due_soon' | 'blocked_resolved';
  task_id: string | null;
  actor_id: number | null;
  actor_name: string;
  body: string | null;
  created_at: string | null;
  read_at: string | null;
  is_read: boolean;
}

interface Kpis { doing: number; review: number; blocked: number; p0: number; overdue: number; unread: number }

interface Props {
  project: { id: number; key: string; name: string } | null;
  username: string;
  // my_work/inbox/inbox_stats/kpis chegam via Inertia::defer (MyWorkController:50-53)
  // → `undefined` no 1º paint. Tipados opcionais + default-guard no destructuring
  // pra NÃO crashar React antes do defer chegar (skill inertia-defer-default,
  // Opção B; espelha OficinaAuto/ServiceOrders/Index.tsx). Sintoma do bug:
  // my_work.forEach() sobre undefined → tela branca.
  my_work?: MyWorkBucket[];
  inbox?: InboxItem[];
  inbox_stats?: { unread: number; total_30d: number };
  kpis?: Kpis;
  filters: { show_read: boolean };
}

// Default-guard pros props deferred (começam vazios/zerados até o defer resolver).
const EMPTY_KPIS: Kpis = { doing: 0, review: 0, blocked: 0, p0: 0, overdue: 0, unread: 0 };
const EMPTY_INBOX_STATS = { unread: 0, total_30d: 0 };

const LS_FOCUS = 'oimpresso.mywork.focus';

const TYPE_ICON = {
  mention:           AtSign,
  assigned:          UserPlus,
  review_requested:  Send,
  status_changed:    RefreshCw,
  commented:         MessageSquare,
  due_soon:          Clock,
  blocked_resolved:  CheckCircle2,
} as const;

const TYPE_LABEL = {
  mention:           'mencionou',
  assigned:          'atribuiu',
  review_requested:  'pediu revisão',
  status_changed:    'mudou status',
  commented:         'comentou',
  due_soon:          'prazo apertando',
  blocked_resolved:  'desbloqueou',
} as const;

function timeAgo(iso: string | null): string {
  if (!iso) return '';
  const d = new Date(iso);
  const diff = Date.now() - d.getTime();
  const min = Math.round(diff / 60_000);
  if (min < 1) return 'agora';
  if (min < 60) return `${min}m`;
  const h = Math.round(min / 60);
  if (h < 24) return `${h}h`;
  const days = Math.round(h / 24);
  if (days < 30) return `${days}d`;
  return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
}

function csrfToken(): string {
  return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
}

function MyWorkIndex({
  project,
  username,
  my_work = [],
  inbox = [],
  inbox_stats = EMPTY_INBOX_STATS,
  kpis = EMPTY_KPIS,
  filters,
}: Props) {
  const [focus, setFocus] = useState<'work' | 'inbox'>(() => {
    if (typeof window === 'undefined') return 'work';
    return (localStorage.getItem(LS_FOCUS) as 'work' | 'inbox') === 'inbox' ? 'inbox' : 'work';
  });
  useEffect(() => { localStorage.setItem(LS_FOCUS, focus); }, [focus]);

  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [selectedInboxId, setSelectedInboxId] = useState<number | null>(null);
  const [optimisticRead, setOptimisticRead] = useState<Set<number>>(new Set());
  const [optimisticStatus, setOptimisticStatus] = useState<Record<string, BoardTask['status']>>({});

  const linearTasks = useMemo(() => {
    const out: BoardTask[] = [];
    my_work.forEach((b) => {
      b.tasks.forEach((t) => {
        const s = optimisticStatus[t.task_id] ?? t.status;
        if (s === 'done' || s === 'cancelled') return;
        out.push({ ...t, status: s });
      });
    });
    return out;
  }, [my_work, optimisticStatus]);

  useEffect(() => {
    if (focus === 'work' && !selectedId && linearTasks.length) {
      setSelectedId(linearTasks[0].task_id);
    }
    if (focus === 'inbox' && !selectedInboxId && inbox.length) {
      setSelectedInboxId(inbox[0].id);
    }
  }, [focus, selectedId, selectedInboxId, linearTasks, inbox]);

  useEffect(() => {
    if (selectedId && !linearTasks.find((t) => t.task_id === selectedId)) {
      setSelectedId(linearTasks[0]?.task_id ?? null);
    }
  }, [linearTasks, selectedId]);

  function bumpStatus(taskId: string, newStatus: BoardTask['status']) {
    setOptimisticStatus((prev) => ({ ...prev, [taskId]: newStatus }));
    fetch(`/project-mgmt/my-work/${taskId}/status`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
      body: JSON.stringify({ status: newStatus }),
    })
      .then((r) => {
        if (!r.ok) {
          setOptimisticStatus((prev) => { const n = { ...prev }; delete n[taskId]; return n; });
        } else {
          router.reload({ only: ['my_work', 'kpis'], preserveScroll: true });
        }
      })
      .catch(() => {
        setOptimisticStatus((prev) => { const n = { ...prev }; delete n[taskId]; return n; });
      });
  }

  function markRead(id: number) {
    setOptimisticRead((prev) => new Set(prev).add(id));
    fetch(`/project-mgmt/my-work/inbox/${id}/read`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
    })
      .then((r) => {
        if (!r.ok) {
          setOptimisticRead((prev) => { const n = new Set(prev); n.delete(id); return n; });
        } else {
          router.reload({ only: ['inbox', 'inbox_stats', 'kpis'], preserveScroll: true });
        }
      })
      .catch(() => {
        setOptimisticRead((prev) => { const n = new Set(prev); n.delete(id); return n; });
      });
  }

  function markAllRead() {
    fetch('/project-mgmt/my-work/inbox/read-all', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
    }).then(() => router.reload({ only: ['inbox', 'inbox_stats', 'kpis'], preserveScroll: true }));
  }

  useEffect(() => {
    function onKey(e: KeyboardEvent) {
      const tgt = e.target as HTMLElement;
      if (tgt && (tgt.tagName === 'INPUT' || tgt.tagName === 'TEXTAREA' || tgt.isContentEditable)) return;

      if (e.key === 'Tab') {
        e.preventDefault();
        setFocus((f) => (f === 'work' ? 'inbox' : 'work'));
        return;
      }

      if (focus === 'work') {
        if (!linearTasks.length) return;
        const idx = selectedId ? linearTasks.findIndex((t) => t.task_id === selectedId) : -1;
        const cur = idx >= 0 ? linearTasks[idx] : null;

        if (e.key === 'j' || e.key === 'J') {
          e.preventDefault();
          const n = idx < 0 ? 0 : Math.min(linearTasks.length - 1, idx + 1);
          setSelectedId(linearTasks[n]?.task_id ?? null);
        } else if (e.key === 'k' || e.key === 'K') {
          e.preventDefault();
          const p = idx <= 0 ? 0 : idx - 1;
          setSelectedId(linearTasks[p]?.task_id ?? null);
        } else if ((e.key === 'e' || e.key === 'E') && cur) {
          e.preventDefault();
          const ns = nextStatus(cur.status);
          if (ns !== cur.status) bumpStatus(cur.task_id, ns);
        }
      }

      if (focus === 'inbox') {
        if (!inbox.length) return;
        const idx = selectedInboxId ? inbox.findIndex((i) => i.id === selectedInboxId) : -1;
        const cur = idx >= 0 ? inbox[idx] : null;

        if (e.key === 'j' || e.key === 'J') {
          e.preventDefault();
          const n = idx < 0 ? 0 : Math.min(inbox.length - 1, idx + 1);
          setSelectedInboxId(inbox[n]?.id ?? null);
        } else if (e.key === 'k' || e.key === 'K') {
          e.preventDefault();
          const p = idx <= 0 ? 0 : idx - 1;
          setSelectedInboxId(inbox[p]?.id ?? null);
        } else if (e.key === 'r' && !e.shiftKey && cur && !cur.is_read && !optimisticRead.has(cur.id)) {
          e.preventDefault();
          markRead(cur.id);
        } else if (e.key === 'R' && e.shiftKey) {
          e.preventDefault();
          markAllRead();
        }
      }
    }
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
     
  }, [focus, linearTasks, selectedId, inbox, selectedInboxId, optimisticRead]);

  useEffect(() => {
    const reload = () => router.reload({ only: ['my_work', 'inbox', 'inbox_stats', 'kpis'], preserveScroll: true });
    const id = setInterval(reload, 30_000);
    window.addEventListener('focus', reload);
    return () => { clearInterval(id); window.removeEventListener('focus', reload); };
  }, []);

  return (
    <>
      <PageHeader
        icon="CheckSquare"
        title={`Bom dia, ${username || 'humano'}`}
        description={
          project
            ? `${kpis.doing} doing · ${kpis.review} em revisão · ${kpis.blocked} bloqueadas · ${kpis.unread} notifs não-lidas`
            : 'Sem projeto default configurado'
        }
        action={
          <div className="text-[11px] text-muted-foreground space-x-2">
            <kbd className="px-1 py-0.5 rounded bg-muted">Tab</kbd>{' '}trocar foco ·{' '}
            <kbd className="px-1 py-0.5 rounded bg-muted">J/K</kbd>{' '}navegar ·{' '}
            <kbd className="px-1 py-0.5 rounded bg-muted">E</kbd>{' '}avançar ·{' '}
            <kbd className="px-1 py-0.5 rounded bg-muted">R</kbd>{' '}lida
          </div>
        }
      />

      <KpiGrid cols={5} className="mt-4">
        <KpiCard icon="Loader" tone="default" label="Doing" value={String(kpis.doing)} />
        <KpiCard icon="Eye" tone="default" label="Em revisão" value={String(kpis.review)} />
        <KpiCard icon="Lock" tone={kpis.blocked > 0 ? 'warning' : 'success'} label="Bloqueadas" value={String(kpis.blocked)} />
        <KpiCard icon="AlertCircle" tone={kpis.overdue > 0 ? 'danger' : 'success'} label="Atrasadas" value={String(kpis.overdue)} />
        <KpiCard icon="Bell" tone={kpis.unread > 0 ? 'info' : 'default'} label="Inbox não-lidas" value={String(kpis.unread)} />
      </KpiGrid>

      <div className="grid mt-4 gap-4" style={{ gridTemplateColumns: 'minmax(0, 2fr) minmax(0, 1fr)' }}>
        <section
          className={[
            'rounded-lg border bg-muted/20 p-4 transition-all',
            focus === 'work' ? 'ring-2 ring-primary/60' : 'opacity-90',
          ].join(' ')}
          onClick={() => setFocus('work')}
        >
          <header className="flex items-center justify-between mb-3">
            <div className="flex items-center gap-2">
              <CheckSquare size={16} className="text-muted-foreground" />
              <h2 className="text-sm font-semibold uppercase tracking-wide">My Work</h2>
              <Badge variant="outline" className="font-mono text-[10px]">{linearTasks.length}</Badge>
            </div>
            <a href="/project-mgmt/board" className="text-[11px] text-muted-foreground hover:underline">
              Ver no Board →
            </a>
          </header>

          {linearTasks.length === 0 && (
            <div className="text-center py-12 border-2 border-dashed rounded-lg text-muted-foreground">
              <Flame size={20} className="mx-auto mb-2 opacity-40" />
              <p className="text-sm">Caixa vazia. Hora de pegar algo do Backlog.</p>
            </div>
          )}

          <div className="flex flex-col gap-4">
            {my_work.map((bucket, bi) => (
              bucket.tasks.length === 0 && bucket.header.is_active === false ? null : (
                <div key={bi}>
                  <div className="flex items-center justify-between mb-2 px-1">
                    <div className="flex items-center gap-2">
                      <span className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        {bucket.header.label}
                      </span>
                      {bucket.header.is_active && (
                        <Badge variant="default" className="text-[10px] h-4 px-1.5">ativo</Badge>
                      )}
                      {typeof bucket.header.days_remaining === 'number' && (
                        <span className="text-[10px] text-muted-foreground">
                          {bucket.header.days_remaining}d restantes
                        </span>
                      )}
                    </div>
                    <span className="text-[10px] font-mono text-muted-foreground">{bucket.tasks.length}</span>
                  </div>
                  {bucket.header.goal && (
                    <p className="text-[11px] text-muted-foreground italic mb-2 px-1 line-clamp-2">
                      <Target className="h-3 w-3 mr-1 inline align-text-bottom not-italic" /> {bucket.header.goal}
                    </p>
                  )}
                  <div className="flex flex-col gap-2">
                    {bucket.tasks.map((t) => {
                      const eff = { ...t, status: optimisticStatus[t.task_id] ?? t.status };
                      if (eff.status === 'done' || eff.status === 'cancelled') return null;
                      return (
                        <TaskCard
                          key={t.task_id}
                          task={eff}
                          selected={focus === 'work' && t.task_id === selectedId}
                          onClick={() => { setFocus('work'); setSelectedId(t.task_id); }}
                        />
                      );
                    })}
                  </div>
                </div>
              )
            ))}
          </div>
        </section>

        <section
          className={[
            'rounded-lg border bg-muted/20 p-4 transition-all',
            focus === 'inbox' ? 'ring-2 ring-primary/60' : 'opacity-90',
          ].join(' ')}
          onClick={() => setFocus('inbox')}
        >
          <header className="flex items-center justify-between mb-3">
            <div className="flex items-center gap-2">
              <InboxIcon size={16} className="text-muted-foreground" />
              <h2 className="text-sm font-semibold uppercase tracking-wide">Inbox</h2>
              <Badge variant={inbox_stats.unread > 0 ? 'default' : 'outline'} className="font-mono text-[10px]">
                {inbox_stats.unread} novas
              </Badge>
            </div>
            <div className="flex gap-1">
              <Button
                variant="ghost"
                size="sm"
                className="h-6 text-[10px] px-2"
                onClick={(e) => {
                  e.stopPropagation();
                  router.get('/project-mgmt/my-work', { show_read: filters.show_read ? '' : '1' }, { preserveScroll: true });
                }}
              >
                {filters.show_read ? 'só não-lidas' : 'mostrar lidas'}
              </Button>
              {inbox_stats.unread > 0 && (
                <Button
                  variant="ghost"
                  size="sm"
                  className="h-6 text-[10px] px-2"
                  onClick={(e) => { e.stopPropagation(); markAllRead(); }}
                  title="Shift+R"
                >
                  marcar todas
                </Button>
              )}
            </div>
          </header>

          {inbox.length === 0 && (
            <div className="text-center py-12 border-2 border-dashed rounded-lg text-muted-foreground">
              <BellOff size={20} className="mx-auto mb-2 opacity-40" />
              <p className="text-sm">Nada na caixa.</p>
              <p className="text-[11px] mt-1">Notifs aparecem aqui ao @mention, atribuição ou review.</p>
            </div>
          )}

          <div className="flex flex-col gap-1.5">
            {inbox.map((item) => {
              const Icon = TYPE_ICON[item.type] ?? AlertCircle;
              const wasRead = item.is_read || optimisticRead.has(item.id);
              return (
                <button
                  type="button"
                  key={item.id}
                  onClick={() => {
                    setFocus('inbox');
                    setSelectedInboxId(item.id);
                    if (!wasRead) markRead(item.id);
                    if (item.task_id) {
                      router.visit(`/project-mgmt/board?focus=${item.task_id}`);
                    }
                  }}
                  className={[
                    'group flex items-start gap-2 p-2 rounded-md text-left transition-colors hover:bg-muted',
                    focus === 'inbox' && selectedInboxId === item.id ? 'bg-muted ring-1 ring-primary/60' : '',
                    wasRead ? 'opacity-60' : '',
                  ].filter(Boolean).join(' ')}
                >
                  <Icon size={14} className="mt-0.5 shrink-0 text-muted-foreground" />
                  <div className="flex-1 min-w-0">
                    <p className="text-xs leading-tight">
                      <span className="font-semibold">{item.actor_name}</span>{' '}
                      <span className="text-muted-foreground">{TYPE_LABEL[item.type] ?? item.type}</span>
                      {item.task_id && (
                        <span className="font-mono text-[10px] ml-1 px-1 py-0.5 rounded bg-muted">
                          {item.task_id}
                        </span>
                      )}
                    </p>
                    {item.body && (
                      <p className="text-[11px] text-muted-foreground line-clamp-2 mt-0.5">{item.body}</p>
                    )}
                    <span className="text-[10px] text-muted-foreground/70">{timeAgo(item.created_at)}</span>
                  </div>
                  {!wasRead && (
                    <span className="w-1.5 h-1.5 rounded-full bg-blue-500 mt-1.5 shrink-0" aria-label="não lida" />
                  )}
                </button>
              );
            })}
          </div>
        </section>
      </div>
    </>
  );
}

MyWorkIndex.layout = (page: ReactNode) => (
  <AppShellV2
    title="Project Mgmt — My Work"
    breadcrumbItems={[{ label: 'Project Mgmt' }, { label: 'My Work' }]}
  >
    {page}
  </AppShellV2>
);

export default MyWorkIndex;
