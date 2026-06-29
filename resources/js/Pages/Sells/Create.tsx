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
import { AlertTriangle, CreditCard, FileText, Loader2, Package, Plus, Printer, Receipt, Search, Settings2, Trash2, Truck } from 'lucide-react';
import EmptyState from '@/Components/shared/EmptyState';
import MercosulPlate from '@/Components/shared/MercosulPlate';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import ProductSearchAutocomplete, {
  type ProductSearchResult,
} from './_components/ProductSearchAutocomplete';
import CustomerSearchAutocomplete, {
  type CustomerSearchResult,
  type VehicleOption,
} from './_components/CustomerSearchAutocomplete';
import QuickAddVehicleSheet from './_components/QuickAddVehicleSheet';
import PaymentRow, { type Payment } from './_components/PaymentRow';
import NumericInputPtBR from '@/Components/ui/numeric-input-ptbr';
import { dropdownEntries } from './_components/dropdownEntries';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import { toast } from 'sonner';

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
  // ADR 0251 — veículo na venda direta de oficina. hasOficinaAuto = gate per-business
  // (vestuário/ROTA LIVRE vem false → seção some). vehicleTypes alimenta o dropdown
  // do cadastro rápido (QuickAddVehicleSheet).
  hasOficinaAuto?: boolean;
  vehicleTypes?: OptionMap;
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

// 2026-06-04 (Wagner) — agora local em ISO "YYYY-MM-DDTHH:mm" pro datetime-local.
function nowLocalIso(): string {
  const d = new Date();
  const p = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`;
}

// Default robusto da data da venda: usa o defaultDatetime do backend SE ele
// parsear pro formato esperado; senão cai pra HOJE. Antes, quando o date_format
// do business não era d/m/Y, o toDatetimeLocal retornava '' → campo vazio → venda
// salva sem data → sumia da consulta (que filtra por data). Pedido Wagner.
function defaultTransactionDate(backendDefault: string): string {
  return toDatetimeLocal(backendDefault) ? backendDefault : fromDatetimeLocal(nowLocalIso());
}

export default function SellsCreate(props: SellsCreatePageProps) {
  // Defaults conservadores ROTA LIVRE: status=final, transaction_date=HOJE (robusto).
  const { data, setData, post, processing, errors, transform } = useForm({
    location_id: props.defaultLocation?.id ?? null,
    contact_id: props.walkInCustomer.id,
    transaction_date: defaultTransactionDate(props.defaultDatetime),
    status: 'final' as 'final' | 'quotation' | 'draft' | 'proforma',
    invoice_scheme_id: props.defaultInvoiceScheme?.id ?? null,
    invoice_no: '',
    pay_term_number: '' as string | number,
    pay_term_type: 'days' as 'days' | 'months',
    price_group_id: props.defaultPriceGroupId,
    commission_agent_id: null as number | null,
    tax_rate_id: null as number | null,
    // ADR 0251 — veículo na venda direta de oficina. null = sem veículo. Só
    // relevante quando OficinaAuto habilitado; em vestuário fica sempre null.
    vehicle_id: null as number | null,
    products: [] as Array<{
      product_id: number;
      variation_id: number | null;
      name: string;
      // Variation name (ex "P", "M", "G") — exibido como `{name} - {variation}` no
      // carrinho pra Larissa identificar tamanho da peça. Sem isso, vestuário com
      // variável vê só "Camiseta" sem distinguir tamanho selecionado.
      variation: string | null;
      sku: string;
      quantity: number;
      unit_price: number;
      discount: number;
      // Paridade Edit — desconto per-line R$ (fixed) ou % (percentage).
      discount_type: 'fixed' | 'percentage';
      // Paridade Edit — IMEI/serial opcional por linha (Blade legacy).
      imei_number?: string;
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
    /** Nota interna pra equipe (separada de notes/additional_notes). Backend: TransactionUtil staff_note. */
    staff_note: '',
    /** Assinatura recorrente (Blade legacy is_recurring). Paridade com Edit.tsx. */
    is_recurring: 0 as 0 | 1,
    /** Endereço cobrança ≠ entrega (Blade legacy customer_secondary_address). Paridade Edit. */
    customer_secondary_address: '',
    /** Documento anexo (file upload Blade legacy sell_document). Paridade Edit. */
    sell_document: null as File | null,
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

  // ADR 0251 — Veículo na venda. Gate per-business: vestuário (ROTA LIVRE) vem
  // hasOficinaAuto=false → toda a seção de veículo some. customerVehicles = catálogo
  // do cliente selecionado (vem do payload getCustomers). quickAddVehicleOpen abre o
  // drawer de cadastro rápido (sem perder a venda).
  const hasOficinaAuto = props.hasOficinaAuto === true;
  const [customerVehicles, setCustomerVehicles] = useState<VehicleOption[]>([]);
  const [quickAddVehicleOpen, setQuickAddVehicleOpen] = useState(false);

  // Cálculos de produtos
  const productSearchRef = useRef<HTMLDivElement>(null);
  // Paridade Edit — desconto % calcula sobre o bruto da linha; R$ é valor direto.
  const lineDiscountValue = (p: { quantity: number; unit_price: number; discount: number; discount_type: 'fixed' | 'percentage' }) =>
    p.discount_type === 'percentage'
      ? (p.quantity * p.unit_price * p.discount) / 100
      : p.discount;
  const subtotalProdutos = useMemo(
    () =>
      data.products.reduce((acc, p) => {
        const lineSubtotal = p.quantity * p.unit_price - lineDiscountValue(p);
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

  // Dor 1 Larissa — handler add produto.
  //   - Mesmo product_id + mesma variation_id  → incrementa qty (+1) na linha existente
  //   - Mesmo product_id + variation_id diferente → adiciona linha nova (sem toast —
  //     R6 2026-05-27 fechou popover de variações, Larissa escolheu explicitamente)
  //   - product_id novo → push linha nova
  const handleAddProduct = (p: ProductSearchResult) => {
    const incomingVariationId = p.variation_id ?? null;
    const existing = data.products.find(
      (row) => row.product_id === p.product_id,
    );

    // Caso 1a — mesmo product_id E mesma variation_id: incrementa qty da linha
    // existente. Fix Dor 1 Larissa (rollback V2 2026-05-13): MARTELO-P + MARTELO-P
    // criava 2 linhas distintas — agora vira 1 linha com qty 2.
    if (existing && existing.variation_id === incomingVariationId) {
      const newQty = Number(existing.quantity) + 1;
      setData(
        'products',
        data.products.map((row) =>
          row.product_id === p.product_id && row.variation_id === incomingVariationId
            ? { ...row, quantity: newQty }
            : row,
        ),
      );
      toast.success(`${p.name} — quantidade ${newQty}`, { duration: 1500 });
      return;
    }

    // Caso 1b/2 — product_id novo OU variação diferente: adiciona linha nova.
    // Larissa @ Rota Livre (vestuário) reportou 2026-05-27: "tamanho não aparece
    // e SKU sem código". Causa: handleAddProduct salvava só `name` (sem variation)
    // e `sku` do produto base. Fix: incluir `variation` no state + usar `sub_sku`
    // da variação no display (SKU da variação ≠ SKU do produto).
    const hasVariation = p.variation !== undefined && p.variation !== null && p.variation !== '';
    setData('products', [
      ...data.products,
      {
        product_id: p.product_id,
        variation_id: incomingVariationId,
        name: p.name,
        variation: hasVariation ? p.variation ?? null : null,
        sku: hasVariation ? p.sub_sku ?? p.sku : p.sku,
        quantity: 1,
        unit_price: Number(p.selling_price ?? 0),
        discount: 0,
        discount_type: 'fixed' as const,
        imei_number: '',
      },
    ]);
  };

  // Bug R3 (E) — recalcular preço retroativo ao trocar Grupo de preço.
  //
  // Cenário Larissa: adiciona "Blusa P" (R$ [redacted Tier 0] padrão), depois muda Grupo → ATACADO.
  // Antes do fix: linha continuava R$ [redacted Tier 0] (preço congelado no add).
  // Depois do fix: refetch /products/list?term=<name>&location_id=X&price_group=Y
  //                pra cada linha, atualiza unit_price com variation_group_price
  //                (legacy pattern public/js/pos.js linhas 265-266).
  //
  // Failsafe: se refetch falha ou variation_id não aparece no resultado, MANTÉM
  // preço atual (sem warning visual — só console.warn). Não quebra fluxo.
  //
  // 1 request HTTP por linha — OK pra primeiro fix (Larissa raramente tem >5 itens).
  // Batch/debounce fica pra otimização futura se necessário.
  //
  // R3 escopo SÓ no handler de price_group_id. Não toca __number_uf, handleAddProduct,
  // ou submit (PR R4 outro agent vai trabalhar handler add).
  const handlePriceGroupChange = async (newGroupId: number | null) => {
    setData('price_group_id', newGroupId);

    // Sem linhas adicionadas → nada a refetchar
    if (data.products.length === 0) return;

    // Sem location → endpoint /products/list não filtra direito (skip refetch)
    if (!data.location_id) {
      console.warn('[Sells/Create] price group changed sem location_id, skip recalc');
      return;
    }

    // Refetch concorrente pra cada linha. Promise.all com Settled pra não quebrar
    // tudo se 1 falhar.
    const requests = data.products.map(async (line) => {
      if (!line.variation_id) return null; // linha sem variation → skip
      const params = new URLSearchParams({ term: line.name });
      params.set('location_id', String(data.location_id));
      if (newGroupId) params.set('price_group', String(newGroupId));

      try {
        const res = await fetch(`/products/list?${params.toString()}`, {
          headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          credentials: 'same-origin',
        });
        if (!res.ok) return null;
        const arr = (await res.json()) as Array<{
          variation_id: number;
          selling_price?: number;
          variation_group_price?: number;
        }>;
        const match = Array.isArray(arr)
          ? arr.find((r) => r.variation_id === line.variation_id)
          : undefined;
        if (!match) return null;
        // Pattern legacy public/js/pos.js: variation_group_price tem prioridade
        const newPrice =
          match.variation_group_price !== undefined &&
          match.variation_group_price !== null
            ? Number(match.variation_group_price)
            : Number(match.selling_price ?? line.unit_price);
        return { variation_id: line.variation_id, unit_price: newPrice };
      } catch (err) {
        console.warn(
          '[Sells/Create] refetch preço falhou pra linha',
          line.variation_id,
          err,
        );
        return null;
      }
    });

    const results = await Promise.all(requests);

    // Aplica atualizações em batch (1 setData só, evita N re-renders)
    const updated = data.products.map((line) => {
      const found = results.find(
        (r) => r && r.variation_id === line.variation_id,
      );
      if (!found) return line; // failsafe: mantém preço atual
      return { ...line, unit_price: found.unit_price };
    });
    setData('products', updated);
  };

  // R8 (2026-05-28) — Bug 2 hotfix Larissa "preço diferenciado" ainda aberto.
  // Quando troca cliente, auto-aplica:
  //   - selling_price_group_id (recalcula unit_price do carrinho via handlePriceGroupChange)
  //   - pay_term_number/pay_term_type (cliente VIP "30 dias" não estava puxando)
  //   - shipping_address (endereço de entrega pré-fill)
  //
  // Backend ContactController@getCustomers já devolve TODOS estes campos (linhas
  // 2150-2176). Antes deste fix, o handler descartava — só salvava contact_id.
  // Paridade Blade `customer_id.on('change')` em public/js/pos.js.
  //
  // shipping_address vive em data.shipping.address (nested) — setData usa dot path.
  const handleCustomerSelect = (c: CustomerSearchResult) => {
    setData('contact_id', c.id);
    if (c.pay_term_number !== null && c.pay_term_number !== undefined && c.pay_term_number !== '') {
      setData('pay_term_number', String(c.pay_term_number));
    }
    if (c.pay_term_type === 'days' || c.pay_term_type === 'months') {
      setData('pay_term_type', c.pay_term_type);
    }
    // Shipping address — `data.shipping.address` é nested. Usa spread pra preservar
    // outros campos (details, cost, status, deliver_to) que user pode ter editado.
    if (c.shipping_address && c.shipping_address !== '') {
      setData('shipping', { ...data.shipping, address: c.shipping_address });
    }
    // Grupo de preço — `handlePriceGroupChange` já existe (R3) e recalcula linhas.
    // null/undefined → não muda (preserva default já aplicado).
    if (c.selling_price_group_id !== null && c.selling_price_group_id !== undefined) {
      void handlePriceGroupChange(c.selling_price_group_id);
    }
    // ADR 0251 — catálogo de veículos do cliente alimenta o seletor. Auto-seleciona
    // quando o cliente tem 1 veículo só (caso comum oficina); senão deixa o vendedor
    // escolher. Troca de cliente reseta o veículo (não vaza veículo de outro dono).
    const vs = c.vehicles ?? [];
    setCustomerVehicles(vs);
    setData('vehicle_id', vs.length === 1 ? vs[0].id : null);
  };

  // R8 — reset ao limpar cliente. Volta pros defaults (walk-in + props default).
  const handleCustomerClear = () => {
    setData('contact_id', props.walkInCustomer.id);
    setData('pay_term_number', '');
    setData('pay_term_type', 'days');
    // ADR 0251 — sem cliente, não há catálogo de veículos.
    setCustomerVehicles([]);
    setData('vehicle_id', null);
    // shipping fica como user editou — não auto-limpa (pode ser endereço manual)
    // price_group volta ao default do business
    if (props.defaultPriceGroupId !== data.price_group_id) {
      void handlePriceGroupChange(props.defaultPriceGroupId);
    }
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

  // Paridade Edit — toggle R$/% por linha (discount_type é union, não number).
  const handleProductDiscountType = (idx: number, type: 'fixed' | 'percentage') => {
    const next = [...data.products];
    next[idx] = { ...next[idx], discount_type: type };
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
  // Wagner 2026-05-27: removida exigência `Math.abs(totalPago - totalGeral) < 0.01`.
  // Venda a prazo / fiado: Larissa @ Rota Livre vende cliente leva produto + paga
  // depois. Backend cria venda com payment_status=due automaticamente quando
  // totalPago < totalGeral. Indicador no rodapé mostra saldo devedor (falta /
  // exato / troco), mas botão Salvar habilita pra qualquer combinação válida.
  // Paridade Blade legacy POS que sempre permitiu finalizar venda sem pagamento.
  const canSubmit =
    !processing &&
    data.products.length > 0 &&
    data.location_id !== null &&
    !discountError;

  const handleSubmit = (withPrint = false) => {
    if (!canSubmit) return;

    // R9 (2026-05-28) — guard transaction_date drift +2h47.
    //
    // Cenário catalogado em session log 2026-05-27: Larissa salvou venda 18:00
    // mas DB gravou transaction_date=20:47 (+2h47 drift = tempo ficou na tela).
    // Root cause provável: input chega vazio no POST (user limpou o input, ou
    // toDatetimeLocal não casou formato AM/PM, ou state perdeu durante sub-views)
    // → SellPosController@store:435 fallback `\Carbon::now()` sobrescreve com
    // hora do MOMENTO do submit (não da abertura).
    //
    // Fix preventivo: validar transaction_date ANTES do POST. Se vazio/inválido,
    // re-aplica defaultDatetime (sempre válido do backend) + console.warn pra
    // rastreabilidade.
    const TX_DATE_RE = /^\d{2}\/\d{2}\/\d{4}\s+\d{2}:\d{2}/;
    if (!data.transaction_date || !TX_DATE_RE.test(data.transaction_date)) {
      console.warn(
        '[Sells/Create] transaction_date inválido no submit:',
        JSON.stringify(data.transaction_date),
        '— recuperando defaultDatetime:',
        JSON.stringify(props.defaultDatetime),
      );
      setData('transaction_date', props.defaultDatetime);
    }

    // Transform: mapeia state UX-friendly do React pra payload que SellPosController@store
    // espera (Blade legacy field names + flat shipping + is_direct_sale flag obrigatório).
    // Refs: SellPosController@store linhas 352-680 + sell/create.blade.php Form::* fields.
    transform((d) => ({
      ...d,
      // R9 anti-drift — fallback em camada de transform (defesa em profundidade).
      // Se setData acima não flushou ainda (race com Inertia post), garante valor.
      transaction_date:
        d.transaction_date && TX_DATE_RE.test(d.transaction_date)
          ? d.transaction_date
          : props.defaultDatetime,
      // Flag CRÍTICO: sem is_direct_sale=1, controller cai em cashRegister check (linha 364).
      is_direct_sale: 1,
      is_save_and_print: withPrint ? 1 : 0,
      // Rename pra Blade legacy convention
      payment: d.payments,
      commission_agent: d.commission_agent_id,
      price_group: d.price_group_id,
      sale_note: d.notes,
      additional_notes: d.notes,
      // Paridade Edit — nota interna equipe + assinatura recorrente (Blade legacy).
      staff_note: d.staff_note,
      is_recurring: d.is_recurring ? 1 : 0,
      // Paridade Edit — endereço de cobrança ≠ entrega (Blade legacy).
      customer_secondary_address: d.customer_secondary_address,
      // Paridade Edit — documento anexo. Inertia auto-detecta File e usa
      // FormData só quando há anexo (caminho JSON comum intacto sem documento).
      // Backend: SellPosController@store:586 uploadFile($request,'sell_document').
      sell_document: d.sell_document,
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
      // Total calculado client-side (backend re-calcula via productUtil mas evita 422).
      // INCIDENTE 2026-06-05: desconto percentual gera total fracionado de 5 casas
      // (ex 204.99605). Enviar arredondado a 2 casas evita float "204.99605" que o
      // num_uf pt-BR do backend interpretava como milhar e inflava p/ 20.499.605.
      final_total: Math.round(totalGeral * 100) / 100,
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
        line_discount_type: p.discount_type ?? 'fixed',
        line_discount_amount: p.discount,
        // Paridade Edit — IMEI/serial opcional por linha (Blade legacy).
        imei_number: p.imei_number ?? '',
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
        // Venda BLOQUEADA pelo store (limite de crédito, estoque/compra, etc).
        // O backend retorna back()->withErrors(['venda' => msg]) em vez de
        // redirecionar pra lista — assim o carrinho fica intacto e o operador
        // corrige sem perder a venda. Mostramos o motivo em toast (8s).
        if (errs.venda) {
          toast.error(errs.venda, { duration: 8000 });
        }
        // Erro POR ITEM (estoque/compra: chave 'item.{variation_id}') → rola
        // pra seção de Produtos, onde a linha já fica contornada em vermelho.
        const hasItemError = Object.keys(errs).some((k) => k.startsWith('item.'));
        if (hasItemError) {
          document.getElementById('sec-produtos')?.scrollIntoView({ behavior: 'smooth' });
          return;
        }
        // Senão, rola pro topo da primeira seção com erro pra Wagner ver feedback.
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

  // US-SELL-007 — Atalho `/` top-level: foca busca de produto (canon Cockpit list/form).
  // PR-FIX-2 audit 2026-05-15 — elimina débito "em breve" do empty state e cumpre
  // promessa do charter §UX Targets. Não dispara quando user está digitando em
  // input/textarea/select/contentEditable (evita roubar `/` em campo de busca/notas).
  useEffect(() => {
    const onSlash = (e: KeyboardEvent) => {
      if (e.key !== '/' || e.metaKey || e.ctrlKey || e.altKey) return;
      const active = document.activeElement as HTMLElement | null;
      const tag = active?.tagName.toLowerCase();
      if (
        tag === 'input' ||
        tag === 'textarea' ||
        tag === 'select' ||
        active?.isContentEditable
      ) {
        return;
      }
      e.preventDefault();
      const input = productSearchRef.current?.querySelector<HTMLInputElement>(
        'input[type="search"]',
      );
      input?.focus();
    };
    window.addEventListener('keydown', onSlash);
    return () => window.removeEventListener('keydown', onSlash);
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
  // Wagner 2026-05-27 HOTFIX: substituído window.confirm() nativo (botões cinza do browser,
  // destoa do resto da UI) por AlertDialog shadcn estilizado. Smoke prod 2026-05-27 (Larissa
  // achou estranho — UI inconsistente). Mantém mesma semântica: OK→recupera, Cancelar→descarta.
  const recoveredRef = useRef(false);
  const [draftRecover, setDraftRecover] = useState<{ data: typeof data; savedAt: number; time: string } | null>(null);
  // 2026-06-04 (Wagner) — Cancelar com confirmação: se há venda montada
  // (produtos ou notas), pede confirmação antes de sair pra não perder tudo
  // num clique acidental. Carrinho vazio sai direto (sem fricção).
  const [cancelConfirm, setCancelConfirm] = useState(false);
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
      setDraftRecover({ data: parsed.data, savedAt: parsed.savedAt, time });
    } catch {
      // Draft corrompido — descartar silenciosamente.
      try {
        localStorage.removeItem(draftKey);
      } catch {
        // ignore
      }
    }
     
  }, [draftKey]);

  const handleDraftRecover = () => {
    // Draft não guarda File — restaura preservando sell_document=null.
    if (draftRecover) setData({ ...draftRecover.data, sell_document: null });
    setDraftRecover(null);
  };

  const handleDraftDiscard = () => {
    if (draftKey) {
      try { localStorage.removeItem(draftKey); } catch { /* ignore */ }
    }
    setDraftRecover(null);
  };

  // Cancelar: confirma se há trabalho a perder (produtos ou notas), senão sai direto.
  const handleCancelClick = () => {
    if (data.products.length > 0 || (data.notes ?? '').trim() !== '') {
      setCancelConfirm(true);
      return;
    }
    router.visit('/sells');
  };

  // Auto-save debounced 500ms quando data mudar (após mount).
  useEffect(() => {
    if (!draftKey || !recoveredRef.current) return;
    const t = setTimeout(() => {
      try {
        // File (sell_document) não serializa em JSON — exclui do draft.
        const { sell_document: _file, ...draftData } = data;
        localStorage.setItem(draftKey, JSON.stringify({ data: draftData, savedAt: Date.now() }));
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
    <div className="flex-1 bg-muted/30 flex flex-col">
      {/* Header sticky no topo + abas seção (pattern Office/OS canon) */}
      <div className="sticky top-0 z-30 bg-background/95 backdrop-blur border-b border-border">
        <div className="container mx-auto px-8 pt-6 pb-3 max-w-7xl">
          <div className="flex items-start gap-4">
            <div className="flex-1 min-w-0">
              <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                Adicionar venda
              </h1>
              <p className="text-sm text-muted-foreground mt-1 leading-relaxed">
                Registre uma venda completa — cliente, produtos, pagamento e frete.
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
              // Desconto (card Resumo) antes do Pagamento — aplica desconto e vê o total
              // correto ANTES de lançar a parte financeira (Wagner 2026-06-18).
              { id: 'sec-resumo', label: 'Resumo', icon: Receipt, count: undefined },
              { id: 'sec-pagamento', label: 'Pagamento', icon: CreditCard, count: undefined },
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
        <div className="rounded-lg border border-border bg-background p-6 shadow-sm">
          <div className="text-[11px] font-semibold uppercase tracking-widest text-muted-foreground">
            Itens
          </div>
          <div className="text-4xl font-semibold tabular-nums text-foreground mt-3">
            {itensCount}
          </div>
        </div>
        <div className="rounded-lg border border-border bg-background p-6 shadow-sm">
          <div className="text-[11px] font-semibold uppercase tracking-widest text-muted-foreground">
            Total venda
          </div>
          <div className="text-3xl font-semibold tabular-nums text-foreground mt-3">
            {formatBRL(totalGeral)}
          </div>
        </div>
        <div className="rounded-lg border border-border bg-background p-6 shadow-sm">
          <div className="text-[11px] font-semibold uppercase tracking-widest text-muted-foreground">
            Pago
          </div>
          <div className="text-3xl font-semibold tabular-nums text-foreground mt-3">
            {formatBRL(totalPago)}
          </div>
        </div>
        {/*
          G1 (tela-venda-arte 2026-05-31) — board flagou "cor crua" aqui, mas
          AVALIADO e MANTIDO de propósito: amber/blue/emerald é SEMÂNTICA DE STATUS
          de pagamento (falta / troco / exato). O projeto mantém cores de status
          intocadas por convenção (resources/css/cowork-payment-gateway-bundle.css:12)
          e o charter lista "tone semântico" como Goal. Tokenizar (--success/--warning)
          é decisão de fundações DS-v3 (ADR), não cleanup desta tela.
        */}
        <div
          className={
            'rounded-lg border p-6 shadow-sm ' +
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
                    : 'text-success')
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
              onSelect={handleCustomerSelect}
              onClear={handleCustomerClear}
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
              value={toDatetimeLocal(data.transaction_date) || nowLocalIso()}
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

          {/* ADR 0251 — Veículo na venda direta de oficina. Só aparece quando
              OficinaAuto habilitado pro business (vestuário/ROTA LIVRE não vê).
              Consome contact.vehicles[]; plaquinha Mercosul reusada do OficinaAuto. */}
          {hasOficinaAuto && (
            <div className="space-y-2 md:col-span-2 lg:col-span-4">
              <Label className="flex items-center gap-2">
                <Truck className="h-4 w-4 text-muted-foreground" />
                Veículo
                {data.vehicle_id !== null && (
                  <span className="rounded bg-primary/10 px-1.5 text-[10px] font-medium text-primary">
                    selecionado
                  </span>
                )}
              </Label>

              {data.contact_id !== props.walkInCustomer.id ? (
                <div className="flex flex-wrap items-center gap-2">
                  {customerVehicles.map((v) => {
                    const selected = data.vehicle_id === v.id;
                    const typeLabel = (props.vehicleTypes ?? {})[v.vehicle_type ?? ''] ?? v.vehicle_type ?? '';
                    return (
                      <button
                        key={v.id}
                        type="button"
                        onClick={() => setData('vehicle_id', selected ? null : v.id)}
                        aria-pressed={selected}
                        className={
                          'flex items-center gap-2 rounded-md border p-2 text-left transition-colors ' +
                          (selected
                            ? 'border-primary bg-primary/5 ring-1 ring-primary'
                            : 'border-border hover:bg-muted/50')
                        }
                      >
                        <MercosulPlate plate={v.plate} size="sm" />
                        {v.secondary_plate && <MercosulPlate plate={v.secondary_plate} size="sm" />}
                        {typeLabel && (
                          <span className="text-xs text-muted-foreground pr-1">{typeLabel}</span>
                        )}
                      </button>
                    );
                  })}

                  <button
                    type="button"
                    onClick={() => setQuickAddVehicleOpen(true)}
                    className="flex items-center gap-1.5 rounded-md border border-dashed border-border p-2 text-sm text-muted-foreground transition-colors hover:bg-muted/50"
                  >
                    <Plus className="h-4 w-4" />
                    Cadastrar veículo
                  </button>
                </div>
              ) : (
                <p className="text-xs text-muted-foreground">
                  Selecione o cliente pra escolher ou cadastrar o veículo do atendimento.
                </p>
              )}

              {data.contact_id !== props.walkInCustomer.id && customerVehicles.length === 0 && (
                <p className="text-xs text-muted-foreground">
                  Cliente sem veículo cadastrado — use <b>Cadastrar veículo</b>.
                </p>
              )}
            </div>
          )}
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
                      p.quantity * p.unit_price - lineDiscountValue(p),
                      0,
                    );
                    // Erro POR ITEM vindo do backend (estoque/compra insuficiente):
                    // contorna a linha exata + mostra o motivo embaixo do produto,
                    // em vez de só um aviso genérico no topo. 2026-06-04 (Wagner).
                    const itemError =
                      p.variation_id != null
                        ? (errors as Record<string, string>)['item.' + p.variation_id]
                        : undefined;
                    return (
                      <tr
                        key={`${p.product_id}-${p.variation_id}-${idx}`}
                        className={itemError ? 'bg-destructive/5' : undefined}
                      >
                        <td
                          className={
                            'px-3 py-2 align-top' +
                            (itemError ? ' border-l-2 border-destructive' : '')
                          }
                        >
                          <div className="font-medium text-foreground">
                            {p.name}
                            {p.variation && <> — <span className="text-muted-foreground">{p.variation}</span></>}
                          </div>
                          <div className="text-xs text-muted-foreground">SKU {p.sku}</div>
                          {/* Paridade Edit — IMEI/serial inline opcional por linha. */}
                          <Input
                            type="text"
                            value={p.imei_number ?? ''}
                            onChange={(e) => {
                              const next = [...data.products];
                              next[idx] = { ...next[idx], imei_number: e.target.value };
                              setData('products', next);
                            }}
                            placeholder="IMEI / nº série (opcional)"
                            className="mt-1 h-7 text-xs"
                            aria-label={`IMEI ou número de série de ${p.name}`}
                          />
                          {itemError && (
                            <div className="mt-1 flex items-start gap-1 text-xs font-medium text-destructive">
                              <AlertTriangle className="h-3.5 w-3.5 shrink-0 mt-px" />
                              <span>{itemError}</span>
                            </div>
                          )}
                        </td>
                        <td className="px-3 py-2">
                          {/* NumericInputPtBR — paridade Blade __read_number/__write_number.
                              Bug origem R$ [redacted Tier 0]k Larissa (2026-05-27): type=number+Number(...) virou parser pt-BR-safe. */}
                          <NumericInputPtBR
                            value={p.quantity}
                            onChange={(n) => handleProductChange(idx, 'quantity', n)}
                            precision={0}
                            aria-label={`Quantidade de ${p.name}`}
                            className="h-8 tabular-nums"
                          />
                        </td>
                        <td className="px-3 py-2">
                          <NumericInputPtBR
                            value={p.unit_price}
                            onChange={(n) => handleProductChange(idx, 'unit_price', n)}
                            precision={2}
                            disabled={!props.permissions.editPrice}
                            aria-label={`Preço unitário de ${p.name}`}
                            className="h-8 tabular-nums"
                          />
                        </td>
                        <td className="px-3 py-2">
                          {/* Paridade Edit — input desconto + toggle R$/% per-line. */}
                          <div className="flex items-center gap-1">
                            <NumericInputPtBR
                              value={p.discount}
                              onChange={(n) => handleProductChange(idx, 'discount', n)}
                              precision={2}
                              disabled={!props.permissions.editDiscount}
                              aria-label={`Desconto em ${p.name}`}
                              className="h-8 tabular-nums"
                            />
                            <Select
                              value={p.discount_type}
                              onValueChange={(v) =>
                                handleProductDiscountType(idx, v as 'fixed' | 'percentage')
                              }
                              disabled={!props.permissions.editDiscount}
                            >
                              <SelectTrigger
                                size="sm"
                                aria-label={`Tipo de desconto de ${p.name}: R$ ou %`}
                                className="h-8 w-14 text-xs"
                              >
                                <SelectValue />
                              </SelectTrigger>
                              <SelectContent>
                                <SelectItem value="fixed">R$</SelectItem>
                                <SelectItem value="percentage">%</SelectItem>
                              </SelectContent>
                            </Select>
                          </div>
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
                    {/* Total de itens no próprio card — evita rolar até o KPI do topo (Wagner 2026-06-18). */}
                    <td className="px-3 py-2 text-muted-foreground tabular-nums">
                      {itensCount} {itensCount === 1 ? 'item' : 'itens'}
                    </td>
                    <td colSpan={3} className="px-3 py-2 text-right font-medium text-foreground">
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
                    : 'border-success/20 bg-success-soft text-success-fg')
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
                  onValueChange={(v) => handlePriceGroupChange(v ? Number(v) : null)}
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

          {/* Paridade Edit — Nota interna (equipe) + Assinatura recorrente */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label htmlFor="staff_note">Nota interna (equipe)</Label>
              <Textarea
                id="staff_note"
                value={data.staff_note}
                onChange={(e) => setData('staff_note', e.target.value)}
                placeholder="Observação visível só pra equipe — não aparece no recibo."
                rows={2}
              />
            </div>
            <div className="flex items-start gap-2 pt-7">
              <Checkbox
                id="is_recurring"
                checked={data.is_recurring === 1}
                onCheckedChange={(c) => setData('is_recurring', c === true ? 1 : 0)}
                className="mt-1"
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
          </div>

          {/* Paridade Edit — endereço de cobrança ≠ entrega (NF-e faturamento separado) */}
          <div className="space-y-1.5">
            <Label htmlFor="customer_secondary_address">
              Endereço de cobrança (se diferente de entrega)
            </Label>
            <Textarea
              id="customer_secondary_address"
              value={data.customer_secondary_address}
              onChange={(e) => setData('customer_secondary_address', e.target.value)}
              rows={2}
              placeholder="Deixe em branco se cobrança = entrega."
            />
            <p className="text-xs text-muted-foreground">
              Usado pra NF-e quando cliente solicita faturamento em endereço diferente.
            </p>
          </div>

          {/* Paridade Edit — Anexar documento (Blade legacy sell_document upload) */}
          <div className="space-y-1.5">
            <Label htmlFor="sell_document">Anexar documento (opcional)</Label>
            <input
              id="sell_document"
              type="file"
              accept=".pdf,.csv,.zip,.doc,.docx,.jpg,.jpeg,.png"
              onChange={(e) => {
                const file = e.target.files?.[0] ?? null;
                if (file && file.size > 5 * 1024 * 1024) {
                  toast.error('Arquivo maior que 5MB. Tente comprimir antes de enviar.');
                  e.target.value = '';
                  return;
                }
                setData('sell_document', file);
              }}
              className="mt-1 block w-full text-sm text-muted-foreground file:mr-3 file:py-1.5 file:px-3 file:rounded file:border file:border-input file:bg-background file:text-foreground file:text-xs hover:file:bg-muted"
            />
            <p className="text-xs text-muted-foreground">
              Aceita .pdf, .csv, .zip, .doc, .docx, .jpg, .png — máx 5MB.
            </p>
            {data.sell_document && (
              <p className="text-xs text-muted-foreground">
                Arquivo selecionado: <span className="font-medium text-foreground">{data.sell_document.name}</span>
              </p>
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
            ) : !canSubmit && data.location_id === null ? (
              <span>Selecione o local da venda</span>
            ) : pagamentoStatus === 'falta' ? (
              // Info-level (não bloqueia mais — venda a prazo permitida).
              // text-warning é token semântico canon (--color-warning).
              <span className="text-warning">
                Venda a prazo — saldo devedor {formatBRL(Math.abs(saldoPagamento))}
              </span>
            ) : pagamentoStatus === 'troco' ? (
              <span>Troco {formatBRL(saldoPagamento)}</span>
            ) : (
              <span className="hidden md:inline">Atalho: Ctrl+Enter pra salvar</span>
            )}
          </div>
          <div className="flex items-center gap-2 shrink-0">
            <Button variant="outline" onClick={handleCancelClick}>
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

      {/* Wagner 2026-05-27 HOTFIX: AlertDialog shadcn substitui window.confirm() nativo
          do browser pra auto-save de rascunho (US-SELL-007). Smoke prod 2026-05-27: UI
          consistente com resto da app, evita "alert cinza ugly" que destoava. */}
      <AlertDialog open={!!draftRecover} onOpenChange={(open) => { if (!open) handleDraftDiscard(); }}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Recuperar rascunho de venda?</AlertDialogTitle>
            <AlertDialogDescription>
              Encontramos um rascunho salvo automaticamente às <strong>{draftRecover?.time}</strong>.
              Você pode recuperar e continuar de onde parou ou descartar e começar do zero.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel onClick={handleDraftDiscard}>Descartar</AlertDialogCancel>
            <AlertDialogAction onClick={handleDraftRecover}>Recuperar</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      {/* 2026-06-04 (Wagner) — confirmar Cancelar quando há venda montada, pra
          o operador não perder tudo num clique acidental (autosave cobre F5,
          não navegação forçada). */}
      <AlertDialog open={cancelConfirm} onOpenChange={setCancelConfirm}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Descartar esta venda?</AlertDialogTitle>
            <AlertDialogDescription>
              Você montou produtos/observações nesta venda. Se sair agora, perde o
              que foi preenchido. Deseja realmente descartar e voltar pra lista?
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Continuar editando</AlertDialogCancel>
            <AlertDialogAction onClick={() => { router.visit('/sells'); }}>
              Descartar e sair
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      {/* ADR 0251 — cadastro rápido de veículo sem perder a venda (drawer lateral) */}
      {hasOficinaAuto && (
        <QuickAddVehicleSheet
          open={quickAddVehicleOpen}
          onClose={() => setQuickAddVehicleOpen(false)}
          contactId={data.contact_id !== props.walkInCustomer.id ? data.contact_id : null}
          vehicleTypes={props.vehicleTypes ?? {}}
          onCreated={(v) => {
            setCustomerVehicles((prev) => [v, ...prev.filter((x) => x.id !== v.id)]);
            setData('vehicle_id', v.id);
          }}
        />
      )}
    </div>
  );
}

// Persistent Layout — auto-mem preference_persistent_layouts.
SellsCreate.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
