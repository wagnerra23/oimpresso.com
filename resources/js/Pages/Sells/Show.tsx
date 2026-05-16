// Wave 1 W1-A — MWART /sells/{id} (Detalhar venda).
// Refs: ADR 0104 (MWART canon), ADR 0149 (screen-pattern reuse Index/SaleSheet),
//       ADR 0107 (visual gate), ADR 0143 (FSM Pipeline LIVE), ADR 0093 (multi-tenant Tier 0).
//
// Layout 2 cols (8/4): esquerda headline + linhas + pagamentos + atividades;
//                       direita FSM action panel + timeline + ações.
// Detail vem DEFERRED (Inertia::defer no controller — RUNBOOK-inertia-defer-pattern).

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Head, Link, router } from '@inertiajs/react';
import { useEffect, type ReactNode } from 'react';
import {
  ArrowLeft,
  CreditCard,
  Edit,
  Mail,
  MapPin,
  Package,
  Phone,
  Printer,
  User as UserIcon,
} from 'lucide-react';
import KpiCard from '@/Components/shared/KpiCard';
import EmptyState from '@/Components/shared/EmptyState';
import { Button } from '@/Components/ui/button';

interface Customer {
  id: number;
  name: string;
  mobile: string | null;
  email: string | null;
}

interface Headline {
  id: number;
  invoice_no: string;
  transaction_date: string;
  final_total: number;
  total_paid: number;
  payment_status: 'paid' | 'due' | 'partial' | string;
  status: 'final' | 'draft' | 'quotation' | 'proforma' | string;
  current_stage_key: string | null;
  customer: Customer | null;
  location: { id: number; name: string } | null;
}

interface SaleLine {
  id: number;
  product_name: string;
  product_sku: string;
  quantity: number;
  unit_price: number;
  discount: number;
  subtotal: number;
  tax_amount: number;
  unit: string;
}

interface SalePayment {
  id: number;
  amount: number;
  method: string;
  paid_on: string | null;
  note: string | null;
}

interface Activity {
  description: string;
  causer_name: string;
  created_at: string;
}

interface ShowDetail {
  lines: SaleLine[];
  payments: SalePayment[];
  taxes: { order_taxes: Record<string, number>; line_taxes: Record<string, number> };
  activities: Activity[];
  shipping: { details: string; address: string; cost: number; status: string | null };
  notes: string | null;
  sub_type: string | null;
  sales_orders: string[];
  statuses: Record<string, string>;
  shipping_statuses: Record<string, string>;
  is_warranty_enabled: boolean;
}

export interface SellsShowPageProps {
  saleId: number;
  headline: Headline;
  detail?: ShowDetail;  // deferred
  permissions: { edit: boolean; delete: boolean; print: boolean };
  urls: { edit: string; print: string; sheet_data: string; back: string };
}

const PAYMENT_STATUS_LABEL: Record<string, string> = {
  paid: 'Pago',
  due: 'A receber',
  partial: 'Parcial',
};

const PAYMENT_STATUS_TONE: Record<string, string> = {
  paid: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300',
  due: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300',
  partial: 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/40 dark:text-blue-300',
};

const PAYMENT_METHOD_LABEL: Record<string, string> = {
  cash: 'Dinheiro',
  card: 'Cartão',
  bank_transfer: 'Transferência',
  custom_pay_1: 'PIX',
  custom_pay_2: 'Boleto',
};

function formatBRL(value: number): string {
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);
}

function formatDateTime(input: string): string {
  if (!input) return '';
  const d = new Date(input);
  if (Number.isNaN(d.getTime())) return input;
  return new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(d);
}

function DetailSkeleton() {
  return (
    <div className="space-y-4">
      <div className="h-32 bg-muted/40 rounded-lg animate-pulse" />
      <div className="h-48 bg-muted/40 rounded-lg animate-pulse" />
      <div className="h-32 bg-muted/40 rounded-lg animate-pulse" />
    </div>
  );
}

export default function SellsShow(props: SellsShowPageProps) {
  const { headline, urls, permissions } = props;

  // Atalhos teclado E (edit) + P (print) + Esc (back)
  useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      if (
        e.target instanceof HTMLInputElement ||
        e.target instanceof HTMLTextAreaElement
      ) {
        return;
      }
      if (e.key === 'e' && permissions.edit) {
        e.preventDefault();
        router.visit(urls.edit);
      }
      if (e.key === 'p' && permissions.print) {
        e.preventDefault();
        window.open(urls.print, '_blank');
      }
      if (e.key === 'Escape') {
        e.preventDefault();
        router.visit(urls.back);
      }
    };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, [permissions.edit, permissions.print, urls.edit, urls.print, urls.back]);

  const totalFalta = Math.max(0, headline.final_total - headline.total_paid);
  const paymentStatusLabel = PAYMENT_STATUS_LABEL[headline.payment_status] ?? headline.payment_status;
  const paymentStatusTone = PAYMENT_STATUS_TONE[headline.payment_status] ?? 'bg-muted/50 text-muted-foreground border-border';

  return (
    <>
      <Head title={`Venda #${headline.invoice_no}`} />

      <div className="container mx-auto px-6 py-6 space-y-6">
        {/* Header */}
        <div className="flex items-start justify-between gap-4 flex-wrap">
          <div className="flex items-start gap-3">
            <Button variant="ghost" size="icon" asChild aria-label="Voltar">
              <Link href={urls.back}>
                <ArrowLeft className="h-4 w-4" />
              </Link>
            </Button>
            <div>
              <h1 className="text-2xl font-semibold tracking-tight">
                Venda #{headline.invoice_no}
              </h1>
              <p className="text-sm text-muted-foreground mt-1">
                {formatDateTime(headline.transaction_date)}
                {headline.location ? ` · ${headline.location.name}` : ''}
              </p>
            </div>
          </div>

          <div className="flex items-center gap-2">
            {permissions.print && (
              <Button variant="outline" size="sm" asChild>
                <a href={urls.print} target="_blank" rel="noopener noreferrer">
                  <Printer className="h-4 w-4 mr-2" />
                  Imprimir
                </a>
              </Button>
            )}
            {permissions.edit && (
              <Button variant="default" size="sm" asChild>
                <Link href={urls.edit}>
                  <Edit className="h-4 w-4 mr-2" />
                  Editar
                </Link>
              </Button>
            )}
          </div>
        </div>

        {/* KPIs grandes (4-col canon V2) */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <KpiCard
            label="Total"
            value={formatBRL(headline.final_total)}
            icon="receipt"
            tone="default"
          />
          <KpiCard
            label="Pago"
            value={formatBRL(headline.total_paid)}
            icon="credit-card"
            tone="success"
          />
          <KpiCard
            label="Falta"
            value={formatBRL(totalFalta)}
            icon="file-text"
            tone={totalFalta > 0 ? 'warning' : 'default'}
          />
          <div className="rounded-xl border border-border bg-card p-4 flex flex-col gap-2 shadow-sm">
            <span className="text-[11px] font-semibold text-muted-foreground uppercase tracking-widest">
              Status pgto
            </span>
            <span
              className={`inline-flex items-center px-2.5 py-1 rounded-md text-sm font-medium border self-start ${paymentStatusTone}`}
            >
              {paymentStatusLabel}
            </span>
          </div>
        </div>

        {/* Layout 2-col: 8/4 */}
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
          {/* COLUNA ESQUERDA — headline + linhas + pagamentos + activities */}
          <div className="lg:col-span-8 space-y-6">
            {/* Cliente */}
            {headline.customer && (
              <section className="rounded-lg border border-border bg-card p-5">
                <div className="flex items-center gap-2 mb-3">
                  <UserIcon className="h-4 w-4 text-muted-foreground" />
                  <h2 className="font-semibold text-sm">Cliente</h2>
                </div>
                <div className="space-y-1">
                  <p className="font-medium text-base">{headline.customer.name}</p>
                  {headline.customer.mobile && (
                    <p className="text-sm text-muted-foreground flex items-center gap-2">
                      <Phone className="h-3 w-3" />
                      {headline.customer.mobile}
                    </p>
                  )}
                  {headline.customer.email && (
                    <p className="text-sm text-muted-foreground flex items-center gap-2">
                      <Mail className="h-3 w-3" />
                      {headline.customer.email}
                    </p>
                  )}
                </div>
              </section>
            )}

            {/* Linhas + pagamentos + atividades — deferred */}
            <Deferred data="detail" fallback={<DetailSkeleton />}>
              <ShowDetailSections detail={props.detail} headline={headline} />
            </Deferred>
          </div>

          {/* COLUNA DIREITA — FSM action panel + timeline */}
          <aside className="lg:col-span-4 space-y-4">
            {/* FSM action panel — reuso shared (apenas se stage_key existe) */}
            {headline.current_stage_key !== null && (
              <section className="rounded-lg border border-border bg-card p-4">
                <h2 className="font-semibold text-sm mb-3">Pipeline</h2>
                {/* FsmActionPanel já existe em _components/ — reuso quando integrável */}
                <p className="text-xs text-muted-foreground">
                  Stage atual: <span className="font-mono">{headline.current_stage_key}</span>
                </p>
              </section>
            )}

            {/* Atalhos hint */}
            <section className="rounded-lg border border-border bg-card p-4">
              <h2 className="font-semibold text-sm mb-2">Atalhos</h2>
              <div className="space-y-1.5 text-xs text-muted-foreground">
                {permissions.edit && <div><kbd className="px-1.5 py-0.5 bg-muted rounded">E</kbd> Editar</div>}
                {permissions.print && <div><kbd className="px-1.5 py-0.5 bg-muted rounded">P</kbd> Imprimir</div>}
                <div><kbd className="px-1.5 py-0.5 bg-muted rounded">Esc</kbd> Voltar</div>
              </div>
            </section>
          </aside>
        </div>
      </div>
    </>
  );
}

interface ShowDetailSectionsProps {
  detail?: ShowDetail;
  headline: Headline;
}

function ShowDetailSections({ detail, headline }: ShowDetailSectionsProps) {
  if (!detail) return <DetailSkeleton />;

  return (
    <>
      {/* Linhas da venda */}
      <section className="rounded-lg border border-border bg-card overflow-hidden">
        <div className="flex items-center gap-2 px-5 py-3 border-b border-border bg-muted/30">
          <Package className="h-4 w-4 text-muted-foreground" />
          <h2 className="font-semibold text-sm">Itens da venda</h2>
          <span className="ml-auto text-xs text-muted-foreground">{detail.lines.length} item(s)</span>
        </div>
        {detail.lines.length === 0 ? (
          <EmptyState
            icon="package"
            title="Nenhum item"
            description="Esta venda não tem produtos."
          />
        ) : (
          <table className="w-full text-sm">
            <thead className="text-xs text-muted-foreground uppercase tracking-wide">
              <tr className="border-b border-border">
                <th className="text-left px-5 py-2 font-medium">Produto</th>
                <th className="text-right px-3 py-2 font-medium">Qtd</th>
                <th className="text-right px-3 py-2 font-medium">Unit</th>
                <th className="text-right px-3 py-2 font-medium">Desc.</th>
                <th className="text-right px-5 py-2 font-medium">Subtotal</th>
              </tr>
            </thead>
            <tbody>
              {detail.lines.map((line, idx) => (
                <tr
                  key={line.id}
                  className={idx % 2 === 0 ? 'bg-transparent' : 'bg-muted/20'}
                >
                  <td className="px-5 py-3">
                    <div className="font-medium">{line.product_name}</div>
                    {line.product_sku && (
                      <div className="text-xs text-muted-foreground">{line.product_sku}</div>
                    )}
                  </td>
                  <td className="px-3 py-3 text-right tabular-nums">
                    {line.quantity} {line.unit}
                  </td>
                  <td className="px-3 py-3 text-right tabular-nums">
                    {formatBRL(line.unit_price)}
                  </td>
                  <td className="px-3 py-3 text-right tabular-nums text-muted-foreground">
                    {line.discount > 0 ? formatBRL(line.discount) : '—'}
                  </td>
                  <td className="px-5 py-3 text-right tabular-nums font-semibold">
                    {formatBRL(line.subtotal)}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </section>

      {/* Pagamentos */}
      <section className="rounded-lg border border-border bg-card overflow-hidden">
        <div className="flex items-center gap-2 px-5 py-3 border-b border-border bg-muted/30">
          <CreditCard className="h-4 w-4 text-muted-foreground" />
          <h2 className="font-semibold text-sm">Pagamentos</h2>
          <span className="ml-auto text-xs text-muted-foreground">{detail.payments.length} lançamento(s)</span>
        </div>
        {detail.payments.length === 0 ? (
          <EmptyState
            icon="credit-card"
            title="Nenhum pagamento registrado"
            description="Venda à vista zerada ou a receber."
          />
        ) : (
          <div className="divide-y divide-border">
            {detail.payments.map((p) => (
              <div key={p.id} className="flex items-center justify-between px-5 py-3">
                <div className="space-y-0.5">
                  <div className="font-medium text-sm">
                    {PAYMENT_METHOD_LABEL[p.method] ?? p.method}
                  </div>
                  {p.paid_on && (
                    <div className="text-xs text-muted-foreground">
                      {formatDateTime(p.paid_on)}
                    </div>
                  )}
                  {p.note && (
                    <div className="text-xs text-muted-foreground italic">{p.note}</div>
                  )}
                </div>
                <div className="text-right tabular-nums font-semibold">
                  {formatBRL(p.amount)}
                </div>
              </div>
            ))}
          </div>
        )}
      </section>

      {/* Frete (se houver) */}
      {detail.shipping.cost > 0 && (
        <section className="rounded-lg border border-border bg-card p-5">
          <div className="flex items-center gap-2 mb-3">
            <MapPin className="h-4 w-4 text-muted-foreground" />
            <h2 className="font-semibold text-sm">Frete</h2>
          </div>
          <div className="space-y-1 text-sm">
            {detail.shipping.address && (
              <p className="text-muted-foreground">{detail.shipping.address}</p>
            )}
            {detail.shipping.details && (
              <p className="text-muted-foreground italic">{detail.shipping.details}</p>
            )}
            <p className="font-semibold tabular-nums mt-2">
              Custo: {formatBRL(detail.shipping.cost)}
            </p>
          </div>
        </section>
      )}

      {/* Atividades */}
      {detail.activities.length > 0 && (
        <section className="rounded-lg border border-border bg-card overflow-hidden">
          <div className="flex items-center gap-2 px-5 py-3 border-b border-border bg-muted/30">
            <h2 className="font-semibold text-sm">Histórico</h2>
            <span className="ml-auto text-xs text-muted-foreground">
              {detail.activities.length} evento(s)
            </span>
          </div>
          <div className="divide-y divide-border">
            {detail.activities.map((a, idx) => (
              <div key={idx} className="px-5 py-3 text-sm">
                <div className="text-foreground">{a.description}</div>
                <div className="text-xs text-muted-foreground mt-0.5">
                  {a.causer_name} · {formatDateTime(a.created_at)}
                </div>
              </div>
            ))}
          </div>
        </section>
      )}
    </>
  );
}

SellsShow.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
