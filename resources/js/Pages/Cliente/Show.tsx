// W1-B3 Cliente/Show — detalhe do cliente Inertia/React (MWART F3).
// Pattern reuse ADR 0149 — deriva blueprint Cowork clientes.
// Backend: ContactController::show($id) — Inertia::render dual via config('mwart.cliente_show.enabled')

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred } from '@inertiajs/react';
import { type ReactNode } from 'react';
import {
  AlertTriangle,
  ChevronLeft,
  CreditCard,
  Edit,
  Mail,
  MapPin,
  Phone,
  ReceiptText,
  User2,
} from 'lucide-react';
import { Button } from '@/Components/ui/button';

interface ContactInfo {
  id: number;
  name: string;
  supplier_business_name: string | null;
  type: string;
  tax_number_masked: string | null;
  mobile: string | null;
  landline: string | null;
  email: string | null;
  city: string | null;
  state: string | null;
  address_line_1: string | null;
}

interface ContactStats {
  total_invoice: number;
  invoice_due: number;
  total_purchase: number;
  purchase_due: number;
  opening_balance: number;
}

interface ContactTransaction {
  id: number;
  invoice_no: string;
  transaction_date: string;
  final_total: number;
  payment_status: string;
}

interface ClienteShowPageProps {
  contact: ContactInfo;
  stats: ContactStats;
  transactions: ContactTransaction[];
  permissions: {
    update: boolean;
  };
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

export default function ClienteShow(props: ClienteShowPageProps) {
  const { contact } = props;

  return (
    <div className="-m-6 bg-muted/30 min-h-[calc(100vh-3rem)]">
      <div className="border-b border-border bg-background">
        <div className="container mx-auto px-8 pt-6 pb-4 max-w-6xl">
          <div className="flex items-center gap-3 mb-2">
            <a
              href="/contacts/customer"
              className="inline-flex items-center text-xs text-muted-foreground hover:text-foreground transition-colors"
            >
              <ChevronLeft size={14} className="mr-1" />
              Voltar para clientes
            </a>
          </div>
          <div className="flex items-start gap-4">
            <div className="h-14 w-14 rounded-md bg-gradient-to-br from-stone-100 to-stone-200 dark:from-stone-800 dark:to-stone-700 flex items-center justify-center text-lg font-semibold text-stone-700 dark:text-stone-200">
              {contact.name.charAt(0).toUpperCase()}
            </div>
            <div className="flex-1 min-w-0">
              <h1 className="text-2xl font-semibold tracking-tight text-foreground">{contact.name}</h1>
              <p className="text-sm text-muted-foreground mt-1">
                {contact.tax_number_masked ?? 'Documento não informado'}
                {contact.type && (
                  <span className="ml-2 inline-flex items-center rounded-full border border-border bg-muted/40 px-2 py-0.5 text-[10px] uppercase tracking-wider">
                    {contact.type}
                  </span>
                )}
              </p>
            </div>
            {props.permissions.update && (
              <Button asChild>
                <a href={`/contacts/${contact.id}/edit`}>
                  <Edit className="mr-1.5 h-4 w-4" />
                  Editar
                </a>
              </Button>
            )}
          </div>

          <Deferred data="stats" fallback={<StatsSkeleton />}>
            <div className="grid grid-cols-1 sm:grid-cols-4 gap-4 mt-6">
              <StatCard label="Total vendido" value={formatBRL(props.stats?.total_invoice ?? 0)} icon={ReceiptText} />
              <StatCard label="A receber" value={formatBRL(props.stats?.invoice_due ?? 0)} icon={CreditCard} danger={(props.stats?.invoice_due ?? 0) > 0} />
              <StatCard label="Total comprado" value={formatBRL(props.stats?.total_purchase ?? 0)} icon={ReceiptText} />
              <StatCard label="Saldo abertura" value={formatBRL(props.stats?.opening_balance ?? 0)} icon={User2} />
            </div>
          </Deferred>
        </div>
      </div>

      <div className="container mx-auto px-8 py-6 max-w-6xl">
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          <aside className="md:col-span-1 space-y-4">
            <div className="rounded-lg border border-border bg-background p-5">
              <h3 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-3">Contato</h3>
              <dl className="space-y-2 text-sm">
                <ContactRow icon={Phone} label="Celular" value={contact.mobile} />
                <ContactRow icon={Phone} label="Fixo" value={contact.landline} />
                <ContactRow icon={Mail} label="E-mail" value={contact.email} />
                <ContactRow
                  icon={MapPin}
                  label="Endereço"
                  value={[contact.address_line_1, contact.city, contact.state].filter(Boolean).join(', ') || null}
                />
              </dl>
            </div>
          </aside>

          <section className="md:col-span-2">
            <div className="rounded-lg border border-border bg-background overflow-hidden">
              <div className="p-4 border-b border-border flex items-center justify-between">
                <h3 className="text-sm font-semibold text-foreground">Histórico de transações</h3>
                <a
                  href={`/contacts/ledger?contact_id=${contact.id}`}
                  className="text-xs text-blue-600 hover:underline"
                >
                  Ver extrato completo →
                </a>
              </div>
              <Deferred data="transactions" fallback={<div className="p-8 text-center text-xs text-muted-foreground">Carregando…</div>}>
                {(props.transactions?.length ?? 0) === 0 ? (
                  <div className="p-8 text-center text-xs text-muted-foreground">
                    Nenhuma transação registrada.
                  </div>
                ) : (
                  <table className="w-full text-sm">
                    <thead className="bg-muted/50">
                      <tr className="border-b border-border">
                        <th className="text-left px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Data</th>
                        <th className="text-left px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Nº Fatura</th>
                        <th className="text-right px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Total</th>
                        <th className="text-left px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Status</th>
                      </tr>
                    </thead>
                    <tbody>
                      {props.transactions.map((tx) => (
                        <tr key={tx.id} className="border-b border-border hover:bg-muted/40">
                          <td className="px-4 py-3 text-xs text-muted-foreground tabular-nums">{formatDate(tx.transaction_date)}</td>
                          <td className="px-4 py-3 font-medium">
                            <a href={`/sells/${tx.id}`} className="text-foreground hover:underline">{tx.invoice_no}</a>
                          </td>
                          <td className="px-4 py-3 text-right tabular-nums text-foreground">{formatBRL(tx.final_total)}</td>
                          <td className="px-4 py-3">
                            <PaymentBadge status={tx.payment_status} />
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                )}
              </Deferred>
            </div>
          </section>
        </div>
      </div>
    </div>
  );
}

ClienteShow.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

function StatsSkeleton() {
  return (
    <div className="grid grid-cols-1 sm:grid-cols-4 gap-4 mt-6">
      {[0, 1, 2, 3].map((i) => (
        <div key={i} className="rounded-xl border border-border bg-background p-5 h-24 animate-pulse" />
      ))}
    </div>
  );
}

function StatCard({ label, value, icon: Icon, danger }: { label: string; value: string; icon: typeof User2; danger?: boolean }) {
  return (
    <div
      className={
        'rounded-xl border p-4 shadow-sm ' +
        (danger
          ? 'border-rose-200 bg-rose-50/50 dark:border-rose-900/40 dark:bg-rose-950/30'
          : 'border-border bg-background')
      }
    >
      <div className={'text-[11px] font-semibold uppercase tracking-widest ' + (danger ? 'text-rose-700 dark:text-rose-400' : 'text-muted-foreground')}>
        {label}
      </div>
      <div className="flex items-end justify-between mt-2">
        <div className={'text-xl font-semibold tabular-nums ' + (danger ? 'text-rose-700 dark:text-rose-300' : 'text-foreground')}>
          {value}
        </div>
        <Icon size={20} className={danger ? 'text-rose-400' : 'text-muted-foreground/60'} strokeWidth={1.5} />
      </div>
    </div>
  );
}

function ContactRow({ icon: Icon, label, value }: { icon: typeof Phone; label: string; value: string | null }) {
  return (
    <div className="flex items-start gap-2">
      <Icon size={14} className="text-muted-foreground mt-0.5 flex-shrink-0" />
      <div className="flex-1 min-w-0">
        <dt className="text-xs text-muted-foreground">{label}</dt>
        <dd className="text-sm text-foreground truncate">{value ?? '—'}</dd>
      </div>
    </div>
  );
}

function PaymentBadge({ status }: { status: string }) {
  const styles: Record<string, string> = {
    paid: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    due: 'bg-amber-50 text-amber-700 border-amber-200',
    partial: 'bg-blue-50 text-blue-700 border-blue-200',
  };
  const labels: Record<string, string> = {
    paid: 'Pago',
    due: 'A receber',
    partial: 'Parcial',
  };
  const cls = styles[status] ?? 'bg-muted text-muted-foreground border-border';
  return (
    <span className={'inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-medium ' + cls}>
      {labels[status] ?? status}
    </span>
  );
}

// Eslint hint: keep AlertTriangle imported in case canary surfaces overdue badge later.
export const _kept = AlertTriangle;
