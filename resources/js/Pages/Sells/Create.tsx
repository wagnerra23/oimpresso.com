// US-SELL-004 — F3 FRONTEND INCREMENTAL: triagem visibilidade campos.
// 18 campos legacy → 8 sempre visíveis + 10 colapsáveis em <details>.
// Refs: ADR 0104 (MWART canônico), ADR 0105 (3 graus regulação),
//        RUNBOOK Sells/create §3.3, SPEC Sells US-SELL-004.
//
// Visíveis (8): Local, Cliente, Data, Status, [Produtos], [Pagamentos], Desconto inline, Notas
// Colapsáveis (10): price_group, commission_agent, pay_term, invoice_scheme,
//                   invoice_no, document, tax_rate, shipping (5 campos como bloco)
//
// Próximos PRs ainda virão:
//   - US-SELL-005: bloco produtos real (busca + tabela + cálculos)
//   - US-SELL-006: bloco pagamentos real + frete
//   - US-SELL-007: atalhos / Esc Cmd+Enter + auto-save draft localStorage

import AppShellV2 from '@/Layouts/AppShellV2';
import { router, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { useAuth, useBusiness } from '@/Hooks/usePageProps';
import { CreditCard, FileText, Loader2, Package, Plus, Printer, Receipt, Search, Settings2, Trash2 } from 'lucide-react';
import PageHeader from '@/Components/shared/PageHeader';
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

interface Location {
  id: number;
  name: string;
  selling_price_group_id: number | null;
}

interface Contact {
  id: number;
  name: string;
}

interface InvoiceScheme {
  id: number;
  name: string;
}

export interface SellsCreatePageProps {
  businessLocations: OptionMap;
  blAttributes: Record<number, Record<string, unknown>>;
  defaultLocation: Location | null;
  walkInCustomer: Contact;
  paymentTypes: Record<string, string>;
  invoiceSchemes: OptionMap;
  defaultInvoiceScheme: InvoiceScheme | null;
  invoiceLayouts?: OptionMap;
  // taxes vem como Record<id, name> do TaxRate::forBusinessDropdown (pluck('name', 'id')).
  // Não é array — usar Object.entries.
  taxes: Record<number, string>;
  priceGroups: OptionMap;
  defaultPriceGroupId: number | null;
  shippingStatuses: Record<string, string>;
  defaultDatetime: string;
  commissionAgents: OptionMap;
  customerGroups: OptionMap;
  accounts: OptionMap;
  typesOfService: OptionMap;
  categories?: Record<string, unknown> | false;
  brands?: Record<string, unknown> | false;
  shortcuts?: Record<string, string> | null;
  featuredProducts?: Array<Record<string, unknown>>;
  users: OptionMap | [];
  permissions: {
    editDiscount: boolean;
    editPrice: boolean;
    maxDiscount?: number | null;
  };
  posSettings: Record<string, unknown>;
  subType: string | null;
  statuses?: Record<string, string>;
  isOrderRequestEnabled?: boolean;
}

const ADVANCED_OPEN_KEY = 'oimpresso.sells.create.advanced.open';
// US-SELL-007 — auto-save draft. STORAGE_KEY DEVE incluir business_id + user_id
// (Tier 0 multi-tenant ADR 0093) — sem isso ROTA LIVRE biz=4 leria draft de biz=1.
const DRAFT_TTL_MS = 24 * 60 * 60 * 1000;

// US-SELL-010 — campos dentro do <details> "Mais opções". Se erro cair em algum
// destes, o details abre automaticamente pra Larissa achar o campo (gap UX
// detectado pelo design-arte agent 2026-05-13).
const COLLAPSED_FIELD_KEYS = [
  'invoice_scheme_id',
  'invoice_no',
  'document',
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

// FieldError — exibe erro de validação por campo. Inline (canon: componente
// reusável só ao 2º uso; aqui é local). role="alert" pra screen reader.
function FieldError({ message }: { message?: string }) {
  if (!message) return null;
  return (
    <p className="text-xs text-destructive mt-1" role="alert">
      {message}
    </p>
  );
}

// dropdownEntries movido pra _components/dropdownEntries.ts (utility shared local).

// Converte "DD/MM/YYYY HH:mm" (formato backend/session) → "YYYY-MM-DDTHH:mm" (datetime-local input).
function toDatetimeLocal(val: string): string {
  const m = val.match(/^(\d{2})\/(\d{2})\/(\d{4})\s+(\d{2}):(\d{2})/);
  if (!m) return '';
  return `${m[3]}-${m[2]}-${m[1]}T${m[4]}:${m[5]}`;
}

// Converte "YYYY-MM-DDTHH:mm" (datetime-local input) → "DD/MM/YYYY HH:mm" (formato backend).
function fromDatetimeLocal(val: string): string {
  const m = val.match(/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/);
  if (!m) return val;
  return `${m[3]}/${m[2]}/${m[1]} ${m[4]}:${m[5]}`;
}

export default function SellsCreate(props: SellsCreatePageProps) {
  // Defaults conservadores ROTA LIVRE: status=final, transaction_date=format_now_local
  const { data, setData, post, processing, errors, transform } = useForm({
    location_id: props.defaultLocation?.id ?? null,
    contact_id: props.walkInCustomer.id,
    transaction_date: props.defaultDatetime,
    status: 'final' as 'final' | 'quotation' | 'draft' | 'proforma',
    invoice_scheme_id: props.defaultInvoiceScheme?.id ?? null,
    invoice_no: '',
    pay_term_number: '' as string | number,
    pay_term_type: 'days' as 'days' | 'months',
    price_group_id: props.defaultPriceGroupId,
    commission_agent_id: null as number | null,
    tax_rate_id: null as number | null,
    products: [] as Array<{
      product_id: number;
      variation_id: number | null;
      name: string;
      sku: string;
      quantity: number;
      unit_price: number;
      discount: number;
    }>,
    payments: [
      {
        amount: 0,
        method: 'cash',
        paid_on: '',
        account_id: null as number | null,
        note: '',
      },
    ] as Payment[],
    discount_type: 'percentage' as 'percentage' | 'fixed',
    discount_amount: 0,
    notes: '',
    shipping: {
      details: '',
      address: '',
      cost: 0,
      status: '' as string,
      deliver_to: '',
    },
    additional_expenses: [
      { key: '', value: 0 },
      { key: '', value: 0 },
      { key: '', value: 0 },
      { key: '', value: 0 },
    ] as Array<{ key: string; value: number }>,
  });

  // Persistir <details> open state em localStorage (DESIGN.md §12)
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
  const hasTypesOfService = Object.keys(props.typesOfService).length > 0;

  // Cálculos de produtos
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

  // max_discount: null/undefined = sem limite.
  const [discountError, setDiscountError] = useState<string | null>(null);
  const maxDiscount = props.permissions.maxDiscount != null ? Number(props.permissions.maxDiscount) : null;

  const additionalExpensesTotal = useMemo(
    () => data.additional_expenses.reduce((acc, e) => acc + (Number(e.value) || 0), 0),
    [data.additional_expenses],
  );

  const totalGeral = useMemo(
    () => Math.max(subtotalProdutos - descontoPedido + data.shipping.cost + additionalExpensesTotal, 0),
    [subtotalProdutos, descontoPedido, data.shipping.cost, additionalExpensesTotal],
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
        product_id: p.product_id,
        variation_id: p.variation_id ?? null,
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
    const next = [...data.products];
    next[idx] = { ...next[idx], [field]: value };
    setData('products', next);
  };

  const handleRemoveProduct = (idx: number) => {
    setData(
      'products',
      data.products.filter((_, i) => i !== idx),
    );
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
    next[idx] = { ...next[idx], [field]: value } as Payment;
    setData('payments', next);
  };

  const handleAddPayment = () => {
    setData('payments', [
      ...data.payments,
      {
        amount: Math.max(-saldoPagamento, 0),
        method: 'cash',
        paid_on: '',
        account_id: null,
        note: '',
      },
    ]);
  };

  const handleRemovePayment = (idx: number) => {
    setData(
      'payments',
      data.payments.filter((_, i) => i !== idx),
    );
  };

  const focusProductSearch = () => {
    const input = productSearchRef.current?.querySelector<HTMLInputElement>(
      'input[type="search"]',
    );
    input?.focus();
  };

  // Itens count pra KPI card
  const itensCount = data.products.reduce((acc, p) => acc + (Number(p.quantity) || 0), 0);

  // Submit handler — POST /sells via Inertia. Backend SellController@store / SellPosController@store
  // recebe o payload e cria Transaction + TransactionSellLine + payments.
  // Refs: ADR 0104 (MWART F2 backend baseline), Inertia useForm docs.
  const canSubmit =
    !processing &&
    data.products.length > 0 &&
    data.location_id !== null &&
    Math.abs(totalPago - totalGeral) < 0.01 &&
    !discountError;

  const handleSubmit = (withPrint = false) => {
    if (!canSubmit) return;
    // Transform: mapeia state UX-friendly do React pra payload que SellPosController@store
    // espera (Blade legacy field names + flat shipping + is_direct_sale flag obrigatório).
    // Refs: SellPosController@store linhas 352-680 + sell/create.blade.php Form::* fields.
    transform((d) => ({
      ...d,
      // Flag CRÍTICO: sem is_direct_sale=1, controller cai em cashRegister check (linha 364).
      is_direct_sale: 1,
      is_save_and_print: withPrint ? 1 : 0,
      // Rename pra Blade legacy convention
      payment: d.payments,
      commission_agent: d.commission_agent_id,
      price_group: d.price_group_id,
      sale_note: d.notes,
      additional_notes: d.notes,
      // Flatten shipping object pra campos top-level
      shipping_details: d.shipping.details,
      shipping_address: d.shipping.address,
      shipping_charges: d.shipping.cost,
      shipping_status: d.shipping.status,
      delivered_to: d.shipping.deliver_to,
      // Despesas adicionais (Blade: additional_expense_key_N + additional_expense_value_N)
      additional_expense_key_1: d.additional_expenses[0]?.key ?? '',
      additional_expense_value_1: d.additional_expenses[0]?.value ?? '',
      additional_expense_key_2: d.additional_expenses[1]?.key ?? '',
      additional_expense_value_2: d.additional_expenses[1]?.value ?? '',
      additional_expense_key_3: d.additional_expenses[2]?.key ?? '',
      additional_expense_value_3: d.additional_expenses[2]?.value ?? '',
      additional_expense_key_4: d.additional_expenses[3]?.key ?? '',
      additional_expense_value_4: d.additional_expenses[3]?.value ?? '',
      // Total calculado client-side (backend re-calcula via productUtil mas evita 422)
      final_total: totalGeral,
      // Campos hidden Blade que controller espera
      default_price_group: d.price_group_id,
      // Products precisam de campos que ProductUtil acessa direto (Undefined array key
      // se faltar). Refs: app/Utils/ProductUtil.php:650 calculateInvoiceTotal +
      // TransactionUtil.php:297-394 createOrUpdateSellLines.
      products: d.products.map((p) => ({
        ...p,
        unit_price_inc_tax: p.unit_price, // sem tax separado por linha (tax via tax_rate_id pedido)
        item_tax: 0,
        tax_id: null,
        line_discount_type: 'fixed',
        line_discount_amount: p.discount,
        // SellPosController:581 acessa $product['enable_stock'] direto. Se faltar,
        // Undefined array key. Defaults seguros: stock-managed e single-type.
        // Idealmente search API retornaria isso por produto — TODO US-SELL-PRODUCT-META.
        enable_stock: 1,
        product_type: 'single',
      })),
    }));
    // POST /pos -> SellPosController@store (mesma rota do Blade legacy form em
    // sell/create.blade.php:58). SellController@store é stub vazio — toda a
    // lógica de criar Transaction + payments + estoque vive em SellPosController.
    post('/pos', {
      preserveScroll: true,
      onSuccess: () => {
        // US-SELL-007 — limpar draft após salvar com sucesso (senão fica entre vendas).
        if (draftKey) {
          try {
            localStorage.removeItem(draftKey);
          } catch {
            // ignore
          }
        }
      },
      onError: (errs) => {
        // Rola pro topo da primeira seção com erro pra Wagner ver feedback.
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

  // Cmd+Enter / Ctrl+Enter atalho pra submit (UX canon Cockpit).
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && e.key === 'Enter' && canSubmit) {
        e.preventDefault();
        handleSubmit();
      }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [canSubmit]);

  // US-SELL-007 — Esc top-level: blur active element (sair de input/select/textarea).
  // Autocompletes (ProductSearch, CustomerSearch) já têm Esc próprio em _components/.
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

  // US-SELL-010 — Auto-open <details> "Mais opções" quando erro está em campo colapsado.
  // Larissa scrola pro erro mas a seção fica fechada — sem isso ela não acha o campo.
  // Detectado pelo design-arte agent 2026-05-13 como maior gap UX restante.
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

  // US-SELL-007 — Auto-save draft localStorage debounced 500ms.
  // STORAGE_KEY com business_id + user_id (Tier 0 multi-tenant ADR 0093).
  // Larissa atende telefone no meio — não pode perder rascunho ao F5.
  const auth = useAuth();
  const business = useBusiness();
  const draftKey = useMemo(() => {
    const bizId = business?.id;
    const userId = auth?.user?.id;
    if (!bizId || !userId) return null;
    return `oimpresso.sells.create.draft.${bizId}.${userId}`;
  }, [auth, business]);

  // Recover ao montar (apenas 1x). Pergunta antes — Larissa pode ter terminado em outro tab.
  const recoveredRef = useRef(false);
  useEffect(() => {
    if (!draftKey || recoveredRef.current) return;
    recoveredRef.current = true;
    try {
      const raw = localStorage.getItem(draftKey);
      if (!raw) return;
      const parsed = JSON.parse(raw) as { data: typeof data; savedAt: number };
      if (!parsed?.savedAt || Date.now() - parsed.savedAt > DRAFT_TTL_MS) {
        localStorage.removeItem(draftKey);
        return;
      }
      const time = new Date(parsed.savedAt).toLocaleTimeString('pt-BR', {
        hour: '2-digit',
        minute: '2-digit',
      });
      if (confirm(`Recuperar rascunho de venda salvo às ${time}?`)) {
        setData(parsed.data);
      } else {
        localStorage.removeItem(draftKey);
      }
    } catch {
      // Draft corrompido — descartar silenciosamente.
      try {
        localStorage.removeItem(draftKey);
      } catch {
        // ignore
      }
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [draftKey]);

  // Auto-save debounced 500ms quando data mudar (após mount).
  useEffect(() => {
    if (!draftKey || !recoveredRef.current) return;
    const t = setTimeout(() => {
      try {
        localStorage.setItem(draftKey, JSON.stringify({ data, savedAt: Date.now() }));
      } catch {
        // localStorage quota / incognito — silencioso.
      }
    }, 500);
    return () => clearTimeout(t);
  }, [data, draftKey]);

  // postMessage: recebe contato criado na aba de cadastro (/contacts/create-page).
  const [forcedCustomer, setForcedCustomer] = useState<{ id: number; text: string } | null>(null);
  useEffect(() => {
    const handler = (event: MessageEvent) => {
      if (event.data?.type === 'contact_created' && event.data?.contact) {
        const c = event.data.contact as { id: number; name: string };
        setData('contact_id', c.id);
        setForcedCustomer({ id: c.id, text: c.name });
      }
    };
    window.addEventListener('message', handler);
    return () => window.removeEventListener('message', handler);
  }, []);

  // Smooth scroll pra seção quando user clica numa aba.
  const scrollToSection = (id: string) => {
    document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  };

  // Scroll-spy: detecta qual aba está visível e marca como ativa (pattern Cockpit canon).
  // Ref: Pages/ProjectMgmt/Board/DetailSheet.tsx — abas com border-b-2 border-primary -mb-px no estado ativo.
  const sectionIds = ['sec-dados', 'sec-produtos', 'sec-pagamento', 'sec-resumo', 'sec-mais-opcoes'];
  const [activeSection, setActiveSection] = useState<string>('sec-dados');
  useEffect(() => {
    const observer = new IntersectionObserver(
      (entries) => {
        // Pega entries visíveis ordenadas por position no DOM. Top entry vence.
        const visible = entries
          .filter((e) => e.isIntersecting)
          .map((e) => e.target.id);
        if (visible.length > 0) {
          // Mantém a primeira seção (mais ao topo) como "ativa".
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

  return (
    <div className="-m-6 bg-muted/30 min-h-[calc(100vh-3rem)] flex flex-col">
      {/* Header sticky no topo + abas seção (pattern Office/OS canon) */}
      <div className="sticky top-0 z-30 bg-background/95 backdrop-blur border-b border-border">
        <div className="container mx-auto px-8 pt-6 pb-3 max-w-7xl">
          <div className="flex items-start gap-4">
            <div className="flex-1 min-w-0">
              <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                Adicionar venda
              </h1>
              <p className="text-sm text-muted-foreground mt-1 leading-relaxed">
                Registre uma venda completa — cliente, produtos, pagamento e frete.{' '}
                {props.defaultLocation?.name && (
                  <span>
                    Local:{' '}
                    <span className="font-medium text-foreground">
                      {props.defaultLocation.name}
                    </span>
                  </span>
                )}
              </p>
            </div>
          </div>

          {/* Filter pills — pattern Cockpit canon (rounded-full + counter, ref exemplo OS Officeimpresso) */}
          <nav
            className="flex items-center gap-2 mt-4 flex-wrap"
            aria-label="Seções do cadastro"
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
                    <span
                      className={
                        'ml-0.5 inline-flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] tabular-nums ' +
                        (isActive ? 'bg-primary-foreground/20' : 'bg-background border border-border')
                      }
                    >
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

      {/* KPI cards — 4 cards GIGANTES, value text-3xl, label uppercase tracking-widest. Estado da arte. */}
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
                  : 'Confere ✓'}
          </div>
        </div>
      </div>

      {/* Conteúdo das seções — espaçamento generoso */}
      <div className="space-y-6">

      {/* Linha 1: Cliente + Data + Status + Local — 4 campos sempre visíveis */}
      <Card id="sec-dados" className="shadow-sm bg-background border-border scroll-mt-32">
        <CardHeader>
          <CardTitle className="text-base">Dados da venda</CardTitle>
        </CardHeader>
        <CardContent className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <div className="space-y-1.5">
            <Label htmlFor="contact_id">Cliente</Label>
            <CustomerSearchAutocomplete
              defaultName={props.walkInCustomer.name}
              onSelect={(c) => setData('contact_id', c.id)}
              onClear={() => setData('contact_id', props.walkInCustomer.id)}
              forcedValue={forcedCustomer}
            />
            <p className="text-xs text-muted-foreground">
              Digite ≥2 caracteres pra buscar. Limpe pra voltar ao cliente padrão.
            </p>
            <FieldError message={errors.contact_id as string | undefined} />
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="transaction_date">Data da venda</Label>
            <Input
              id="transaction_date"
              type="datetime-local"
              value={toDatetimeLocal(data.transaction_date)}
              onChange={(e) => setData('transaction_date', fromDatetimeLocal(e.target.value))}
            />
            <FieldError message={errors.transaction_date as string | undefined} />
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="status">Status</Label>
            <Select
              value={data.status}
              onValueChange={(v) => setData('status', v as typeof data.status)}
            >
              <SelectTrigger id="status">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="final">Final</SelectItem>
                <SelectItem value="draft">Rascunho</SelectItem>
                <SelectItem value="quotation">Orçamento</SelectItem>
                <SelectItem value="proforma">Pró-forma</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="location_id">Local</Label>
            <Select
              value={data.location_id ? String(data.location_id) : ''}
              onValueChange={(v) => setData('location_id', v ? Number(v) : null)}
            >
              <SelectTrigger id="location_id">
                <SelectValue placeholder="Selecionar local" />
              </SelectTrigger>
              <SelectContent>
                {dropdownEntries(props.businessLocations).map(([id, name]) => (
                  <SelectItem key={id} value={id}>
                    {name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <FieldError message={errors.location_id as string | undefined} />
          </div>
        </CardContent>
      </Card>

      {/* Bloco produtos — busca + tabela editável (US-SELL-005) */}
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
              title="Nenhum produto adicionado"
              description="Use a busca acima ou aperte / pra focar (em breve)."
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
                            disabled={!props.permissions.editPrice}
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
                            disabled={!props.permissions.editDiscount}
                            aria-label={`Desconto em ${p.name}`}
                            className="h-8 tabular-nums"
                          />
                        </td>
                        <td className="px-3 py-2 text-right tabular-nums font-medium text-foreground align-middle">
                          {formatBRL(lineSubtotal)}
                        </td>
                        <td className="px-3 py-2 text-right align-middle">
                          <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            onClick={() => handleRemoveProduct(idx)}
                            aria-label={`Remover ${p.name}`}
                            className="text-muted-foreground hover:text-destructive"
                          >
                            <Trash2 className="h-4 w-4" />
                          </Button>
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

      {/* Bloco pagamentos — split de pagamento + indicador saldo (US-SELL-006) */}
      <Card id="sec-pagamento" className="shadow-sm bg-background border-border scroll-mt-32">
        <CardHeader>
          <div className="flex items-center justify-between">
            <CardTitle className="text-base">Pagamento</CardTitle>
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={handleAddPayment}
            >
              <Plus className="h-4 w-4 mr-1" />
              Adicionar pagamento
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
              defaultDatetime={props.defaultDatetime}
              onChange={handlePaymentChange}
              onRemove={handleRemovePayment}
              removable={data.payments.length > 1}
            />
          ))}

          {/* Indicador saldo de pagamento */}
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
        </CardContent>
      </Card>

      {/* Desconto inline + Notas — sempre visíveis (8 campos visíveis: 4 acima + 1 desconto + 1 nota + produtos + pagamentos) */}
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
                disabled={!props.permissions.editDiscount}
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
            />
          </div>

          {/* Despesas adicionais (embalagem, etiquetagem, etc) */}
          <details className="rounded-md border border-border">
            <summary className="cursor-pointer px-4 py-2.5 text-sm font-medium text-muted-foreground hover:text-foreground select-none">
              Despesas adicionais
              {additionalExpensesTotal > 0 && (
                <span className="ml-2 text-xs text-foreground tabular-nums">({formatBRL(additionalExpensesTotal)})</span>
              )}
            </summary>
            <div className="px-4 pb-4 pt-2 space-y-2 border-t border-border">
              {data.additional_expenses.map((exp, i) => (
                <div key={i} className="grid grid-cols-2 gap-2">
                  <Input
                    type="text"
                    value={exp.key}
                    onChange={(e) => {
                      const next = [...data.additional_expenses];
                      next[i] = { key: e.target.value, value: next[i]?.value ?? 0 };
                      setData('additional_expenses', next);
                    }}
                    placeholder={`Descrição ${i + 1}`}
                    className="text-sm"
                  />
                  <Input
                    type="number"
                    inputMode="decimal"
                    step="0.01"
                    min="0"
                    value={exp.value || ''}
                    onChange={(e) => {
                      const next = [...data.additional_expenses];
                      next[i] = { key: next[i]?.key ?? '', value: Number(e.target.value) };
                      setData('additional_expenses', next);
                    }}
                    placeholder="R$ [redacted Tier 0]"
                    className="text-sm tabular-nums"
                  />
                </div>
              ))}
            </div>
          </details>

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
            {additionalExpensesTotal > 0 && (
              <div className="flex justify-between text-muted-foreground">
                <span>Despesas adicionais</span>
                <span className="tabular-nums">+ {formatBRL(additionalExpensesTotal)}</span>
              </div>
            )}
            <div className="flex justify-between border-t border-border pt-2 text-base font-semibold text-foreground">
              <span>Total geral</span>
              <span className="tabular-nums">{formatBRL(totalGeral)}</span>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Mais opções — 10 campos colapsáveis em <details> com persistência localStorage */}
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
          {/* Linha colapsável 1: invoice + faturamento */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div className="space-y-1.5">
              <Label htmlFor="invoice_scheme_id">Esquema da fatura</Label>
              <Select
                value={data.invoice_scheme_id ? String(data.invoice_scheme_id) : ''}
                onValueChange={(v) => setData('invoice_scheme_id', v ? Number(v) : null)}
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
              <Label htmlFor="invoice_no">Nº da fatura</Label>
              <Input
                id="invoice_no"
                value={data.invoice_no}
                onChange={(e) => setData('invoice_no', e.target.value)}
                placeholder="Auto-gerado se vazio"
              />
              <FieldError message={errors.invoice_no as string | undefined} />
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="tax_rate_id">Imposto do pedido</Label>
              <Select
                value={data.tax_rate_id ? String(data.tax_rate_id) : ''}
                onValueChange={(v) => setData('tax_rate_id', v ? Number(v) : null)}
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
          </div>

          {/* Linha colapsável 2: prazo + comissão + price group (cada um só se aplicável) */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
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
                />
                <Select
                  value={data.pay_term_type}
                  onValueChange={(v) =>
                    setData('pay_term_type', v as typeof data.pay_term_type)
                  }
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

            {hasCommissionAgent && (
              <div className="space-y-1.5">
                <Label htmlFor="commission_agent_id">Comissionista</Label>
                <Select
                  value={data.commission_agent_id ? String(data.commission_agent_id) : ''}
                  onValueChange={(v) =>
                    setData('commission_agent_id', v ? Number(v) : null)
                  }
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

          {/* Bloco frete colapsável dentro de Mais opções (5 campos juntos) */}
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
                />
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="shipping_status">Status da remessa</Label>
                <Select
                  value={data.shipping.status}
                  onValueChange={(v) => setData('shipping', { ...data.shipping, status: v })}
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
                />
              </div>
            </div>
          </div>
        </div>
      </details>

      </div>
      </div>

      {/* Footer sticky — pattern Office/OS: ações principais sempre acessíveis no fim do form longo */}
      <div className="sticky bottom-0 z-30 bg-background/95 backdrop-blur border-t border-border shadow-[0_-1px_3px_rgba(0,0,0,0.04)]">
        <div className="container mx-auto px-8 py-3 max-w-7xl flex items-center justify-between gap-3">
          {/* Validação inline — diz por que botão tá desabilitado, sem precisar adivinhar */}
          <div className="text-xs text-muted-foreground flex-1 min-w-0">
            {Object.keys(errors).length > 0 ? (
              <span className="text-destructive font-medium">
                {Object.values(errors)[0] as string}
              </span>
            ) : !canSubmit && data.products.length === 0 ? (
              <span>Adicione pelo menos 1 produto</span>
            ) : !canSubmit && Math.abs(totalPago - totalGeral) >= 0.01 ? (
              <span>Pagamento {pagamentoStatus === 'falta' ? 'falta fechar' : 'excede o total'}</span>
            ) : !canSubmit && data.location_id === null ? (
              <span>Selecione o local da venda</span>
            ) : (
              <span className="hidden md:inline">Atalho: Ctrl+Enter pra salvar</span>
            )}
          </div>
          <div className="flex items-center gap-2 shrink-0">
            <Button variant="outline" onClick={() => router.visit('/sells')}>
              Cancelar
            </Button>
            <Button variant="outline" onClick={() => handleSubmit(true)} disabled={!canSubmit}>
              {processing && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
              <Printer className="h-4 w-4 mr-1.5" />
              {processing ? 'Salvando…' : 'Salvar e Imprimir'}
            </Button>
            <Button onClick={() => handleSubmit()} disabled={!canSubmit}>
              {processing && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
              {processing ? 'Salvando…' : 'Salvar venda'}
            </Button>
          </div>
        </div>
      </div>
    </div>
  );
}

// Persistent Layout — auto-mem preference_persistent_layouts.
SellsCreate.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
