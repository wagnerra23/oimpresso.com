// Timeline FSM auditável da Ordem de Serviço (Wave 7-C — gap #1 estado-da-arte FSM screen).
// Espelha resources/js/Pages/Sells/_components/SaleTimeline.tsx (US-SELL-035 LIVE prod).
//
// Render via ServiceOrderSheet (drawer lateral) — substitui o placeholder
// "Em breve — timeline auditável das transições FSM." (linhas 346-351 pre-Wave 7-C).
//
// Refs: PR backend ServiceOrderFsmActionController::history(), ADR 0143 (FSM Pipeline LIVE),
//       memory/sessions/2026-05-20-arte-tela-fsm-workflow.md gap #1.

import { useEffect, useState } from 'react';
import { Clock, Loader2, User2, Zap, FileCheck2, ArrowRight, PlayCircle } from 'lucide-react';

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
  service_order_id: number;
  count: number;
  items: TimelineItem[];
}

interface Props {
  serviceOrderId: number;
  enabled: boolean;
}

const STAGE_COLOR_MAP: Record<string, string> = {
  gray: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
  blue: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
  cyan: 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/40 dark:text-cyan-300',
  amber: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
  yellow: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300',
  violet: 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300',
  indigo: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300',
  emerald: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
  green: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
  red: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
  rose: 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300',
  slate: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
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

export default function ServiceOrderTimeline({ serviceOrderId, enabled }: Props) {
  const [items, setItems] = useState<TimelineItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [forbidden, setForbidden] = useState(false);

  useEffect(() => {
    if (!enabled || !serviceOrderId) return;
    let cancelled = false;
    setLoading(true);
    setError(null);
    setForbidden(false);

    fetch(`/oficina-auto/service-orders/${serviceOrderId}/history`, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    })
      .then(async (res) => {
        if (res.status === 403) {
          if (!cancelled) {
            setForbidden(true);
            setItems([]);
          }
          return null;
        }
        if (!res.ok) {
          throw new Error(`HTTP ${res.status}`);
        }
        return (await res.json()) as TimelineResponse;
      })
      .then((data) => {
        if (cancelled || !data) return;
        setItems(data.items ?? []);
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
  }, [serviceOrderId, enabled]);

  if (forbidden) {
    return (
      <p className="text-xs text-muted-foreground">
        Você não tem permissão pra ver o histórico desta OS (<code>oficinaauto.service_order.view</code>).
      </p>
    );
  }

  if (loading) {
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

  if (items.length === 0) {
    return (
      <p className="text-xs text-muted-foreground">
        Nenhuma transição registrada ainda. Inicie o pipeline FSM ou execute uma
        ação (iniciar locação, recolher caçamba, concluir) — aparecem aqui.
      </p>
    );
  }

  return (
    <ol className="relative space-y-3 border-l border-border pl-4">
      {items.map((item) => {
        const motivo = (item.payload?.motivo as string | undefined) ?? null;
        const pipelineStarted = item.payload?.pipeline_started === true;
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
                {pipelineStarted && !item.action && (
                  <span className="inline-flex items-center gap-1 text-xs text-muted-foreground">
                    <PlayCircle size={12} className="text-emerald-600 dark:text-emerald-400" />
                    Pipeline iniciado
                  </span>
                )}
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
