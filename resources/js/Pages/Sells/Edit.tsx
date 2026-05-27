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
import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { ArrowLeft, CreditCard, FileText, Loader2, Package, Paperclip, Receipt, Save, Settings2, Trash2, Undo2 } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { useAuth, useBusiness } from '@/Hooks/usePageProps';
// ADR 0192 Onda 2 follow-up — editor commission_split mecânico/balcão.
import CommissionSplitEditor, { type CommissionSplitValue } from '@/Pages/Sells/_components/CommissionSplitEditor';
// PR #1657 — bloco Produtos real (paridade Create.tsx).
import ProductSearchAutocomplete, { type ProductSearchResult } from '@/Pages/Sells/_components/ProductSearchAutocomplete';
// PR #1661 — Customer search Cowork (paridade Create.tsx).
import CustomerSearchAutocomplete from '@/Pages/Sells/_components/CustomerSearchAutocomplete';

// PR parking-lot P2 — auto-save draft TTL (24h espelha Create.tsx).
const DRAFT_TTL_MS = 24 * 60 * 60 * 1000;

// Linha de produto editável no form Edit (espelha tipo Create.tsx).
interface EditProductLine {
  product_id: number;
  variation_id: number | null;
  name: string;
  sku: string;
  quantity: number;
  unit_price: number;
  discount: number;
  /** Tipo de desconto por linha — PR parking-lot P1 (toggle R$/%). */
  discount_type: 'fixed' | 'percentage';
  /** IMEI/serial number opcional por linha — PR parking-lot P1 paridade Blade legacy. */
  imei_number?: string;
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
  // PR #1663 — customer payload (backend eager-loaded $transaction->contact).
  customer: {
    id: number;
    name: string;
    mobile: string | null;
    email: string | null;
    /** Soma de outras vendas due deste cliente (R$) — pra alerta inline 'cliente vencido'. */
    dues_total: number;
  } | null;
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
  const { data, setData, processing, errors } = useForm({
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
    // PR parking-lot P1 — features só-no-Blade preservadas.
    /** Endereço cobrança ≠ entrega (Blade legacy `customer_secondary_address`). */
    customer_secondary_address: '',
    /** Nota interna pra equipe (separada de additional_notes). Backend: TransactionUtil staff_note. */
    staff_note: '',
    /** Assinatura recorrente (Blade legacy `is_recurring`). */
    is_recurring: 0 as 0 | 1,
    /** Responsável/comissionado (Blade legacy `commission_agent` select). */
    commission_agent: null as number | null,
    /** Documento anexo (file upload Blade legacy `sell_document`). */
    sell_document: null as File | null,
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
        discount_type: 'fixed' as const,
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
        // PR parking-lot P1 — backend pre-fill quando disponível.
        customer_secondary_address: (tx as unknown as { customer_secondary_address?: string }).customer_secondary_address ?? '',
        staff_note: (tx as unknown as { staff_note?: string }).staff_note ?? '',
        is_recurring: ((tx as unknown as { is_recurring?: 0 | 1 }).is_recurring ?? 0) as 0 | 1,
        commission_agent: (tx as unknown as { commission_agent?: number | null }).commission_agent ?? null,
        sell_document: null,
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
        discount_type: 'fixed',
        imei_number: '',
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

  // PR #1659 — converte EditProductLine[] → formato esperado pelo backend
  // SellPosController@update (TransactionUtil::createOrUpdateSellLines).
  // Keys esperadas: transaction_sell_lines_id (pra UPDATE), product_id, variation_id,
  // quantity, unit_price, line_discount_amount, line_discount_type.
  // PR parking-lot P1 — agora honra discount_type per-line + imei_number.
  const buildProductsPayload = () => {
    return data.products.map((p) => ({
      transaction_sell_lines_id: p.sell_line_id ?? undefined,
      product_id: p.product_id,
      variation_id: p.variation_id,
      quantity: p.quantity,
      unit_price: p.unit_price,
      line_discount_amount: p.discount,
      line_discount_type: p.discount_type ?? 'fixed',
      // PR parking-lot P1 — IMEI/serial opcional (Blade legacy field).
      // Backend não tem coluna dedicada hoje; passamos pra futura wire-up sem quebrar.
      imei_number: p.imei_number ?? '',
    }));
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!permissions.update || processing) return;
    // ⚠️ NUNCA setamos current_stage_id — FSM trait GuardsFsmTransitions ADR 0143 bloqueia.
    // PR #1659 — usar router.put direto pra customizar payload (Inertia useForm.put
    // não suporta transform; useForm.data é tipado e não bate com backend `products`
    // que precisa de transaction_sell_lines_id/line_discount_amount/etc).
    // PR parking-lot P1 — sell_document separado pq File precisa multipart (forceFormData).
    const { sell_document, ...rest } = data;
    const payload: Record<string, unknown> = {
      ...rest,
      products: buildProductsPayload(),
      // Backend espera tax_rate_id; frontend useForm tem tax_id (campo legacy serializer).
      tax_rate_id: data.tax_id,
      // PR parking-lot P1 — checkbox Inscrever-se? envia 0/1 (backend espera is_recurring).
      is_recurring: data.is_recurring ? 1 : 0,
    };
    if (sell_document) {
      // Anexo presente — usa POST + _method=PUT pra multipart (Laravel form spoofing).
      // TransactionUtil::uploadFile($request, 'sell_document', 'documents') aceita esse nome.
      payload.sell_document = sell_document;
      payload._method = 'put';
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      router.post(urls.submit, payload as any, { preserveScroll: true, forceFormData: true });
      return;
    }
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    router.put(urls.submit, payload as any, { preserveScroll: true });
  };

  // Atalho Cmd+Enter pra submeter — agora chama handleSubmit programaticamente
  // (PR parking-lot P3 — antes chamava useForm.put que NÃO incluía products[]).
  useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
        e.preventDefault();
        if (!processing && permissions.update) {
          // Mesma rota do submit button click — products[] + imei + staff_note + etc.
          handleSubmit({ preventDefault: () => {} } as unknown as React.FormEvent);
        }
      }
    };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [processing, permissions.update, data]);

  // PR parking-lot P2 — auto-save draft localStorage (espelha Create.tsx US-SELL-007).
  // STORAGE_KEY com business_id + user_id + sale_id pra isolar:
  //   1. Multi-tenant Tier 0 (ADR 0093) — biz=4 nunca lê draft de biz=1
  //   2. Multi-sale — cada venda em edição tem sua chave própria (não vaza entre IDs)
  // TTL 24h — venda mexida há mais de 1 dia não restaura silenciosa.
  // Backend updated_at vence draft (se backend > draft.savedAt, descarta draft stale).
  const auth = useAuth();
  const business = useBusiness();
  const draftKey = useMemo(() => {
    const bizId = business?.id;
    const userId = auth?.user?.id;
    const saleId = props.saleId;
    if (!bizId || !userId || !saleId) return null;
    return `oimpresso.sells.b${bizId}.u${userId}.edit.${saleId}.draft`;
  }, [auth, business, props.saleId]);

  const [draftRestored, setDraftRestored] = useState(false);
  const recoveredRef = useRef(false);

  // Recover ao montar (apenas 1x). Espera form deferred chegar antes pra comparar
  // updated_at — se backend é mais novo que draft, descarta.
  useEffect(() => {
    if (!draftKey || recoveredRef.current) return;
    if (!props.form?.transaction) return; // aguarda deferred
    recoveredRef.current = true;
    try {
      const raw = localStorage.getItem(draftKey);
      if (!raw) return;
      const parsed = JSON.parse(raw) as { data: typeof data; savedAt: number };
      if (!parsed?.savedAt || Date.now() - parsed.savedAt > DRAFT_TTL_MS) {
        localStorage.removeItem(draftKey);
        return;
      }
      // Backend updated_at vs draft.savedAt — backend > draft = venda mudou no servidor.
      const txAny = props.form.transaction as unknown as { updated_at?: string };
      const backendUpdated = txAny.updated_at ? new Date(txAny.updated_at).getTime() : 0;
      if (backendUpdated > parsed.savedAt) {
        localStorage.removeItem(draftKey);
        return;
      }
      const time = new Date(parsed.savedAt).toLocaleTimeString('pt-BR', {
        hour: '2-digit',
        minute: '2-digit',
      });
      if (confirm(`Recuperar rascunho desta venda salvo às ${time}?`)) {
        // Restaura preservando sell_document=null (não serializável).
        setData({ ...parsed.data, sell_document: null });
        setDraftRestored(true);
      } else {
        localStorage.removeItem(draftKey);
      }
    } catch {
      try {
        localStorage.removeItem(draftKey);
      } catch {
        // ignore
      }
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [draftKey, props.form?.transaction?.id]);

  // Auto-save debounced 500ms quando data mudar (após mount + form chegar).
  useEffect(() => {
    if (!draftKey || !recoveredRef.current) return;
    const t = setTimeout(() => {
      try {
        // Não serializa File (sell_document) — localStorage só aceita string.
        const { sell_document: _f, ...serializable } = data;
        void _f;
        localStorage.setItem(draftKey, JSON.stringify({ data: serializable, savedAt: Date.now() }));
      } catch {
        // localStorage quota / incognito — silencioso (Larissa não precisa saber).
      }
    }, 500);
    return () => clearTimeout(t);
  }, [data, draftKey]);

  // Descartar rascunho manualmente (botão visível no header quando draft restaurado).
  const discardDraft = () => {
    if (!draftKey) return;
    try {
      localStorage.removeItem(draftKey);
    } catch {
      // ignore
    }
    setDraftRestored(false);
    // Recarrega payload backend (descarta mudanças locais).
    router.reload({ only: ['form'] });
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
              {/* PR parking-lot P2 — Descartar rascunho (visível só quando draft restaurado). */}
              {draftRestored && (
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={discardDraft}
                  className="shrink-0"
                  aria-label="Descartar rascunho auto-salvo e recarregar dados originais"
                >
                  <Undo2 className="h-3.5 w-3.5 mr-1.5" />
                  Descartar rascunho
                </Button>
              )}
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
  // PR parking-lot P1 — features só-no-Blade preservadas.
  customer_secondary_address: string;
  staff_note: string;
  is_recurring: 0 | 1;
  commission_agent: number | null;
  sell_document: File | null;
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
          <div className="md:col-span-2">
            <Label htmlFor="contact_id">Cliente</Label>
            <CustomerSearchAutocomplete
              defaultName={form.customer?.name ?? `Cliente #${data.contact_id || '—'}`}
              onSelect={(c) => setData('contact_id', c.id)}
              placeholder="Buscar cliente por nome, CPF/CNPJ ou telefone…"
              disabled={!permissions.update}
            />
            {/* PR 1663 — Cliente vencido alerta inline (paridade Blade legacy) */}
            {form.customer && form.customer.dues_total > 0 && (
              <p className="text-xs text-amber-700 dark:text-amber-300 mt-1 font-medium">
                ⚠ Cliente vencido: {form.customer.dues_total.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}
              </p>
            )}
            <p className="text-xs text-muted-foreground mt-1">
              Digite ≥2 caracteres pra buscar. Cliente atual já vinculado à venda.
            </p>
            {errors.contact_id && (
              <p className="text-xs text-destructive mt-1" role="alert">{errors.contact_id}</p>
            )}
          </div>
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

      {/* Bloco Produtos real · paridade Create.tsx (PR 1657) */}
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
                  // PR parking-lot P1 — desconto per-line agora honra discount_type (R$ vs %).
                  const lineGross = p.quantity * p.unit_price;
                  const lineDiscountValue = p.discount_type === 'percentage'
                    ? (lineGross * p.discount) / 100
                    : p.discount;
                  const subtotal = Math.max(lineGross - lineDiscountValue, 0);
                  return (
                    <tr key={`${idx}-${p.product_id}`} className="border-b border-border last:border-0">
                      <td className="px-3 py-2">
                        <div className="font-medium">{p.name}</div>
                        {p.sku && <div className="text-xs text-muted-foreground">{p.sku}</div>}
                        {/* PR parking-lot P1 — IMEI/serial inline opcional (paridade Blade legacy). */}
                        <Input
                          type="text"
                          value={p.imei_number ?? ''}
                          onChange={(e) => onUpdateProduct(idx, { imei_number: e.target.value })}
                          placeholder="IMEI / nº série (opcional)"
                          className="mt-1 h-7 text-xs"
                          aria-label={`IMEI ou número de série de ${p.name}`}
                          disabled={!permissions.update}
                        />
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
                        {/* PR parking-lot P1 — toggle R$/% + input desconto per-line. */}
                        <div className="flex items-center gap-1 justify-end">
                          <Input
                            type="number"
                            step="0.01"
                            min="0"
                            value={p.discount}
                            onChange={(e) => onUpdateProduct(idx, { discount: parseFloat(e.target.value) || 0 })}
                            className="text-right tabular-nums h-8 w-20"
                            disabled={!permissions.editDiscount}
                            aria-label={`Desconto de ${p.name}`}
                          />
                          <select
                            value={p.discount_type}
                            onChange={(e) => onUpdateProduct(idx, { discount_type: e.target.value as 'fixed' | 'percentage' })}
                            disabled={!permissions.editDiscount}
                            className="border border-input rounded-md px-1 py-1 bg-background text-xs h-8 disabled:opacity-50"
                            aria-label={`Tipo de desconto de ${p.name}: R$ ou %`}
                          >
                            <option value="fixed">R$</option>
                            <option value="percentage">%</option>
                          </select>
                        </div>
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
                      .reduce((acc, p) => {
                        // PR parking-lot P1 — total honra discount_type per-line.
                        const gross = p.quantity * p.unit_price;
                        const disc = p.discount_type === 'percentage'
                          ? (gross * p.discount) / 100
                          : p.discount;
                        return acc + Math.max(gross - disc, 0);
                      }, 0)
                      .toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}
                  </td>
                  <td />
                </tr>
              </tfoot>
            </table>
          </div>
        )}
      </section>

      {/* PR parking-lot P1 — Bloco Responsável + Notas equipe + Inscrever-se + Anexo.
          Sit acima de "Mais opções" porque são features visíveis com frequência (não advanced). */}
      <section className="rounded-lg border border-border bg-card p-5 space-y-4 scroll-mt-32">
        <h2 className="font-semibold text-sm">Responsável, notas e anexos</h2>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {/* PR parking-lot P1 — Responsável select avatar (Blade legacy commission_agent). */}
          <div>
            <Label htmlFor="commission_agent">Responsável / comissionado</Label>
            <select
              id="commission_agent"
              value={data.commission_agent ?? ''}
              onChange={(e) => setData('commission_agent', e.target.value ? Number(e.target.value) : null)}
              disabled={!permissions.update}
              className="mt-1 w-full border border-input rounded-md px-3 py-2 bg-background text-sm disabled:opacity-50"
            >
              <option value="">— Sem responsável —</option>
              {Object.entries((form.users ?? {}) as Record<number, string>).map(([id, name]) => (
                <option key={id} value={id}>{name}</option>
              ))}
            </select>
            <p className="text-xs text-muted-foreground mt-1">
              Usuário responsável pela venda (aparece no recibo + relatório comissão).
            </p>
          </div>

          {/* PR parking-lot P1 — Inscrever-se? checkbox (Blade legacy is_recurring). */}
          <div className="flex items-start gap-2 pt-6">
            <input
              type="checkbox"
              id="is_recurring"
              checked={data.is_recurring === 1}
              onChange={(e) => setData('is_recurring', e.target.checked ? 1 : 0)}
              disabled={!permissions.update}
              className="mt-1 h-4 w-4 rounded border-input"
            />
            <div className="flex-1">
              <Label htmlFor="is_recurring" className="cursor-pointer">
                Assinatura recorrente
              </Label>
              <p className="text-xs text-muted-foreground mt-0.5">
                Marca esta venda como recorrente (gera próxima fatura automática).
              </p>
            </div>
          </div>

          {/* PR parking-lot P1 — Notas equipe (staff_note, separado de additional_notes). */}
          <div className="md:col-span-2">
            <Label htmlFor="staff_note">Nota interna (equipe)</Label>
            <Textarea
              id="staff_note"
              value={data.staff_note}
              onChange={(e) => setData('staff_note', e.target.value)}
              disabled={!permissions.update}
              placeholder="Observação visível só pra equipe — não aparece no recibo."
              className="mt-1"
              rows={2}
            />
          </div>

          {/* PR parking-lot P1 — Anexar documento (Blade legacy sell_document upload). */}
          <div className="md:col-span-2">
            <Label htmlFor="sell_document">
              <Paperclip className="inline h-3.5 w-3.5 mr-1" />
              Anexar documento (opcional)
            </Label>
            <input
              id="sell_document"
              type="file"
              accept=".pdf,.csv,.zip,.doc,.docx,.jpg,.jpeg,.png"
              onChange={(e) => {
                const file = e.target.files?.[0] ?? null;
                if (file && file.size > 5 * 1024 * 1024) {
                  alert('Arquivo maior que 5MB. Tente comprimir antes de enviar.');
                  e.target.value = '';
                  return;
                }
                setData('sell_document', file);
              }}
              disabled={!permissions.update}
              className="mt-1 block w-full text-sm text-muted-foreground file:mr-3 file:py-1.5 file:px-3 file:rounded file:border file:border-input file:bg-background file:text-foreground file:text-xs hover:file:bg-muted disabled:opacity-50"
            />
            <p className="text-xs text-muted-foreground mt-1">
              Aceita .pdf, .csv, .zip, .doc, .docx, .jpg, .png — máx 5MB.
            </p>
            {data.sell_document && (
              <p className="text-xs text-emerald-700 dark:text-emerald-300 mt-1">
                Arquivo selecionado: <span className="font-medium">{data.sell_document.name}</span>
              </p>
            )}
          </div>
        </div>
      </section>

      {/* Bloco Frete (colapsável simples) · ID scroll-target pra pill "Mais opções" */}
      <details id="edit-sec-mais-opcoes" className="rounded-lg border border-border bg-card scroll-mt-32">
        <summary className="cursor-pointer p-5 font-semibold text-sm select-none">
          Frete e endereços
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
                placeholder="Endereço pra onde o produto será entregue."
              />
            </div>
            {/* PR parking-lot P1 — endereço de cobrança ≠ entrega (Blade legacy customer_secondary_address). */}
            <div className="md:col-span-2">
              <Label htmlFor="customer_secondary_address">Endereço de cobrança (se diferente de entrega)</Label>
              <Textarea
                id="customer_secondary_address"
                value={data.customer_secondary_address}
                onChange={(e) => setData('customer_secondary_address', e.target.value)}
                className="mt-1"
                rows={2}
                placeholder="Deixe em branco se cobrança = entrega."
              />
              <p className="text-xs text-muted-foreground mt-1">
                Usado pra NF-e quando cliente solicita faturamento em endereço diferente.
              </p>
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
