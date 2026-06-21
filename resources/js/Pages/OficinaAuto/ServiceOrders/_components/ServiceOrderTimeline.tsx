// Timeline FSM auditável da Ordem de Serviço (Wave 7-C — gap #1 estado-da-arte FSM screen).
// Espelha resources/js/Pages/Sells/_components/SaleTimeline.tsx (US-SELL-035 LIVE prod).
//
// Render via ServiceOrderSheet (drawer lateral) — substitui o placeholder
// "Em breve — timeline auditável das transições FSM." (linhas 346-351 pre-Wave 7-C).
//
// Refs: PR backend ServiceOrderFsmActionController::history(), ADR 0143 (FSM Pipeline LIVE),
//       memory/sessions/2026-05-20-arte-tela-fsm-workflow.md gap #1.

import { useEffect, useState } from 'react';
import { Loader2, Zap, FileCheck2, PlayCircle } from 'lucide-react';

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
  /**
   * F3 OS-V2-4 — render alternativo quando o histórico FSM real está VAZIO (OS antiga
   * sem transições registradas). O drawer passa a TimelineSkeleton derivada das datas
   * (entered/prazo/completed) pra não deixar a seção careca.
   */
  fallback?: React.ReactNode;
}

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

export default function ServiceOrderTimeline({ serviceOrderId, enabled, fallback }: Props) {
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
    // F3 OS-V2-4 — OS antiga sem histórico FSM: cai pra timeline derivada (skeleton).
    if (fallback) {
      return <>{fallback}</>;
    }
    return (
      <p className="text-xs text-muted-foreground">
        Nenhuma transição registrada ainda. Inicie o pipeline FSM ou execute uma
        ação (iniciar diagnóstico, concluir serviço, entregar) — aparecem aqui.
      </p>
    );
  }

  // Canon .ofc-timeline (protótipo Cowork oficina-page/oficina-fila): fio vertical
  // + dot por evento + 3 linhas (quando · o quê · quem). As pills coloridas com
  // seta saíram (polish canon Board 2026-06-11) — transição vira texto "De → Pra".
  return (
    <ol className="relative pl-4">
      <span className="absolute left-1 top-1.5 bottom-1.5 w-px bg-border" aria-hidden="true" />
      {items.map((item) => {
        const motivo = (item.payload?.motivo as string | undefined) ?? null;
        const pipelineStarted = item.payload?.pipeline_started === true;
        return (
          <li key={item.id} className="relative pl-2 pb-3 text-[11.5px] last:pb-0">
            <span
              className="absolute -left-[10px] top-[5px] h-[7px] w-[7px] rounded-full border-[1.5px] border-success bg-success"
              aria-hidden="true"
            />
            <div className="text-[10.5px] tabular-nums text-muted-foreground">
              {formatDate(item.executed_at)}
            </div>
            <div className="flex flex-wrap items-center gap-x-1.5 text-foreground">
              {pipelineStarted && !item.action ? (
                <span className="inline-flex items-center gap-1">
                  <PlayCircle size={11} className="text-emerald-600 dark:text-emerald-400" />
                  Pipeline iniciado
                </span>
              ) : item.from_stage && item.to_stage ? (
                <span>
                  {item.from_stage.name} → <strong className="font-semibold">{item.to_stage.name}</strong>
                </span>
              ) : item.to_stage ? (
                <strong className="font-semibold">{item.to_stage.name}</strong>
              ) : null}
              {item.action?.has_side_effect && (
                <span title="Side-effect disparado" className="inline-flex items-center text-amber-600 dark:text-amber-400">
                  <Zap size={11} />
                </span>
              )}
              {item.action?.has_event && (
                <span title="Event disparado" className="inline-flex items-center text-blue-600 dark:text-blue-400">
                  <FileCheck2 size={11} />
                </span>
              )}
            </div>
            <div className="text-[10.5px] text-muted-foreground">
              {item.user.name ?? '—'}
              {item.action && <> · {item.action.label}</>}
            </div>
            {motivo && (
              <p className="text-[10.5px] italic text-muted-foreground">
                Motivo: {motivo}
              </p>
            )}
          </li>
        );
      })}
    </ol>
  );
}
