// Wave A — US-CRM-063 Tab Pagamentos (MWART F3 paridade /contacts/{id} tab payments)
// Restrições Tier 0 (ADR 0093): multi-tenant — backend ContactController::getContactPayments($contact_id)
// já filtra por business_id. Componente é puro view: recebe payments via props ou fetch on-mount.
// Source funcional: resources/views/contact/partials/contact_payments_tab.blade.php + payment_row.blade.php
// Backend endpoint existente: GET /contacts/payments/{contact_id} (linha 181 routes/web.php)
//
// Pattern reuse: Cliente/Ledger.tsx (filtros + tabela densa) + Atendimento/Channels/Show.tsx (Deferred per-tab).

import { useEffect, useState } from 'react';
import { CreditCard, ExternalLink, Banknote, Receipt, CornerDownRight } from 'lucide-react';
import { Button } from '@/Components/ui/button';

export interface PaymentRow {
  id: number;
  paid_on: string | null;
  payment_ref_no: string;
  parent_payment_ref_no: string | null; // se filha, exibe identação
  amount: number;
  is_return: 0 | 1;
  method: string; // cash, card, cheque, bank_transfer, other, custom_pay_1..7
  invoice_no: string | null;
  ref_no: string | null;
  transaction_id: number | null;
  transaction_type: 'sell' | 'purchase' | 'opening_balance' | 'expense' | null;
  cheque_number: string | null;
  card_transaction_number: string | null;
  bank_account_number: string | null; // PII — backend já mascara
  parent_id: number | null;
}

export interface PaymentsTabProps {
  contactId: number;
  /** Quando vier via Inertia::defer, props.payments é passado direto */
  payments?: PaymentRow[];
  /** Permissões pra mostrar botão "Ver" / link transação */
  canViewSell?: boolean;
}

const formatBRL = (value: number) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);

const formatDate = (iso: string | null) => {
  if (!iso) return '—';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return iso;
  return new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  }).format(d);
};

const METHOD_LABELS: Record<string, string> = {
  cash: 'Dinheiro',
  card: 'Cartão',
  cheque: 'Cheque',
  bank_transfer: 'Transferência',
  other: 'Outro',
  advance: 'Adiantamento',
  custom_pay_1: 'Pix',
  custom_pay_2: 'Boleto',
  custom_pay_3: 'Crediário',
  custom_pay_4: 'Personalizado 4',
  custom_pay_5: 'Personalizado 5',
  custom_pay_6: 'Personalizado 6',
  custom_pay_7: 'Personalizado 7',
};

const METHOD_ICONS: Record<string, typeof Banknote> = {
  cash: Banknote,
  card: CreditCard,
  cheque: Receipt,
  bank_transfer: CreditCard,
  custom_pay_1: CreditCard,
  custom_pay_2: Receipt,
};

function methodLabel(method: string): string {
  return METHOD_LABELS[method] ?? method;
}

function MethodIcon({ method }: { method: string }) {
  const Icon = METHOD_ICONS[method] ?? Banknote;
  return <Icon size={12} className="text-muted-foreground" aria-hidden />;
}

export default function PaymentsTab({ contactId, payments: paymentsProp, canViewSell = false }: PaymentsTabProps) {
  const [payments, setPayments] = useState<PaymentRow[] | null>(paymentsProp ?? null);
  const [loading, setLoading] = useState(paymentsProp === undefined);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    // Se payments veio via props (Inertia::defer), não precisa fetch.
    if (paymentsProp !== undefined) {
      setPayments(paymentsProp);
      setLoading(false);
      return;
    }

    let cancelled = false;
    setLoading(true);
    setError(null);

    fetch(`/contacts/payments/${contactId}`, {
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
      },
      credentials: 'same-origin',
    })
      .then(async (res) => {
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        // Endpoint legacy retorna blade HTML — futuro: criar getContactPaymentsJson(). Por agora extrai JSON se vier.
        const ct = res.headers.get('content-type') ?? '';
        if (!ct.includes('application/json')) {
          // Fallback: backend ainda é blade. Wave A entrega só estrutura; parent wiring fará Inertia::defer.
          throw new Error('Endpoint legacy retorna HTML — aguardando wiring Inertia::defer no parent.');
        }
        return res.json();
      })
      .then((data) => {
        if (cancelled) return;
        setPayments(data.payments ?? []);
        setLoading(false);
      })
      .catch((e: Error) => {
        if (cancelled) return;
        setError(e.message);
        setLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [contactId, paymentsProp]);

  if (loading) return <PaymentsSkeleton />;

  if (error) {
    return (
      <div className="rounded-lg border border-amber-200 bg-amber-50/50 dark:bg-amber-950/20 p-6 text-sm text-amber-800 dark:text-amber-200">
        <p className="font-medium">Aguardando wiring</p>
        <p className="text-xs mt-1 opacity-80">{error}</p>
        <p className="text-xs mt-2">Parent (Show.tsx) deve passar <code className="font-mono">payments</code> via Inertia::defer.</p>
      </div>
    );
  }

  if (!payments || payments.length === 0) {
    return (
      <div className="rounded-lg border border-border bg-background p-12 text-center">
        <Receipt className="mx-auto h-10 w-10 text-muted-foreground/40 mb-2" strokeWidth={1.5} />
        <p className="text-sm text-muted-foreground">Nenhum pagamento registrado.</p>
        <p className="text-xs text-muted-foreground/70 mt-1">Pagamentos aparecem aqui quando vendas forem quitadas.</p>
      </div>
    );
  }

  return (
    <div className="rounded-lg border border-border bg-background overflow-hidden" data-testid="payments-tab-root">
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead className="bg-muted/50">
            <tr className="border-b border-border">
              <Th className="w-28">Data</Th>
              <Th>Nº Ref</Th>
              <Th className="text-right w-32">Valor</Th>
              <Th className="w-32">Método</Th>
              <Th>Pago por</Th>
              <Th className="text-right w-24">Ação</Th>
            </tr>
          </thead>
          <tbody>
            {payments.map((p) => {
              const isChild = p.parent_id !== null;
              return (
                <tr
                  key={p.id}
                  className={'border-b border-border hover:bg-muted/40 ' + (isChild ? 'bg-muted/20' : '')}
                  data-testid={`payment-row-${p.id}`}
                >
                  <td className="px-4 py-2.5 text-xs text-muted-foreground tabular-nums">
                    {isChild && <CornerDownRight size={12} className="inline mr-1 text-muted-foreground/60" aria-hidden />}
                    {formatDate(p.paid_on)}
                  </td>
                  <td className="px-4 py-2.5">
                    <span className="font-medium text-foreground">{p.payment_ref_no || '—'}</span>
                    {p.parent_payment_ref_no && (
                      <span className="ml-1.5 text-[10px] text-muted-foreground">↳ {p.parent_payment_ref_no}</span>
                    )}
                  </td>
                  <td className="px-4 py-2.5 text-right tabular-nums">
                    <span className={p.is_return ? 'text-rose-700' : 'text-emerald-700'}>
                      {p.is_return ? '−' : ''}
                      {formatBRL(p.amount)}
                    </span>
                  </td>
                  <td className="px-4 py-2.5">
                    <span className="inline-flex items-center gap-1.5 text-xs">
                      <MethodIcon method={p.method} />
                      <span className="text-foreground">{methodLabel(p.method)}</span>
                    </span>
                  </td>
                  <td className="px-4 py-2.5 text-xs text-muted-foreground">
                    {p.transaction_type === 'sell' && p.invoice_no && (
                      <>
                        Venda <span className="font-mono text-foreground">{p.invoice_no}</span>
                      </>
                    )}
                    {p.transaction_type === 'purchase' && p.ref_no && (
                      <>
                        Compra <span className="font-mono text-foreground">{p.ref_no}</span>
                      </>
                    )}
                    {p.transaction_type === 'opening_balance' && <>Saldo abertura</>}
                    {!p.transaction_type && '—'}
                  </td>
                  <td className="px-4 py-2.5 text-right">
                    {canViewSell && p.transaction_id && (
                      <Button variant="ghost" size="sm" asChild>
                        <a href={`/sells/${p.transaction_id}`} aria-label={`Ver venda ${p.invoice_no}`}>
                          <ExternalLink size={14} />
                        </a>
                      </Button>
                    )}
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
    </div>
  );
}

function PaymentsSkeleton() {
  return (
    <div className="rounded-lg border border-border bg-background overflow-hidden" data-testid="payments-tab-skeleton">
      <div className="p-4 space-y-2">
        {[0, 1, 2, 3, 4].map((i) => (
          <div key={i} className="flex items-center gap-3 py-2">
            <div className="h-3 w-20 bg-muted/40 rounded animate-pulse" />
            <div className="h-3 w-24 bg-muted/40 rounded animate-pulse" />
            <div className="h-3 w-16 bg-muted/40 rounded animate-pulse ml-auto" />
            <div className="h-3 w-20 bg-muted/40 rounded animate-pulse" />
            <div className="h-3 w-32 bg-muted/40 rounded animate-pulse" />
          </div>
        ))}
      </div>
    </div>
  );
}

function Th({ children, className = '' }: { children: React.ReactNode; className?: string }) {
  return (
    <th
      className={
        'text-left px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground ' + className
      }
    >
      {children}
    </th>
  );
}
