// Wire-up UI FSM ServiceOrder — botões dinâmicos no drawer ServiceOrderSheet
// pra executar transições FSM (Iniciar locação, Recolher caçamba, Concluir, etc).
//
// Refs: Wave 7-A backend (ServiceOrderFsmActionController),
//       Pages/Sells/_components/FsmActionPanel.tsx (pattern canon pós-PR #717),
//       ADR 0129 §Service + ADR 0143 (FSM Pipeline LIVE prod biz=1).
//
// CRÍTICO React 19 — useMemo/useCallback nos handlers descendentes pra evitar
// re-render loop no modal de confirmação (lição PR #717 SaleSheet/FsmActionPanel).

import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  AlertTriangle,
  Check,
  CircleSlash,
  Loader2,
  Play,
  Zap,
  X,
} from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/Components/ui/button';
import { Textarea } from '@/Components/ui/textarea';
import { Label } from '@/Components/ui/label';

interface FsmStage {
  key: string;
  name: string;
  color: string | null;
  is_terminal?: boolean;
}

interface FsmAction {
  key: string;
  label: string;
  target_stage: FsmStage | null;
  is_critical: boolean;
  requires_confirmation: boolean;
  has_side_effect: boolean;
  can_execute: boolean;
}

interface ActionsResponse {
  service_order_id: number;
  current_stage: FsmStage | null;
  actions: FsmAction[];
  in_pipeline: boolean;
}

interface Props {
  serviceOrderId: number;
  enabled: boolean;
  /** Callback chamado após transição bem-sucedida — drawer pai refresh sheet-data + history */
  onTransition?: () => void;
}

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
  rose: 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300',
  slate: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
};

const stageBadge = (stage: FsmStage | null) => {
  if (!stage) return null;
  const classes = STAGE_COLOR_MAP[stage.color ?? 'gray'] ?? STAGE_COLOR_MAP.gray;
  return (
    <span className={`inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ${classes}`}>
      {stage.name}
    </span>
  );
};

function getCsrfToken(): string {
  const meta = document.querySelector('meta[name="csrf-token"]');
  return meta?.getAttribute('content') ?? '';
}

/**
 * Empty state quando OS ainda não tem current_stage_id (legada / pré-FSM).
 * Mostra botão "Iniciar pipeline FSM" que chama o endpoint backend Wave 7-A.
 */
function StartPipelineEmptyState({
  serviceOrderId,
  onStarted,
}: {
  serviceOrderId: number;
  onStarted: () => void;
}) {
  const [starting, setStarting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const startPipeline = useCallback(async () => {
    setStarting(true);
    setError(null);
    try {
      const csrf = getCsrfToken();
      const res = await fetch(`/oficina-auto/service-orders/${serviceOrderId}/fsm/start-pipeline`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({}),
      });
      const json = await res.json();
      if (!res.ok) {
        setError(json?.error ?? `HTTP ${res.status}`);
        return;
      }
      onStarted();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Erro ao iniciar pipeline');
    } finally {
      setStarting(false);
    }
  }, [serviceOrderId, onStarted]);

  return (
    <div className="space-y-2">
      <p className="text-xs text-muted-foreground">
        Esta OS ainda não está em pipeline FSM (Recebido → Em locação → Recolhido → Concluído).
        Inicie pra rastrear o ciclo completo via timeline auditável.
      </p>
      <Button
        size="sm"
        variant="outline"
        onClick={startPipeline}
        disabled={starting}
        className="text-xs"
      >
        {starting ? (
          <>
            <Loader2 size={12} className="mr-1 animate-spin" />
            Iniciando…
          </>
        ) : (
          <>
            <Play size={12} className="mr-1" />
            Iniciar pipeline FSM
          </>
        )}
      </Button>
      {error && <p className="text-xs text-destructive">{error}</p>}
    </div>
  );
}

export default function ServiceOrderFsmActionPanel({
  serviceOrderId,
  enabled,
  onTransition,
}: Props) {
  const [data, setData] = useState<ActionsResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [confirmAction, setConfirmAction] = useState<FsmAction | null>(null);
  const [motivo, setMotivo] = useState('');
  const [executing, setExecuting] = useState(false);

  const fetchActions = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await fetch(`/oficina-auto/service-orders/${serviceOrderId}/fsm/actions`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      });
      if (res.status === 401 || res.status === 403) {
        setError('Sem permissão pra ver transições FSM');
        setData(null);
        return;
      }
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      setData(await res.json());
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Erro ao carregar transições');
    } finally {
      setLoading(false);
    }
  }, [serviceOrderId]);

  useEffect(() => {
    if (!enabled || !serviceOrderId) return;
    fetchActions();
  }, [enabled, serviceOrderId, fetchActions]);

  const closeModal = useCallback(() => {
    setConfirmAction(null);
    setMotivo('');
  }, []);

  const doExecute = useCallback(
    async (actionKey: string, payload: Record<string, unknown>) => {
      setExecuting(true);
      try {
        const csrf = getCsrfToken();
        const res = await fetch(`/oficina-auto/service-orders/${serviceOrderId}/fsm/execute`, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrf,
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify({ action_key: actionKey, payload }),
        });
        const json = await res.json();
        if (!res.ok) {
          // Erro 4xx/5xx do backend — exibe motivo via toast (substitui alert() legacy)
          toast.error(json?.error ?? `HTTP ${res.status}`);
          return;
        }
        closeModal();
        await fetchActions();
        onTransition?.();
        // Feedback de sucesso — usa label da action recém-executada
        const executedLabel =
          data?.actions.find((a) => a.key === actionKey)?.label ?? actionKey;
        toast.success(`Transição aplicada: ${executedLabel}`);
      } catch (e) {
        // Erro de rede / parse — substitui alert() legacy
        toast.error(e instanceof Error ? e.message : 'Erro ao executar transição');
      } finally {
        setExecuting(false);
      }
    },
    [serviceOrderId, data, fetchActions, onTransition, closeModal],
  );

  const executeAction = useCallback(
    async (action: FsmAction) => {
      if (action.requires_confirmation || action.is_critical) {
        setConfirmAction(action);
        return;
      }
      await doExecute(action.key, {});
    },
    [doExecute],
  );

  const confirmExecute = useCallback(async () => {
    if (!confirmAction) return;
    const payload: Record<string, unknown> = {};
    if (motivo.trim() !== '') {
      payload.motivo = motivo.trim();
    }
    await doExecute(confirmAction.key, payload);
  }, [confirmAction, motivo, doExecute]);

  // Memoizar listas derivadas pra estabilizar identidade entre renders
  // (evita re-render loop nos botões filhos — lição PR #717).
  const actionsExecutable = useMemo(
    () => (data?.actions ?? []).filter((a) => a.can_execute),
    [data],
  );
  const hiddenCount = useMemo(
    () => (data?.actions ?? []).filter((a) => !a.can_execute).length,
    [data],
  );

  if (loading) {
    return (
      <div className="flex items-center gap-2 text-xs text-muted-foreground">
        <Loader2 size={14} className="animate-spin" />
        Carregando ações…
      </div>
    );
  }

  if (error) {
    return <p className="text-xs text-muted-foreground italic">{error}</p>;
  }

  if (!data || !data.in_pipeline) {
    return <StartPipelineEmptyState serviceOrderId={serviceOrderId} onStarted={fetchActions} />;
  }

  const stage = data.current_stage;

  return (
    <div className="space-y-3">
      {stage && (
        <div className="flex items-center gap-2 text-sm">
          <span className="text-muted-foreground">Estágio atual:</span>
          {stageBadge(stage)}
          {stage.is_terminal && (
            <span className="text-xs italic text-muted-foreground">(terminal)</span>
          )}
        </div>
      )}

      {actionsExecutable.length === 0 ? (
        <p className="text-xs text-muted-foreground italic">
          Nenhuma transição disponível neste estágio.
        </p>
      ) : (
        <div className="flex flex-wrap gap-2">
          {actionsExecutable.map((action) => (
            <Button
              key={action.key}
              size="sm"
              variant={action.is_critical ? 'destructive' : 'default'}
              onClick={() => executeAction(action)}
              disabled={executing}
              title={
                action.target_stage
                  ? `Move pra: ${action.target_stage.name}`
                  : 'Ação que não transita stage'
              }
              className="text-xs"
            >
              {action.is_critical ? (
                <AlertTriangle size={12} className="mr-1" />
              ) : (
                <Play size={12} className="mr-1" />
              )}
              {action.label}
              {action.has_side_effect && <Zap size={12} className="ml-1 opacity-70" />}
            </Button>
          ))}
        </div>
      )}

      {hiddenCount > 0 && (
        <p className="text-xs text-muted-foreground italic flex items-center gap-1">
          <CircleSlash size={12} />
          {hiddenCount} ação(ões) oculta(s) por falta de permissão
        </p>
      )}

      {/* Modal confirmação */}
      {confirmAction && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="bg-background border rounded-lg shadow-lg max-w-md w-full p-4 space-y-3">
            <div className="flex items-center justify-between">
              <h3 className="font-semibold text-sm">Confirmar: {confirmAction.label}</h3>
              <Button variant="ghost" size="sm" onClick={closeModal} disabled={executing}>
                <X size={16} />
              </Button>
            </div>

            <div className="text-xs text-muted-foreground space-y-1">
              {confirmAction.is_critical && (
                <p className="text-amber-600 dark:text-amber-400 flex items-center gap-1">
                  <AlertTriangle size={12} />
                  Ação crítica — requer autorização explícita
                </p>
              )}
              {confirmAction.has_side_effect && (
                <p className="flex items-center gap-1">
                  <Zap size={12} />
                  Esta ação dispara efeitos colaterais (vehicle status, agendamento, notificação)
                </p>
              )}
              {confirmAction.target_stage && (
                <p>
                  Move estágio pra: <strong>{confirmAction.target_stage.name}</strong>
                </p>
              )}
            </div>

            <div className="space-y-1">
              <Label htmlFor="motivo-os" className="text-xs">
                Motivo {confirmAction.is_critical ? '(recomendado)' : '(opcional)'}
              </Label>
              <Textarea
                id="motivo-os"
                value={motivo}
                onChange={(e) => setMotivo(e.target.value)}
                placeholder="Ex: Cliente solicitou recolher 1 dia antes do prazo"
                rows={2}
                disabled={executing}
                className="text-xs"
              />
            </div>

            <div className="flex items-center justify-end gap-2 pt-1">
              <Button variant="outline" size="sm" onClick={closeModal} disabled={executing}>
                Cancelar
              </Button>
              <Button
                variant={confirmAction.is_critical ? 'destructive' : 'default'}
                size="sm"
                onClick={confirmExecute}
                disabled={executing}
              >
                {executing ? (
                  <>
                    <Loader2 size={12} className="mr-1 animate-spin" />
                    Executando…
                  </>
                ) : (
                  <>
                    <Check size={12} className="mr-1" />
                    Confirmar
                  </>
                )}
              </Button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
