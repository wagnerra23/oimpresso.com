// Mini-grafo horizontal de stages FSM (Wave 7-E — gap #2 estado-da-arte FSM screen).
// Render "você está aqui" pictórico no drawer ServiceOrderSheet — bullets conectados,
// stage atual destacado, passados com check, futuros opacos. Lateral stages (manutencao
// no fluxo locacao) renderizam abaixo como variantes.
//
// Refs: memory/sessions/2026-05-20-arte-tela-fsm-workflow.md gap #2,
//       app/Http/Controllers/ServiceOrderFsmActionController::actions() (payload stages_pipeline)

import { useEffect, useState } from 'react';
import { Check, Circle, Loader2 } from 'lucide-react';
import { cn } from '@/Lib/utils';

interface StageNode {
  key: string;
  name: string;
  color: string | null;
  sort_order: number;
  is_initial: boolean;
  is_terminal: boolean;
  is_current: boolean;
}

interface ActionsResponse {
  service_order_id: number;
  process_key: string | null;
  current_stage: { key: string | null; name: string | null; color: string | null; is_terminal: boolean } | null;
  stages_pipeline?: StageNode[];
  actions: unknown[];
  in_pipeline: boolean;
}

interface Props {
  serviceOrderId: number;
  enabled: boolean;
  /** Bump pra forçar re-fetch (incrementado pelo Sheet pós-FSM transition). */
  refreshKey?: number;
}

// Paleta Tailwind por stage.color (mesma do STAGE_CHIP_COLOR_MAP do Index).
const STAGE_DOT_COLOR_MAP: Record<string, { current: string; past: string; future: string }> = {
  gray:    { current: 'bg-gray-500 ring-gray-200',       past: 'bg-gray-400',    future: 'bg-gray-200' },
  blue:    { current: 'bg-blue-500 ring-blue-200',       past: 'bg-blue-400',    future: 'bg-blue-200' },
  cyan:    { current: 'bg-cyan-500 ring-cyan-200',       past: 'bg-cyan-400',    future: 'bg-cyan-200' },
  amber:   { current: 'bg-amber-500 ring-amber-200',     past: 'bg-amber-400',   future: 'bg-amber-200' },
  yellow:  { current: 'bg-yellow-500 ring-yellow-200',   past: 'bg-yellow-400',  future: 'bg-yellow-200' },
  violet:  { current: 'bg-violet-500 ring-violet-200',   past: 'bg-violet-400',  future: 'bg-violet-200' },
  indigo:  { current: 'bg-indigo-500 ring-indigo-200',   past: 'bg-indigo-400',  future: 'bg-indigo-200' },
  emerald: { current: 'bg-emerald-500 ring-emerald-200', past: 'bg-emerald-400', future: 'bg-emerald-200' },
  green:   { current: 'bg-green-500 ring-green-200',     past: 'bg-green-400',   future: 'bg-green-200' },
  red:     { current: 'bg-red-500 ring-red-200',         past: 'bg-red-400',     future: 'bg-red-200' },
  rose:    { current: 'bg-rose-500 ring-rose-200',       past: 'bg-rose-400',    future: 'bg-rose-200' },
  slate:   { current: 'bg-slate-500 ring-slate-200',     past: 'bg-slate-400',   future: 'bg-slate-200' },
};

const FALLBACK_COLOR = { current: 'bg-gray-500 ring-gray-200', past: 'bg-gray-400', future: 'bg-gray-200' };

export default function ServiceOrderStagePipeline({ serviceOrderId, enabled, refreshKey }: Props) {
  const [data, setData] = useState<ActionsResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!enabled || !serviceOrderId) return;
    let cancelled = false;
    setLoading(true);
    setError(null);

    fetch(`/oficina-auto/service-orders/${serviceOrderId}/fsm/actions`, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    })
      .then(async (res) => {
        if (!res.ok) {
          // 403 sem permission OU OS sem stage — silencia (FsmActionPanel já trata)
          if (!cancelled) setData(null);
          return null;
        }
        return (await res.json()) as ActionsResponse;
      })
      .then((json) => {
        if (cancelled || !json) return;
        setData(json);
      })
      .catch((e) => {
        if (cancelled) return;
        setError(e instanceof Error ? e.message : 'Erro ao carregar pipeline');
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [serviceOrderId, enabled, refreshKey]);

  if (loading) {
    return (
      <div className="flex items-center gap-2 text-xs text-muted-foreground">
        <Loader2 size={12} className="animate-spin" />
        Carregando pipeline…
      </div>
    );
  }

  if (error) {
    return <p className="text-xs text-destructive">{error}</p>;
  }

  if (!data || !data.in_pipeline || !data.stages_pipeline || data.stages_pipeline.length === 0) {
    // Quiet — empty state já tratado em FsmActionPanel ("Iniciar pipeline FSM")
    return null;
  }

  const stages = data.stages_pipeline;
  const currentIdx = stages.findIndex((s) => s.is_current);

  // Separa pipeline principal (não-lateral) de stages laterais
  // Lateral = stage não-inicial, não-terminal, com sort_order > último terminal canonical
  // Heurística: pega os 3 primeiros (initial+canonical) e separa o resto se houver mais de 3.
  // Pra cacamba_locacao: disponivel(0) → locada(1) → recolhida(2 terminal); manutencao(3) lateral.
  // Pra cacamba_manutencao: aberta(0) → em_servico(1) → concluida(2 terminal); cancelada(3) lateral.
  const mainPath: StageNode[] = [];
  const lateral: StageNode[] = [];
  let foundTerminal = false;
  for (const s of stages) {
    if (foundTerminal && !s.is_terminal) {
      lateral.push(s); // stage não-terminal após terminal = lateral (ex manutencao)
    } else if (foundTerminal && s.is_terminal) {
      lateral.push(s); // 2º terminal (ex cancelada) também lateral
    } else {
      mainPath.push(s);
      if (s.is_terminal) foundTerminal = true;
    }
  }

  return (
    <div className="space-y-3">
      {/* Pipeline principal — horizontal bullets+linha */}
      <div className="relative flex items-center justify-between">
        {/* Linha de fundo cobrindo todo o range */}
        <div className="absolute top-3 left-3 right-3 h-0.5 bg-border" aria-hidden="true" />
        {/* Linha de progresso até o stage atual */}
        {currentIdx > 0 && (
          <div
            className="absolute top-3 left-3 h-0.5 bg-foreground/40 transition-all"
            style={{
              width: `calc(${(Math.min(currentIdx, mainPath.length - 1) / Math.max(mainPath.length - 1, 1)) * 100}% - ${currentIdx === mainPath.length - 1 ? '0.75rem' : '0px'})`,
            }}
            aria-hidden="true"
          />
        )}
        {mainPath.map((stage, idx) => {
          const idxInMain = idx;
          const currentIdxInMain = mainPath.findIndex((s) => s.is_current);
          const isPast = currentIdxInMain >= 0 && idxInMain < currentIdxInMain;
          const isCurrent = stage.is_current;
          const colors = STAGE_DOT_COLOR_MAP[stage.color ?? 'gray'] ?? FALLBACK_COLOR;

          return (
            <div key={stage.key} className="relative z-10 flex flex-col items-center gap-1.5">
              <div
                className={cn(
                  'flex h-6 w-6 items-center justify-center rounded-full ring-4 transition-colors',
                  isCurrent
                    ? colors.current
                    : isPast
                      ? colors.past
                      : colors.future,
                  isCurrent && 'ring-offset-1',
                )}
                title={stage.is_terminal ? `${stage.name} (terminal)` : stage.name}
              >
                {isPast || (isCurrent && stage.is_terminal) ? (
                  <Check size={12} className="text-white" />
                ) : isCurrent ? (
                  <Circle size={8} className="fill-white text-white" />
                ) : null}
              </div>
              <span
                className={cn(
                  'max-w-[80px] text-center text-[10px] leading-tight',
                  isCurrent ? 'font-semibold text-foreground' : 'text-muted-foreground',
                )}
              >
                {stage.name}
              </span>
            </div>
          );
        })}
      </div>

      {/* Stages laterais (manutencao / cancelada) — renderizam abaixo como variantes */}
      {lateral.length > 0 && (
        <div className="flex flex-wrap items-center gap-2 pl-1 pt-1 border-t border-border/50">
          <span className="text-[10px] uppercase tracking-wider text-muted-foreground">
            Variantes:
          </span>
          {lateral.map((stage) => {
            const isCurrent = stage.is_current;
            const colors = STAGE_DOT_COLOR_MAP[stage.color ?? 'gray'] ?? FALLBACK_COLOR;
            return (
              <span
                key={stage.key}
                className={cn(
                  'inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px]',
                  isCurrent
                    ? 'border-foreground bg-foreground text-background font-semibold'
                    : 'border-border text-muted-foreground opacity-70',
                )}
                title={stage.is_terminal ? `${stage.name} (terminal)` : stage.name}
              >
                <span
                  className={cn(
                    'inline-block h-1.5 w-1.5 rounded-full',
                    isCurrent ? 'bg-background' : colors.future.replace('bg-', 'bg-'),
                  )}
                />
                {stage.name}
              </span>
            );
          })}
        </div>
      )}
    </div>
  );
}
