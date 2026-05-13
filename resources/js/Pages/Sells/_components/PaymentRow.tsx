// US-SELL-006 — PaymentRow (componente local da Sells/Create).
//
// Linha de pagamento editável: valor + método + conta + data + nota.
// + campos extras condicionais por método (cartão, cheque, TED).
// Não vira shared ainda (R-DS-001 — extrair quando 2ª tela usar).

import { Trash2 } from 'lucide-react';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import { dropdownEntries } from './dropdownEntries';

export interface Payment {
  amount: number;
  method: string;
  paid_on: string;
  account_id: number | null;
  note: string;
  // Cartão
  card_number?: string;
  card_holder_name?: string;
  card_transaction_number?: string;
  card_type?: string;
  card_month?: string;
  card_year?: string;
  card_security?: string;
  // Cheque
  cheque_number?: string;
  // TED / transferência bancária
  bank_account_number?: string;
  // Pagamentos customizados (custom_pay_1..7)
  transaction_no?: string;
}

interface Props {
  payment: Payment;
  index: number;
  paymentTypes: Record<string, string>;
  accounts: Record<number, string>;
  defaultDatetime: string;
  onChange: (index: number, field: keyof Payment, value: string | number | null) => void;
  onRemove: (index: number) => void;
  removable: boolean;
}

const CARD_TYPES = [
  ['credit', 'Crédito'],
  ['debit', 'Débito'],
  ['other', 'Outro'],
] as const;

const CARD_MONTHS = Array.from({ length: 12 }, (_, i) => {
  const m = String(i + 1).padStart(2, '0');
  return [m, m] as [string, string];
});

const CARD_YEARS = Array.from({ length: 10 }, (_, i) => {
  const y = String(new Date().getFullYear() + i);
  return [y, y] as [string, string];
});

export default function PaymentRow({
  payment,
  index,
  paymentTypes,
  accounts,
  defaultDatetime,
  onChange,
  onRemove,
  removable,
}: Props) {
  const hasAccounts = Object.keys(accounts).length > 0;
  const isCard = payment.method === 'card';
  const isCheque = payment.method === 'cheque';
  const isBank = payment.method === 'bank_transfer';
  const isCustom = payment.method.startsWith('custom_pay_');

  return (
    <div className="rounded-md border border-border p-4 space-y-3 relative">
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
        <div className="space-y-1.5">
          <Label htmlFor={`payment-${index}-amount`}>Valor</Label>
          <Input
            id={`payment-${index}-amount`}
            type="number"
            inputMode="decimal"
            min="0"
            step="0.01"
            value={payment.amount}
            onChange={(e) => onChange(index, 'amount', Number(e.target.value))}
            className="tabular-nums"
            aria-label={`Valor do pagamento ${index + 1}`}
          />
        </div>

        <div className="space-y-1.5">
          <Label htmlFor={`payment-${index}-method`}>Método</Label>
          <Select
            value={payment.method}
            onValueChange={(v) => onChange(index, 'method', v)}
          >
            <SelectTrigger id={`payment-${index}-method`}>
              <SelectValue placeholder="Escolher" />
            </SelectTrigger>
            <SelectContent>
              {dropdownEntries(paymentTypes).map(([key, label]) => (
                <SelectItem key={key} value={key}>
                  {label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        <div className="space-y-1.5">
          <Label htmlFor={`payment-${index}-paid_on`}>Pago em</Label>
          <Input
            id={`payment-${index}-paid_on`}
            type="text"
            value={payment.paid_on || defaultDatetime}
            onChange={(e) => onChange(index, 'paid_on', e.target.value)}
            placeholder="DD/MM/AAAA HH:mm"
          />
        </div>

        {hasAccounts && (
          <div className="space-y-1.5">
            <Label htmlFor={`payment-${index}-account_id`}>Conta</Label>
            <Select
              value={payment.account_id ? String(payment.account_id) : ''}
              onValueChange={(v) => onChange(index, 'account_id', v ? Number(v) : null)}
            >
              <SelectTrigger id={`payment-${index}-account_id`}>
                <SelectValue placeholder="Sem conta" />
              </SelectTrigger>
              <SelectContent>
                {dropdownEntries(accounts).map(([id, name]) => (
                  <SelectItem key={id} value={id}>
                    {name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
        )}
      </div>

      {/* Campos extras: Cartão */}
      {isCard && (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-3 pt-1 border-t border-border/60">
          <div className="space-y-1.5">
            <Label htmlFor={`payment-${index}-card_number`} className="text-xs">Nº do cartão</Label>
            <Input
              id={`payment-${index}-card_number`}
              value={payment.card_number ?? ''}
              onChange={(e) => onChange(index, 'card_number', e.target.value)}
              placeholder="**** **** **** ****"
              className="text-sm"
            />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor={`payment-${index}-card_holder_name`} className="text-xs">Nome no cartão</Label>
            <Input
              id={`payment-${index}-card_holder_name`}
              value={payment.card_holder_name ?? ''}
              onChange={(e) => onChange(index, 'card_holder_name', e.target.value)}
              className="text-sm"
            />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor={`payment-${index}-card_transaction_number`} className="text-xs">Nº transação</Label>
            <Input
              id={`payment-${index}-card_transaction_number`}
              value={payment.card_transaction_number ?? ''}
              onChange={(e) => onChange(index, 'card_transaction_number', e.target.value)}
              className="text-sm"
            />
          </div>
          <div className="space-y-1.5">
            <Label className="text-xs">Tipo</Label>
            <Select
              value={payment.card_type ?? ''}
              onValueChange={(v) => onChange(index, 'card_type', v)}
            >
              <SelectTrigger><SelectValue placeholder="Tipo" /></SelectTrigger>
              <SelectContent>
                {CARD_TYPES.map(([v, l]) => <SelectItem key={v} value={v}>{l}</SelectItem>)}
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-1.5">
            <Label className="text-xs">Validade</Label>
            <div className="flex gap-1">
              <Select value={payment.card_month ?? ''} onValueChange={(v) => onChange(index, 'card_month', v)}>
                <SelectTrigger><SelectValue placeholder="Mês" /></SelectTrigger>
                <SelectContent>{CARD_MONTHS.map(([v]) => <SelectItem key={v} value={v}>{v}</SelectItem>)}</SelectContent>
              </Select>
              <Select value={payment.card_year ?? ''} onValueChange={(v) => onChange(index, 'card_year', v)}>
                <SelectTrigger><SelectValue placeholder="Ano" /></SelectTrigger>
                <SelectContent>{CARD_YEARS.map(([v]) => <SelectItem key={v} value={v}>{v}</SelectItem>)}</SelectContent>
              </Select>
            </div>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor={`payment-${index}-card_security`} className="text-xs">CVV</Label>
            <Input
              id={`payment-${index}-card_security`}
              value={payment.card_security ?? ''}
              onChange={(e) => onChange(index, 'card_security', e.target.value)}
              placeholder="***"
              maxLength={4}
              className="text-sm w-20"
            />
          </div>
        </div>
      )}

      {/* Campos extras: Cheque */}
      {isCheque && (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-3 pt-1 border-t border-border/60">
          <div className="space-y-1.5">
            <Label htmlFor={`payment-${index}-cheque_number`} className="text-xs">Nº do cheque</Label>
            <Input
              id={`payment-${index}-cheque_number`}
              value={payment.cheque_number ?? ''}
              onChange={(e) => onChange(index, 'cheque_number', e.target.value)}
              className="text-sm"
            />
          </div>
        </div>
      )}

      {/* Campos extras: TED / transferência */}
      {isBank && (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-3 pt-1 border-t border-border/60">
          <div className="space-y-1.5">
            <Label htmlFor={`payment-${index}-bank_account_number`} className="text-xs">Conta bancária</Label>
            <Input
              id={`payment-${index}-bank_account_number`}
              value={payment.bank_account_number ?? ''}
              onChange={(e) => onChange(index, 'bank_account_number', e.target.value)}
              className="text-sm"
            />
          </div>
        </div>
      )}

      {/* Campos extras: custom_pay */}
      {isCustom && (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-3 pt-1 border-t border-border/60">
          <div className="space-y-1.5">
            <Label htmlFor={`payment-${index}-transaction_no`} className="text-xs">Nº da transação</Label>
            <Input
              id={`payment-${index}-transaction_no`}
              value={payment.transaction_no ?? ''}
              onChange={(e) => onChange(index, 'transaction_no', e.target.value)}
              className="text-sm"
            />
          </div>
        </div>
      )}

      <div className="space-y-1.5">
        <Label htmlFor={`payment-${index}-note`} className="text-xs text-muted-foreground">
          Nota (opcional)
        </Label>
        <Input
          id={`payment-${index}-note`}
          type="text"
          value={payment.note}
          onChange={(e) => onChange(index, 'note', e.target.value)}
          placeholder="Ex: parcela 1/3"
          className="text-sm"
        />
      </div>

      {removable && (
        <button
          type="button"
          onClick={() => onRemove(index)}
          aria-label={`Remover pagamento ${index + 1}`}
          className="absolute top-2 right-2 text-muted-foreground hover:text-destructive p-1"
        >
          <Trash2 className="h-4 w-4" />
        </button>
      )}
    </div>
  );
}
