// US-SELL-006 — PaymentRow (componente local da Sells/Create).
//
// Linha de pagamento editável: valor + método + conta + data + nota.
// + campos extras condicionais por método (cartão, cheque, TED).
//
// 2026-05-17 — P1-2 dossier responsivo:
//   - Mobile-first: stack vertical em card claro <md, grid horizontal md+ (paridade atual).
//   - Touch targets 44px+ em mobile (h-11) / 36px desktop (h-9 default Input).
//   - Cartão: 7 campos colapsam em <details> "Detalhes do cartão" SEMPRE em mobile
//     (anti-avalanche) e expandido em desktop (paridade atual).
//   - A11y: aria-label nos inputs + role="alert" nos erros + htmlFor já existia.
//   - Prop nova `errors` SEPARADA da interface Payment (não-breaking) — opcional.
//
// Não vira shared ainda (R-DS-001 — extrair quando 2ª tela usar).

import { useEffect, useRef } from 'react';
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
import NumericInputPtBR from '@/Components/ui/numeric-input-ptbr';

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

/**
 * Erros opcionais por campo. Separado de Payment pra NÃO quebrar consumidores
 * existentes (Create.tsx importa `Payment` direto). Adição append-only.
 */
export type PaymentErrors = Partial<Record<keyof Payment, string>>;

interface Props {
  payment: Payment;
  index: number;
  paymentTypes: Record<string, string>;
  accounts: Record<number, string>;
  defaultDatetime: string;
  onChange: (index: number, field: keyof Payment, value: string | number | null) => void;
  onRemove: (index: number) => void;
  removable: boolean;
  /** Erros opcionais por campo (mensagens validação backend). */
  errors?: PaymentErrors;
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

// Classes utilitárias (consolidadas pra reuso Wave 2):
//
// Touch target canon (Apple HIG 44pt / Material 48dp):
//   - input/select: `h-11 md:h-9` → 44px mobile, 36px desktop
//   - botão delete: `h-11 w-11 md:h-8 md:w-8`
//
// Wrapper card:
//   - sempre `rounded-md border border-border` (paridade visual com layout legado).
//   - mobile gana padding maior (`p-4`), desktop preserva (`p-4`).
const INPUT_TOUCH_CLASS = 'h-11 md:h-9';
const SELECT_TRIGGER_TOUCH_CLASS = 'h-11 md:h-9';

/** Helper render-error inline. Não renderiza nada se `msg` falsy. */
function FieldError({ msg, id }: { msg?: string; id?: string }) {
  if (!msg) return null;
  return (
    <p id={id} role="alert" className="text-xs text-destructive mt-1">
      {msg}
    </p>
  );
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
  errors,
}: Props) {
  const hasAccounts = Object.keys(accounts).length > 0;
  const isCard = payment.method === 'card';
  const isCheque = payment.method === 'cheque';
  const isBank = payment.method === 'bank_transfer';
  const isCustom = payment.method.startsWith('custom_pay_');

  // <details> Cartão: aberto por default (SSR-safe). Em mobile (<768px) JS fecha
  // pra evitar avalanche de 7 campos verticais ao expandir método=card.
  // Pattern: SSR-default open garante visibilidade desktop sem flash; effect
  // ajusta uma única vez ao mount em mobile.
  const cardDetailsRef = useRef<HTMLDetailsElement>(null);
  useEffect(() => {
    if (!isCard) return;
    if (typeof window === 'undefined') return;
    const isMobile = window.matchMedia('(max-width: 767px)').matches;
    if (isMobile && cardDetailsRef.current) {
      cardDetailsRef.current.open = false;
    }
  }, [isCard]);

  // ids canon (estável p/ labels + erro aria-describedby)
  const id = {
    amount: `payment-${index}-amount`,
    method: `payment-${index}-method`,
    paidOn: `payment-${index}-paid_on`,
    accountId: `payment-${index}-account_id`,
    note: `payment-${index}-note`,
    cardNumber: `payment-${index}-card_number`,
    cardHolder: `payment-${index}-card_holder_name`,
    cardTx: `payment-${index}-card_transaction_number`,
    cardType: `payment-${index}-card_type`,
    cardMonth: `payment-${index}-card_month`,
    cardYear: `payment-${index}-card_year`,
    cardSecurity: `payment-${index}-card_security`,
    chequeNumber: `payment-${index}-cheque_number`,
    bankAccount: `payment-${index}-bank_account_number`,
    txNo: `payment-${index}-transaction_no`,
    // ids de erro (aria-describedby)
    err: (f: keyof Payment) => `payment-${index}-${String(f)}-error`,
  };

  return (
    <div className="rounded-md border border-border p-4 space-y-3 relative">
      {/* Header mobile: identifica qual pagamento na stack (sr-only em desktop pra
          preservar paridade visual com layout atual). */}
      <div className="md:sr-only flex items-center justify-between">
        <h3 className="text-sm font-semibold text-foreground">
          Pagamento {index + 1}
        </h3>
        {/* Delete versão mobile no header (touch-friendly à direita).
            Em desktop usamos a versão `absolute` no rodapé do componente. */}
        {removable && (
          <button
            type="button"
            onClick={() => onRemove(index)}
            aria-label={`Remover pagamento ${index + 1}`}
            className="h-11 w-11 inline-flex items-center justify-center rounded-md text-muted-foreground hover:text-destructive hover:bg-destructive/10 transition-colors"
          >
            <Trash2 className="h-5 w-5" />
          </button>
        )}
      </div>

      {/* Grade principal:
          mobile (<md) — Valor full-width destacado (text-lg) em row 1,
                         Método + Pago em row 2 (2-col),
                         Conta full-width em row 3.
          md+         — grid-cols-2 (paridade)
          lg+         — grid-cols-4 (paridade — Valor / Método / Pago / Conta) */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        {/* Valor — destaque mobile (col-span-full + text-lg).
            Bug R$ [redacted Tier 0]k Larissa (2026-05-27): convertido pra NumericInputPtBR.
            Antes: type="number" + Number(e.target.value) virava NaN quando user
            digitava "25,00" → totalPago=NaN → canSubmit=false (botão Salvar
            permanecia desabilitado). */}
        <div className="space-y-1.5 sm:col-span-2 lg:col-span-1">
          <Label htmlFor={id.amount}>Valor</Label>
          <NumericInputPtBR
            id={id.amount}
            value={payment.amount}
            onChange={(n) => onChange(index, 'amount', n)}
            precision={2}
            className={`${INPUT_TOUCH_CLASS} tabular-nums text-lg md:text-sm font-semibold md:font-normal`}
            aria-label={`Valor do pagamento ${index + 1}`}
            aria-invalid={errors?.amount ? true : undefined}
            aria-describedby={errors?.amount ? id.err('amount') : undefined}
          />
          <FieldError msg={errors?.amount} id={id.err('amount')} />
        </div>

        {/* Método */}
        <div className="space-y-1.5">
          <Label htmlFor={id.method}>Método</Label>
          <Select
            value={payment.method}
            onValueChange={(v) => onChange(index, 'method', v)}
          >
            <SelectTrigger
              id={id.method}
              className={SELECT_TRIGGER_TOUCH_CLASS}
              aria-invalid={errors?.method ? true : undefined}
              aria-describedby={errors?.method ? id.err('method') : undefined}
            >
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
          <FieldError msg={errors?.method} id={id.err('method')} />
        </div>

        {/* Pago em */}
        <div className="space-y-1.5">
          <Label htmlFor={id.paidOn}>Pago em</Label>
          <Input
            id={id.paidOn}
            type="text"
            value={payment.paid_on || defaultDatetime}
            onChange={(e) => onChange(index, 'paid_on', e.target.value)}
            placeholder="DD/MM/AAAA HH:mm"
            className={INPUT_TOUCH_CLASS}
            aria-invalid={errors?.paid_on ? true : undefined}
            aria-describedby={errors?.paid_on ? id.err('paid_on') : undefined}
          />
          <FieldError msg={errors?.paid_on} id={id.err('paid_on')} />
        </div>

        {/* Conta (condicional — só se houver contas cadastradas) */}
        {hasAccounts && (
          <div className="space-y-1.5 sm:col-span-2 lg:col-span-1">
            <Label htmlFor={id.accountId}>Conta</Label>
            <Select
              value={payment.account_id ? String(payment.account_id) : ''}
              onValueChange={(v) => onChange(index, 'account_id', v ? Number(v) : null)}
            >
              <SelectTrigger
                id={id.accountId}
                className={SELECT_TRIGGER_TOUCH_CLASS}
                aria-invalid={errors?.account_id ? true : undefined}
                aria-describedby={errors?.account_id ? id.err('account_id') : undefined}
              >
                <SelectValue placeholder="Sem conta" />
              </SelectTrigger>
              <SelectContent>
                {dropdownEntries(accounts).map(([accId, name]) => (
                  <SelectItem key={accId} value={accId}>
                    {name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <FieldError msg={errors?.account_id} id={id.err('account_id')} />
          </div>
        )}
      </div>

      {/* Campos extras: Cartão
          SSR: <details open> (visível em desktop sem flash).
          Mobile (<768): useEffect fecha pra evitar avalanche de 7 campos verticais.
          Em md+ o <summary> fica escondido (md:hidden) — o usuário desktop nunca
          interage com o toggle, mantém paridade visual atual. */}
      {isCard && (
        <details
          ref={cardDetailsRef}
          open
          className="rounded-md border border-border/60 md:border-0 md:rounded-none"
        >
          <summary className="cursor-pointer px-3 py-2.5 text-sm font-medium text-muted-foreground hover:text-foreground select-none md:hidden">
            Detalhes do cartão
          </summary>
          <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 px-3 pb-3 md:px-0 md:pb-0 md:pt-1 md:border-t md:border-border/60">
            <div className="space-y-1.5">
              <Label htmlFor={id.cardNumber} className="text-xs">Nº do cartão</Label>
              <Input
                id={id.cardNumber}
                value={payment.card_number ?? ''}
                onChange={(e) => onChange(index, 'card_number', e.target.value)}
                placeholder="**** **** **** ****"
                className={`${INPUT_TOUCH_CLASS} text-sm`}
                inputMode="numeric"
                aria-invalid={errors?.card_number ? true : undefined}
                aria-describedby={errors?.card_number ? id.err('card_number') : undefined}
              />
              <FieldError msg={errors?.card_number} id={id.err('card_number')} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor={id.cardHolder} className="text-xs">Nome no cartão</Label>
              <Input
                id={id.cardHolder}
                value={payment.card_holder_name ?? ''}
                onChange={(e) => onChange(index, 'card_holder_name', e.target.value)}
                className={`${INPUT_TOUCH_CLASS} text-sm`}
                aria-invalid={errors?.card_holder_name ? true : undefined}
                aria-describedby={errors?.card_holder_name ? id.err('card_holder_name') : undefined}
              />
              <FieldError msg={errors?.card_holder_name} id={id.err('card_holder_name')} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor={id.cardTx} className="text-xs">Nº transação</Label>
              <Input
                id={id.cardTx}
                value={payment.card_transaction_number ?? ''}
                onChange={(e) => onChange(index, 'card_transaction_number', e.target.value)}
                className={`${INPUT_TOUCH_CLASS} text-sm`}
                aria-invalid={errors?.card_transaction_number ? true : undefined}
                aria-describedby={errors?.card_transaction_number ? id.err('card_transaction_number') : undefined}
              />
              <FieldError msg={errors?.card_transaction_number} id={id.err('card_transaction_number')} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor={id.cardType} className="text-xs">Tipo</Label>
              <Select
                value={payment.card_type ?? ''}
                onValueChange={(v) => onChange(index, 'card_type', v)}
              >
                <SelectTrigger id={id.cardType} className={SELECT_TRIGGER_TOUCH_CLASS}>
                  <SelectValue placeholder="Tipo" />
                </SelectTrigger>
                <SelectContent>
                  {CARD_TYPES.map(([v, l]) => <SelectItem key={v} value={v}>{l}</SelectItem>)}
                </SelectContent>
              </Select>
              <FieldError msg={errors?.card_type} id={id.err('card_type')} />
            </div>
            <div className="space-y-1.5">
              <Label className="text-xs">Validade</Label>
              <div className="grid grid-cols-2 gap-2">
                <Select
                  value={payment.card_month ?? ''}
                  onValueChange={(v) => onChange(index, 'card_month', v)}
                >
                  <SelectTrigger
                    id={id.cardMonth}
                    className={SELECT_TRIGGER_TOUCH_CLASS}
                    aria-label="Mês de validade"
                  >
                    <SelectValue placeholder="Mês" />
                  </SelectTrigger>
                  <SelectContent>
                    {CARD_MONTHS.map(([v]) => <SelectItem key={v} value={v}>{v}</SelectItem>)}
                  </SelectContent>
                </Select>
                <Select
                  value={payment.card_year ?? ''}
                  onValueChange={(v) => onChange(index, 'card_year', v)}
                >
                  <SelectTrigger
                    id={id.cardYear}
                    className={SELECT_TRIGGER_TOUCH_CLASS}
                    aria-label="Ano de validade"
                  >
                    <SelectValue placeholder="Ano" />
                  </SelectTrigger>
                  <SelectContent>
                    {CARD_YEARS.map(([v]) => <SelectItem key={v} value={v}>{v}</SelectItem>)}
                  </SelectContent>
                </Select>
              </div>
            </div>
            <div className="space-y-1.5">
              <Label htmlFor={id.cardSecurity} className="text-xs">CVV</Label>
              <Input
                id={id.cardSecurity}
                value={payment.card_security ?? ''}
                onChange={(e) => onChange(index, 'card_security', e.target.value)}
                placeholder="***"
                maxLength={4}
                inputMode="numeric"
                className={`${INPUT_TOUCH_CLASS} text-sm w-full md:w-20`}
                aria-invalid={errors?.card_security ? true : undefined}
                aria-describedby={errors?.card_security ? id.err('card_security') : undefined}
              />
              <FieldError msg={errors?.card_security} id={id.err('card_security')} />
            </div>
          </div>
        </details>
      )}

      {/* Campos extras: Cheque */}
      {isCheque && (
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1 border-t border-border/60">
          <div className="space-y-1.5">
            <Label htmlFor={id.chequeNumber} className="text-xs">Nº do cheque</Label>
            <Input
              id={id.chequeNumber}
              value={payment.cheque_number ?? ''}
              onChange={(e) => onChange(index, 'cheque_number', e.target.value)}
              className={`${INPUT_TOUCH_CLASS} text-sm`}
              inputMode="numeric"
              aria-invalid={errors?.cheque_number ? true : undefined}
              aria-describedby={errors?.cheque_number ? id.err('cheque_number') : undefined}
            />
            <FieldError msg={errors?.cheque_number} id={id.err('cheque_number')} />
          </div>
        </div>
      )}

      {/* Campos extras: TED / transferência */}
      {isBank && (
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1 border-t border-border/60">
          <div className="space-y-1.5">
            <Label htmlFor={id.bankAccount} className="text-xs">Conta bancária</Label>
            <Input
              id={id.bankAccount}
              value={payment.bank_account_number ?? ''}
              onChange={(e) => onChange(index, 'bank_account_number', e.target.value)}
              className={`${INPUT_TOUCH_CLASS} text-sm`}
              aria-invalid={errors?.bank_account_number ? true : undefined}
              aria-describedby={errors?.bank_account_number ? id.err('bank_account_number') : undefined}
            />
            <FieldError msg={errors?.bank_account_number} id={id.err('bank_account_number')} />
          </div>
        </div>
      )}

      {/* Campos extras: custom_pay */}
      {isCustom && (
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1 border-t border-border/60">
          <div className="space-y-1.5">
            <Label htmlFor={id.txNo} className="text-xs">Nº da transação</Label>
            <Input
              id={id.txNo}
              value={payment.transaction_no ?? ''}
              onChange={(e) => onChange(index, 'transaction_no', e.target.value)}
              className={`${INPUT_TOUCH_CLASS} text-sm`}
              aria-invalid={errors?.transaction_no ? true : undefined}
              aria-describedby={errors?.transaction_no ? id.err('transaction_no') : undefined}
            />
            <FieldError msg={errors?.transaction_no} id={id.err('transaction_no')} />
          </div>
        </div>
      )}

      {/* Nota (full-width sempre) */}
      <div className="space-y-1.5">
        <Label htmlFor={id.note} className="text-xs text-muted-foreground">
          Nota (opcional)
        </Label>
        <Input
          id={id.note}
          type="text"
          value={payment.note}
          onChange={(e) => onChange(index, 'note', e.target.value)}
          placeholder="Ex: parcela 1/3"
          className={`${INPUT_TOUCH_CLASS} text-sm`}
          aria-invalid={errors?.note ? true : undefined}
          aria-describedby={errors?.note ? id.err('note') : undefined}
        />
        <FieldError msg={errors?.note} id={id.err('note')} />
      </div>

      {/* Delete versão DESKTOP (absolute top-right — preserva paridade visual com
          layout legado). Em mobile usamos a versão no header (touch-friendly). */}
      {removable && (
        <button
          type="button"
          onClick={() => onRemove(index)}
          aria-label={`Remover pagamento ${index + 1}`}
          className="hidden md:inline-flex absolute top-2 right-2 h-8 w-8 items-center justify-center rounded-md text-muted-foreground hover:text-destructive hover:bg-destructive/10 transition-colors"
        >
          <Trash2 className="h-4 w-4" />
        </button>
      )}
    </div>
  );
}
