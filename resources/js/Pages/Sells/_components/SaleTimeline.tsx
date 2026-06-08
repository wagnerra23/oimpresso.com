// US-SELL-035 frontend — Timeline da venda.
// Modos:
//   - mode="fsm" (default — back-compat): só transições FSM (/api/sells/{id}/history)
//   - mode="unified" (P4 parking lot #11): FSM + payments + activities + comments + audit
//     num único stream cronológico reverso com avatar + tone + icon.
//
// Refs: PR #618 backend (SaleHistoryController), CASOS-USO-PIPELINE-VENDAS.md §CU-07
//       PR P4 #2026-05-26 backend timelineUnified() + r4 visual-comparison gap #11.

import { useCallback, useEffect, useState } from 'react';
import {
  ArrowRight,
  Clock,
  CreditCard,
  FileCheck2,
  FileText,
  Inbox,
  Loader2,
  MessageSquare,
  ShieldCheck,
  User2,
  Zap,
} from 'lucide-react';

// ─── Tipos FSM (modo legado) ───────────────────────────────────────────

interface TimelineUser {
  id: number | null;
  name: string | null;
}

interface TimelineAction {
  key: string;
  label: string;
  has_side_effect: boolean;
  has_event: boolean;
}

interface TimelineStage {
  key: string;
  name: string;
  color: string | null;
}

interface TimelineItem {
  id: number;
  executed_at: string | null;
  user: TimelineUser;
  action: TimelineAction | null;
  from_stage: TimelineStage | null;
  to_stage: TimelineStage | null;
  payload: Record<string, unknown> | null;
}

interface TimelineResponse {
  transaction_id: number;
  count: number;
  items: TimelineItem[];
}

// ─── Tipos Unified (modo cross-source) ─────────────────────────────────

type UnifiedEventType = 'fsm_transition' | 'payment' | 'activity' | 'comment' | 'audit';
type UnifiedTone = 'blue' | 'green' | 'amber' | 'red' | 'neutral';
type UnifiedIcon = 'ArrowRight' | 'CreditCard' | 'FileText' | 'MessageSquare' | 'ShieldCheck';

interface UnifiedUser {
  id: number;
  name: string | null;
  abbr: string | null;
}

interface UnifiedEvent {
  type: UnifiedEventType;
  occurred_at: string | null;
  user: UnifiedUser | null;
  icon: UnifiedIcon;
  tone: UnifiedTone;
  title: string;
  description: string | null;
  payload: Record<string, unknown> | null;
}

interface UnifiedResponse {
  transaction_id: number;
  count: number;
  events: UnifiedEvent[];
}

// ─── Props ─────────────────────────────────────────────────────────────

interface Props {
  saleId: number;
  enabled: boolean;
  /**
   * 'fsm' = só transições FSM (default — back-compat com Wave 3).
   * 'unified' = cross-source (FSM + payments + activities + comments + audit).
   */
  mode?: 'fsm' | 'unified';
  /**
   * Trigger pra re-fetch (incrementa quando ação externa cria evento novo).
   */
  refreshKey?: number;
}

// ─── Helpers visuais ───────────────────────────────────────────────────

const STAGE_COLOR_MAP: Record<string, string> = {
  gray: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
  blue: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
  cyan: 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/40 dark:text-cyan-300',
  amber: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
  violet: 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300',
  indigo: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300',
  emerald: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
  green: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
  red: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
  slate: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
};

const TONE_COLORBAR: Record<UnifiedTone, string> = {
  blue: 'bg-blue-500',
  green: 'bg-emerald-500',
  amber: 'bg-amber-500',
  red: 'bg-red-500',
  neutral: 'bg-slate-400',
};

const TONE_ICON_BG: Record<UnifiedTone, string> = {
  blue: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
  green: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
  amber: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
  red: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
  neutral: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
};

const ICON_MAP: Record<UnifiedIcon, typeof ArrowRight> = {
  ArrowRight,
  CreditCard,
  FileText,
  MessageSquare,
  ShieldCheck,
};

const stageBadge = (stage: TimelineStage | null) => {
  if (!stage) return null;
  const classes = STAGE_COLOR_MAP[stage.color ?? 'gray'] ?? STAGE_COLOR_MAP.gray;
  return (
    <span className={`inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ${classes}`}>
      {stage.name}
    </span>
  );
};

const formatDate = (iso: string | null) => {
  if (!iso) return '—';
  try {
    return new Intl.DateTimeFormat('pt-BR', {
      day: '2-digit', month: '2-digit', year: '2-digit',
      hour: '2-digit', minute: '2-digit',
    }).format(new Date(iso));
  } catch {
    return iso;
  }
};

// Relativo: "há 3h", "há 2d", "agora", abs no title pra hover.
const formatRelative = (iso: string | null): string => {
  if (!iso) return '—';
  try {
    const then = new Date(iso).getTime();
    const now = Date.now();
    const diff = Math.max(0, now - then);
    const sec = Math.floor(diff / 1000);
    if (sec < 60) return 'agora';
    const min = Math.floor(sec / 60);
    if (min < 60) return `há ${min}min`;
    const hr = Math.floor(min / 60);
    if (hr < 24) return `há ${hr}h`;
    const day = Math.floor(hr / 24);
    if (day < 30) return `há ${day}d`;
    return formatDate(iso);
  } catch {
    return iso;
  }
};

// Hash determinístico → 5 cores pré-aprovadas pra avatar.
const AVATAR_PALETTE = ['#6366f1', '#0ea5e9', '#10b981', '#f59e0b', '#ef4444'];
const avatarColor = (name: string | null): string => {
  if (!name) return '#94a3b8';
  let h = 0;
  for (let i = 0; i < name.length; i++) h = (h << 5) - h + name.charCodeAt(i);
  return AVATAR_PALETTE[Math.abs(h) % AVATAR_PALETTE.length] ?? '#94a3b8';
};

// ─── Componente ────────────────────────────────────────────────────────

export default function SaleTimeline({ saleId, enabled, mode = 'fsm', refreshKey = 0 }: Props) {
  const [items, setItems] = useState<TimelineItem[]>([]);
  const [events, setEvents] = useState<UnifiedEvent[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [forbidden, setForbidden] = useState(false);

  const url = mode === 'unified'
    ? `/api/sells/${saleId}/timeline-unified`
    : `/api/sells/${saleId}/history`;

  const fetchTimeline = useCallback(() => {
    if (!enabled || !saleId) return () => {};
    let cancelled = false;
    setLoading(true);
    setError(null);
    setForbidden(false);

    fetch(url, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    })
      .then(async (res) => {
        if (res.status === 403) {
          if (!cancelled) {
            setForbidden(true);
            setItems([]);
            setEvents([]);
          }
          return null;
        }
        if (!res.ok) {
          throw new Error(`HTTP ${res.status}`);
        }
        return await res.json();
      })
      .then((data) => {
        if (cancelled || !data) return;
        if (mode === 'unified') {
          const r = data as UnifiedResponse;
          setEvents(r.events ?? []);
        } else {
          const r = data as TimelineResponse;
          setItems(r.items ?? []);
        }
      })
      .catch((e) => {
        if (cancelled) return;
        setError(e instanceof Error ? e.message : 'Erro ao carregar histórico');
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [enabled, saleId, url, mode]);

  useEffect(() => {
    const cleanup = fetchTimeline();
    return cleanup;
  }, [fetchTimeline, refreshKey]);

  if (forbidden) {
    return (
      <p className="text-xs text-muted-foreground">
        Você não tem permissão pra ver o histórico desta venda (<code>sale.history.view</code>).
      </p>
    );
  }

  if (loading) {
    if (mode === 'unified') {
      return (
        <div className="sb-timeline-loading space-y-3" aria-busy="true">
          {[0, 1, 2].map((i) => (
            <div key={i} className="flex items-start gap-3 animate-pulse">
              <div className="h-8 w-8 rounded-full bg-muted" />
              <div className="flex-1 space-y-2">
                <div className="h-3 w-1/3 rounded bg-muted" />
                <div className="h-3 w-2/3 rounded bg-muted/70" />
              </div>
            </div>
          ))}
        </div>
      );
    }
    return (
      <div className="flex items-center gap-2 text-xs text-muted-foreground">
        <Loader2 size={14} className="animate-spin" />
        Carregando histórico…
      </div>
    );
  }

  if (error) {
    return (
      <p className="text-xs text-destructive">
        Falha ao carregar histórico: {error}
      </p>
    );
  }

  // ── Render unified (cross-source) ──
  if (mode === 'unified') {
    if (events.length === 0) {
      return (
        <div className="sb-timeline-empty flex flex-col items-center justify-center gap-2 py-8 text-center">
          <Inbox size={32} className="text-muted-foreground/50" />
          <p className="text-sm text-muted-foreground">
            Sem eventos ainda.
          </p>
          <p className="text-xs text-muted-foreground/70 max-w-xs">
            Quando ações forem executadas (faturar, pagar, transitar FSM, comentar),
            tudo aparece aqui em ordem cronológica reversa.
          </p>
        </div>
      );
    }

    return (
      <ol className="sb-timeline-unified relative space-y-3">
        {events.map((ev, idx) => {
          const Icon = ICON_MAP[ev.icon] ?? FileText;
          const colorbar = TONE_COLORBAR[ev.tone] ?? TONE_COLORBAR.neutral;
          const iconBg = TONE_ICON_BG[ev.tone] ?? TONE_ICON_BG.neutral;
          const userName = ev.user?.name ?? null;
          const userAbbr = ev.user?.abbr ?? '?';
          const absDate = formatDate(ev.occurred_at);

          return (
            <li
              key={`${ev.type}-${idx}-${ev.occurred_at ?? ''}`}
              className="sb-timeline-event relative flex items-stretch gap-3 rounded-md border border-border bg-card overflow-hidden"
            >
              {/* Colorbar à esquerda */}
              <span
                aria-hidden
                className={`sb-timeline-colorbar w-1 shrink-0 ${colorbar}`}
              />

              {/* Avatar circular */}
              <div className="flex items-start pt-3 pl-1">
                {userName ? (
                  <span
                    className="sb-timeline-avatar inline-flex h-8 w-8 items-center justify-center rounded-full text-[10px] font-semibold text-white"
                    style={{ backgroundColor: avatarColor(userName) }}
                    title={userName}
                  >
                    {userAbbr}
                  </span>
                ) : (
                  <span className="sb-timeline-avatar inline-flex h-8 w-8 items-center justify-center rounded-full bg-muted text-muted-foreground">
                    <User2 size={14} />
                  </span>
                )}
              </div>

              {/* Conteúdo */}
              <div className="flex-1 py-3 pr-3 space-y-1 min-w-0">
                <div className="flex items-start justify-between gap-2">
                  <div className="flex items-center gap-2 min-w-0">
                    <span className={`sb-timeline-icon inline-flex h-6 w-6 items-center justify-center rounded ${iconBg}`}>
                      <Icon size={12} />
                    </span>
                    <span className="text-sm font-medium text-foreground truncate" title={ev.title}>
                      {ev.title}
                    </span>
                  </div>
                  <span
                    className="text-[11px] text-muted-foreground whitespace-nowrap"
                    title={absDate}
                  >
                    {formatRelative(ev.occurred_at)}
                  </span>
                </div>
                {ev.description && (
                  <p className="text-xs text-muted-foreground line-clamp-2">
                    {ev.description}
                  </p>
                )}
                {userName && (
                  <p className="text-[11px] text-muted-foreground/80">
                    por <span className="font-medium text-foreground/80">{userName}</span>
                  </p>
                )}
              </div>
            </li>
          );
        })}
      </ol>
    );
  }

  // ── Render FSM (modo legado) ──
  if (items.length === 0) {
    return (
      <p className="text-xs text-muted-foreground">
        Nenhuma transição registrada ainda. Quando ações FSM forem executadas
        (aprovar orçamento, iniciar produção, faturar, etc), aparecem aqui.
      </p>
    );
  }

  return (
    <ol className="relative space-y-3 border-l border-border pl-4">
      {items.map((item) => {
        const motivo = (item.payload?.motivo as string | undefined) ?? null;
        return (
          <li key={item.id} className="relative">
            <span className="absolute -left-[21px] top-1 flex h-3 w-3 items-center justify-center rounded-full bg-background ring-2 ring-border">
              <span className="h-1.5 w-1.5 rounded-full bg-foreground" />
            </span>
            <div className="space-y-1">
              <div className="flex items-center justify-between gap-2 text-xs">
                <span className="flex items-center gap-1 text-muted-foreground">
                  <Clock size={12} /> {formatDate(item.executed_at)}
                </span>
                {item.user.name && (
                  <span className="flex items-center gap-1 text-muted-foreground">
                    <User2 size={12} /> {item.user.name}
                  </span>
                )}
              </div>
              <div className="flex flex-wrap items-center gap-1.5 text-sm">
                {stageBadge(item.from_stage)}
                {item.from_stage && item.to_stage && <ArrowRight size={12} className="text-muted-foreground" />}
                {stageBadge(item.to_stage)}
                {item.action && (
                  <span className="text-xs text-muted-foreground">
                    via <strong className="text-foreground">{item.action.label}</strong>
                  </span>
                )}
                {item.action?.has_side_effect && (
                  <span title="Side-effect disparado" className="inline-flex items-center text-amber-600 dark:text-amber-400">
                    <Zap size={12} />
                  </span>
                )}
                {item.action?.has_event && (
                  <span title="Event disparado" className="inline-flex items-center text-blue-600 dark:text-blue-400">
                    <FileCheck2 size={12} />
                  </span>
                )}
              </div>
              {motivo && (
                <p className="text-xs italic text-muted-foreground">
                  Motivo: {motivo}
                </p>
              )}
            </div>
          </li>
        );
      })}
    </ol>
  );
}

export type { UnifiedEvent, UnifiedResponse, UnifiedEventType, UnifiedTone, UnifiedIcon };
