// US-SELL-008 — Drawer lateral direito de detalhes da venda (pattern Cockpit canon).
// Refs: exemplo Officeimpresso/OS Anthropic claude.ai/design (gold-standard Wagner aprovou),
//        Pages/ProjectMgmt/Board/DetailSheet.tsx (pattern fonte interno).

import { useEffect, useState } from 'react';
import {
  AlertTriangle,
  CheckCircle2,
  Clock,
  CreditCard,
  Edit,
  ExternalLink,
  Loader2,
  MapPin,
  Package,
  Phone,
  Printer,
  Receipt,
  User,
} from 'lucide-react';
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetDescription,
} from '@/Components/ui/sheet';
import { Button } from '@/Components/ui/button';

interface Customer {
  id: number;
  name: string;
  secondary: string | null;
  mobile: string | null;
  email: string | null;
}

interface Line {
  id: number;
  product_name: string | null;
  product_sku: string | null;
  quantity: number;
  unit_price: number;
  discount: number;
  subtotal: number;
}

interface Payment {
  id: number;
  amount: number;
  method: string;
  paid_on: string | null;
  note: string | null;
}

interface SaleDetail {
  id: number;
  invoice_no: string;
  transaction_date: string;
  final_total: number;
  total_paid: number;
  tax_amount: number;
  discount_amount: number;
  shipping_charges: number;
  payment_status: string;
  shipping_status: string | null;
  status: string;
  additional_notes: string | null;
  customer: Customer | null;
  location: { id: number; name: string } | null;
  lines: Line[];
  payments: Payment[];
  urls: {
    edit: string;
    print: string;
  };
}

interface Props {
  saleId: number | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

const formatBRL = (value: number) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);

const formatDate = (iso: string) => {
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return iso;
  return new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(d);
};

const PAYMENT_METHOD_LABEL: Record<string, string> = {
  cash: 'Dinheiro',
  card: 'Cartão',
  cheque: 'Cheque',
  bank_transfer: 'Transferência',
  other: 'Outros',
  custom_pay_1: 'Pix',
  custom_pay_2: 'Boleto',
  custom_pay_3: 'Crediário',
};

const PAYMENT_STATUS_LABEL: Record<string, string> = {
  paid: 'Pago',
  due: 'A receber',
  partial: 'Parcial',
};

const PAYMENT_STATUS_STYLE: Record<string, string> = {
  paid: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300',
  due: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300',
  partial: 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/40 dark:text-blue-300',
};

export default function SaleSheet({ saleId, open, onOpenChange }: Props) {
  const [data, setData] = useState<SaleDetail | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!saleId || !open) {
      setData(null);
      setError(null);
      return;
    }
    let cancelled = false;
    setLoading(true);
    setError(null);
    fetch(`/sells/${saleId}/sheet-data`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
      .then((r) => {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      })
      .then((json) => {
        if (!cancelled) setData(json);
      })
      .catch((e) => {
        if (!cancelled) setError(String(e?.message || e));
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [saleId, open]);

  const saldoDevedor = data ? data.final_total - data.total_paid : 0;

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent
        side="right"
        className="w-full sm:max-w-xl flex flex-col p-0 overflow-hidden"
      >
        {loading && (
          <div className="flex-1 flex items-center justify-center">
            <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
          </div>
        )}

        {error && !loading && (
          <div className="flex-1 flex items-center justify-center p-6 text-center">
            <div>
              <AlertTriangle className="h-8 w-8 text-rose-500 mx-auto mb-2" />
              <p className="text-sm text-foreground font-medium">Não foi possível carregar a venda</p>
              <p className="text-xs text-muted-foreground mt-1">{error}</p>
            </div>
          </div>
        )}

        {!loading && data && (
          <>
            {/* Header drawer — invoice nº + status badges + cliente */}
            <SheetHeader className="px-6 pt-6 pb-4 border-b border-border space-y-2">
              <div className="flex items-center gap-2 flex-wrap">
                <span className="font-mono text-xs text-muted-foreground">#{data.invoice_no}</span>
                <PaymentBadge status={data.payment_status} />
                {data.shipping_status && data.shipping_status !== 'delivered' && (
                  <span className="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-[11px] font-medium text-amber-700 dark:border-amber-900/40 dark:bg-amber-950/40 dark:text-amber-300">
                    Frete: {data.shipping_status}
                  </span>
                )}
              </div>
              <SheetTitle className="text-xl font-semibold tracking-tight text-foreground">
                Venda {data.invoice_no}
              </SheetTitle>
              <SheetDescription className="text-sm text-muted-foreground">
                {data.customer ? (
                  <span>
                    {data.customer.name}
                    {data.customer.secondary && ` · ${data.customer.secondary}`}
                  </span>
                ) : (
                  <span>Cliente não informado</span>
                )}
              </SheetDescription>
            </SheetHeader>

            {/* Conteúdo scroll */}
            <div className="flex-1 overflow-y-auto px-6 py-5 space-y-6">
              {/* 4 KPIs mini horizontais */}
              <div className="grid grid-cols-4 gap-3">
                <MiniKpi label="Itens" value={data.lines.length.toString()} />
                <MiniKpi label="Valor" value={formatBRL(data.final_total)} />
                <MiniKpi
                  label="Pago"
                  value={formatBRL(data.total_paid)}
                  tone={data.payment_status === 'paid' ? 'success' : undefined}
                />
                <MiniKpi
                  label="Saldo"
                  value={formatBRL(saldoDevedor)}
                  tone={saldoDevedor > 0 ? 'warning' : 'success'}
                />
              </div>

              {/* Cliente + local */}
              <Section title="Cliente">
                <div className="space-y-1.5 text-sm">
                  {data.customer ? (
                    <>
                      <div className="flex items-center gap-2 text-foreground">
                        <User size={13} className="text-muted-foreground" />
                        {data.customer.name}
                      </div>
                      {data.customer.mobile && (
                        <div className="flex items-center gap-2 text-muted-foreground text-xs">
                          <Phone size={12} />
                          {data.customer.mobile}
                        </div>
                      )}
                      {data.customer.email && (
                        <div className="flex items-center gap-2 text-muted-foreground text-xs">
                          <ExternalLink size={12} />
                          {data.customer.email}
                        </div>
                      )}
                    </>
                  ) : (
                    <p className="text-xs text-muted-foreground">—</p>
                  )}
                  {data.location && (
                    <div className="flex items-center gap-2 text-muted-foreground text-xs pt-1.5 border-t border-border/60">
                      <MapPin size={12} />
                      {data.location.name}
                    </div>
                  )}
                  <div className="flex items-center gap-2 text-muted-foreground text-xs">
                    <Clock size={12} />
                    {formatDate(data.transaction_date)}
                  </div>
                </div>
              </Section>

              {/* Linhas (produtos) */}
              <Section title={`Produtos (${data.lines.length})`} icon={Package}>
                {data.lines.length === 0 ? (
                  <p className="text-xs text-muted-foreground">Sem produtos.</p>
                ) : (
                  <div className="rounded-md border border-border overflow-hidden">
                    <table className="w-full text-sm">
                      <thead className="bg-muted/40">
                        <tr>
                          <th className="text-left px-3 py-2 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
                            Produto
                          </th>
                          <th className="text-right px-3 py-2 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground w-16">
                            Qtde
                          </th>
                          <th className="text-right px-3 py-2 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground w-24">
                            Subtotal
                          </th>
                        </tr>
                      </thead>
                      <tbody>
                        {data.lines.map((l) => (
                          <tr key={l.id} className="border-t border-border">
                            <td className="px-3 py-2">
                              <div className="text-foreground">{l.product_name ?? '—'}</div>
                              {l.product_sku && (
                                <div className="text-[10px] text-muted-foreground font-mono">{l.product_sku}</div>
                              )}
                            </td>
                            <td className="px-3 py-2 text-right tabular-nums text-muted-foreground">
                              {l.quantity}
                            </td>
                            <td className="px-3 py-2 text-right tabular-nums text-foreground">
                              {formatBRL(l.subtotal)}
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </Section>

              {/* Histórico de pagamentos */}
              <Section title={`Pagamentos (${data.payments.length})`} icon={CreditCard}>
                {data.payments.length === 0 ? (
                  <p className="text-xs text-muted-foreground">Nenhum pagamento registrado.</p>
                ) : (
                  <ul className="space-y-2">
                    {data.payments.map((p) => (
                      <li key={p.id} className="flex items-start gap-3 text-sm">
                        <CheckCircle2 size={14} className="text-emerald-500 mt-0.5 flex-shrink-0" />
                        <div className="flex-1 min-w-0">
                          <div className="flex items-baseline justify-between gap-2">
                            <span className="font-medium text-foreground tabular-nums">{formatBRL(p.amount)}</span>
                            <span className="text-xs text-muted-foreground">
                              {p.paid_on ? formatDate(p.paid_on) : '—'}
                            </span>
                          </div>
                          <div className="text-xs text-muted-foreground">
                            {PAYMENT_METHOD_LABEL[p.method] ?? p.method}
                            {p.note && ` · ${p.note}`}
                          </div>
                        </div>
                      </li>
                    ))}
                  </ul>
                )}
              </Section>

              {/* Notas */}
              {data.additional_notes && (
                <Section title="Observações" icon={Receipt}>
                  <p className="text-sm text-muted-foreground whitespace-pre-wrap">{data.additional_notes}</p>
                </Section>
              )}
            </div>

            {/* Footer ações sticky */}
            <div className="border-t border-border px-6 py-3 bg-background flex items-center justify-end gap-2">
              <Button variant="outline" size="sm" asChild>
                <a href={data.urls.print} target="_blank" rel="noopener noreferrer">
                  <Printer size={14} className="mr-1.5" />
                  Imprimir
                </a>
              </Button>
              <Button size="sm" asChild>
                <a href={data.urls.edit}>
                  <Edit size={14} className="mr-1.5" />
                  Editar
                </a>
              </Button>
            </div>
          </>
        )}
      </SheetContent>
    </Sheet>
  );
}

// ─── Subcomponents ───────────────────────────────────────────────────────────

function Section({
  title,
  icon: Icon,
  children,
}: {
  title: string;
  icon?: typeof Package;
  children: React.ReactNode;
}) {
  return (
    <section>
      <h3 className="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground mb-2 flex items-center gap-1.5">
        {Icon && <Icon size={11} />}
        {title}
      </h3>
      {children}
    </section>
  );
}

function MiniKpi({
  label,
  value,
  tone,
}: {
  label: string;
  value: string;
  tone?: 'success' | 'warning';
}) {
  return (
    <div
      className={
        'rounded-md border p-2.5 ' +
        (tone === 'warning'
          ? 'border-amber-200 bg-amber-50/60 dark:border-amber-900/40 dark:bg-amber-950/30'
          : tone === 'success'
            ? 'border-emerald-200 bg-emerald-50/60 dark:border-emerald-900/40 dark:bg-emerald-950/30'
            : 'border-border bg-muted/30')
      }
    >
      <div
        className={
          'text-[10px] font-semibold uppercase tracking-wider ' +
          (tone === 'warning'
            ? 'text-amber-700 dark:text-amber-400'
            : tone === 'success'
              ? 'text-emerald-700 dark:text-emerald-400'
              : 'text-muted-foreground')
        }
      >
        {label}
      </div>
      <div
        className={
          'text-sm font-semibold tabular-nums mt-0.5 truncate ' +
          (tone === 'warning'
            ? 'text-amber-700 dark:text-amber-300'
            : tone === 'success'
              ? 'text-emerald-700 dark:text-emerald-300'
              : 'text-foreground')
        }
        title={value}
      >
        {value}
      </div>
    </div>
  );
}

function PaymentBadge({ status }: { status: string }) {
  const cls = PAYMENT_STATUS_STYLE[status] ?? 'bg-muted text-muted-foreground border-border';
  const label = PAYMENT_STATUS_LABEL[status] ?? status;
  return (
    <span className={'inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-medium ' + cls}>
      {label}
    </span>
  );
}
