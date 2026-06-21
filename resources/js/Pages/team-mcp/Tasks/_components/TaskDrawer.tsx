// Forja PR-1 — drawer de issue da tela /team-mcp/tasks.
//   Endpoint: GET /team-mcp/tasks/{taskId}/detail (TasksAdminController@show, read-only).
//   SEM dado fantasma: só situação (status) + atividade real (mcp_task_events) +
//   vínculos (blocked_by resolvidos) + subtasks. Largura 560px (issue ≠ cadastro
//   760px ADR 0185 — exceção registrada no visual-comparison).
//   Locators resilientes via data-testid (NÚCLEO #7). Layout via primitivos (ADR 0253).

import { useEffect, useState } from 'react';
import {
  Activity as ActivityIcon,
  ChevronsRight,
  FileText,
  GitBranch,
  ListChecks,
  Loader2,
  Lock,
  Plus,
  RefreshCw,
} from 'lucide-react';
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetDescription,
} from '@/Components/ui/sheet';
import { Grid, Inline } from '@/Components/layout';
import { cn } from '@/Lib/utils';
import { ActorSeal, PriorityDot, TaskStatusPill } from './taskBadges';
import { type Priority } from './taskTokens';

interface TaskDetail {
  task_id: string;
  display_id: string;
  identifier: string | null;
  title: string;
  description: string | null;
  module: string | null;
  owner: string | null;
  sprint: string | null;
  priority: Priority;
  status: string;
  type: string | null;
  estimate_h: number | null;
  story_points: number | null;
  due_date: string | null;
  blocked_by: string[];
  is_blocked: boolean;
  updated_at?: number;
  created_at: string | null;
}

interface ActivityEvent {
  id: number;
  event_type: string;
  from_value: string | null;
  to_value: string | null;
  author: string | null;
  note: string | null;
  occurred_at: string | null;
}

interface Subtask {
  task_id: string;
  display_id: string;
  title: string;
  status: string;
  priority: Priority;
}

interface Blocker {
  task_id: string;
  display_id: string;
  title: string;
  status: string;
}

interface DetailPayload {
  task: TaskDetail;
  events: ActivityEvent[];
  subtasks: Subtask[];
  blockers: Blocker[];
}

interface Props {
  taskId: string | null;
  agents: string[];
  onClose: () => void;
}

type Tab = 'visao' | 'atividade' | 'subtasks';

const EVENT_LABEL: Record<string, string> = {
  created: 'criou',
  status_changed: 'mudou status',
  assigned: 'atribuiu',
  field_updated: 'atualizou',
  commented: 'comentou',
  cancelled: 'cancelou',
};

function eventIcon(type: string) {
  switch (type) {
    case 'created': return Plus;
    case 'status_changed': return ChevronsRight;
    case 'field_updated': return RefreshCw;
    default: return ActivityIcon;
  }
}

function timeAgo(iso: string | null): string {
  if (!iso) return '';
  const d = new Date(iso);
  const diff = Date.now() - d.getTime();
  const min = Math.round(diff / 60_000);
  if (min < 1) return 'agora';
  if (min < 60) return `${min}m atrás`;
  const h = Math.round(min / 60);
  if (h < 24) return `${h}h atrás`;
  const days = Math.round(h / 24);
  if (days < 30) return `${days}d atrás`;
  return d.toLocaleDateString('pt-BR', { day: '2-digit', month: 'short', year: 'numeric' });
}

export default function TaskDrawer({ taskId, agents, onClose }: Props) {
  const [data, setData] = useState<DetailPayload | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [tab, setTab] = useState<Tab>('visao');

  useEffect(() => {
    if (!taskId) return;
    setLoading(true);
    setError(null);
    setData(null);
    setTab('visao');

    const ctrl = new AbortController();
    fetch(`/team-mcp/tasks/${encodeURIComponent(taskId)}/detail`, {
      headers: { Accept: 'application/json' },
      signal: ctrl.signal,
    })
      .then((r) => {
        if (r.status === 403) throw new Error('Sem permissão para ver esta task.');
        if (r.status === 404) throw new Error('Task não encontrada.');
        if (!r.ok) throw new Error(`Erro ${r.status}`);
        return r.json();
      })
      .then((d: DetailPayload) => {
        setData(d);
        setLoading(false);
      })
      .catch((err: Error) => {
        if (err.name === 'AbortError') return;
        setError(err.message);
        setLoading(false);
      });

    return () => ctrl.abort();
  }, [taskId]);

  const open = !!taskId;
  const t = data?.task;

  const tabs: Array<{ key: Tab; label: string; icon: typeof FileText; count?: number }> = [
    { key: 'visao', label: 'Visão', icon: FileText },
    { key: 'atividade', label: 'Atividade', icon: ActivityIcon, count: data?.events.length },
    { key: 'subtasks', label: 'Subtasks', icon: ListChecks, count: data?.subtasks.length },
  ];

  return (
    <Sheet open={open} onOpenChange={(v) => { if (!v) onClose(); }}>
      <SheetContent
        side="right"
        className="w-full overflow-y-auto sm:max-w-[560px]"
        data-testid="task-drawer"
      >
        <SheetHeader className="border-b">
          <Inline gap={2} align="start">
            <PriorityDot priority={t?.priority ?? 'p2'} className="mt-1.5" />
            <div className="min-w-0 flex-1">
              <Inline gap={2} wrap>
                <span className="font-mono text-xs text-muted-foreground" data-testid="drawer-id">
                  {t?.display_id ?? taskId}
                </span>
                {t?.type && (
                  <span className="rounded bg-muted px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
                    {t.type}
                  </span>
                )}
                {t?.status && <TaskStatusPill status={t.status} />}
                {t?.is_blocked && (
                  <span className="inline-flex items-center gap-1 text-[10px] font-semibold text-destructive">
                    <Lock size={10} /> bloqueada
                  </span>
                )}
              </Inline>
              <SheetTitle className="mt-1 text-base leading-snug" data-testid="drawer-title">
                {t?.title ?? <span className="italic text-muted-foreground">(sem título)</span>}
              </SheetTitle>
              <SheetDescription asChild>
                <Inline gap={2} wrap className="mt-1 text-xs">
                  {t?.module && <span className="rounded bg-muted px-1.5 py-0.5">{t.module}</span>}
                  {t && <ActorSeal owner={t.owner} agents={agents} />}
                  {t?.sprint && <span className="text-muted-foreground">onda {t.sprint}</span>}
                  {t?.estimate_h ? <span className="text-muted-foreground tabular-nums">{t.estimate_h}h</span> : null}
                </Inline>
              </SheetDescription>
            </div>
          </Inline>
        </SheetHeader>

        {!loading && data && (
          <div className="-mt-2 border-b px-4">
            <Inline gap={1} role="tablist">
              {tabs.map((tabItem) => {
                const Icon = tabItem.icon;
                const isActive = tab === tabItem.key;
                return (
                  <button
                    key={tabItem.key}
                    type="button"
                    role="tab"
                    aria-selected={isActive}
                    data-testid={`drawer-tab-${tabItem.key}`}
                    onClick={() => setTab(tabItem.key)}
                    className={cn(
                      'relative inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium transition-colors',
                      isActive
                        ? '-mb-px border-b-2 border-primary text-foreground'
                        : 'text-muted-foreground hover:text-foreground',
                    )}
                  >
                    <Icon size={13} />
                    {tabItem.label}
                    {tabItem.count !== undefined && tabItem.count > 0 && (
                      <span className="ml-1 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-muted px-1 text-[10px] tabular-nums">
                        {tabItem.count}
                      </span>
                    )}
                  </button>
                );
              })}
            </Inline>
          </div>
        )}

        <div className="flex-1 overflow-y-auto px-4 pb-4">
          {loading && (
            <Inline justify="center" className="py-12 text-sm text-muted-foreground">
              <Loader2 className="mr-2 h-4 w-4 animate-spin" /> carregando…
            </Inline>
          )}

          {error && (
            <div className="my-6 rounded-md border border-destructive/20 bg-destructive-soft px-3 py-2 text-sm text-destructive-fg" data-testid="drawer-error">
              {error}
            </div>
          )}

          {!loading && !error && data && t && (
            <>
              {tab === 'visao' && (
                <div className="space-y-5 py-4" data-testid="drawer-visao">
                  <div>
                    {t.description ? (
                      <p className="whitespace-pre-wrap text-sm leading-relaxed">{t.description}</p>
                    ) : (
                      <p className="text-sm italic text-muted-foreground">Sem descrição.</p>
                    )}
                  </div>

                  <Grid cols={2} gap={2} className="text-xs">
                    <div><div className="text-muted-foreground">Situação</div><div className="mt-0.5"><TaskStatusPill status={t.status} /></div></div>
                    <div><div className="text-muted-foreground">Prioridade</div><div className="mt-0.5 inline-flex items-center gap-1.5"><PriorityDot priority={t.priority} />{t.priority.toUpperCase()}</div></div>
                    <div><div className="text-muted-foreground">Responsável</div><div className="mt-0.5"><ActorSeal owner={t.owner} agents={agents} /></div></div>
                    <div><div className="text-muted-foreground">Onda</div><div className="mt-0.5">{t.sprint ?? '—'}</div></div>
                    {t.story_points != null && <div><div className="text-muted-foreground">Story points</div><div className="mt-0.5 tabular-nums">{t.story_points}</div></div>}
                    {t.due_date && <div><div className="text-muted-foreground">Prazo</div><div className="mt-0.5 tabular-nums">{t.due_date}</div></div>}
                  </Grid>

                  {data.blockers.length > 0 && (
                    <div data-testid="drawer-blockers">
                      <h4 className="mb-2 inline-flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                        <GitBranch size={12} /> Bloqueada por ({data.blockers.length})
                      </h4>
                      <ul className="space-y-1.5">
                        {data.blockers.map((b) => (
                          <li key={b.task_id} className="inline-flex w-full items-center gap-2 text-xs">
                            <span className="font-mono text-muted-foreground">{b.display_id}</span>
                            <span className="min-w-0 flex-1 truncate">{b.title}</span>
                            <TaskStatusPill status={b.status} className="shrink-0" />
                          </li>
                        ))}
                      </ul>
                    </div>
                  )}
                </div>
              )}

              {tab === 'atividade' && (
                <div className="py-4" data-testid="drawer-atividade">
                  {data.events.length === 0 ? (
                    <p className="text-sm italic text-muted-foreground">Sem atividade registrada.</p>
                  ) : (
                    <ul className="space-y-2">
                      {data.events.map((e) => {
                        const Icon = eventIcon(e.event_type);
                        const label = EVENT_LABEL[e.event_type] ?? e.event_type;
                        return (
                          <li key={e.id} className="inline-flex w-full items-start gap-2 text-xs">
                            <Icon size={13} className="mt-0.5 shrink-0 text-muted-foreground" />
                            <div className="min-w-0 flex-1">
                              <div>
                                <span className="font-semibold">@{e.author ?? 'system'}</span>{' '}
                                <span className="text-muted-foreground">{label}</span>
                                {e.from_value && e.to_value && (
                                  <>
                                    {' '}<span className="text-muted-foreground">de</span>{' '}
                                    <span className="font-mono text-muted-foreground">{e.from_value}</span>{' '}
                                    <span className="text-muted-foreground">para</span>{' '}
                                    <span className="font-mono text-foreground">{e.to_value}</span>
                                  </>
                                )}
                              </div>
                              {e.note && <p className="mt-0.5 italic text-muted-foreground">{e.note}</p>}
                              <p className="mt-0.5 text-[10px] text-muted-foreground">{timeAgo(e.occurred_at)}</p>
                            </div>
                          </li>
                        );
                      })}
                    </ul>
                  )}
                </div>
              )}

              {tab === 'subtasks' && (
                <div className="py-4" data-testid="drawer-subtasks">
                  {data.subtasks.length === 0 ? (
                    <p className="text-sm italic text-muted-foreground">Sem subtasks.</p>
                  ) : (
                    <ul className="space-y-1">
                      {data.subtasks.map((s) => {
                        const isDone = s.status === 'done' || s.status === 'cancelled';
                        return (
                          <li key={s.task_id} className={cn('inline-flex w-full items-center gap-2 rounded px-2 py-1 text-xs hover:bg-muted/50', isDone && 'opacity-60')}>
                            <PriorityDot priority={s.priority} />
                            <span className="font-mono text-muted-foreground">{s.display_id}</span>
                            <span className={cn('min-w-0 flex-1 truncate', isDone && 'line-through')}>{s.title}</span>
                            <TaskStatusPill status={s.status} className="shrink-0" />
                          </li>
                        );
                      })}
                    </ul>
                  )}
                </div>
              )}
            </>
          )}
        </div>

        {!loading && t && (
          <div className="border-t px-4 py-2 text-[11px] text-muted-foreground">
            Editar campos: <code className="font-mono">tasks-update {t.task_id}</code> via MCP, ou edite o SPEC e rode{' '}
            <code className="font-mono">php artisan mcp:tasks:sync</code>.
          </div>
        )}
      </SheetContent>
    </Sheet>
  );
}
