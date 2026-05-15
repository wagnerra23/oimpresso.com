// US-SELL-EDIT-001 — Editar venda Inertia/React (MWART F3 FRONTEND INCREMENTAL).
//
// Reusa pattern de Sells/Create.tsx mas pra UPDATE:
//   - Pre-fill props.transaction (com items + payments + contact + location)
//   - 5 seções colapsáveis (Dados / Produtos / Pagamento / Resumo / Mais opções)
//   - Atalhos Cmd/Ctrl+S salva · Esc blur input
//   - Submit via Inertia router.put('/sells/{id}') → SellPosController@update
//   - Cancelar venda (status='final/draft' → 'cancelled') via botão destacado
//   - DUAL-MODE: backend Blade legacy preservado em sell/edit.blade.php (canary biz=164)
//
// Persona: Lara (estoque · filha Martinho) + Dani (financeiro · DANIELLI id=297)
//          biz=164 prod canary semana 19/maio. Monitor 1280px.
//
// Refs:
//   - ADR 0104 (MWART canônico §F3 FRONTEND INCREMENTAL)
//   - ADR 0093 (Multi-tenant Tier 0 IRREVOGÁVEL)
//   - ADR 0110 (Cockpit Pattern V2)
//   - Pages/Sells/Create.tsx (pattern form Sells canônico)
//   - Pages/Crm/Contacts/Edit.tsx (pattern Edit reusa Create — mode='edit')
//   - resources/views/sell/edit.blade.php (Blade legacy preservado)
//   - memory/requisitos/Sells/RUNBOOK-edit.md

import AppShellV2 from '@/Layouts/AppShellV2';
import { router, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { useAuth, useBusiness } from '@/Hooks/usePageProps';
import { Ban, CreditCard, FileText, Loader2, Package, Plus, Printer, Receipt, Settings2, Trash2 } from 'lucide-react';
import EmptyState from '@/Components/shared/EmptyState';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import ProductSearchAutocomplete, {
  type ProductSearchResult,
} from './_components/ProductSearchAutocomplete';
import CustomerSearchAutocomplete from './_components/CustomerSearchAutocomplete';
import PaymentRow, { type Payment } from './_components/PaymentRow';
import { dropdownEntries } from './_components/dropdownEntries';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';

interface OptionMap {
  [id: number]: string;
}

interface Contact {
  id: number;
  name: string;
}

interface TransactionForEdit {
  id: number;
  business_id: number;
  location_id: number;
  contact_id: number;
  invoice_no: string;
  transaction_date: string;
  status: 'final' | 'draft' | 'quotation' | 'cancelled' | 'proforma' | string;
  sub_status: string | null;
  type: 'sell' | 'sales_order' | string;
  discount_type: 'fixed' | 'percentage';
  discount_amount: number;
  tax_id: number | null;
  pay_term_number: number | null;
  pay_term_type: 'days' | 'months' | null;
  commission_agent: number | null;
  selling_price_group_id: number | null;
  invoice_scheme_id: number | null;
  additional_notes: string | null;
  shipping_details: string | null;
  shipping_address: string | null;
  shipping_charges: number;
  shipping_status: string | null;
  delivered_to: string | null;
  delivery_person: number | null;
  contact: Contact;
  final_total: number;
  total_paid: number;
  // Auditoria — quando schema disponibilizar
  updated_at?: string | null;
  updated_by?: number | null;
}

export interface SellEditLine {
  // Espelha colunas selecionadas em SellController@edit::sell_details query (linha 1735+).
  id: number;
  transaction_sell_lines_id: number;
  product_id: number;
  variation_id: number;
  product_name: string;
  product_actual_name: string;
  sub_sku: string;
  quantity_ordered: number;
  default_sell_price: number;
  sell_price_inc_tax: number;
  line_discount_type: 'fixed' | 'percentage';
  line_discount_amount: number;
  item_tax: number;
  tax_id: number | null;
  unit: string | null;
  sell_line_note: string | null;
  enable_stock: number;
  product_type: string;
  qty_available?: number;
}

export interface SellEditPaymentLine {
  // Espelha TransactionUtil::getPaymentDetails — paid_on já formatado, amount float.
  id?: number;
  amount: number;
  method: string;
  paid_on: string;
  account_id: number | null;
  note: string | null;
  is_return: number;
  // Campos extras condicionais por método
  card_number?: string;
  card_holder_name?: string;
  card_transaction_number?: string;
  cheque_number?: string;
  bank_account_number?: string;
  transaction_no?: string;
}

export interface SellsEditPageProps {
  transaction: TransactionForEdit;
  sellDetails: SellEditLine[];
  paymentLines: SellEditPaymentLine[];
  // Dropdowns / contexto
  paymentTypes: Record<string, string>;
  invoiceSchemes: OptionMap;
  taxes: Record<number, string>;
  priceGroups: OptionMap;
  shippingStatuses: Record<string, string>;
  commissionAgents: OptionMap;
  customerGroups: OptionMap;
  accounts: OptionMap;
  statuses: Record<string, string>;
  users: OptionMap | [];
  walkInCustomer: Contact;
  // Permissões
  permissions: {
    editDiscount: boolean;
    editPrice: boolean;
    canCancel: boolean;
    maxDiscount?: number | null;
  };
  posSettings: Record<string, unknown>;
  customerDue?: string;
  isOrderRequestEnabled?: boolean;
}

// US-SELL-EDIT-010 — campos dentro do <details> "Mais opções". Se erro cair em algum
// destes, o details abre automaticamente (gap UX detectado pelo design-arte agent
// 2026-05-13 — mesmo pattern de Create.tsx).
const COLLAPSED_FIELD_KEYS = [
  'invoice_scheme_id',
  'invoice_no',
  'pay_term_number',
  'pay_term_type',
  'price_group_id',
  'commission_agent_id',
  'tax_rate_id',
  'shipping_details',
  'shipping_address',
  'shipping_charges',
  'shipping_status',
  'delivered_to',
];

const ADVANCED_OPEN_KEY = 'oimpresso.sells.edit.advanced.open';

// FieldError — exibe erro de validação por campo. role="alert" pra screen reader.
function FieldError({ message }: { message?: string }) {
  if (!message) return null;
  return (
    <p className="text-xs text-destructive mt-1" role="alert">
      {message}
    </p>
  );
}

// Converte "DD/MM/YYYY HH:mm" → "YYYY-MM-DDTHH:mm" (datetime-local input).
function toDatetimeLocal(val: string): string {
  if (!val) return '';
  const m = val.match(/^(\d{2})\/(\d{2})\/(\d{4})\s+(\d{2}):(\d{2})/);
  if (!m) return '';
  return `${m[3]}-${m[2]}-${m[1]}T${m[4]}:${m[5]}`;
}

// Converte "YYYY-MM-DDTHH:mm" → "DD/MM/YYYY HH:mm" (formato backend).
function fromDatetimeLocal(val: string): string {
  const m = val.match(/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/);
  if (!m) return val;
  return `${m[3]}/${m[2]}/${m[1]} ${m[4]}:${m[5]}`;
}

export default function SellsEdit(props: SellsEditPageProps) {
  const { transaction, sellDetails, paymentLines } = props;

  // Pre-fill values existentes. Casts conservadores (decimal → number).
  const { data, setData, put, processing, errors, transform } = useForm({
    location_id: transaction.location_id,
    contact_id: transaction.contact_id,
    transaction_date: transaction.transaction_date,
    status: transaction.status as 'final' | 'quotation' | 'draft' | 'proforma',
    invoice_no: transaction.invoice_no ?? '',
    invoice_scheme_id: transaction.invoice_scheme_id ?? null,
    pay_term_number: (transaction.pay_term_number ?? '') as string | number,
    pay_term_type: (transaction.pay_term_type ?? 'days') as 'days' | 'months',
    price_group_id: transaction.selling_price_group_id ?? null,
    commission_agent_id: transaction.commission_agent ?? null,
    tax_rate_id: transaction.tax_id ?? null,
    products: sellDetails.map((line) => ({
      transaction_sell_lines_id: line.transaction_sell_lines_id,
      product_id: line.product_id,
      variation_id: line.variation_id,
      name: line.product_name,
      sku: line.sub_sku,
      quantity: Number(line.quantity_ordered),
      unit_price: Number(line.default_sell_price),
      discount: Number(line.line_discount_amount ?? 0),
    })),
    payments: (paymentLines.length > 0 ? paymentLines : [{
      amount: 0,
      method: 'cash',
      paid_on: '',
      account_id: null,
      note: '',
      is_return: 0,
    }]).filter((p) => p.is_return !== 1).map((p) => ({
      payment_id: p.id ?? null,
      amount: Number(p.amount ?? 0),
      method: p.method ?? 'cash',
      paid_on: p.paid_on ?? '',
      account_id: p.account_id ?? null,
      note: p.note ?? '',
    })) as Array<Payment & { payment_id?: number | null }>,
    discount_type: (transaction.discount_type ?? 'percentage') as 'percentage' | 'fixed',
    discount_amount: Number(transaction.discount_amount ?? 0),
    notes: transaction.additional_notes ?? '',
    shipping: {
      details: transaction.shipping_details ?? '',
      address: transaction.shipping_address ?? '',
      cost: Number(transaction.shipping_charges ?? 0),
      status: transaction.shipping_status ?? '',
      deliver_to: transaction.delivered_to ?? '',
    },
  });

  // Persistir <details> open state em localStorage (mesma pattern Create).
  const [advancedOpen, setAdvancedOpen] = useState<boolean>(false);
  useEffect(() => {
    try {
      const stored = localStorage.getItem(ADVANCED_OPEN_KEY);
      if (stored === 'true') setAdvancedOpen(true);
    } catch {
      // localStorage indisponível (incognito raro); fica fechado
    }
  }, []);
  const handleAdvancedToggle = (e: React.SyntheticEvent<HTMLDetailsElement>) => {
    const open = e.currentTarget.open;
    setAdvancedOpen(open);
    try {
      localStorage.setItem(ADVANCED_OPEN_KEY, String(open));
    } catch {
      // ignore
    }
  };

  const hasMultiplePriceGroups = Object.keys(props.priceGroups).length > 1;
  const hasCommissionAgent = Object.keys(props.commissionAgents).length > 0;

  // Cálculos
  const productSearchRef = useRef<HTMLDivElement>(null);
  const subtotalProdutos = useMemo(
    () =>
      data.products.reduce((acc, p) => {
        const lineSubtotal = p.quantity * p.unit_price - p.discount;
        return acc + Math.max(lineSubtotal, 0);
      }, 0),
    [data.products],
  );

  const descontoPedido = useMemo(() => {
    if (data.discount_type === 'percentage') {
      return (subtotalProdutos * data.discount_amount) / 100;
    }
    return data.discount_amount;
  }, [subtotalProdutos, data.discount_amount, data.discount_type]);

  // max_discount: null/undefined = sem limite (preserva Blade legacy).
  const [discountError, setDiscountError] = useState<string | null>(null);
  const maxDiscount = props.permissions.maxDiscount != null ? Number(props.permissions.maxDiscount) : null;

  const totalGeral = useMemo(
    () => Math.max(subtotalProdutos - descontoPedido + data.shipping.cost, 0),
    [subtotalProdutos, descontoPedido, data.shipping.cost],
  );

  const formatBRL = (value: number) =>
    value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

  const validateDiscount = (type: string, amount: number) => {
    if (maxDiscount == null || maxDiscount <= 0) { setDiscountError(null); return; }
    if (type === 'percentage' && amount > maxDiscount) {
      setDiscountError(`Desconto máximo permitido: ${maxDiscount}%`);
    } else if (type === 'fixed') {
      const maxFixed = (subtotalProdutos * maxDiscount) / 100;
      setDiscountError(amount > maxFixed ? `Desconto máximo: ${formatBRL(maxFixed)}` : null);
    } else {
      setDiscountError(null);
    }
  };

  const handleAddProduct = (p: ProductSearchResult) => {
    setData('products', [
      ...data.products,
      {
        transaction_sell_lines_id: 0, // novo item, sem id existente
        product_id: p.product_id,
        variation_id: p.variation_id ?? 0,
        name: p.name,
        sku: p.sku,
        quantity: 1,
        unit_price: Number(p.selling_price ?? 0),
        discount: 0,
      },
    ]);
  };

  const handleProductChange = (
    idx: number,
    field: 'quantity' | 'unit_price' | 'discount',
    value: number,
  ) => {
    const next = data.products.map((p, i) => (i === idx ? { ...p, [field]: value } : p));
    setData('products', next);
  };

  const handleRemoveProduct = (idx: number) => {
    setData('products', data.products.filter((_, i) => i !== idx));
  };

  // Pagamentos
  const totalPago = useMemo(
    () => data.payments.reduce((acc, p) => acc + (Number(p.amount) || 0), 0),
    [data.payments],
  );
  const saldoPagamento = totalPago - totalGeral;
  const pagamentoStatus =
    Math.abs(saldoPagamento) < 0.01
      ? 'exato'
      : saldoPagamento < 0
        ? 'falta'
        : 'troco';

  const handlePaymentChange = (
    idx: number,
    field: keyof Payment,
    value: string | number | null,
  ) => {
    const next = [...data.payments];
    next[idx] = { ...next[idx], [field]: value } as typeof next[number];
    setData('payments', next);
  };

  const handleAddPayment = () => {
    setData('payments', [
      ...data.payments,
      {
        payment_id: null,
        amount: Math.max(-saldoPagamento, 0),
        method: 'cash',
        paid_on: '',
        account_id: null,
        note: '',
      },
    ]);
  };

  const handleRemovePayment = (idx: number) => {
    setData('payments', data.payments.filter((_, i) => i !== idx));
  };

  const focusProductSearch = () => {
    const input = productSearchRef.current?.querySelector<HTMLInputElement>(
      'input[type="search"]',
    );
    input?.focus();
  };

  const itensCount = data.products.reduce((acc, p) => acc + (Number(p.quantity) || 0), 0);

  // Submit handler — PUT /pos/{id} (Route::resource pos linha 303 → SellPosController@update).
  // Mesma rota do Blade legacy form em sell/edit.blade.php:28
  // (`action(SellPosController::class, 'update')`). PUT /sells/{id} mapeia pra SellController@update
  // que não existe — todas vendas (direct sell + POS) compartilham SellPosController@update.
  const canSubmit =
    !processing &&
    data.products.length > 0 &&
    data.location_id !== null &&
    Math.abs(totalPago - totalGeral) < 0.01 &&
    !discountError;

  const handleSubmit = (withPrint = false) => {
    if (!canSubmit) return;
    transform((d) => ({
      ...d,
      is_direct_sale: 1,
      is_save_and_print: withPrint ? 1 : 0,
      payment: d.payments,
      commission_agent: d.commission_agent_id,
      price_group: d.price_group_id,
      sale_note: d.notes,
      additional_notes: d.notes,
      // Flatten shipping object pra campos top-level (Blade legacy convention)
      shipping_details: d.shipping.details,
      shipping_address: d.shipping.address,
      shipping_charges: d.shipping.cost,
      shipping_status: d.shipping.status,
      delivered_to: d.shipping.deliver_to,
      // Total calculado client-side (backend re-calcula via productUtil mas evita 422)
      final_total: totalGeral,
      // Products — preserva transaction_sell_lines_id pra UPDATE (vs INSERT pra novos)
      products: d.products.map((p) => ({
        ...p,
        unit_price_inc_tax: p.unit_price,
        item_tax: 0,
        tax_id: null,
        line_discount_type: 'fixed',
        line_discount_amount: p.discount,
        enable_stock: 1,
        product_type: 'single',
        // transaction_sell_lines_id > 0 → UPDATE; === 0 → INSERT (TransactionUtil convenção).
      })),
    }));
    put(`/pos/${transaction.id}`, {
      preserveScroll: true,
      onError: (errs) => {
        const firstErrorKey = Object.keys(errs)[0];
        if (firstErrorKey) {
          const sectionMap: Record<string, string> = {
            location_id: 'sec-dados',
            contact_id: 'sec-dados',
            transaction_date: 'sec-dados',
            products: 'sec-produtos',
            payments: 'sec-pagamento',
            invoice_no: 'sec-mais-opcoes',
          };
          const section = sectionMap[firstErrorKey] ?? 'sec-dados';
          document.getElementById(section)?.scrollIntoView({ behavior: 'smooth' });
        }
      },
    });
  };

  // Cmd/Ctrl+S salva venda (Edit canon — diferente Create que usa Cmd+Enter).
  // Lara/Dani esperam Ctrl+S do mundo Word/Excel.
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && (e.key === 's' || e.key === 'S')) {
        e.preventDefault();
        if (canSubmit) handleSubmit();
      }
      // Cmd+Enter também salva (consistência com Create.tsx)
      if ((e.metaKey || e.ctrlKey) && e.key === 'Enter' && canSubmit) {
        e.preventDefault();
        handleSubmit();
      }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [canSubmit]);

  // Esc top-level: blur active element (consistência Create.tsx).
  useEffect(() => {
    const onEsc = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        const active = document.activeElement as HTMLElement | null;
        if (active && typeof active.blur === 'function' && active !== document.body) {
          active.blur();
        }
      }
    };
    window.addEventListener('keydown', onEsc);
    return () => window.removeEventListener('keydown', onEsc);
  }, []);

  // Auto-open <details> "Mais opções" quando erro está em campo colapsado.
  useEffect(() => {
    const errKeys = Object.keys(errors);
    if (errKeys.length === 0) return;
    const hasCollapsedError = errKeys.some((k) => COLLAPSED_FIELD_KEYS.includes(k));
    if (hasCollapsedError && !advancedOpen) {
      setAdvancedOpen(true);
      try {
        localStorage.setItem(ADVANCED_OPEN_KEY, 'true');
      } catch {
        // ignore
      }
    }
  }, [errors, advancedOpen]);

  // Cancelar venda — muda status pra 'cancelled' via PUT.
  // FsmAuthorizationFlag canônico (ADR 0143) — backend permite via stage action 'cancelar_venda'.
  // UI canônica: button destrutivo com confirm dialog.
  const auth = useAuth();
  const business = useBusiness();
  const [showCancelConfirm, setShowCancelConfirm] = useState(false);
  const handleCancelSale = () => {
    if (!props.permissions.canCancel) return;
    // Cast pra FormData-like — useForm.data tem shipping object nested.
    // Inertia v3 router.put runtime aceita objeto plain JSON, mas TS RequestPayload é strict.
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const payload = {
      ...data,
      status: 'cancelled',
      cancel_reason: 'Cancelado via tela de edição',
    } as any;
    router.put(`/pos/${transaction.id}`, payload, {
      preserveScroll: true,
      onSuccess: () => {
        setShowCancelConfirm(false);
        router.visit('/sells');
      },
    });
  };

  // Smooth scroll pra seção quando user clica numa aba.
  const scrollToSection = (id: string) => {
    document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  };

  // Scroll-spy: detecta seção visível (pattern Create.tsx).
  const sectionIds = ['sec-dados', 'sec-produtos', 'sec-pagamento', 'sec-resumo', 'sec-mais-opcoes'];
  const [activeSection, setActiveSection] = useState<string>('sec-dados');
  useEffect(() => {
    const observer = new IntersectionObserver(
      (entries) => {
        const visible = entries
          .filter((e) => e.isIntersecting)
          .map((e) => e.target.id);
        if (visible.length > 0) {
          const first = sectionIds.find((id) => visible.includes(id));
          if (first) setActiveSection(first);
        }
      },
      { rootMargin: '-128px 0px -50% 0px', threshold: 0 },
    );
    sectionIds.forEach((id) => {
      const el = document.getElementById(id);
      if (el) observer.observe(el);
    });
    return () => observer.disconnect();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Avoid unused-var warnings on auth/business when not currently rendered.
  const _user = auth?.user?.id;
  const _biz = business?.id;
  void _user;
  void _biz;

  const isCancelled = transaction.status === 'cancelled';

  return (
    <div className="-m-6 bg-muted/30 min-h-[calc(100vh-3rem)] flex flex-col">
      {/* Header sticky + abas seção (pattern Cockpit V2 canon — espelha Create.tsx) */}
      <div className="sticky top-0 z-30 bg-background/95 backdrop-blur border-b border-border">
        <div className="container mx-auto px-8 pt-6 pb-3 max-w-7xl">
          <div className="flex items-start gap-4">
            <div className="flex-1 min-w-0">
              <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                Editar venda{' '}
                <span className="text-muted-foreground font-normal">
                  #{transaction.invoice_no}
                </span>
              </h1>
              <p className="text-sm text-muted-foreground mt-1 leading-relaxed">
                Atualize cliente, produtos, pagamentos ou frete. Mudanças ficam registradas
                em auditoria.
                {transaction.contact?.name && (
                  <>
                    {' '}Cliente:{' '}
                    <span className="font-medium text-foreground">
                      {transaction.contact.name}
                    </span>
                  </>
                )}
              </p>
              {isCancelled && (
                <div className="mt-3 inline-flex items-center gap-2 rounded-full bg-rose-50 dark:bg-rose-950/40 px-3 py-1 text-xs font-medium text-rose-700 dark:text-rose-300">
                  <Ban className="h-3.5 w-3.5" />
                  Venda cancelada — somente leitura
                </div>
              )}
            </div>
          </div>

          {/* Filter pills — pattern Cockpit V2 canon */}
          <nav
            className="flex items-center gap-2 mt-4 flex-wrap"
            aria-label="Seções da edição"
          >
            {[
              { id: 'sec-dados', label: 'Dados', icon: FileText, count: undefined as number | undefined },
              { id: 'sec-produtos', label: 'Produtos', icon: Package, count: itensCount > 0 ? itensCount : undefined },
              { id: 'sec-pagamento', label: 'Pagamento', icon: CreditCard, count: undefined },
              { id: 'sec-resumo', label: 'Resumo', icon: Receipt, count: undefined },
              { id: 'sec-mais-opcoes', label: 'Mais opções', icon: Settings2, count: undefined },
            ].map((tab) => {
              const isActive = activeSection === tab.id;
              const Icon = tab.icon;
              return (
                <button
                  key={tab.id}
                  type="button"
                  onClick={() => scrollToSection(tab.id)}
                  className={
                    'inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs font-medium transition-colors ' +
                    (isActive
                      ? 'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300'
                      : 'bg-muted/40 text-muted-foreground hover:bg-muted hover:text-foreground')
                  }
                  aria-current={isActive ? 'true' : undefined}
                >
                  <Icon size={13} />
                  {tab.label}
                  {tab.count !== undefined && tab.count > 0 && (
                    <span
                      className={
                        'ml-0.5 inline-flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] tabular-nums ' +
                        (isActive ? 'bg-blue-100 dark:bg-blue-900/60' : 'bg-background')
                      }
                    >
                      {tab.count}
                    </span>
                  )}
                </button>
              );
            })}
          </nav>
        </div>
      </div>

      <div className="container mx-auto py-6 px-8 space-y-6 max-w-7xl flex-1">

      {/* KPI cards — 4 cards grandes (pattern Create.tsx) */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div className="rounded-xl border border-border bg-background p-6 shadow-sm">
          <div className="text-[11px] font-semibold uppercase tracking-widest text-muted-foreground">
            Itens
          </div>
          <div className="text-4xl font-semibold tabular-nums text-foreground mt-3">
            {itensCount}
          </div>
        </div>
        <div className="rounded-xl border border-border bg-background p-6 shadow-sm">
          <div className="text-[11px] font-semibold uppercase tracking-widest text-muted-foreground">
            Total venda
          </div>
          <div className="text-3xl font-semibold tabular-nums text-foreground mt-3">
            {formatBRL(totalGeral)}
          </div>
        </div>
        <div className="rounded-xl border border-border bg-background p-6 shadow-sm">
          <div className="text-[11px] font-semibold uppercase tracking-widest text-muted-foreground">
            Pago
          </div>
          <div className="text-3xl font-semibold tabular-nums text-foreground mt-3">
            {formatBRL(totalPago)}
          </div>
        </div>
        <div
          className={
            'rounded-xl border p-6 shadow-sm ' +
            (totalGeral === 0
              ? 'border-border bg-background'
              : pagamentoStatus === 'falta'
                ? 'border-amber-500/40 bg-amber-50 dark:bg-amber-950/30'
                : pagamentoStatus === 'troco'
                  ? 'border-blue-500/40 bg-blue-50 dark:bg-blue-950/30'
                  : 'border-emerald-500/40 bg-emerald-50 dark:bg-emerald-950/30')
          }
        >
          <div className="text-[11px] font-semibold uppercase tracking-widest text-muted-foreground">
            Status pgto
          </div>
          <div
            className={
              'text-2xl font-semibold tabular-nums mt-3 ' +
              (totalGeral === 0
                ? 'text-foreground/70'
                : pagamentoStatus === 'falta'
                  ? 'text-amber-700 dark:text-amber-300'
                  : pagamentoStatus === 'troco'
                    ? 'text-blue-700 dark:text-blue-300'
                    : 'text-emerald-700 dark:text-emerald-300')
            }
          >
            {totalGeral === 0
              ? 'Aguardando'
              : pagamentoStatus === 'falta'
                ? `Falta ${formatBRL(Math.abs(saldoPagamento))}`
                : pagamentoStatus === 'troco'
                  ? `Troco ${formatBRL(saldoPagamento)}`
                  : 'Confere'}
          </div>
        </div>
      </div>

      {/* Conteúdo das seções */}
      <div className="space-y-6">

      {/* Linha 1: Dados da venda */}
      <Card id="sec-dados" className="shadow-sm bg-background border-border scroll-mt-32">
        <CardHeader>
          <CardTitle className="text-base">Dados da venda</CardTitle>
        </CardHeader>
        <CardContent className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <div className="space-y-1.5">
            <Label htmlFor="contact_id">Cliente</Label>
            <CustomerSearchAutocomplete
              defaultName={transaction.contact?.name ?? props.walkInCustomer.name}
              onSelect={(c) => setData('contact_id', c.id)}
              onClear={() => setData('contact_id', props.walkInCustomer.id)}
              forcedValue={{ id: transaction.contact_id, text: transaction.contact?.name ?? '' }}
            />
            <FieldError message={errors.contact_id as string | undefined} />
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="transaction_date">Data da venda</Label>
            <Input
              id="transaction_date"
              type="datetime-local"
              value={toDatetimeLocal(data.transaction_date)}
              onChange={(e) => setData('transaction_date', fromDatetimeLocal(e.target.value))}
              disabled={isCancelled}
            />
            <FieldError message={errors.transaction_date as string | undefined} />
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="status">Status</Label>
            <Select
              value={data.status}
              onValueChange={(v) => setData('status', v as typeof data.status)}
              disabled={isCancelled}
            >
              <SelectTrigger id="status">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {dropdownEntries(props.statuses).map(([k, label]) => (
                  <SelectItem key={k} value={k}>
                    {label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="invoice_no">Nº da fatura</Label>
            <Input
              id="invoice_no"
              value={data.invoice_no}
              onChange={(e) => setData('invoice_no', e.target.value)}
              disabled={isCancelled}
            />
            <FieldError message={errors.invoice_no as string | undefined} />
          </div>
        </CardContent>
      </Card>

      {/* Produtos */}
      <Card id="sec-produtos" className="shadow-sm bg-background border-border scroll-mt-32">
        <CardHeader>
          <CardTitle className="text-base">Produtos</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div ref={productSearchRef}>
            <ProductSearchAutocomplete
              locationId={data.location_id}
              onSelect={handleAddProduct}
            />
          </div>

          {data.products.length === 0 ? (
            <EmptyState
              icon="package"
              title="Nenhum produto"
              description="Use a busca acima ou aperte / pra focar."
              action={
                <Button variant="outline" size="sm" onClick={focusProductSearch}>
                  Buscar produto
                </Button>
              }
            />
          ) : (
            <div className="overflow-x-auto rounded-md border border-border">
              <table className="w-full text-sm">
                <thead className="bg-muted/50">
                  <tr className="text-left text-xs font-medium text-muted-foreground">
                    <th className="px-3 py-2">Produto</th>
                    <th className="px-3 py-2 w-24">Qtd.</th>
                    <th className="px-3 py-2 w-32">Preço unit.</th>
                    <th className="px-3 py-2 w-32">Desconto</th>
                    <th className="px-3 py-2 w-32 text-right">Subtotal</th>
                    <th className="px-3 py-2 w-12"></th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-border">
                  {data.products.map((p, idx) => {
                    const lineSubtotal = Math.max(
                      p.quantity * p.unit_price - p.discount,
                      0,
                    );
                    return (
                      <tr key={`${p.product_id}-${p.variation_id}-${idx}`}>
                        <td className="px-3 py-2 align-top">
                          <div className="font-medium text-foreground">{p.name}</div>
                          <div className="text-xs text-muted-foreground">SKU {p.sku}</div>
                        </td>
                        <td className="px-3 py-2">
                          <Input
                            type="number"
                            inputMode="decimal"
                            min="0"
                            step="1"
                            value={p.quantity}
                            onChange={(e) =>
                              handleProductChange(idx, 'quantity', Number(e.target.value))
                            }
                            aria-label={`Quantidade de ${p.name}`}
                            className="h-8"
                            disabled={isCancelled}
                          />
                        </td>
                        <td className="px-3 py-2">
                          <Input
                            type="number"
                            inputMode="decimal"
                            min="0"
                            step="0.01"
                            value={p.unit_price}
                            onChange={(e) =>
                              handleProductChange(
                                idx,
                                'unit_price',
                                Number(e.target.value),
                              )
                            }
                            disabled={!props.permissions.editPrice || isCancelled}
                            aria-label={`Preço unitário de ${p.name}`}
                            className="h-8 tabular-nums"
                          />
                        </td>
                        <td className="px-3 py-2">
                          <Input
                            type="number"
                            inputMode="decimal"
                            min="0"
                            step="0.01"
                            value={p.discount}
                            onChange={(e) =>
                              handleProductChange(idx, 'discount', Number(e.target.value))
                            }
                            disabled={!props.permissions.editDiscount || isCancelled}
                            aria-label={`Desconto em ${p.name}`}
                            className="h-8 tabular-nums"
                          />
                        </td>
                        <td className="px-3 py-2 text-right tabular-nums font-medium text-foreground align-middle">
                          {formatBRL(lineSubtotal)}
                        </td>
                        <td className="px-3 py-2 text-right align-middle">
                          <button
                            type="button"
                            onClick={() => handleRemoveProduct(idx)}
                            aria-label={`Remover ${p.name}`}
                            className="text-muted-foreground hover:text-destructive p-1"
                            disabled={isCancelled}
                          >
                            <Trash2 className="h-4 w-4" />
                          </button>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
                <tfoot className="bg-muted/30 border-t border-border">
                  <tr className="text-sm">
                    <td colSpan={4} className="px-3 py-2 text-right font-medium text-foreground">
                      Subtotal
                    </td>
                    <td className="px-3 py-2 text-right tabular-nums font-semibold text-foreground">
                      {formatBRL(subtotalProdutos)}
                    </td>
                    <td></td>
                  </tr>
                </tfoot>
              </table>
            </div>
          )}
        </CardContent>
      </Card>

      {/* Pagamentos */}
      <Card id="sec-pagamento" className="shadow-sm bg-background border-border scroll-mt-32">
        <CardHeader>
          <div className="flex items-center justify-between">
            <CardTitle className="text-base">Pagamentos</CardTitle>
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={handleAddPayment}
              disabled={isCancelled}
            >
              <Plus className="h-4 w-4 mr-1" />
              Adicionar parcela
            </Button>
          </div>
        </CardHeader>
        <CardContent className="space-y-3">
          {data.payments.map((p, idx) => (
            <PaymentRow
              key={idx}
              payment={p}
              index={idx}
              paymentTypes={props.paymentTypes}
              accounts={props.accounts}
              defaultDatetime={transaction.transaction_date}
              onChange={handlePaymentChange}
              onRemove={handleRemovePayment}
              removable={data.payments.length > 1 && !isCancelled}
            />
          ))}

          {totalGeral > 0 && (
            <div
              className={
                'rounded-md border p-3 text-sm flex items-center justify-between ' +
                (pagamentoStatus === 'falta'
                  ? 'border-amber-500/50 bg-amber-500/10 text-amber-700 dark:text-amber-300'
                  : pagamentoStatus === 'troco'
                    ? 'border-blue-500/50 bg-blue-500/10 text-blue-700 dark:text-blue-300'
                    : 'border-emerald-500/50 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300')
              }
            >
              <div>
                <div className="font-medium">
                  {pagamentoStatus === 'exato' && 'Total pago confere com a venda'}
                  {pagamentoStatus === 'falta' &&
                    `Falta ${formatBRL(Math.abs(saldoPagamento))} pra fechar`}
                  {pagamentoStatus === 'troco' &&
                    `Troco de ${formatBRL(saldoPagamento)}`}
                </div>
                <div className="text-xs text-muted-foreground">
                  Pago {formatBRL(totalPago)} · Total venda {formatBRL(totalGeral)}
                </div>
              </div>
            </div>
          )}
          {props.customerDue && (
            <p className="text-xs text-amber-700 dark:text-amber-300">
              Saldo devedor do cliente: <strong>{props.customerDue}</strong>
            </p>
          )}
        </CardContent>
      </Card>

      {/* Resumo: desconto + notas + total */}
      <Card id="sec-resumo" className="shadow-sm bg-background border-border scroll-mt-32">
        <CardHeader>
          <CardTitle className="text-base">Resumo</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div className="space-y-1.5">
              <Label htmlFor="discount_type">Tipo de desconto</Label>
              <Select
                value={data.discount_type}
                onValueChange={(v) => {
                  const newType = v as typeof data.discount_type;
                  setData('discount_type', newType);
                  validateDiscount(newType, data.discount_amount);
                }}
                disabled={isCancelled}
              >
                <SelectTrigger id="discount_type">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="percentage">Porcentagem</SelectItem>
                  <SelectItem value="fixed">Valor fixo</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="discount_amount">
                Valor do desconto
                {maxDiscount != null && maxDiscount > 0 && (
                  <span className="ml-1 text-xs text-muted-foreground">(máx {maxDiscount}%)</span>
                )}
              </Label>
              <Input
                id="discount_amount"
                type="number"
                inputMode="decimal"
                step="0.01"
                value={data.discount_amount}
                onChange={(e) => {
                  const v = Number(e.target.value);
                  setData('discount_amount', v);
                  validateDiscount(data.discount_type, v);
                }}
                disabled={!props.permissions.editDiscount || isCancelled}
              />
              {discountError && <FieldError message={discountError} />}
            </div>
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="notes">Notas</Label>
            <Textarea
              id="notes"
              value={data.notes}
              onChange={(e) => setData('notes', e.target.value)}
              placeholder="Observações sobre a venda…"
              rows={3}
              disabled={isCancelled}
            />
          </div>

          {/* Total consolidado */}
          <div className="rounded-md border border-border bg-muted/30 p-4 space-y-1.5 text-sm">
            <div className="flex justify-between text-muted-foreground">
              <span>Subtotal produtos</span>
              <span className="tabular-nums">{formatBRL(subtotalProdutos)}</span>
            </div>
            {descontoPedido > 0 && (
              <div className="flex justify-between text-muted-foreground">
                <span>
                  Desconto do pedido
                  {data.discount_type === 'percentage' && data.discount_amount > 0 && (
                    <span className="text-xs"> ({data.discount_amount}%)</span>
                  )}
                </span>
                <span className="tabular-nums">- {formatBRL(descontoPedido)}</span>
              </div>
            )}
            {data.shipping.cost > 0 && (
              <div className="flex justify-between text-muted-foreground">
                <span>Frete</span>
                <span className="tabular-nums">+ {formatBRL(data.shipping.cost)}</span>
              </div>
            )}
            <div className="flex justify-between border-t border-border pt-2 text-base font-semibold text-foreground">
              <span>Total geral</span>
              <span className="tabular-nums">{formatBRL(totalGeral)}</span>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Mais opções — 10 campos colapsáveis */}
      <details
        id="sec-mais-opcoes"
        open={advancedOpen}
        onToggle={handleAdvancedToggle}
        className="rounded-lg border border-border bg-card scroll-mt-32"
      >
        <summary className="cursor-pointer px-6 py-4 font-medium text-foreground hover:bg-muted/50 select-none">
          Mais opções (frete, fatura, comissão, prazo, imposto)
        </summary>
        <div className="px-6 pb-6 space-y-6 border-t border-border pt-4">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div className="space-y-1.5">
              <Label htmlFor="invoice_scheme_id">Esquema da fatura</Label>
              <Select
                value={data.invoice_scheme_id ? String(data.invoice_scheme_id) : ''}
                onValueChange={(v) => setData('invoice_scheme_id', v ? Number(v) : null)}
                disabled={isCancelled}
              >
                <SelectTrigger id="invoice_scheme_id">
                  <SelectValue placeholder="Padrão" />
                </SelectTrigger>
                <SelectContent>
                  {dropdownEntries(props.invoiceSchemes).map(([id, name]) => (
                    <SelectItem key={id} value={id}>
                      {name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="tax_rate_id">Imposto do pedido</Label>
              <Select
                value={data.tax_rate_id ? String(data.tax_rate_id) : ''}
                onValueChange={(v) => setData('tax_rate_id', v ? Number(v) : null)}
                disabled={isCancelled}
              >
                <SelectTrigger id="tax_rate_id">
                  <SelectValue placeholder="Sem imposto" />
                </SelectTrigger>
                <SelectContent>
                  {dropdownEntries(props.taxes).map(([id, name]) => (
                    <SelectItem key={id} value={id}>
                      {name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="pay_term_number">Prazo de pagamento</Label>
              <div className="flex gap-2">
                <Input
                  id="pay_term_number"
                  type="number"
                  value={data.pay_term_number}
                  onChange={(e) => setData('pay_term_number', e.target.value)}
                  placeholder="Nº"
                  className="flex-1"
                  disabled={isCancelled}
                />
                <Select
                  value={data.pay_term_type}
                  onValueChange={(v) =>
                    setData('pay_term_type', v as typeof data.pay_term_type)
                  }
                  disabled={isCancelled}
                >
                  <SelectTrigger className="w-32">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="days">Dias</SelectItem>
                    <SelectItem value="months">Meses</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            {hasCommissionAgent && (
              <div className="space-y-1.5">
                <Label htmlFor="commission_agent_id">Comissionista</Label>
                <Select
                  value={data.commission_agent_id ? String(data.commission_agent_id) : ''}
                  onValueChange={(v) =>
                    setData('commission_agent_id', v ? Number(v) : null)
                  }
                  disabled={isCancelled}
                >
                  <SelectTrigger id="commission_agent_id">
                    <SelectValue placeholder="Nenhum" />
                  </SelectTrigger>
                  <SelectContent>
                    {dropdownEntries(props.commissionAgents).map(([id, name]) => (
                      <SelectItem key={id} value={id}>
                        {name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            )}

            {hasMultiplePriceGroups && (
              <div className="space-y-1.5">
                <Label htmlFor="price_group_id">Grupo de preço</Label>
                <Select
                  value={data.price_group_id ? String(data.price_group_id) : ''}
                  onValueChange={(v) => setData('price_group_id', v ? Number(v) : null)}
                  disabled={isCancelled}
                >
                  <SelectTrigger id="price_group_id">
                    <SelectValue placeholder="Padrão" />
                  </SelectTrigger>
                  <SelectContent>
                    {dropdownEntries(props.priceGroups).map(([id, name]) => (
                      <SelectItem key={id} value={id}>
                        {name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            )}
          </div>

          {/* Frete (5 campos juntos) */}
          <div className="rounded-md border border-border p-4 space-y-4">
            <h3 className="text-sm font-semibold text-foreground">Entrega / Frete</h3>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="space-y-1.5">
                <Label htmlFor="shipping_details">Detalhes de envio</Label>
                <Input
                  id="shipping_details"
                  value={data.shipping.details}
                  onChange={(e) =>
                    setData('shipping', { ...data.shipping, details: e.target.value })
                  }
                  disabled={isCancelled}
                />
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="shipping_address">Endereço de entrega</Label>
                <Input
                  id="shipping_address"
                  value={data.shipping.address}
                  onChange={(e) =>
                    setData('shipping', { ...data.shipping, address: e.target.value })
                  }
                  disabled={isCancelled}
                />
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="shipping_cost">Custo de envio</Label>
                <Input
                  id="shipping_cost"
                  type="number"
                  inputMode="decimal"
                  step="0.01"
                  value={data.shipping.cost}
                  onChange={(e) =>
                    setData('shipping', { ...data.shipping, cost: Number(e.target.value) })
                  }
                  disabled={isCancelled}
                />
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="shipping_status">Status da remessa</Label>
                <Select
                  value={data.shipping.status}
                  onValueChange={(v) => setData('shipping', { ...data.shipping, status: v })}
                  disabled={isCancelled}
                >
                  <SelectTrigger id="shipping_status">
                    <SelectValue placeholder="Selecionar" />
                  </SelectTrigger>
                  <SelectContent>
                    {dropdownEntries(props.shippingStatuses).map(([k, label]) => (
                      <SelectItem key={k} value={k}>
                        {label}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-1.5 md:col-span-2">
                <Label htmlFor="shipping_deliver_to">Entregar a</Label>
                <Input
                  id="shipping_deliver_to"
                  value={data.shipping.deliver_to}
                  onChange={(e) =>
                    setData('shipping', { ...data.shipping, deliver_to: e.target.value })
                  }
                  disabled={isCancelled}
                />
              </div>
            </div>
          </div>
        </div>
      </details>

      </div>
      </div>

      {/* Footer sticky — Cancelar venda + Salvar */}
      <div className="sticky bottom-0 z-30 bg-background/95 backdrop-blur border-t border-border shadow-[0_-1px_3px_rgba(0,0,0,0.04)]">
        <div className="container mx-auto px-8 py-3 max-w-7xl flex items-center justify-between gap-3">
          <div className="text-xs text-muted-foreground flex-1 min-w-0">
            {Object.keys(errors).length > 0 ? (
              <span className="text-destructive font-medium">
                {Object.values(errors)[0] as string}
              </span>
            ) : !canSubmit && data.products.length === 0 ? (
              <span>Adicione pelo menos 1 produto</span>
            ) : !canSubmit && Math.abs(totalPago - totalGeral) >= 0.01 ? (
              <span>Pagamento {pagamentoStatus === 'falta' ? 'falta fechar' : 'excede o total'}</span>
            ) : (
              <span className="hidden md:inline">Atalho: Ctrl+S pra salvar</span>
            )}
          </div>
          <div className="flex items-center gap-2 shrink-0">
            <Button variant="outline" onClick={() => router.visit('/sells')}>
              Voltar
            </Button>
            {props.permissions.canCancel && !isCancelled && (
              <Button
                variant="outline"
                onClick={() => setShowCancelConfirm(true)}
                className="text-rose-600 border-rose-300 hover:bg-rose-50 dark:hover:bg-rose-950/40"
              >
                <Ban className="h-4 w-4 mr-1.5" />
                Cancelar venda
              </Button>
            )}
            <Button variant="outline" onClick={() => handleSubmit(true)} disabled={!canSubmit || isCancelled}>
              {processing && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
              <Printer className="h-4 w-4 mr-1.5" />
              {processing ? 'Salvando…' : 'Salvar e imprimir'}
            </Button>
            <Button onClick={() => handleSubmit()} disabled={!canSubmit || isCancelled}>
              {processing && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
              {processing ? 'Salvando…' : 'Salvar alterações'}
            </Button>
          </div>
        </div>
      </div>

      {/* Confirm modal cancelamento (simples; full dialog Radix overkill aqui) */}
      {showCancelConfirm && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm"
          role="dialog"
          aria-modal="true"
          aria-labelledby="cancel-sale-title"
        >
          <Card className="max-w-md w-full mx-4 shadow-lg">
            <CardHeader>
              <CardTitle id="cancel-sale-title" className="text-base flex items-center gap-2">
                <Ban className="h-5 w-5 text-rose-600" />
                Cancelar esta venda?
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
              <p className="text-sm text-muted-foreground">
                A venda #{transaction.invoice_no} será marcada como cancelada. Estoque
                reservado é liberado e qualquer NFe emitida precisa ser cancelada
                separadamente via fluxo fiscal.
              </p>
              <p className="text-xs text-muted-foreground">
                Esta ação fica registrada no histórico de auditoria com seu usuário e a
                data atual.
              </p>
              <div className="flex gap-2 justify-end pt-2">
                <Button variant="outline" onClick={() => setShowCancelConfirm(false)}>
                  Voltar
                </Button>
                <Button
                  onClick={handleCancelSale}
                  className="bg-rose-600 hover:bg-rose-700 text-white"
                >
                  Sim, cancelar venda
                </Button>
              </div>
            </CardContent>
          </Card>
        </div>
      )}
    </div>
  );
}

// Persistent Layout — auto-mem preference_persistent_layouts.
SellsEdit.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
