// US-CRM-CONT-004 — Detalhe do Contato (Show) Inertia/React.
// Layout Cockpit V2: header + KPIs financeiros + tabs (Histórico / Documentos / Endereço).
//
// Refs: ADR 0104, ADR 0110, ADR 0093, RUNBOOK-contacts.md.

import AppShellV2 from '@/Layouts/AppShellV2';
import { useMemo, type ReactNode } from 'react';
import {
  ArrowLeft,
  CheckCircle2,
  CreditCard,
  Edit,
  ExternalLink,
  Mail,
  MapPin,
  Phone,
  Power,
  Receipt,
  Trash2,
  TrendingUp,
} from 'lucide-react';
import { Button } from '@/Components/ui/button';

interface ContactDetail {
  id: number;
  type: 'customer' | 'supplier' | 'both' | string;
  contact_type: 'individual' | 'business' | string | null;
  name: string;
  supplier_business_name: string | null;
  contact_id: string | null;
  contact_status: 'active' | 'inactive' | string;
  is_default: boolean;
  // Identification
  tax_number: string | null;
  email: string | null;
  mobile: string | null;
  landline: string | null;
  alternate_number: string | null;
  // Address
  address_line_1: string | null;
  address_line_2: string | null;
  city: string | null;
  state: string | null;
  country: string | null;
  zip_code: string | null;
  // Financial aggregates (vindo do ContactUtil::getContactInfo)
  total_invoice: number | null;
  invoice_received: number | null;
  total_purchase: number | null;
  purchase_paid: number | null;
  total_sell_return: number | null;
  total_purchase_return: number | null;
  opening_balance: number | null;
  balance: number | null;
}

interface RecentTransaction {
  id: number;
  type: string;
  invoice_no: string | null;
  transaction_date: string;
  final_total: number;
  total_paid: number;
  payment_status: string | null;
  url: string;
}

export interface ContactsShowPageProps {
  contact: ContactDetail;
  recentTransactions: RecentTransaction[];
  financialSummary: {
    total_invoice: number;
    invoice_received: number;
    total_due: number;
    opening_balance: number;
    advance_balance: number;
  };
  permissions: {
    update: boolean;
    delete: boolean;
    pay: boolean;
  };
}

const formatBRL = (value: number | null | undefined) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value ?? 0);

const formatDate = (iso: string | null | undefined) => {
  if (!iso) return '—';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return iso;
  return new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: '2-digit',
  }).format(d);
};

function formatTaxNumber(raw: string | null): string {
  if (!raw) return '—';
  const d = raw.replace(/\D/g, '');
  if (d.length === 11) return d.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
  if (d.length === 14) return d.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
  return raw;
}

function formatPhone(raw: string | null): string {
  if (!raw) return '—';
  const d = raw.replace(/\D/g, '');
  if (d.length === 11) return `(${d.slice(0, 2)}) ${d.slice(2, 7)}-${d.slice(7)}`;
  if (d.length === 10) return `(${d.slice(0, 2)}) ${d.slice(2, 6)}-${d.slice(6)}`;
  return raw;
}

export default function ContactsShow(props: ContactsShowPageProps) {
  const { contact, recentTransactions, financialSummary, permissions } = props;

  const displayName = contact.supplier_business_name ?? contact.name;
  const secondaryName = contact.supplier_business_name && contact.name !== contact.supplier_business_name
    ? contact.name
    : null;

  const fullAddress = useMemo(() => {
    return [
      contact.address_line_1,
      contact.address_line_2,
      contact.city,
      contact.state,
      contact.country,
      contact.zip_code,
    ]
      .filter(Boolean)
      .join(', ');
  }, [contact]);

  const backUrl = `/contacts?type=${contact.type === 'both' ? 'customer' : contact.type}`;

  const handleDelete = async () => {
    if (contact.is_default) {
      alert('Contato padrão não pode ser excluído.');
      return;
    }
    if (!confirm(`Excluir "${displayName}"? Essa ação não pode ser desfeita.`)) return;
    try {
      const meta = document.querySelector('meta[name="csrf-token"]');
      const csrf = meta?.getAttribute('content') ?? '';
      const res = await fetch(`/contacts/${contact.id}`, {
        method: 'DELETE',
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrf,
        },
        credentials: 'same-origin',
      });
      const json = await res.json().catch(() => null);
      if (!res.ok || json?.success === false) {
        alert(json?.msg ?? 'Falha ao excluir.');
        return;
      }
      window.location.href = backUrl;
    } catch (err) {
      alert('Erro de rede: ' + String((err as Error)?.message || err));
    }
  };

  return (
    <div className="-m-6 bg-muted/30 min-h-[calc(100vh-3rem)]">
      {/* Header */}
      <div className="border-b border-border bg-background">
        <div className="container mx-auto px-8 pt-6 pb-4 max-w-6xl">
          <div className="text-xs text-muted-foreground mb-2">
            <a href={backUrl} className="inline-flex items-center gap-1 hover:text-foreground">
              <ArrowLeft size={11} />
              Contatos
            </a>
            <span className="mx-1">/</span>
            <span>{displayName}</span>
          </div>
          <div className="flex items-start gap-4">
            <div className="flex-1 min-w-0">
              <div className="flex items-center gap-2 flex-wrap">
                <h1 className="text-2xl font-semibold tracking-tight text-foreground truncate">{displayName}</h1>
                {contact.contact_status === 'inactive' ? (
                  <span className="inline-flex items-center gap-1 rounded-full border border-rose-200 bg-rose-50 px-2.5 py-0.5 text-[11px] font-medium text-rose-700">
                    <Power size={11} />
                    Inativo
                  </span>
                ) : (
                  <span className="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-[11px] font-medium text-emerald-700">
                    <CheckCircle2 size={11} />
                    Ativo
                  </span>
                )}
              </div>
              {secondaryName && (
                <p className="text-sm text-muted-foreground mt-1">{secondaryName}</p>
              )}
              <div className="flex items-center gap-4 mt-2 text-xs text-muted-foreground flex-wrap">
                {contact.tax_number && (
                  <span>{formatTaxNumber(contact.tax_number)}</span>
                )}
                {contact.mobile && (
                  <span className="inline-flex items-center gap-1">
                    <Phone size={11} />
                    {formatPhone(contact.mobile)}
                  </span>
                )}
                {contact.email && (
                  <a href={`mailto:${contact.email}`} className="inline-flex items-center gap-1 hover:text-foreground">
                    <Mail size={11} />
                    {contact.email}
                  </a>
                )}
                {contact.contact_id && <span>#{contact.contact_id}</span>}
              </div>
            </div>
            <div className="flex-shrink-0 flex items-center gap-2">
              {permissions.update && (
                <Button asChild variant="outline">
                  <a href={`/contacts/${contact.id}/edit`}>
                    <Edit className="mr-1.5 h-4 w-4" />
                    Editar
                  </a>
                </Button>
              )}
              {permissions.delete && !contact.is_default && (
                <Button variant="outline" onClick={handleDelete} className="text-rose-600 hover:text-rose-700 hover:bg-rose-50">
                  <Trash2 className="mr-1.5 h-4 w-4" />
                  Excluir
                </Button>
              )}
            </div>
          </div>

          {/* Financial KPIs */}
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6">
            <KpiCard label="Total faturado" value={formatBRL(financialSummary.total_invoice)} icon={Receipt} />
            <KpiCard label="Recebido" value={formatBRL(financialSummary.invoice_received)} icon={CheckCircle2} />
            <KpiCard
              label="A receber"
              value={formatBRL(financialSummary.total_due)}
              icon={TrendingUp}
              danger={financialSummary.total_due > 0}
            />
            <KpiCard label="Saldo adiantado" value={formatBRL(financialSummary.advance_balance)} icon={CreditCard} />
          </div>
        </div>
      </div>

      <div className="container mx-auto px-8 py-6 max-w-6xl grid grid-cols-1 md:grid-cols-3 gap-4">
        {/* Endereço + identificação detalhada */}
        <div className="space-y-4">
          <section className="rounded-lg border border-border bg-background p-5">
            <h2 className="text-sm font-semibold text-foreground mb-3 inline-flex items-center gap-2">
              <MapPin size={14} className="text-muted-foreground" />
              Endereço
            </h2>
            {fullAddress ? (
              <p className="text-sm text-foreground leading-relaxed whitespace-pre-line">{fullAddress}</p>
            ) : (
              <p className="text-xs text-muted-foreground">Sem endereço cadastrado.</p>
            )}
          </section>

          <section className="rounded-lg border border-border bg-background p-5">
            <h2 className="text-sm font-semibold text-foreground mb-3">Contatos</h2>
            <dl className="space-y-2 text-sm">
              {contact.mobile && (
                <div className="flex items-center justify-between">
                  <dt className="text-xs text-muted-foreground">Celular</dt>
                  <dd className="text-foreground tabular-nums">{formatPhone(contact.mobile)}</dd>
                </div>
              )}
              {contact.landline && (
                <div className="flex items-center justify-between">
                  <dt className="text-xs text-muted-foreground">Fixo</dt>
                  <dd className="text-foreground tabular-nums">{formatPhone(contact.landline)}</dd>
                </div>
              )}
              {contact.alternate_number && (
                <div className="flex items-center justify-between">
                  <dt className="text-xs text-muted-foreground">Alternativo</dt>
                  <dd className="text-foreground tabular-nums">{formatPhone(contact.alternate_number)}</dd>
                </div>
              )}
              {contact.email && (
                <div className="flex items-center justify-between">
                  <dt className="text-xs text-muted-foreground">Email</dt>
                  <dd className="text-foreground">{contact.email}</dd>
                </div>
              )}
              {!contact.mobile && !contact.landline && !contact.alternate_number && !contact.email && (
                <p className="text-xs text-muted-foreground">Sem contatos cadastrados.</p>
              )}
            </dl>
          </section>
        </div>

        {/* Histórico de transações */}
        <section className="md:col-span-2 rounded-lg border border-border bg-background">
          <div className="border-b border-border px-5 py-4">
            <h2 className="text-sm font-semibold text-foreground">Histórico recente</h2>
            <p className="text-xs text-muted-foreground mt-0.5">
              Últimas 10 transações deste contato.
            </p>
          </div>

          {recentTransactions.length === 0 ? (
            <div className="px-5 py-12 text-center">
              <p className="text-xs text-muted-foreground">Nenhuma transação registrada.</p>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="bg-muted/40">
                  <tr className="border-b border-border">
                    <th className="text-left px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Data</th>
                    <th className="text-left px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Nº</th>
                    <th className="text-right px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Total</th>
                    <th className="text-right px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Pago</th>
                    <th className="w-8 text-right pr-4">&nbsp;</th>
                  </tr>
                </thead>
                <tbody>
                  {recentTransactions.map((tx) => (
                    <tr key={tx.id} className="border-b border-border hover:bg-muted/30 transition-colors">
                      <td className="px-4 py-3 text-xs text-muted-foreground tabular-nums whitespace-nowrap">{formatDate(tx.transaction_date)}</td>
                      <td className="px-4 py-3 font-medium text-foreground">{tx.invoice_no ?? `#${tx.id}`}</td>
                      <td className="px-4 py-3 text-right tabular-nums text-foreground">{formatBRL(tx.final_total)}</td>
                      <td className="px-4 py-3 text-right tabular-nums text-muted-foreground">{formatBRL(tx.total_paid)}</td>
                      <td className="px-2 py-3 text-right pr-4">
                        <a
                          href={tx.url}
                          className="inline-flex h-7 w-7 items-center justify-center rounded text-muted-foreground hover:bg-muted hover:text-foreground"
                          aria-label="Abrir transação"
                          target="_blank"
                          rel="noopener noreferrer"
                        >
                          <ExternalLink size={14} />
                        </a>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </section>
      </div>
    </div>
  );
}

ContactsShow.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

function KpiCard({
  label,
  value,
  icon: Icon,
  danger,
}: {
  label: string;
  value: string;
  icon: typeof Receipt;
  danger?: boolean;
}) {
  return (
    <div
      className={
        'rounded-xl border p-4 shadow-sm ' +
        (danger
          ? 'border-rose-200 bg-rose-50/50 dark:border-rose-900/40 dark:bg-rose-950/30'
          : 'border-border bg-background')
      }
    >
      <div className={'text-[10px] font-semibold uppercase tracking-widest ' + (danger ? 'text-rose-700 dark:text-rose-400' : 'text-muted-foreground')}>
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
