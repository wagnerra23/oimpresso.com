// VdNextActionPanel — Painel "Próxima Ação" contextual no Sells/Show.
// Refs: Cowork KB-9.75 vendas-flow.jsx:215 (canon visual), ADR 0143 FSM Pipeline,
//        memory/requisitos/Sells/Sells-r4-cowork-kb975-2026-05-26-visual-comparison.md gap #1.
//
// Glossário BR (correção semântica 2026-05-26):
//   Faturar  = emitir NF (NF-e/NFS-e)  → gera título no contas a receber
//   Receber  = baixa do título            → entrada caixa/banco
//   "Marcar paga" SÓ depois que NF existe + entrega ocorreu
//
// Reusa endpoint /api/sells/{id}/fsm-actions (mesmo do FsmActionPanel) — pega primeira
// can_execute=true como "próxima ação" prominent + mostra gate quando bloqueado.

import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';

interface FsmStageMeta {
  key: string;
  name: string;
  color: string | null;
  is_terminal?: boolean;
}

interface FsmAction {
  key: string;
  label: string;
  target_stage: FsmStageMeta | null;
  is_critical: boolean;
  requires_confirmation: boolean;
  has_side_effect: boolean;
  can_execute: boolean;
}

interface ActionsResponse {
  transaction_id: number;
  current_stage: FsmStageMeta | null;
  actions: FsmAction[];
  in_pipeline: boolean;
}

interface Props {
  saleId: number;
  /** payment_status do headline — pra detectar ciclo concluído sem stage_key terminal */
  paymentStatus?: string | null;
  /** Stage atual key — fallback se /fsm-actions não responde */
  currentStageKey?: string | null;
  /** Callback após transition bem-sucedida — Show refresh sheet-data + history */
  onTransition?: () => void;
}

// Mapping label → cor visual (alinhado com Cowork .vd-next-btn-{blue,indigo,amber,green})
const ACTION_COLOR_BY_KEYWORD: Array<{ pattern: RegExp; color: string; icon: string }> = [
  { pattern: /aprov|confirma.*pedido/i, color: 'blue', icon: '✓' },
  { pattern: /fatur/i,                  color: 'indigo', icon: '📄' },
  { pattern: /entreg/i,                 color: 'amber', icon: '📦' },
  { pattern: /receb|baixa|pagamento/i,  color: 'green', icon: '💰' },
  { pattern: /cancel|estorn/i,          color: 'red', icon: '⊘' },
];

function deriveColorAndIcon(label: string): { color: string; icon: string } {
  for (const m of ACTION_COLOR_BY_KEYWORD) {
    if (m.pattern.test(label)) return { color: m.color, icon: m.icon };
  }
  return { color: 'blue', icon: '→' };
}

// Detecta gate fiscal pelo label/descrição (ex: "Faturar" sem NF disponível).
// Backend hoje retorna can_execute=false em transições bloqueadas — usamos isso
// como sinal "ação bloqueada por requisito fiscal/operacional".
function detectGate(action: FsmAction | null, allActions: FsmAction[]): string | null {
  if (!action) return null;
  // Ação executável → sem gate
  if (action.can_execute) return null;
  // Ação não-executável → derive razão genérica
  if (/fatur/i.test(action.label)) {
    return 'Emita NF-e ou NFS-e antes de faturar.';
  }
  if (/entreg/i.test(action.label)) {
    return 'Confirme o faturamento antes de marcar entrega.';
  }
  if (/receb|pagamento/i.test(action.label)) {
    return 'Confirme a entrega antes de receber pagamento.';
  }
  return 'Ação bloqueada — verifique pré-requisitos no painel Transições.';
}

async function getCsrfToken(): Promise<string> {
  const meta = document.querySelector('meta[name="csrf-token"]');
  return meta?.getAttribute('content') ?? '';
}

export default function VdNextActionPanel({
  saleId,
  paymentStatus,
  currentStageKey,
  onTransition,
}: Props) {
  const [data, setData] = useState<ActionsResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [executing, setExecuting] = useState(false);

  const fetchActions = useCallback(async () => {
    setLoading(true);
    try {
      const res = await fetch(`/api/sells/${saleId}/fsm-actions`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      });
      if (!res.ok) {
        setData(null);
        return;
      }
      setData(await res.json());
    } catch (e) {
      setData(null);
    } finally {
      setLoading(false);
    }
  }, [saleId]);

  useEffect(() => {
    if (!saleId) return;
    fetchActions();
  }, [saleId, fetchActions]);

  const advance = async (action: FsmAction) => {
    if (action.is_critical || action.requires_confirmation) {
      toast.info('Confirme abaixo no painel "Todas as transições"');
      return;
    }
    setExecuting(true);
    try {
      const csrf = await getCsrfToken();
      const res = await fetch(`/sells/${saleId}/fsm-action`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ action_key: action.key, payload: {} }),
      });
      const json = await res.json();
      if (!res.ok) {
        toast.error(json?.error ?? `HTTP ${res.status}`);
        return;
      }
      // Glossário BR — toast diferenciado por tipo de ação
      if (/fatur/i.test(action.label)) {
        toast.success(`Venda faturada · título lançado no contas a receber`);
        window.dispatchEvent(new CustomEvent('oimpresso:venda-invoiced', { detail: { saleId } }));
      } else if (/receb|pagamento/i.test(action.label)) {
        toast.success(`Pagamento recebido · título baixado`);
        window.dispatchEvent(new CustomEvent('oimpresso:venda-paid', { detail: { saleId } }));
      } else {
        toast.success(`Transição aplicada: ${action.label}`);
      }
      await fetchActions();
      onTransition?.();
    } catch (e) {
      toast.error(e instanceof Error ? e.message : 'Erro ao avançar');
    } finally {
      setExecuting(false);
    }
  };

  // Ciclo completo: payment_status='paid' OU stage terminal sem actions
  const isCycleDone =
    paymentStatus === 'paid' ||
    (data?.current_stage?.is_terminal && data.actions.length === 0) ||
    (data?.in_pipeline && data.actions.length === 0);

  if (loading) {
    return (
      <div className="rounded-lg border border-border bg-card p-4">
        <div className="text-xs text-muted-foreground">Carregando próxima ação…</div>
      </div>
    );
  }

  // Sem pipeline FSM ou sem dados — não renderiza (FsmActionPanel mostra empty state)
  if (!data || !data.in_pipeline) {
    return null;
  }

  if (isCycleDone) {
    return (
      <div className="sells-cowork">
        <div className="vendas-aplus">
          <div className="vd-next vd-next-done">
            <div className="vd-next-h">
              <span className="vd-next-ic">✓</span>
              <div>
                <b>Ciclo concluído</b>
                <small>
                  Venda finalizada{data.current_stage ? ` · ${data.current_stage.name}` : ''}
                </small>
              </div>
            </div>
          </div>
        </div>
      </div>
    );
  }

  // Próxima ação = primeira can_execute=true; se nenhuma, primeira não-executável (mostra gate)
  const nextAction =
    data.actions.find((a) => a.can_execute) ?? data.actions[0] ?? null;

  if (!nextAction) {
    return null;
  }

  const { color, icon } = deriveColorAndIcon(nextAction.label);
  const gate = detectGate(nextAction, data.actions);
  const stage = data.current_stage;

  // Progress dots — derivado das actions disponíveis + atual
  // FSM CV/MEC canônico tem ~5 etapas. Como não temos o set completo no endpoint,
  // renderizamos um indicador simples "atual + total" via stage.name.
  const totalActions = data.actions.length;

  return (
    <div className="sells-cowork">
      <div className="vendas-aplus">
        <div className={`vd-next vd-next-${color}`}>
          <div className="vd-next-h">
            <span className="vd-next-now">
              <small>Etapa atual</small>
              <b>{stage?.name ?? currentStageKey ?? '—'}</b>
              {totalActions > 0 && (
                <span className="vd-next-progress" aria-label="Progresso pipeline">
                  {Array.from({ length: Math.max(3, totalActions + 1) }).map((_, i) => (
                    <span
                      key={i}
                      className={`vd-next-dot ${i === 0 ? 'done' : i === 1 ? 'current' : ''}`}
                    />
                  ))}
                </span>
              )}
            </span>
            <span className="vd-next-arr" aria-hidden="true">→</span>
            <span className="vd-next-cta">
              <small>Próxima ação</small>
              {gate ? (
                <b className="vd-next-gate-lbl">
                  <span className="vd-next-ic">⚠</span>
                  {nextAction.label} bloqueado
                </b>
              ) : (
                <button
                  type="button"
                  className={`vd-next-btn ${color}`}
                  onClick={() => advance(nextAction)}
                  disabled={executing}
                >
                  <span className="vd-next-ic">{icon}</span>
                  {nextAction.label}
                </button>
              )}
            </span>
          </div>
          {gate ? (
            <div className="vd-next-gate">
              <span className="vd-next-gate-msg">{gate}</span>
            </div>
          ) : (
            nextAction.target_stage && (
              <p className="vd-next-desc">
                Move pra: <strong>{nextAction.target_stage.name}</strong>
                {nextAction.has_side_effect && ' · dispara efeitos colaterais (estoque/NFe/notificação)'}
              </p>
            )
          )}
        </div>
      </div>
    </div>
  );
}
