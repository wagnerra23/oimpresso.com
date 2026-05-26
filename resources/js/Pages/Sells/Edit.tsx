// Wave 1 W1-A — MWART /sells/{id}/edit (Editar venda).
// US-SELL-EDIT-COWORK — Onda Cowork (visual scoped form espelha família .sells-cowork).
// Refs: ADR 0104 (MWART canon), ADR 0149 (pattern reuse Sells/Create),
//       ADR 0143 (FSM safety — NUNCA toca current_stage_id), ADR 0093 (multi-tenant).
//
// Pattern derivado de Sells/Create — mesmo form layout filter-pills sticky.
// Diferenças: pre-fill via form deferred; submit PUT; guards canBeEdited/isReturnExist.
// Visual: classe outer `.sells-cowork-edit` aplica tokens oklch + tipografia IBM Plex
// (resources/css/sells-cowork-edit.css). Sem mudança funcional — só scope visual.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState, type ReactNode } from 'react';
import { ArrowLeft, CreditCard, FileText, Loader2, Package, Receipt, Save, Settings2, Trash2 } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
// ADR 0192 Onda 2 follow-up — editor commission_split mecânico/balcão.
import CommissionSplitEditor, { type CommissionSplitValue } from '@/Pages/Sells/_components/CommissionSplitEditor';
// PR #1657 — bloco Produtos real (paridade Create.tsx).
import ProductSearchAutocomplete, { type ProductSearchResult } from '@/Pages/Sells/_components/ProductSearchAutocomplete';

// Linha de produto editável no form Edit (espelha tipo Create.tsx).
interface EditProductLine {
  product_id: number;
  variation_id: number | null;
  name: string;
  sku: string;
  quantity: number;
  unit_price: number;
  discount: number;
  /** ID da linha existente (sell_lines.id) — pra backend identificar update vs create. */
  sell_line_id?: number | null;
}

// Tipo do row sellDetails que o backend serializer devolve (pré-fill).
interface BackendSellDetail {
  id?: number;
  product_id?: number;
  variation_id?: number | null;
  quantity?: number;
  unit_price_inc_tax?: number;
  unit_price?: number;
  line_discount_amount?: number;
  product?: { name?: string; sku?: string };
  variations?: { sub_sku?: string };
}

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
    // ADR 0192 Onda 2 follow-up — split de comissão mecânico/balcão (JSON nullable).
    commission_split: CommissionSplitValue | null;
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
  urls: { submit: string; cancel: string; back: string; commission_split?: string };
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
    // PR #1657 — linhas de produto editáveis (paridade Create.tsx).
    products: [] as EditProductLine[],
  });

  // Re-popula form quando deferred form chegar (initial render veio sem dados).
  useEffect(() => {
    if (props.form?.transaction) {
      const tx = props.form.transaction;
      // PR #1657 — converte sellDetails do backend pra EditProductLine[].
      const productsFromBackend: EditProductLine[] = (props.form.sellDetails as BackendSellDetail[] | undefined)?.map((sl) => ({
        sell_line_id: sl.id ?? null,
        product_id: sl.product_id ?? 0,
        variation_id: sl.variation_id ?? null,
        name: sl.product?.name ?? '—',
        sku: sl.variations?.sub_sku ?? sl.product?.sku ?? '',
        quantity: Number(sl.quantity ?? 1),
        unit_price: Number(sl.unit_price_inc_tax ?? sl.unit_price ?? 0),
        discount: Number(sl.line_discount_amount ?? 0),
      })) ?? [];
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
        products: productsFromBackend,
      });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [props.form?.transaction?.id]);

  // PR #1657 — handlers paridade Create.tsx pra add/update/remove produtos.
  const handleAddProduct = (p: ProductSearchResult) => {
    setData('products', [
      ...data.products,
      {
        sell_line_id: null,
        product_id: Number(p.product_id),
        variation_id: p.variation_id != null ? Number(p.variation_id) : null,
        name: p.name,
        sku: p.sku ?? p.sub_sku ?? '',
        quantity: 1,
        unit_price: Number(p.selling_price ?? 0),
        discount: 0,
      },
    ]);
  };

  const handleUpdateProduct = (idx: number, patch: Partial<EditProductLine>) => {
    const next = [...data.products];
    next[idx] = { ...next[idx]!, ...patch };
    setData('products', next);
  };

  const handleRemoveProduct = (idx: number) => {
    setData('products', data.products.filter((_, i) => i !== idx));
  };

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

  // KB-9.75 paridade Create — KPIs derivadas do form deferred (PR #1655).
  // Pre-deferred: KPIs mostram placeholders. Post-deferred: valores reais.
  const itensCount = props.form?.sellDetails?.length ?? 0;
  const totalVenda = props.form?.transaction?.final_total ?? 0;
  const totalPago = useMemo(() => {
    if (!props.form?.paymentLines) return 0;
    return (props.form.paymentLines as Array<{ amount?: number }>).reduce(
      (acc, p) => acc + (Number(p.amount) || 0),
      0,
    );
  }, [props.form?.paymentLines]);
  const totalFalta = Math.max(totalVenda - totalPago, 0);
  const pagamentoStatus: 'paid' | 'falta' | 'zero' =
    totalVenda === 0 ? 'zero' : totalFalta === 0 ? 'paid' : 'falta';

  // KB-9.75 paridade Create — filter pills/tabs section scroll. Use IDs `edit-sec-*`
  // pra não colidir com Create `sec-*` quando user navega entre as duas no SPA.
  const [activeSection, setActiveSection] = useState<string>('edit-sec-dados');
  const scrollToSection = (id: string) => {
    const el = document.getElementById(id);
    if (el) {
      el.scrollIntoView({ behavior: 'smooth', block: 'start' });
      setActiveSection(id);
    }
  };

  const formatBRL = (value: number) =>
    value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

  return (
    <>
      <Head title={`Editar venda #${headline.invoice_no}`} />

      <div className="sells-cowork-edit -m-6 bg-muted/30 min-h-[calc(100vh-3rem)] flex flex-col">
        {/* Header sticky no topo + filter pills (KB-9.75 paridade Create pattern) */}
        <div className="sticky top-0 z-30 bg-background/95 backdrop-blur border-b border-border">
          <div className="container mx-auto px-8 pt-6 pb-3 max-w-7xl">
            <div className="flex items-start gap-4">
              <Button variant="ghost" size="icon" asChild aria-label="Voltar">
                <Link href={urls.back}>
                  <ArrowLeft className="h-4 w-4" />
                </Link>
              </Button>
              <div className="flex-1 min-w-0">
                <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                  Editar venda #{headline.invoice_no}
                </h1>
                <p className="text-sm text-muted-foreground mt-1 leading-relaxed">
                  Status atual: <span className="font-medium">{headline.status}</span>
                  {headline.current_stage_key && (
                    <span className="ml-2">
                      · Pipeline: <span className="font-mono text-xs">{headline.current_stage_key}</span>
                    </span>
                  )}
                </p>
              </div>
            </div>

            {/* Filter pills paridade Create — Dados/Produtos/Pagamento/Resumo/Mais opções */}
            <nav className="flex items-center gap-2 mt-4 flex-wrap" aria-label="Seções da edição">
              {[
                { id: 'edit-sec-dados', label: 'Dados', icon: FileText, count: undefined as number | undefined },
                { id: 'edit-sec-produtos', label: 'Produtos', icon: Package, count: itensCount > 0 ? itensCount : undefined },
                { id: 'edit-sec-pagamento', label: 'Pagamento', icon: CreditCard, count: undefined },
                { id: 'edit-sec-resumo', label: 'Resumo', icon: Receipt, count: undefined },
                { id: 'edit-sec-mais-opcoes', label: 'Mais opções', icon: Settings2, count: undefined },
              ].map((tab) => {
                const isActive = activeSection === tab.id;
                const Icon = tab.icon;
                return (
                  <Button
                    key={tab.id}
                    type="button"
                    variant={isActive ? 'default' : 'ghost'}
                    size="sm"
                    onClick={() => scrollToSection(tab.id)}
                    className="rounded-full text-xs"
                    aria-current={isActive ? 'true' : undefined}
                  >
                    <Icon size={13} />
                    {tab.label}
                    {tab.count !== undefined && tab.count > 0 && (
                      <span className={'ml-0.5 inline-flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] tabular-nums ' + (isActive ? 'bg-primary-foreground/20' : 'bg-background border border-border')}>
                        {tab.count}
                      </span>
                    )}
                  </Button>
                );
              })}
            </nav>
          </div>
        </div>

        <div className="container mx-auto py-6 px-8 space-y-6 max-w-7xl flex-1">
          {/* 4 KPI cards GIGANTES — paridade Create pattern KB-9.75 */}
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div className="rounded-xl border border-border bg-background p-6 shadow-sm">
              <div className="text-[11px] font-semibold uppercase tracking-widest text-muted-foreground">Itens</div>
              <div className="text-4xl font-semibold tabular-nums text-foreground mt-3">{itensCount}</div>
            </div>
            <div className="rounded-xl border border-border bg-background p-6 shadow-sm">
              <div className="text-[11px] font-semibold uppercase tracking-widest text-muted-foreground">Total venda</div>
              <div className="text-3xl font-semibold tabular-nums text-foreground mt-3">{formatBRL(totalVenda)}</div>
            </div>
            <div className="rounded-xl border border-border bg-background p-6 shadow-sm">
              <div className="text-[11px] font-semibold uppercase tracking-widest text-muted-foreground">Pago</div>
              <div className="text-3xl font-semibold tabular-nums text-foreground mt-3">{formatBRL(totalPago)}</div>
            </div>
            <div
              className={
                'rounded-xl border p-6 shadow-sm ' +
                (pagamentoStatus === 'zero'
                  ? 'border-border bg-background'
                  : pagamentoStatus === 'falta'
                    ? 'border-amber-500/40 bg-amber-50 dark:bg-amber-950/30'
                    : 'border-emerald-500/40 bg-emerald-50 dark:bg-emerald-950/30')
              }
            >
              <div className="text-[11px] font-semibold uppercase tracking-widest text-muted-foreground">Status pgto</div>
              <div className="text-3xl font-semibold tabular-nums mt-3">
                {pagamentoStatus === 'zero' && <span className="text-muted-foreground">—</span>}
                {pagamentoStatus === 'falta' && <span className="text-amber-700 dark:text-amber-300">{formatBRL(totalFalta)}</span>}
                {pagamentoStatus === 'paid' && <span className="text-emerald-700 dark:text-emerald-300">Pago</span>}
              </div>
              {pagamentoStatus === 'falta' && (
                <p className="text-[11px] text-amber-700 dark:text-amber-300 mt-1">falta receber</p>
              )}
            </div>
          </div>

          {/* Form deferred — sections com IDs `edit-sec-*` pra scroll de pills */}
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
              onAddProduct={handleAddProduct}
              onUpdateProduct={handleUpdateProduct}
              onRemoveProduct={handleRemoveProduct}
            />
          </Deferred>
        </div>
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
  // PR #1657 — linhas produtos editáveis (paridade Create).
  products: EditProductLine[];
}

interface EditFormBodyProps {
  data: EditFormData;
  setData: (key: keyof EditFormData, value: EditFormData[keyof EditFormData]) => void;
  errors: Partial<Record<keyof EditFormData, string>>;
  processing: boolean;
  permissions: { editPrice: boolean; editDiscount: boolean; update: boolean };
  urls: { submit: string; cancel: string; back: string; commission_split?: string };
  form?: EditFormPayload;
  onSubmit: (e: React.FormEvent) => void;
  // PR #1657 — handlers produtos.
  onAddProduct: (p: ProductSearchResult) => void;
  onUpdateProduct: (idx: number, patch: Partial<EditProductLine>) => void;
  onRemoveProduct: (idx: number) => void;
}

function EditFormBody({ data, setData, errors, processing, permissions, urls, form, onSubmit, onAddProduct, onUpdateProduct, onRemoveProduct }: EditFormBodyProps) {
  if (!form) {
    return <FormSkeleton />;
  }

  return (
    <form onSubmit={onSubmit} className="space-y-6">
      {/* Bloco Dados básicos · ID scroll-target pra filter pill "Dados" */}
      <section id="edit-sec-dados" className="rounded-lg border border-border bg-card p-5 space-y-4 scroll-mt-32">
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

      {/* Bloco Desconto + Notas · ID scroll-target pra filter pill "Resumo" */}
      <section id="edit-sec-resumo" className="rounded-lg border border-border bg-card p-5 space-y-4 scroll-mt-32">
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

      {/* Bloco Comissão (ADR 0192 Onda 2 follow-up) · ID scroll-target pra pill "Pagamento" */}
      <div id="edit-sec-pagamento" className="scroll-mt-32">
        {urls.commission_split && form.users && (
          <CommissionSplitEditor
            value={form.transaction.commission_split ?? null}
            users={form.users as Record<number, string>}
            saveUrl={urls.commission_split}
            disabled={!permissions.update}
          />
        )}
      </div>

      {/* Bloco Produtos real · paridade Create.tsx (PR #1657) */}
      <section id="edit-sec-produtos" className="rounded-lg border border-border bg-card p-5 space-y-4 scroll-mt-32">
        <div className="flex items-center gap-2">
          <Package className="h-4 w-4 text-muted-foreground" />
          <h2 className="font-semibold text-sm">Produtos</h2>
          <span className="ml-auto text-xs text-muted-foreground tabular-nums">
            {data.products.length} item(s)
          </span>
        </div>

        <ProductSearchAutocomplete
          locationId={data.location_id || null}
          onSelect={onAddProduct}
          disabled={!permissions.editPrice && !permissions.update}
          placeholder="Buscar produto por nome, SKU ou código de barras…"
        />

        {data.products.length === 0 ? (
          <div className="rounded-md border border-dashed border-border bg-muted/20 p-8 text-center">
            <Package className="inline-block h-6 w-6 text-muted-foreground" />
            <p className="text-sm text-muted-foreground mt-2">Nenhum produto na venda</p>
            <p className="text-xs text-muted-foreground mt-1">Use a busca acima pra adicionar</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="text-xs text-muted-foreground uppercase tracking-wide">
                <tr className="border-b border-border">
                  <th className="text-left px-3 py-2 font-medium">Produto</th>
                  <th className="text-right px-3 py-2 font-medium w-24">Qtd</th>
                  <th className="text-right px-3 py-2 font-medium w-32">Preço unit.</th>
                  <th className="text-right px-3 py-2 font-medium w-28">Desconto</th>
                  <th className="text-right px-3 py-2 font-medium w-32">Subtotal</th>
                  <th className="w-10" />
                </tr>
              </thead>
              <tbody>
                {data.products.map((p, idx) => {
                  const subtotal = Math.max(p.quantity * p.unit_price - p.discount, 0);
                  return (
                    <tr key={`${idx}-${p.product_id}`} className="border-b border-border last:border-0">
                      <td className="px-3 py-2">
                        <div className="font-medium">{p.name}</div>
                        {p.sku && <div className="text-xs text-muted-foreground">{p.sku}</div>}
                      </td>
                      <td className="px-3 py-2 text-right">
                        <Input
                          type="number"
                          step="1"
                          min="0"
                          value={p.quantity}
                          onChange={(e) => onUpdateProduct(idx, { quantity: parseFloat(e.target.value) || 0 })}
                          className="text-right tabular-nums h-8"
                          disabled={!permissions.update}
                        />
                      </td>
                      <td className="px-3 py-2 text-right">
                        <Input
                          type="number"
                          step="0.01"
                          min="0"
                          value={p.unit_price}
                          onChange={(e) => onUpdateProduct(idx, { unit_price: parseFloat(e.target.value) || 0 })}
                          className="text-right tabular-nums h-8"
                          disabled={!permissions.editPrice}
                        />
                      </td>
                      <td className="px-3 py-2 text-right">
                        <Input
                          type="number"
                          step="0.01"
                          min="0"
                          value={p.discount}
                          onChange={(e) => onUpdateProduct(idx, { discount: parseFloat(e.target.value) || 0 })}
                          className="text-right tabular-nums h-8"
                          disabled={!permissions.editDiscount}
                        />
                      </td>
                      <td className="px-3 py-2 text-right tabular-nums font-semibold">
                        {subtotal.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}
                      </td>
                      <td className="px-2 py-2 text-right">
                        <Button
                          type="button"
                          variant="ghost"
                          size="icon"
                          className="h-7 w-7 text-destructive hover:bg-destructive/10"
                          onClick={() => onRemoveProduct(idx)}
                          disabled={!permissions.update}
                          aria-label="Remover produto"
                        >
                          <Trash2 className="h-3.5 w-3.5" />
                        </Button>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
              <tfoot>
                <tr className="border-t-2 border-border">
                  <td colSpan={4} className="px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                    Total produtos
                  </td>
                  <td className="px-3 py-2 text-right tabular-nums font-bold text-base">
                    {data.products
                      .reduce((acc, p) => acc + Math.max(p.quantity * p.unit_price - p.discount, 0), 0)
                      .toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}
                  </td>
                  <td />
                </tr>
              </tfoot>
            </table>
          </div>
        )}
      </section>

      {/* Bloco Frete (colapsável simples) · ID scroll-target pra pill "Mais opções" */}
      <details id="edit-sec-mais-opcoes" className="rounded-lg border border-border bg-card scroll-mt-32">
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
