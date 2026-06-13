// Wave E — US-CRM-067 Sub-component: Modal Aplicar Desconto (ledger_discount)
// Restrições Tier 0 (ADR 0093): backend LedgerDiscountController::store filtra business_id.
// Backend endpoint: POST /ledger-discount (resource route linha 177 routes/web.php)
// Source funcional: resources/views/ledger_discount/create.blade.php

import { useState } from 'react';
import { Loader2, PiggyBank, X } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Input } from '@/Components/ui/input';

export interface AddDiscountModalProps {
  contactId: number;
  contactName: string;
  contactType: 'customer' | 'supplier' | 'both';
  onClose: () => void;
  onSuccess: () => void;
}

function getCsrf(): string {
  return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

const todayIso = () => new Date().toISOString().slice(0, 10);

const errorMessage = (e: unknown): string => {
  if (e instanceof Error) return e.message;
  if (typeof e === 'string') return e;
  return 'Erro ao aplicar desconto';
};

export default function AddDiscountModal({
  contactId,
  contactName,
  contactType,
  onClose,
  onSuccess,
}: AddDiscountModalProps) {
  const [date, setDate] = useState(todayIso());
  const [amount, setAmount] = useState<string>('');
  const [subType, setSubType] = useState<'sell_discount' | 'purchase_discount'>(
    contactType === 'supplier' ? 'purchase_discount' : 'sell_discount',
  );
  const [note, setNote] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setSubmitting(true);

    try {
      const fd = new FormData();
      fd.append('contact_id', String(contactId));
      fd.append('date', date);
      fd.append('amount', amount);
      if (contactType === 'both') fd.append('sub_type', subType);
      if (note) fd.append('note', note);

      const res = await fetch('/ledger-discount', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
        headers: {
          'X-CSRF-TOKEN': getCsrf(),
          'X-Requested-With': 'XMLHttpRequest',
          Accept: 'application/json',
        },
      });
      if (!res.ok) {
        const data = await res.json().catch(() => null);
        throw new Error(data?.msg ?? `HTTP ${res.status}`);
      }
      onSuccess();
    } catch (e) {
      setError(errorMessage(e));
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-foreground/50 p-4"
      onClick={onClose}
      data-testid="discount-modal-backdrop"
    >
      <form
        className="rounded-lg border border-border bg-background w-full max-w-md shadow-lg"
        onClick={(e) => e.stopPropagation()}
        onSubmit={handleSubmit}
        data-testid="discount-modal"
      >
        <div className="flex items-center justify-between px-5 py-3 border-b border-border">
          <h3 className="text-base font-semibold text-foreground flex items-center gap-2">
            <PiggyBank size={16} className="text-muted-foreground" aria-hidden />
            Aplicar desconto · {contactName}
          </h3>
          <button
            type="button"
            onClick={onClose}
            className="text-muted-foreground hover:text-foreground"
            aria-label="Fechar"
          >
            <X size={16} />
          </button>
        </div>

        <div className="p-5 space-y-3">
          <div>
            <label htmlFor="discount-date" className="text-xs font-medium text-muted-foreground mb-1.5 block">
              Data <span className="text-destructive">*</span>
            </label>
            <Input
              id="discount-date"
              type="date"
              value={date}
              onChange={(e) => setDate(e.target.value)}
              required
              data-testid="discount-date-input"
            />
          </div>
          <div>
            <label htmlFor="discount-amount" className="text-xs font-medium text-muted-foreground mb-1.5 block">
              Valor (R$) <span className="text-destructive">*</span>
            </label>
            <Input
              id="discount-amount"
              type="number"
              step="0.01"
              min="0"
              value={amount}
              onChange={(e) => setAmount(e.target.value)}
              placeholder="0,00"
              required
              autoFocus
              data-testid="discount-amount-input"
            />
          </div>
          {contactType === 'both' && (
            <div>
              <label htmlFor="discount-sub-type" className="text-xs font-medium text-muted-foreground mb-1.5 block">
                Aplicar em
              </label>
              <Select value={subType} onValueChange={(v) => setSubType(v as typeof subType)}>
                <SelectTrigger id="discount-sub-type" className="w-full" aria-label="Aplicar em" data-testid="discount-subtype-select">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="sell_discount">Vendas</SelectItem>
                  <SelectItem value="purchase_discount">Compras</SelectItem>
                </SelectContent>
              </Select>
            </div>
          )}
          <div>
            <label htmlFor="discount-note" className="text-xs font-medium text-muted-foreground mb-1.5 block">
              Observação
            </label>
            <textarea
              id="discount-note"
              value={note}
              onChange={(e) => setNote(e.target.value)}
              rows={3}
              className="w-full rounded-md border border-border bg-background px-3 py-2 text-sm placeholder:text-muted-foreground/60 focus:outline-none focus:ring-2 focus:ring-ring"
              placeholder="Motivo do desconto…"
              data-testid="discount-note-input"
            />
          </div>
          {error && (
            <div className="rounded-md border border-destructive/20 bg-destructive-soft p-3 text-xs text-destructive-fg" role="alert">
              {error}
            </div>
          )}
        </div>

        <div className="flex items-center justify-end gap-2 px-5 py-3 border-t border-border bg-muted/20">
          <Button type="button" variant="outline" onClick={onClose} disabled={submitting}>
            Cancelar
          </Button>
          <Button type="submit" disabled={submitting || !amount} data-testid="discount-submit-btn">
            {submitting && <Loader2 className="mr-1.5 h-4 w-4 animate-spin" aria-hidden />}
            {submitting ? 'Aplicando…' : 'Aplicar desconto'}
          </Button>
        </div>
      </form>
    </div>
  );
}
