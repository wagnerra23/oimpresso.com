// Wave 1 W1-A — MWART /sells/{id}/edit (Editar venda).
// Refs: ADR 0104 (MWART canon), ADR 0149 (pattern reuse Sells/Create),
//       ADR 0143 (FSM safety — NUNCA toca current_stage_id), ADR 0093 (multi-tenant).
//
// Pattern derivado de Sells/Create — mesmo form layout filter-pills sticky.
// Diferenças: pre-fill via form deferred; submit PUT; guards canBeEdited/isReturnExist.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, type ReactNode } from 'react';
import { ArrowLeft, Loader2, Save } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';

interface Headline {
  id: number;
  invoice_no: string;
  type: string;
  status: string;
  current_stage_key: string | null;
}

interface EditFormPayload {
  transaction: {
    id: number;
    invoice_no: string;
    transaction_date: string;
    status: string;
    contact_id: number;
    location_id: number;
    final_total: number;
    discount_type: 'percentage' | 'fixed' | string;
    discount_amount: number;
    tax_id: number | null;
    tax_amount: number;
    shipping_details: string;
    shipping_address: string;
    shipping_charges: number;
    shipping_status: string | null;
    additional_notes: string | null;
    invoice_scheme_id: number | null;
    pay_term_number: number | null;
    pay_term_type: string | null;
  };
  sellDetails: unknown[];
  taxes: Record<number, string>;
  commissionAgents: Record<number, string> | [];
  customerGroups: Record<number, string>;
  posSettings: Record<string, unknown>;
  invoiceSchemes: Record<number, string> | [];
  defaultInvoiceScheme: { id: number; name: string } | null;
  redeemDetails: Record<string, unknown> | [];
  permissions: { editDiscount: boolean; editPrice: boolean };
  shippingStatuses: Record<string, string>;
  warranties: Record<number, string> | [];
  statuses: Record<string, string>;
  salesOrders: Record<number, string> | [];
  paymentTypes: Record<string, string>;
  accounts: Record<number, string> | [];
  paymentLines: unknown[];
  isOrderRequestEnabled: boolean;
  customerDue: string;
  users: Record<number, string> | [];
}

export interface SellsEditPageProps {
  saleId: number;
  headline: Headline;
  form?: EditFormPayload;  // deferred
  permissions: { editPrice: boolean; editDiscount: boolean; update: boolean };
  urls: { submit: string; cancel: string; back: string };
}

function FormSkeleton() {
  return (
    <div className="space-y-4">
      <div className="h-12 bg-muted/40 rounded-lg animate-pulse" />
      <div className="h-32 bg-muted/40 rounded-lg animate-pulse" />
      <div className="h-48 bg-muted/40 rounded-lg animate-pulse" />
      <div className="h-32 bg-muted/40 rounded-lg animate-pulse" />
    </div>
  );
}

export default function SellsEdit(props: SellsEditPageProps) {
  const { headline, urls, permissions } = props;

  // useForm chamado SEMPRE no top-level (regra hooks React). Inicializado vazio
  // e re-populado via useEffect quando form deferred chegar.
  const initialTx = props.form?.transaction;
  const { data, setData, put, processing, errors } = useForm({
    transaction_date: initialTx?.transaction_date ?? '',
    contact_id: initialTx?.contact_id ?? 0,
    location_id: initialTx?.location_id ?? 0,
    status: initialTx?.status ?? 'final',
    discount_type: (initialTx?.discount_type ?? 'percentage') as 'percentage' | 'fixed',
    discount_amount: initialTx?.discount_amount ?? 0,
    tax_id: initialTx?.tax_id ?? null,
    shipping_details: initialTx?.shipping_details ?? '',
    shipping_address: initialTx?.shipping_address ?? '',
    shipping_charges: initialTx?.shipping_charges ?? 0,
    shipping_status: initialTx?.shipping_status ?? '',
    additional_notes: initialTx?.additional_notes ?? '',
    invoice_scheme_id: initialTx?.invoice_scheme_id ?? null,
    pay_term_number: initialTx?.pay_term_number ?? null,
    pay_term_type: initialTx?.pay_term_type ?? 'days',
  });

  // Re-popula form quando deferred form chegar (initial render veio sem dados).
  useEffect(() => {
    if (props.form?.transaction) {
      const tx = props.form.transaction;
      setData({
        transaction_date: tx.transaction_date,
        contact_id: tx.contact_id,
        location_id: tx.location_id,
        status: tx.status,
        discount_type: (tx.discount_type ?? 'percentage') as 'percentage' | 'fixed',
        discount_amount: tx.discount_amount ?? 0,
        tax_id: tx.tax_id ?? null,
        shipping_details: tx.shipping_details ?? '',
        shipping_address: tx.shipping_address ?? '',
        shipping_charges: tx.shipping_charges ?? 0,
        shipping_status: tx.shipping_status ?? '',
        additional_notes: tx.additional_notes ?? '',
        invoice_scheme_id: tx.invoice_scheme_id ?? null,
        pay_term_number: tx.pay_term_number ?? null,
        pay_term_type: tx.pay_term_type ?? 'days',
      });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [props.form?.transaction?.id]);

  // Atalho Cmd+Enter pra submeter
  useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
        e.preventDefault();
        if (!processing && permissions.update) {
          put(urls.submit, { preserveScroll: true });
        }
      }
    };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, [processing, permissions.update, put, urls.submit]);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!permissions.update || processing) return;
    // ⚠️ NUNCA setamos current_stage_id — FSM trait GuardsFsmTransitions ADR 0143 bloqueia.
    put(urls.submit, { preserveScroll: true });
  };

  return (
    <>
      <Head title={`Editar venda #${headline.invoice_no}`} />

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
                Editar venda #{headline.invoice_no}
              </h1>
              <p className="text-sm text-muted-foreground mt-1">
                Status atual: <span className="font-medium">{headline.status}</span>
                {headline.current_stage_key && (
                  <span className="ml-2">
                    · Pipeline: <span className="font-mono text-xs">{headline.current_stage_key}</span>
                  </span>
                )}
              </p>
            </div>
          </div>
        </div>

        {/* Form deferred */}
        <Deferred data="form" fallback={<FormSkeleton />}>
          <EditFormBody
            data={data}
            setData={setData}
            errors={errors}
            processing={processing}
            permissions={permissions}
            urls={urls}
            form={props.form}
            onSubmit={handleSubmit}
          />
        </Deferred>
      </div>
    </>
  );
}

interface EditFormData {
  transaction_date: string;
  contact_id: number;
  location_id: number;
  status: string;
  discount_type: 'percentage' | 'fixed';
  discount_amount: number;
  tax_id: number | null;
  shipping_details: string;
  shipping_address: string;
  shipping_charges: number;
  shipping_status: string | null;
  additional_notes: string | null;
  invoice_scheme_id: number | null;
  pay_term_number: number | null;
  pay_term_type: string;
}

interface EditFormBodyProps {
  data: EditFormData;
  setData: (key: keyof EditFormData, value: EditFormData[keyof EditFormData]) => void;
  errors: Partial<Record<keyof EditFormData, string>>;
  processing: boolean;
  permissions: { editPrice: boolean; editDiscount: boolean; update: boolean };
  urls: { submit: string; cancel: string; back: string };
  form?: EditFormPayload;
  onSubmit: (e: React.FormEvent) => void;
}

function EditFormBody({ data, setData, errors, processing, permissions, urls, form, onSubmit }: EditFormBodyProps) {
  if (!form) {
    return <FormSkeleton />;
  }

  return (
    <form onSubmit={onSubmit} className="space-y-6">
      {/* Bloco Dados básicos */}
      <section className="rounded-lg border border-border bg-card p-5 space-y-4">
        <h2 className="font-semibold text-sm">Dados da venda</h2>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <Label htmlFor="transaction_date">Data da venda</Label>
            <Input
              id="transaction_date"
              value={data.transaction_date}
              onChange={(e) => setData('transaction_date', e.target.value)}
              className="mt-1"
            />
            {errors.transaction_date && (
              <p className="text-xs text-destructive mt-1" role="alert">{errors.transaction_date}</p>
            )}
          </div>
          <div>
            <Label htmlFor="status">Status</Label>
            <select
              id="status"
              value={data.status}
              onChange={(e) => setData('status', e.target.value)}
              className="mt-1 w-full border border-input rounded-md px-3 py-2 bg-background text-sm"
            >
              {Object.entries(form.statuses).map(([k, label]) => (
                <option key={k} value={k}>{label}</option>
              ))}
            </select>
          </div>
        </div>
      </section>

      {/* Bloco Desconto + Notas */}
      <section className="rounded-lg border border-border bg-card p-5 space-y-4">
        <h2 className="font-semibold text-sm">Desconto e observações</h2>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <Label htmlFor="discount_type">Tipo de desconto</Label>
            <select
              id="discount_type"
              value={data.discount_type}
              onChange={(e) => setData('discount_type', e.target.value as 'percentage' | 'fixed')}
              disabled={!permissions.editDiscount}
              className="mt-1 w-full border border-input rounded-md px-3 py-2 bg-background text-sm disabled:opacity-50"
            >
              <option value="percentage">Percentual (%)</option>
              <option value="fixed">Valor fixo (R$)</option>
            </select>
          </div>
          <div>
            <Label htmlFor="discount_amount">Valor desconto</Label>
            <Input
              id="discount_amount"
              type="number"
              step="0.01"
              value={data.discount_amount}
              onChange={(e) => setData('discount_amount', parseFloat(e.target.value) || 0)}
              disabled={!permissions.editDiscount}
              className="mt-1"
            />
          </div>
        </div>
        <div>
          <Label htmlFor="additional_notes">Observações</Label>
          <Textarea
            id="additional_notes"
            value={data.additional_notes ?? ''}
            onChange={(e) => setData('additional_notes', e.target.value)}
            className="mt-1"
            rows={3}
          />
        </div>
      </section>

      {/* Bloco Frete (colapsável simples) */}
      <details className="rounded-lg border border-border bg-card">
        <summary className="cursor-pointer p-5 font-semibold text-sm select-none">
          Mais opções (frete, impostos, condição pagamento)
        </summary>
        <div className="p-5 pt-0 space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <Label htmlFor="shipping_charges">Frete (R$)</Label>
              <Input
                id="shipping_charges"
                type="number"
                step="0.01"
                value={data.shipping_charges}
                onChange={(e) => setData('shipping_charges', parseFloat(e.target.value) || 0)}
                className="mt-1"
              />
            </div>
            <div>
              <Label htmlFor="shipping_status">Status frete</Label>
              <select
                id="shipping_status"
                value={data.shipping_status ?? ''}
                onChange={(e) => setData('shipping_status', e.target.value)}
                className="mt-1 w-full border border-input rounded-md px-3 py-2 bg-background text-sm"
              >
                <option value="">— Selecione —</option>
                {Object.entries(form.shippingStatuses).map(([k, label]) => (
                  <option key={k} value={k}>{label}</option>
                ))}
              </select>
            </div>
            <div className="md:col-span-2">
              <Label htmlFor="shipping_address">Endereço entrega</Label>
              <Textarea
                id="shipping_address"
                value={data.shipping_address}
                onChange={(e) => setData('shipping_address', e.target.value)}
                className="mt-1"
                rows={2}
              />
            </div>
            <div className="md:col-span-2">
              <Label htmlFor="shipping_details">Detalhes frete</Label>
              <Input
                id="shipping_details"
                value={data.shipping_details}
                onChange={(e) => setData('shipping_details', e.target.value)}
                className="mt-1"
              />
            </div>
          </div>
        </div>
      </details>

      {/* Footer sticky */}
      <div className="sticky bottom-0 -mx-6 px-6 py-4 bg-background/80 backdrop-blur border-t border-border flex items-center justify-end gap-3 z-10">
        <Button variant="outline" asChild>
          <Link href={urls.cancel}>Cancelar</Link>
        </Button>
        <Button
          type="submit"
          disabled={!permissions.update || processing}
          className="min-w-[140px]"
        >
          {processing ? (
            <>
              <Loader2 className="h-4 w-4 mr-2 animate-spin" />
              Salvando…
            </>
          ) : (
            <>
              <Save className="h-4 w-4 mr-2" />
              Salvar venda
            </>
          )}
        </Button>
      </div>
    </form>
  );
}

SellsEdit.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
