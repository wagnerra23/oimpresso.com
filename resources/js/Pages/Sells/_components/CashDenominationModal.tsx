// US-SELL-006 — P0-4 paridade pré-canary Martinho 19/maio.
//
// Modal de contagem nota a nota (cash denomination) — Dani conta dinheiro no
// fechamento de caixa físico. Equivalente ao bloco `cash_denomination_div` em
// `resources/views/sale_pos/partials/payment_row_form.blade.php` (linhas 58-121).
//
// Brief:
//   - Tabela de denominações BR (R$ 200/100/50/20/10/5/2 cédulas + R$ 1 / 0.50 / 0.25 / 0.10 / 0.05 moedas)
//   - Cada linha: [qty input] × R$ valor = R$ subtotal linha (real-time)
//   - Total geral somatório
//   - Confirmar preenche input "Valor pago" da PaymentRow pai
//   - Esc fecha · Ctrl/Cmd+S confirma
//
// Tier 0:
//   - PT-BR labels ("Conferir notas" · "Confirmar")
//   - toFixed(2) em totals (decimal accuracy)
//   - NÃO remove features Create.tsx existentes

import { useCallback, useEffect, useMemo, useState } from 'react';
import { Calculator } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog';

/**
 * Denominações canon BR — mesma ordem do legacy Blade
 * (pos_settings.cash_denominations default).
 *
 * Cédulas: 200, 100, 50, 20, 10, 5, 2
 * Moedas:  1, 0.50, 0.25, 0.10, 0.05
 *
 * Total: 12 denominações.
 */
export const CASH_DENOMINATIONS_BR: Array<{ value: number; kind: 'cedula' | 'moeda' }> = [
  { value: 200, kind: 'cedula' },
  { value: 100, kind: 'cedula' },
  { value: 50, kind: 'cedula' },
  { value: 20, kind: 'cedula' },
  { value: 10, kind: 'cedula' },
  { value: 5, kind: 'cedula' },
  { value: 2, kind: 'cedula' },
  { value: 1, kind: 'moeda' },
  { value: 0.5, kind: 'moeda' },
  { value: 0.25, kind: 'moeda' },
  { value: 0.1, kind: 'moeda' },
  { value: 0.05, kind: 'moeda' },
];

const BRL_FMT = new Intl.NumberFormat('pt-BR', {
  style: 'currency',
  currency: 'BRL',
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
});

function formatBRL(value: number): string {
  // Round-half-up via toFixed(2) e re-parse — evita "0.05 × 3 = 0.15000000001" no display
  const rounded = Number(value.toFixed(2));
  return BRL_FMT.format(rounded);
}

function formatDenom(value: number): string {
  // Exibe "R$ 200,00" / "R$ 0,05"
  return BRL_FMT.format(value);
}

interface Props {
  /** Controle externo de abertura — Create.tsx mantém o state. */
  open: boolean;
  /** Callback ao fechar (Esc, X, cancelar). */
  onClose: () => void;
  /** Callback ao confirmar — recebe total calculado em R$ (number, 2 casas). */
  onConfirm: (totalCalculated: number) => void;
  /** Valor inicial do input "Valor pago" — pré-preenche contadores se vazio? Não — só info. */
  initialAmount?: number;
}

export default function CashDenominationModal({
  open,
  onClose,
  onConfirm,
  initialAmount = 0,
}: Props) {
  // State: Record<denominationValueAsString, quantity>
  // Usa string key pra evitar precisão float (Object key "0.05" vs 0.05)
  const [counts, setCounts] = useState<Record<string, number>>({});

  // Reset quando o modal abre (fresh count toda vez)
  useEffect(() => {
    if (open) {
      setCounts({});
    }
  }, [open]);

  // Total geral via useMemo — recalcula em real-time conforme inputs mudam
  const totalGeral = useMemo(() => {
    let total = 0;
    for (const d of CASH_DENOMINATIONS_BR) {
      const qty = counts[String(d.value)] ?? 0;
      total += qty * d.value;
    }
    // Round-half-up final
    return Number(total.toFixed(2));
  }, [counts]);

  const handleQtyChange = useCallback((denomValue: number, raw: string) => {
    const qty = Math.max(0, Math.floor(Number(raw) || 0));
    setCounts((prev) => ({ ...prev, [String(denomValue)]: qty }));
  }, []);

  const handleConfirm = useCallback(() => {
    onConfirm(totalGeral);
    onClose();
  }, [onConfirm, onClose, totalGeral]);

  // Atalho Ctrl/Cmd+S confirma (Esc já fecha via Radix Dialog)
  useEffect(() => {
    if (!open) return;
    const handler = (e: KeyboardEvent) => {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
        e.preventDefault();
        handleConfirm();
      }
    };
    document.addEventListener('keydown', handler);
    return () => document.removeEventListener('keydown', handler);
  }, [open, handleConfirm]);

  return (
    <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
      <DialogContent
        className="sm:max-w-[480px]"
        aria-label="Conferir notas — contagem nota a nota"
      >
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2 text-base">
            <Calculator className="h-4 w-4" aria-hidden="true" />
            Conferir notas
          </DialogTitle>
          <DialogDescription className="text-xs">
            Conte cada denominação · o total preenche o valor pago.
            {initialAmount > 0 && (
              <span className="block mt-1">
                Valor informado atualmente:{' '}
                <strong className="tabular-nums">{formatBRL(initialAmount)}</strong>
              </span>
            )}
          </DialogDescription>
        </DialogHeader>

        <div className="max-h-[60vh] overflow-y-auto pr-1">
          <table
            className="w-full text-sm border-collapse"
            aria-label="Tabela de denominações brasileiras"
          >
            <thead>
              <tr className="border-b border-border">
                <th className="text-right py-1.5 px-2 font-medium text-muted-foreground text-xs">
                  Nota / Moeda
                </th>
                <th className="text-center py-1.5 px-1 w-6 text-muted-foreground text-xs">
                  ×
                </th>
                <th className="text-center py-1.5 px-2 font-medium text-muted-foreground text-xs">
                  Qtd
                </th>
                <th className="text-center py-1.5 px-1 w-6 text-muted-foreground text-xs">
                  =
                </th>
                <th className="text-right py-1.5 px-2 font-medium text-muted-foreground text-xs">
                  Subtotal
                </th>
              </tr>
            </thead>
            <tbody>
              {CASH_DENOMINATIONS_BR.map((d) => {
                const qty = counts[String(d.value)] ?? 0;
                const subtotal = qty * d.value;
                return (
                  <tr
                    key={d.value}
                    className="border-b border-border/40"
                    data-denomination={d.value}
                    data-kind={d.kind}
                  >
                    <td className="text-right py-1.5 px-2 tabular-nums font-medium">
                      {formatDenom(d.value)}
                    </td>
                    <td className="text-center text-muted-foreground">×</td>
                    <td className="py-1.5 px-2">
                      <Label
                        htmlFor={`denom-qty-${d.value}`}
                        className="sr-only"
                      >
                        Quantidade de {formatDenom(d.value)}
                      </Label>
                      <Input
                        id={`denom-qty-${d.value}`}
                        type="number"
                        inputMode="numeric"
                        min={0}
                        step={1}
                        value={qty === 0 ? '' : String(qty)}
                        onChange={(e) => handleQtyChange(d.value, e.target.value)}
                        placeholder="0"
                        className="text-center tabular-nums h-8 w-20 mx-auto"
                        aria-label={`Quantidade de notas/moedas de ${formatDenom(d.value)}`}
                        data-testid={`denom-input-${d.value}`}
                      />
                    </td>
                    <td className="text-center text-muted-foreground">=</td>
                    <td className="text-right py-1.5 px-2 tabular-nums">
                      {subtotal > 0 ? (
                        <span className="font-medium" data-testid={`denom-subtotal-${d.value}`}>
                          {formatBRL(subtotal)}
                        </span>
                      ) : (
                        <span className="text-muted-foreground">—</span>
                      )}
                    </td>
                  </tr>
                );
              })}
            </tbody>
            <tfoot className="bg-muted/30 border-t-2 border-border">
              <tr>
                <td
                  colSpan={4}
                  className="text-right py-2 px-2 font-semibold text-sm"
                >
                  Total
                </td>
                <td className="text-right py-2 px-2 tabular-nums font-bold text-sm text-foreground" data-testid="denom-total">
                  {formatBRL(totalGeral)}
                </td>
              </tr>
            </tfoot>
          </table>
        </div>

        <DialogFooter className="gap-2">
          <Button
            type="button"
            variant="outline"
            onClick={onClose}
            aria-label="Cancelar contagem de notas"
          >
            Cancelar
          </Button>
          <Button
            type="button"
            onClick={handleConfirm}
            aria-label="Confirmar contagem e preencher valor pago"
            data-testid="denom-confirm"
          >
            Confirmar ({formatBRL(totalGeral)})
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
