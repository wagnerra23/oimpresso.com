// QuickPaymentPopover — versão em-place (anchored ao botão "Pagar" na row)
// do antigo QuickPaymentDialog (modal full). Onda Unificação PR5/6 — Wagner
// pediu UX moderna estilo Linear/Notion: popover compacto ao lado da linha
// preservando contexto da tabela (sem overlay full que tirava visibilidade
// do row clicado).
//
// Decisões de design:
//  - Mesma fonte de verdade do POST /sells/{id}/quick-payment (idêntico ao
//    Dialog antigo e ao SaleSheet inline) — zero mudança backend.
//  - Trigger é injetado de fora via prop `trigger: React.ReactNode` pra
//    permitir que cada call-site (Lista row-actions, Grade Avançada tbody)
//    controle o estilo do botão. Wraps com PopoverTrigger asChild.
//  - shadcn Popover (Radix primitive) já traz Esc-close + click-outside-close
//    + focus trap default — não precisa lógica manual.
//  - Lógica de submit/draft/error copiada 1:1 do QuickPaymentDialog antigo;
//    QuickPaymentDialog.tsx fica marcado @deprecated mas não é removido
//    (backup compat caso algum lugar referencie).
//
// Refs:
//  - QuickPaymentDialog.tsx (predecessor — Onda Unificação PR3/6 #1320)
//  - ADR 0178 Onda Unificação tabs Visão supersede 0136
//  - ADR 0093 multi-tenant Tier 0 (saleId vem do payload tenant logado)

import { useEffect, useState, type FormEvent, type ReactNode } from 'react';
import { CheckCircle2, Loader2, X } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/Components/ui/popover';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';

// Mesma fonte canônica de SaleSheet + QuickPaymentDialog (UltimatePOS
// armazena chaves curtas custom_pay_1=PIX/custom_pay_2=Boleto — labels PT-BR
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

interface QuickPaymentPopoverProps {
  saleId: number;
  invoiceNo: string;
  dueAmount: number;
  onSuccess: () => void;
  /**
   * Botão (ou qualquer trigger) que abre o popover. É clonado via Radix
   * PopoverTrigger asChild — então precisa aceitar ref + onClick. Em geral
   * um <button> simples já basta.
   */
  trigger: ReactNode;
}

export default function QuickPaymentPopover({
  saleId,
  invoiceNo,
  dueAmount,
  onSuccess,
  trigger,
}: QuickPaymentPopoverProps) {
  const [open, setOpen] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [paymentError, setPaymentError] = useState<string | null>(null);
  const [draft, setDraft] = useState({
    amount: dueAmount > 0 ? dueAmount.toFixed(2) : '',
    method: 'custom_pay_1',
    paid_on: todayISO(),
    note: '',
  });

  // Reseta o draft toda vez que o popover abre — evita carregar valor de
  // outra venda quando o user fechou sem submeter e abriu em outra linha.
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
      setOpen(false);
    } catch (err) {
      setPaymentError(String((err as Error)?.message || err));
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>{trigger}</PopoverTrigger>
      <PopoverContent
        className="w-80 p-3"
        align="end"
        // stopPropagation evita que click no form abra o drawer da venda
        // (row tem onClick que abre SaleSheet — ver Index.tsx vd-row).
        onClick={(e) => e.stopPropagation()}
      >
        <div className="mb-2">
          <div className="text-sm font-medium leading-none">Registrar pagamento</div>
          <div className="mt-1 text-xs text-muted-foreground">
            Venda #{invoiceNo} · saldo devedor{' '}
            <span className="font-medium text-foreground tabular-nums">{formatBRL(dueAmount)}</span>
          </div>
        </div>
        <form onSubmit={handleSubmit} className="space-y-3">
          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1.5">
              <Label htmlFor="qpp-amount" className="text-xs">Valor</Label>
              <Input
                id="qpp-amount"
                type="text"
                inputMode="decimal"
                value={draft.amount}
                onChange={(e) => setDraft({ ...draft, amount: e.target.value })}
                placeholder="0,00"
                required
                autoFocus
                className="h-8 text-sm"
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="qpp-method" className="text-xs">Forma</Label>
              <select
                id="qpp-method"
                value={draft.method}
                onChange={(e) => setDraft({ ...draft, method: e.target.value })}
                className="flex h-8 w-full rounded-md border border-input bg-background px-2 py-1 text-sm"
              >
                {PAYMENT_METHODS_OPTIONS.map((m) => (
                  <option key={m.value} value={m.value}>{m.label}</option>
                ))}
              </select>
            </div>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="qpp-date" className="text-xs">Data</Label>
            <Input
              id="qpp-date"
              type="date"
              value={draft.paid_on}
              onChange={(e) => setDraft({ ...draft, paid_on: e.target.value })}
              required
              className="h-8 text-sm"
            />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="qpp-note" className="text-xs">Observação (opcional)</Label>
            <Textarea
              id="qpp-note"
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
              onClick={() => setOpen(false)}
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
      </PopoverContent>
    </Popover>
  );
}
