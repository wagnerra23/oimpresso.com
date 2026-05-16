// @memcofre
//   componente: ProjectMgmt/Board/DetailSheet
//   stories: PMG-004 (ADR 0100) — Detail Sheet Jira-style
//   permissao: copiloto.mcp.usage.all
//
// Sheet slide-in à direita ao clicar num card do Kanban. Tabs
// state-driven (sem lib Tabs nova): Description / Comments / Activity / Subtasks.
//
// Foundation pra Fase 2 inteira:
//   - PMG-005 @mentions (entra no tab Comments com input rico)
//   - PMG-006 Watchers UI (entra como section no header)
//   - PMG-007 Subtasks UI (já tem aba — refinar com create/complete)
//
// Endpoint: GET /project-mgmt/board/{taskId}/detail (BoardController@show).

import { useEffect, useState } from 'react';
import {
  Activity as ActivityIcon,
  AlertCircle,
  Calendar,
  ChevronsRight,
  ExternalLink,
  FileText,
  GitBranch,
  ListChecks,
  Loader2,
  Lock,
  MessageSquare,
  Plus,
  RefreshCw,
  Send,
  UserPlus,
} from 'lucide-react';
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetDescription,
} from '@/Components/ui/sheet';
import { Button } from '@/Components/ui/button';
import MentionInput from '@/Components/MentionInput';
import { PRIORITY_BADGE, type Priority, type Status } from '@/Components/board/badges';

interface TaskDetail {
  task_id: string;
  identifier: string | null;
  display_id: string;
  title: string;
  description: string | null;
  module: string | null;
  owner: string | null;
  priority: Priority;
  status: Status;
  type: string | null;
  estimate_h: number | null;
  story_points: number | null;
  due_date: string | null;
  parent_task_id: number | null;
  is_blocked: boolean;
  is_overdue: boolean;
  updated_at?: number;
  project_key: string | null;
  project_name: string | null;
}

interface Comment {
  id: number;
  author: string | null;
  body: string;
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
  status: Status;
  priority: Priority;
}

interface DependencyTarget {
  display_id: string;
  title: string;
  status: Status;
}

interface Dependency {
  id: number;
  depends_on_task_id: string | null;
  type: string;
  target: DependencyTarget | null;
}

interface DetailPayload {
  task: TaskDetail;
  comments: Comment[];
  events: ActivityEvent[];
  subtasks: Subtask[];
  dependencies: Dependency[];
}

interface Props {
  taskId: string | null;
  onClose: () => void;
}

type Tab = 'description' | 'comments' | 'activity' | 'subtasks';

const EVENT_LABEL: Record<string, string> = {
  created: 'criou',
  status_changed: 'mudou status',
  assigned: 'atribuiu',
  field_updated: 'atualizou',
  commented: 'comentou',
  cancelled: 'cancelou',
};

const STATUS_BADGE: Record<string, string> = {
  backlog: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
  todo: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
  doing: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-200',
  review: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-200',
  done: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-200',
  blocked: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-200',
  cancelled: 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400',
};

function eventIcon(type: string) {
  switch (type) {
    case 'created':
      return Plus;
    case 'status_changed':
      return ChevronsRight;
    case 'assigned':
      return UserPlus;
    case 'commented':
      return MessageSquare;
    case 'field_updated':
      return RefreshCw;
    default:
      return ActivityIcon;
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

function dueShort(iso: string | null): string | null {
  if (!iso) return null;
  return new Date(iso + 'T00:00:00').toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

export default function DetailSheet({ taskId, onClose }: Props) {
  const [data, setData] = useState<DetailPayload | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [tab, setTab] = useState<Tab>('description');

  // PMG-005 (ADR 0100) — formulário comment com @mentions
  const [commentDraft, setCommentDraft] = useState<string>('');
  const [posting, setPosting] = useState(false);
  const [postError, setPostError] = useState<string | null>(null);

  // Fetch quando abre
  useEffect(() => {
    if (!taskId) return;
    setLoading(true);
    setError(null);
    setData(null);
    setTab('description');

    const ctrl = new AbortController();
    fetch(`/project-mgmt/board/${taskId}/detail`, {
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

  // Reset form quando troca de task
  useEffect(() => {
    setCommentDraft('');
    setPostError(null);
  }, [taskId]);

  // PMG-005 — submeter comment + refetch
  async function handlePostComment() {
    if (!taskId || !commentDraft.trim() || posting) return;
    setPosting(true);
    setPostError(null);

    const csrfToken =
      (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';

    try {
      const r = await fetch(`/project-mgmt/board/${taskId}/comment`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify({ body: commentDraft.trim() }),
      });

      if (r.status === 403) throw new Error('Sem permissão para comentar.');
      if (r.status === 422) {
        const j = await r.json().catch(() => ({}));
        throw new Error(j?.message ?? 'Comentário inválido.');
      }
      if (!r.ok) throw new Error(`Erro ${r.status}`);

      const json = await r.json();
      // Otimista: anexa novo comment local e clear draft
      setData((prev) =>
        prev ? { ...prev, comments: [...prev.comments, json.comment] } : prev
      );
      setCommentDraft('');
    } catch (err) {
      setPostError(err instanceof Error ? err.message : 'Erro ao enviar.');
    } finally {
      setPosting(false);
    }
  }

  const open = !!taskId;
  const t = data?.task;

  const tabs: Array<{ key: Tab; label: string; icon: typeof FileText; count?: number }> = [
    { key: 'description', label: 'Descrição', icon: FileText },
    { key: 'comments', label: 'Comentários', icon: MessageSquare, count: data?.comments.length },
    { key: 'activity', label: 'Atividade', icon: ActivityIcon, count: data?.events.length },
    { key: 'subtasks', label: 'Subtasks', icon: ListChecks, count: data?.subtasks.length },
  ];

  return (
    <Sheet open={open} onOpenChange={(v) => { if (!v) onClose(); }}>
      <SheetContent className="w-full sm:max-w-2xl overflow-y-auto">
        <SheetHeader className="border-b">
          <div className="flex items-start gap-2">
            <span className={`mt-1 inline-block h-2 w-2 rounded-full ${
              t?.priority === 'p0' ? 'bg-red-500' :
              t?.priority === 'p1' ? 'bg-orange-500' :
              t?.priority === 'p2' ? 'bg-yellow-500' :
              'bg-blue-500'
            }`} />
            <div className="flex-1 min-w-0">
              <div className="flex items-center gap-2">
                <span className="font-mono text-xs text-muted-foreground">
                  {t?.display_id ?? taskId}
                </span>
                {t?.priority && (
                  <span className={`inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase ${PRIORITY_BADGE[t.priority]}`}>
                    {t.priority}
                  </span>
                )}
                {t?.status && (
                  <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium ${STATUS_BADGE[t.status] ?? STATUS_BADGE.todo}`}>
                    {t.status}
                  </span>
                )}
                {t?.is_blocked && (
                  <span className="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[10px] font-semibold bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300">
                    <Lock size={10} /> blocked
                  </span>
                )}
              </div>
              <SheetTitle className="mt-1 text-lg leading-snug">
                {t?.title ?? <span className="text-muted-foreground italic">(sem título)</span>}
              </SheetTitle>
              <SheetDescription className="mt-1 flex flex-wrap items-center gap-2 text-xs">
                {t?.module && <span className="bg-muted px-1.5 py-0.5 rounded">{t.module}</span>}
                {t?.owner && <span className="bg-muted px-1.5 py-0.5 rounded">@{t.owner}</span>}
                {t?.due_date && (
                  <span className={`inline-flex items-center gap-1 ${t.is_overdue ? 'text-red-600 font-semibold' : 'text-muted-foreground'}`}>
                    {t.is_overdue ? <AlertCircle size={11} /> : <Calendar size={11} />}
                    {dueShort(t.due_date)}
                  </span>
                )}
                {t?.estimate_h !== null && t?.estimate_h !== undefined && t.estimate_h > 0 && (
                  <span className="text-muted-foreground">{t.estimate_h}h estimadas</span>
                )}
                {t?.project_key && (
                  <span className="text-muted-foreground">
                    em <span className="font-mono">{t.project_key}</span>
                  </span>
                )}
              </SheetDescription>
            </div>
          </div>
        </SheetHeader>

        {/* Tabs switcher */}
        {!loading && data && (
          <div className="border-b -mt-2 px-4">
            <div className="flex gap-1">
              {tabs.map((tabItem) => {
                const Icon = tabItem.icon;
                const isActive = tab === tabItem.key;
                return (
                  <button
                    key={tabItem.key}
                    type="button"
                    onClick={() => setTab(tabItem.key)}
                    className={`relative px-3 py-2 text-xs font-medium transition-colors flex items-center gap-1.5 ${
                      isActive
                        ? 'text-foreground border-b-2 border-primary -mb-px'
                        : 'text-muted-foreground hover:text-foreground'
                    }`}
                  >
                    <Icon size={13} />
                    {tabItem.label}
                    {tabItem.count !== undefined && tabItem.count > 0 && (
                      <span className="ml-1 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-muted px-1 text-[10px]">
                        {tabItem.count}
                      </span>
                    )}
                  </button>
                );
              })}
            </div>
          </div>
        )}

        {/* Body */}
        <div className="flex-1 overflow-y-auto px-4 pb-4">
          {loading && (
            <div className="flex items-center justify-center py-12 text-sm text-muted-foreground">
              <Loader2 className="mr-2 h-4 w-4 animate-spin" />
              carregando…
            </div>
          )}

          {error && (
            <div className="my-6 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900/40 dark:bg-red-950/40 dark:text-red-200">
              {error}
            </div>
          )}

          {!loading && !error && data && (
            <>
              {tab === 'description' && (
                <div className="py-4">
                  {t?.description ? (
                    <p className="text-sm whitespace-pre-wrap leading-relaxed">{t.description}</p>
                  ) : (
                    <p className="text-sm text-muted-foreground italic">Sem descrição.</p>
                  )}

                  {data.dependencies.length > 0 && (
                    <div className="mt-6">
                      <h4 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground mb-2 flex items-center gap-1">
                        <GitBranch size={12} /> Dependências ({data.dependencies.length})
                      </h4>
                      <ul className="space-y-1.5">
                        {data.dependencies.map((d) => (
                          <li key={d.id} className="flex items-center gap-2 text-xs">
                            <span className="rounded bg-amber-100 dark:bg-amber-900/30 px-1.5 py-0.5 text-amber-800 dark:text-amber-200 font-medium uppercase">
                              {d.type}
                            </span>
                            {d.target ? (
                              <>
                                <span className="font-mono text-muted-foreground">{d.target.display_id}</span>
                                <span className="truncate">{d.target.title}</span>
                                <span className={`ml-auto inline-flex rounded-full px-1.5 py-0.5 text-[10px] ${STATUS_BADGE[d.target.status] ?? STATUS_BADGE.todo}`}>
                                  {d.target.status}
                                </span>
                              </>
                            ) : (
                              <span className="font-mono text-muted-foreground">{d.depends_on_task_id ?? '—'}</span>
                            )}
                          </li>
                        ))}
                      </ul>
                    </div>
                  )}
                </div>
              )}

              {tab === 'comments' && (
                <div className="py-4 space-y-4">
                  {data.comments.length === 0 ? (
                    <p className="text-sm text-muted-foreground italic">Sem comentários ainda.</p>
                  ) : (
                    <ul className="space-y-3">
                      {data.comments.map((c) => (
                        <li key={c.id} className="rounded-md border bg-card px-3 py-2">
                          <div className="flex items-baseline gap-2 mb-1">
                            <span className="text-xs font-semibold">@{c.author ?? 'system'}</span>
                            <span className="text-[10px] text-muted-foreground">{timeAgo(c.created_at)}</span>
                          </div>
                          <p className="text-sm whitespace-pre-wrap leading-relaxed">{c.body}</p>
                        </li>
                      ))}
                    </ul>
                  )}

                  {/* PMG-005 (ADR 0100) — form add comment com @mentions */}
                  <div className="border-t pt-4">
                    <MentionInput
                      value={commentDraft}
                      onChange={setCommentDraft}
                      onSubmit={handlePostComment}
                      placeholder="Comentar (use @ pra mencionar)…"
                      disabled={posting}
                      rows={3}
                    />

                    {postError && (
                      <p className="mt-2 text-xs text-red-600 dark:text-red-400">{postError}</p>
                    )}

                    <div className="mt-2 flex justify-end">
                      <Button
                        size="sm"
                        onClick={handlePostComment}
                        disabled={posting || !commentDraft.trim()}
                      >
                        {posting ? (
                          <Loader2 className="mr-1 h-3 w-3 animate-spin" />
                        ) : (
                          <Send className="mr-1 h-3 w-3" />
                        )}
                        Enviar
                      </Button>
                    </div>
                  </div>
                </div>
              )}

              {tab === 'activity' && (
                <div className="py-4">
                  {data.events.length === 0 ? (
                    <p className="text-sm text-muted-foreground italic">Sem atividade registrada.</p>
                  ) : (
                    <ul className="space-y-2">
                      {data.events.map((e) => {
                        const Icon = eventIcon(e.event_type);
                        const label = EVENT_LABEL[e.event_type] ?? e.event_type;
                        return (
                          <li key={e.id} className="flex items-start gap-2 text-xs">
                            <Icon size={13} className="mt-0.5 shrink-0 text-muted-foreground" />
                            <div className="flex-1 min-w-0">
                              <div>
                                <span className="font-semibold">@{e.author ?? 'system'}</span>{' '}
                                <span className="text-muted-foreground">{label}</span>
                                {e.from_value && e.to_value && (
                                  <>
                                    {' '}
                                    <span className="text-muted-foreground">de</span>{' '}
                                    <span className="font-mono text-muted-foreground">{e.from_value}</span>{' '}
                                    <span className="text-muted-foreground">para</span>{' '}
                                    <span className="font-mono text-emerald-700 dark:text-emerald-400">{e.to_value}</span>
                                  </>
                                )}
                              </div>
                              {e.note && (
                                <p className="text-muted-foreground mt-0.5 italic">{e.note}</p>
                              )}
                              <p className="text-[10px] text-muted-foreground mt-0.5">{timeAgo(e.occurred_at)}</p>
                            </div>
                          </li>
                        );
                      })}
                    </ul>
                  )}
                </div>
              )}

              {tab === 'subtasks' && (
                <div className="py-4">
                  {data.subtasks.length === 0 ? (
                    <p className="text-sm text-muted-foreground italic">Sem subtasks.</p>
                  ) : (
                    <ul className="space-y-1.5">
                      {data.subtasks.map((s) => (
                        <li key={s.task_id} className="flex items-center gap-2 text-xs">
                          <span className={`shrink-0 inline-block h-2 w-2 rounded-full ${
                            s.priority === 'p0' ? 'bg-red-500' :
                            s.priority === 'p1' ? 'bg-orange-500' :
                            s.priority === 'p2' ? 'bg-yellow-500' :
                            'bg-blue-500'
                          }`} />
                          <span className="font-mono text-muted-foreground">{s.display_id}</span>
                          <span className="truncate flex-1">{s.title}</span>
                          <span className={`shrink-0 inline-flex rounded-full px-1.5 py-0.5 text-[10px] ${STATUS_BADGE[s.status] ?? STATUS_BADGE.todo}`}>
                            {s.status}
                          </span>
                        </li>
                      ))}
                    </ul>
                  )}
                  <p className="mt-4 text-[11px] text-muted-foreground">
                    UI completa de subtasks (criar / completar / arrastar) entra em PMG-007.
                  </p>
                </div>
              )}
            </>
          )}
        </div>

        {/* Footer compacto com link pra board do projeto */}
        {!loading && t?.project_key && (
          <div className="border-t px-4 py-2 text-[11px] text-muted-foreground">
            Editar campos detalhados:{' '}
            <code className="font-mono">tasks-update {t.task_id}</code>{' '}
            via MCP.{' '}
            <a
              href={`/project-mgmt/board?project=${t.project_key}`}
              className="inline-flex items-center gap-0.5 text-foreground hover:underline"
            >
              ver no board <ExternalLink size={10} />
            </a>
          </div>
        )}
      </SheetContent>
    </Sheet>
  );
}
