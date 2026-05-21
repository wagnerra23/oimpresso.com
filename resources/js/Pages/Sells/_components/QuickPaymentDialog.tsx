/**
 * @deprecated Substituído por `QuickPaymentPopover.tsx` (Onda Unificação PR5/6
 * 2026-05-21). Popover anchored preserva contexto da linha (modal full tirava
 * visibilidade da tabela). Componente mantido aqui como backup compat — caso
 * algum lugar externo ainda referencie. Próxima limpeza (PR6 / Onda futura)
 * pode deletar quando confirmado zero usage via grep.
 */
// QuickPaymentDialog — Modal pra registrar pagamento rápido inline na linha da
// listagem de vendas. Reusa o endpoint POST /sells/{id}/quick-payment que já
// alimenta o "Adicionar pagamento" inline do SaleSheet (drawer), evitando
// duplicação. Larissa @ ROTA LIVRE biz=4 pediu 2026-05-21 "adicionar
// pagamentos como antigamente" (UltimatePOS Blade legacy tinha botão "Add
// Payment" na linha) — ADR 0105 sinal qualificado.
//
// Refs:
//  - SaleSheet.tsx linhas 195-276 (padrão original do form inline)
//  - ADR 0093 multi-tenant Tier 0 (saleId vem do payload do tenant logado)

import { useEffect, useState, type FormEvent } from 'react';
import { CheckCircle2, Loader2, X } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';

// Mesma fonte canônica de SaleSheet (UltimatePOS armazena chaves curtas
// custom_pay_1=PIX/custom_pay_2=Boleto/custom_pay_3=Crediário — labels PT-BR
// no SellController::inertiaList match cases linha 1287-1298).
const PAYMENT_METHODS_OPTIONS = [
  { value: 'cash', label: 'Dinheiro' },
  { value: 'custom_pay_1', label: 'PIX' },
  { value: 'card', label: 'Cartão' },
  { value: 'bank_transfer', label: 'Transferência' },
  { value: 'custom_pay_2', label: 'Boleto' },
  { value: 'cheque', label: 'Cheque' },
  { value: 'other', label: 'Outros' },
];

const todayISO = () => new Date().toISOString().slice(0, 10);

function getCsrfToken(): string {
  const meta = document.querySelector('meta[name="csrf-token"]');
  return meta?.getAttribute('content') ?? '';
}

const formatBRL = (value: number) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);

interface QuickPaymentDialogProps {
  saleId: number | null;
  invoiceNo: string;
  dueAmount: number;
  open: boolean;
  onClose: () => void;
  onSuccess: () => void;
}

export default function QuickPaymentDialog({
  saleId,
  invoiceNo,
  dueAmount,
  open,
  onClose,
  onSuccess,
}: QuickPaymentDialogProps) {
  const [submitting, setSubmitting] = useState(false);
  const [paymentError, setPaymentError] = useState<string | null>(null);
  const [draft, setDraft] = useState({
    amount: dueAmount > 0 ? dueAmount.toFixed(2) : '',
    method: 'custom_pay_1',
    paid_on: todayISO(),
    note: '',
  });

  // Reseta o draft toda vez que o modal abre — evita carregar valor de outra
  // venda quando o user fechou sem submeter e abriu em outra linha.
  useEffect(() => {
    if (open) {
      setDraft({
        amount: dueAmount > 0 ? dueAmount.toFixed(2) : '',
        method: 'custom_pay_1',
        paid_on: todayISO(),
        note: '',
      });
      setPaymentError(null);
    }
  }, [open, dueAmount]);

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    if (!saleId) return;
    setSubmitting(true);
    setPaymentError(null);
    try {
      const res = await fetch(`/sells/${saleId}/quick-payment`, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': getCsrfToken(),
        },
        credentials: 'same-origin',
        body: JSON.stringify({
          amount: Number(draft.amount.replace(',', '.')),
          method: draft.method,
          paid_on: draft.paid_on,
          note: draft.note || null,
        }),
      });
      const json = await res.json();
      if (!res.ok || !json.success) {
        const validation = json.errors
          ? Object.values(json.errors).flat().join(' · ')
          : json.msg || 'Falha ao registrar pagamento.';
        setPaymentError(validation);
        return;
      }
      onSuccess();
      onClose();
    } catch (err) {
      setPaymentError(String((err as Error)?.message || err));
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle className="text-base">Registrar pagamento</DialogTitle>
          <DialogDescription className="text-xs text-muted-foreground">
            Venda #{invoiceNo} · saldo devedor{' '}
            <span className="font-medium text-foreground tabular-nums">{formatBRL(dueAmount)}</span>
          </DialogDescription>
        </DialogHeader>
        <form onSubmit={handleSubmit} className="space-y-3">
          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1.5">
              <Label htmlFor="qpd-amount" className="text-xs">Valor</Label>
              <Input
                id="qpd-amount"
                type="text"
                inputMode="decimal"
                value={draft.amount}
                onChange={(e) => setDraft({ ...draft, amount: e.target.value })}
                placeholder="0,00"
                required
                autoFocus
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="qpd-method" className="text-xs">Forma</Label>
              <select
                id="qpd-method"
                value={draft.method}
                onChange={(e) => setDraft({ ...draft, method: e.target.value })}
                className="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm"
              >
                {PAYMENT_METHODS_OPTIONS.map((m) => (
                  <option key={m.value} value={m.value}>{m.label}</option>
                ))}
              </select>
            </div>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="qpd-date" className="text-xs">Data</Label>
            <Input
              id="qpd-date"
              type="date"
              value={draft.paid_on}
              onChange={(e) => setDraft({ ...draft, paid_on: e.target.value })}
              required
            />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="qpd-note" className="text-xs">Observação (opcional)</Label>
            <Textarea
              id="qpd-note"
              value={draft.note}
              onChange={(e) => setDraft({ ...draft, note: e.target.value })}
              rows={2}
              className="text-sm"
            />
          </div>
          {paymentError && (
            <div className="rounded-md bg-rose-50 border border-rose-200 dark:bg-rose-950/40 dark:border-rose-900/40 px-2.5 py-2 text-xs text-rose-700 dark:text-rose-300">
              {paymentError}
            </div>
          )}
          <div className="flex items-center justify-end gap-2 pt-1">
            <Button
              type="button"
              variant="ghost"
              size="sm"
              onClick={onClose}
              disabled={submitting}
            >
              <X size={14} className="mr-1" />
              Cancelar
            </Button>
            <Button type="submit" size="sm" disabled={submitting || !draft.amount}>
              {submitting ? (
                <Loader2 size={14} className="mr-1 animate-spin" />
              ) : (
                <CheckCircle2 size={14} className="mr-1" />
              )}
              Registrar
            </Button>
          </div>
        </form>
      </DialogContent>
    </Dialog>
  );
}
