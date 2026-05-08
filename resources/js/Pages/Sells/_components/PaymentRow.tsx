// US-SELL-006 — PaymentRow (componente local da Sells/Create).
//
// Linha de pagamento editável: valor + método + conta + data + nota.
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
