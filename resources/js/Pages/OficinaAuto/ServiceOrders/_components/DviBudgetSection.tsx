// DviBudgetSection — Vistoria Digital (DVI) → orçamento (US-OFICINA-040).
// Delta do protótipo Cowork "Nova OS" (oficina-os-page.jsx · seção "Inspeção" +
// botão "+ orçamento"): item reprovado/atenção da vistoria vira linha de orçamento
// (ServiceOrderItem mão-de-obra) com 1 clique. Backend: DviInspectionController::toOrcamento.

import { useCallback, useState } from 'react';
import { Search, Plus, Check, Loader2 } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/Components/ui/button';
import type { ServiceOrderItemDto } from './ServiceOrderItemRow';

export interface DviItemDto {
  id: number;
  categoria: string;
  descricao: string;
  severity: string; // ok | atencao | critico
  recomendacao: string | null;
  valor_recomendado: number | null;
  budget_item_id: number | null;
}

interface Props {
  serviceOrderId: number;
  dviItems: DviItemDto[];
  onItemAdded: (item: ServiceOrderItemDto) => void;
}

function getCsrfToken(): string {
  const meta = document.querySelector('meta[name="csrf-token"]');
  return meta?.getAttribute('content') ?? '';
}

function formatBRL(value: number | null): string {
  return (value ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

// Tokens semânticos (success/warning/destructive) — evita cor crua (UI lint R1 / ds rule).
const SEV_DEFAULT = { dot: 'bg-success', label: 'OK' };
const SEVERITY: Record<string, { dot: string; label: string }> = {
  ok: SEV_DEFAULT,
  atencao: { dot: 'bg-warning', label: 'Atenção' },
  critico: { dot: 'bg-destructive', label: 'Reprovado' },
};

export default function DviBudgetSection({ serviceOrderId, dviItems, onItemAdded }: Props) {
  // Estado local de "adicionado ao orçamento" — inicia do budget_item_id do payload.
  const [added, setAdded] = useState<Record<number, boolean>>(() =>
    Object.fromEntries(dviItems.filter((d) => d.budget_item_id).map((d) => [d.id, true])),
  );
  const [busyId, setBusyId] = useState<number | null>(null);

  const reprovados = dviItems.filter((d) => d.severity === 'critico').length;
  const atencoes = dviItems.filter((d) => d.severity === 'atencao').length;

  const handleAdd = useCallback(
    async (dvi: DviItemDto) => {
      setBusyId(dvi.id);
      try {
        const res = await fetch(
          `/oficina-auto/ordens-servico/${serviceOrderId}/dvi/${dvi.id}/to-orcamento`,
          {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
              Accept: 'application/json',
              'X-CSRF-TOKEN': getCsrfToken(),
              'X-Requested-With': 'XMLHttpRequest',
            },
          },
        );
        const json = await res.json().catch(() => ({}));
        if (res.status === 409) {
          setAdded((p) => ({ ...p, [dvi.id]: true }));
          toast.info('Item já estava no orçamento.');
          return;
        }
        if (!res.ok) {
          toast.error(json?.message ?? `Erro HTTP ${res.status}`);
          return;
        }
        setAdded((p) => ({ ...p, [dvi.id]: true }));
        onItemAdded(json.item as ServiceOrderItemDto);
        toast.success('Item adicionado ao orçamento.');
      } catch (e) {
        toast.error(e instanceof Error ? e.message : 'Erro de rede.');
      } finally {
        setBusyId(null);
      }
    },
    [serviceOrderId, onItemAdded],
  );

  if (dviItems.length === 0) return null;

  return (
    <section className="mt-6">
      <div className="flex items-center gap-2 mb-3">
        <Search className="size-4 text-muted-foreground" />
        <h2 className="text-sm font-semibold text-foreground">Vistoria (DVI)</h2>
        <span className="text-[11px] text-muted-foreground tabular-nums">
          {reprovados} reprovados · {atencoes} atenção
        </span>
      </div>

      <ul className="divide-y divide-border/60 border rounded-md overflow-hidden bg-background">
        {dviItems.map((dvi) => {
          const sev = SEVERITY[dvi.severity] ?? SEV_DEFAULT;
          const isAdded = !!added[dvi.id];
          const canBudget = dvi.severity === 'critico' || dvi.severity === 'atencao';
          return (
            <li key={dvi.id} className="flex items-center gap-3 px-3 py-2">
              <span
                className={`size-2.5 rounded-full shrink-0 ${sev.dot}`}
                title={sev.label}
                aria-label={sev.label}
              />
              <div className="min-w-0 flex-1">
                <p className="text-sm text-foreground truncate">{dvi.descricao}</p>
                {dvi.recomendacao && (
                  <p className="text-[11px] text-muted-foreground truncate">{dvi.recomendacao}</p>
                )}
              </div>
              {dvi.valor_recomendado != null && dvi.valor_recomendado > 0 && (
                <span className="text-xs tabular-nums text-muted-foreground shrink-0">
                  {formatBRL(dvi.valor_recomendado)}
                </span>
              )}
              {canBudget &&
                (isAdded ? (
                  <span className="inline-flex items-center gap-1 text-xs text-success-foreground shrink-0">
                    <Check className="size-3.5" />
                    no orçamento
                  </span>
                ) : (
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => handleAdd(dvi)}
                    disabled={busyId === dvi.id}
                    className="shrink-0 h-7 gap-1 text-xs"
                  >
                    {busyId === dvi.id ? (
                      <Loader2 className="size-3.5 animate-spin" />
                    ) : (
                      <Plus className="size-3.5" />
                    )}
                    orçamento
                  </Button>
                ))}
            </li>
          );
        })}
      </ul>
    </section>
  );
}
