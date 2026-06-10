// ServiceOrderStageGate — "Checklist de etapa" (F3 OS-V2-5).
//
// Mostra os requisitos da PRÓXIMA transição FSM da OS e bloqueia o avanço até que os
// requisitos BLOQUEANTES estejam satisfeitos — espelho do gate ENFORÇADO no servidor
// (ServiceOrderFsmActionController::execute valida a MESMA regra). Porta o conceito
// StageGate do protótipo Cowork aprovado [W] 2026-06-09 (oficina-forms.jsx).
//
// Fonte da verdade: GET /oficina-auto/service-orders/{id}/fsm/gate. O CTA "Avançar"
// dispara POST /fsm/execute {action_key} — quando o gerente/superadmin tem can_override,
// um caminho secundário permite avançar com override (registrado na trilha).
//
// Requisitos:
//   - auto   → puxados do sistema (DVI/fotos/itens/aprovação). Bloqueiam.
//   - manual → conferência humana (checkbox persistido local). Advisory, não bloqueia.
//
// CRÍTICO React 19 — useCallback nos handlers. Multi-tenant Tier 0 [ADR 0093]: backend escopa.

import { useCallback, useEffect, useState } from 'react';
import { ArrowRight, Check, Loader2, Lock } from 'lucide-react';
import { toast } from 'sonner';
import { Checkbox } from '@/Components/ui/checkbox';
import { Inline } from '@/Components/layout';

interface GateRequirement {
  key: string;
  label: string;
  type: 'auto' | 'manual' | string;
  ok: boolean;
  blocking: boolean;
}

interface ForwardAction {
  key: string;
  label: string;
  is_critical: boolean;
  target_stage: { key: string; name: string; color: string | null } | null;
}

interface GateResponse {
  in_pipeline: boolean;
  current_stage: { key: string; name: string; color: string | null; is_terminal: boolean } | null;
  forward_action: ForwardAction | null;
  requirements: GateRequirement[];
  blocking_unmet: number;
  total: number;
  done: number;
  satisfied: boolean;
  can_override: boolean;
}

interface Props {
  serviceOrderId: number;
  enabled: boolean;
  /** Chamado após avançar a etapa — drawer pai refetch (status/itens/timeline). */
  onChanged?: () => void;
  /** Bump pra forçar refetch quando o pipeline muda FORA daqui (ex.: "Iniciar pipeline
      FSM" no FsmActionPanel) — sem isso o checklist fica stale até reabrir o drawer
      (bug pego pelo E2E UC-11, run 27276374828). */
  refreshToken?: number;
}

function csrfToken(): string {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

// Prefixo canônico multi-tenant Tier 0 (R3 · AP3): oimpresso.<modulo>.*
const stageGateKey = (osId: number) => `oimpresso.oficinaauto.stageGate.${osId}`;

// Checks manuais (advisory) persistidos por OS — não afetam o gate do servidor.
function readManual(osId: number): Record<string, boolean> {
  try {
    return JSON.parse(localStorage.getItem(stageGateKey(osId)) || '{}');
  } catch {
    return {};
  }
}

export default function ServiceOrderStageGate({ serviceOrderId, enabled, onChanged, refreshToken = 0 }: Props) {
  const [data, setData] = useState<GateResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [executing, setExecuting] = useState(false);
  const [manual, setManual] = useState<Record<string, boolean>>({});

  const fetchGate = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await fetch(`/oficina-auto/service-orders/${serviceOrderId}/fsm/gate`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      });
      if (res.status === 403) {
        setError('Sem permissão pra ver o checklist de etapa.');
        setData(null);
        return;
      }
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      setData((await res.json()) as GateResponse);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Erro ao carregar checklist');
    } finally {
      setLoading(false);
    }
  }, [serviceOrderId]);

  useEffect(() => {
    if (!enabled || !serviceOrderId) return;
    setManual(readManual(serviceOrderId));
    void fetchGate();
  }, [enabled, serviceOrderId, fetchGate, refreshToken]);

  const setManualCheck = useCallback(
    (key: string, checked: boolean) => {
      setManual((prev) => {
        const next = { ...prev, [key]: checked };
        try {
          localStorage.setItem(stageGateKey(serviceOrderId), JSON.stringify(next));
        } catch {
          /* ignore quota */
        }
        return next;
      });
    },
    [serviceOrderId],
  );

  const advance = useCallback(
    async (actionKey: string, override: boolean) => {
      setExecuting(true);
      try {
        const res = await fetch(`/oficina-auto/service-orders/${serviceOrderId}/fsm/execute`, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify({ action_key: actionKey, ...(override ? { override: true } : {}) }),
        });
        const json = await res.json().catch(() => ({}));
        if (!res.ok) {
          toast.error(json?.error ?? `HTTP ${res.status}`);
          // Re-sincroniza o gate (pode ter mudado o que falta).
          void fetchGate();
          return;
        }
        toast.success(override ? 'Etapa avançada (override registrado).' : 'Etapa avançada.');
        await fetchGate();
        onChanged?.();
      } catch (e) {
        toast.error(e instanceof Error ? e.message : 'Falha ao avançar etapa.');
      } finally {
        setExecuting(false);
      }
    },
    [serviceOrderId, fetchGate, onChanged],
  );

  if (loading) {
    return (
      <Inline className="text-xs text-muted-foreground">
        <Loader2 size={14} className="animate-spin" />
        Carregando checklist…
      </Inline>
    );
  }

  if (error) {
    return <p className="text-xs text-muted-foreground italic">{error}</p>;
  }

  if (!data || !data.in_pipeline) {
    return (
      <p className="text-xs text-muted-foreground italic">
        Inicie o pipeline FSM (abaixo) pra ver o checklist da próxima etapa.
      </p>
    );
  }

  if (!data.forward_action) {
    return (
      <p className="text-xs text-muted-foreground italic">
        OS no fim do fluxo — sem checklist de avanço.
      </p>
    );
  }

  const forward = data.forward_action;
  // Sem requisitos cadastrados: avanço livre (sem checklist) — mostra só o CTA.
  const items = data.requirements.map((r) => ({
    ...r,
    displayOk: r.type === 'manual' ? !!manual[r.key] : r.ok,
  }));
  const total = items.length;
  const doneDisplay = items.filter((i) => i.displayOk).length;
  const pct = total > 0 ? Math.round((doneDisplay / total) * 100) : 100;
  const ready = data.satisfied; // só requisitos BLOQUEANTES contam pro servidor

  return (
    <div
      className={
        'rounded-lg border p-3 ' +
        (ready ? 'border-success/40 bg-success/5' : 'border-border bg-muted/30')
      }
    >
      {/* Cabeçalho: ícone + "Gate p/ X" + done/total + pct */}
      <Inline align="start" className="gap-2.5">
        <span
          className={
            'grid h-7 w-7 shrink-0 place-items-center rounded-full ' +
            (ready ? 'bg-success/15 text-success' : 'bg-destructive/10 text-destructive')
          }
          aria-hidden
        >
          {ready ? <Check size={14} /> : <Lock size={13} />}
        </span>
        <div className="min-w-0 flex-1">
          <div className="text-[12.5px] font-medium text-foreground">
            Avançar p/ “{forward?.label}”
          </div>
          {total > 0 && (
            <div className={'text-[10.5px] ' + (ready ? 'text-success' : 'text-muted-foreground')}>
              {doneDisplay}/{total} requisitos ·{' '}
              {ready ? 'tudo pronto, pode avançar' : 'OS bloqueada até completar a checklist'}
            </div>
          )}
        </div>
        {total > 0 && (
          <span
            className={
              'shrink-0 text-[13px] font-semibold tabular-nums ' +
              (ready ? 'text-success' : 'text-destructive')
            }
          >
            {pct}%
          </span>
        )}
      </Inline>

      {total > 0 && (
        <>
          {/* Barra de progresso */}
          <div className="mt-2.5 h-1 overflow-hidden rounded-full bg-border">
            <div
              className={'h-full rounded-full transition-all ' + (ready ? 'bg-success' : 'bg-destructive')}
              style={{ width: `${pct}%` }}
            />
          </div>

          {/* Lista de requisitos */}
          <ul className="mt-2.5 space-y-1">
            {items.map((it) => (
              <li
                key={it.key}
                className={
                  'flex items-center gap-2 rounded-md px-2 py-1.5 text-[11.5px] ' +
                  (it.displayOk ? 'bg-success/10 text-success' : 'bg-muted text-foreground')
                }
              >
                {it.type === 'manual' ? (
                  <Inline className="w-full">
                    <Checkbox
                      checked={it.displayOk}
                      onCheckedChange={(c) => setManualCheck(it.key, c === true)}
                      aria-label={it.label}
                      className="h-3.5 w-3.5 shrink-0"
                    />
                    <span className="flex-1">{it.label}</span>
                    <span className="text-[9.5px] italic text-muted-foreground">conferir</span>
                  </Inline>
                ) : (
                  <>
                    <span
                      className={
                        'grid h-3.5 w-3.5 shrink-0 place-items-center rounded-full border text-[9px] ' +
                        (it.displayOk
                          ? 'border-success bg-success text-white'
                          : 'border-border bg-background text-muted-foreground')
                      }
                      aria-hidden
                    >
                      {it.displayOk ? <Check size={9} /> : '○'}
                    </span>
                    <span className="flex-1">{it.label}</span>
                    <span className="text-[9.5px] italic text-muted-foreground">auto</span>
                  </>
                )}
              </li>
            ))}
          </ul>
        </>
      )}

      {/* CTA de avanço */}
      {ready ? (
        <button
          type="button"
          disabled={executing}
          onClick={() => forward && advance(forward.key, false)}
          className="mt-3 inline-flex h-9 w-full items-center justify-center gap-1.5 rounded-md bg-success text-sm font-medium text-white transition-colors hover:bg-success/90 disabled:opacity-60"
        >
          {executing ? <Loader2 size={14} className="animate-spin" /> : <ArrowRight size={14} />}
          {forward?.label}
        </button>
      ) : (
        <>
          <button
            type="button"
            disabled
            className="mt-3 inline-flex h-9 w-full cursor-not-allowed items-center justify-center gap-1.5 rounded-md border border-dashed border-border bg-muted text-sm font-medium text-muted-foreground"
            title={items
              .filter((i) => i.blocking && !i.ok)
              .map((i) => i.label)
              .join(' · ')}
          >
            <Lock size={13} />
            {data.blocking_unmet}{' '}
            {data.blocking_unmet === 1 ? 'requisito pendente' : 'requisitos pendentes'}
          </button>
          {data.can_override && (
            <button
              type="button"
              disabled={executing}
              onClick={() => forward && advance(forward.key, true)}
              className="mt-1.5 inline-flex w-full items-center justify-center gap-1 text-[11px] text-muted-foreground underline-offset-2 hover:text-foreground hover:underline disabled:opacity-60"
            >
              {executing ? <Loader2 size={12} className="animate-spin" /> : null}
              Avançar mesmo assim (responsável)
            </button>
          )}
        </>
      )}
    </div>
  );
}
