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
import { Loader2, Plus, Search, Trash2 } from 'lucide-react';
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
  };
  posSettings: Record<string, unknown>;
  subType: string | null;
  statuses?: Record<string, string>;
  isOrderRequestEnabled?: boolean;
}

const ADVANCED_OPEN_KEY = 'oimpresso.sells.create.advanced.open';

// dropdownEntries movido pra _components/dropdownEntries.ts (utility shared local).

export default function SellsCreate(props: SellsCreatePageProps) {
  // Defaults conservadores ROTA LIVRE: status=final, transaction_date=format_now_local
  const { data, setData, processing } = useForm({
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

  const totalGeral = useMemo(
    () => Math.max(subtotalProdutos - descontoPedido + data.shipping.cost, 0),
    [subtotalProdutos, descontoPedido, data.shipping.cost],
  );

  const formatBRL = (value: number) =>
    value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

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

  return (
    <div className="container mx-auto p-6 space-y-6 max-w-7xl">
      <PageHeader icon="shopping-cart" title="Adicionar venda" />

      {/* Action bar sticky com KPIs + ações primárias.
          Adaptação do canon os-page.jsx (os-stats) pra tela de criação.
          Refs: sells-create-visual-comparison.md §1 Layout, §8 Componentes */}
      <div className="sticky top-0 z-30 -mx-6 px-6 py-3 bg-background/95 backdrop-blur border-b border-border">
        <div className="flex items-center gap-4 justify-between flex-wrap">
          <div className="flex items-center gap-6 text-sm">
            <div className="flex flex-col">
              <span className="text-xs text-muted-foreground">Itens</span>
              <span className="font-semibold tabular-nums text-foreground">{itensCount}</span>
            </div>
            <div className="flex flex-col">
              <span className="text-xs text-muted-foreground">Total venda</span>
              <span className="font-semibold tabular-nums text-foreground">
                {formatBRL(totalGeral)}
              </span>
            </div>
            <div className="flex flex-col">
              <span className="text-xs text-muted-foreground">
                Pago{' '}
                {totalGeral > 0 && (
                  <span
                    className={
                      pagamentoStatus === 'falta'
                        ? 'text-amber-600 dark:text-amber-400'
                        : pagamentoStatus === 'troco'
                          ? 'text-blue-600 dark:text-blue-400'
                          : 'text-emerald-600 dark:text-emerald-400'
                    }
                  >
                    ·{' '}
                    {pagamentoStatus === 'falta'
                      ? `falta ${formatBRL(Math.abs(saldoPagamento))}`
                      : pagamentoStatus === 'troco'
                        ? `troco ${formatBRL(saldoPagamento)}`
                        : 'OK'}
                  </span>
                )}
              </span>
              <span className="font-semibold tabular-nums text-foreground">
                {formatBRL(totalPago)}
              </span>
            </div>
          </div>
          <div className="flex gap-2">
            <Button variant="outline" size="sm" onClick={() => router.visit('/sells')}>
              Cancelar
            </Button>
            <Button size="sm" disabled={processing}>
              {processing && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
              {processing ? 'Salvando…' : 'Salvar venda'}
            </Button>
          </div>
        </div>
      </div>

      {/* Linha 1: Cliente + Data + Status + Local — 4 campos sempre visíveis */}
      <Card>
        <CardHeader>
          <CardTitle className="text-base">Dados da venda</CardTitle>
        </CardHeader>
        <CardContent className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <div className="space-y-1.5">
            <Label htmlFor="contact_id">Cliente</Label>
            <div className="relative">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none" />
              <Input
                id="contact_id"
                value={props.walkInCustomer.name}
                readOnly
                disabled
                placeholder="Buscar cliente por nome ou CPF/CNPJ…"
                className="pl-9"
                aria-label="Cliente da venda"
              />
            </div>
            <p className="text-xs text-muted-foreground">
              Autocomplete em breve. Hoje só cliente padrão.
            </p>
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="transaction_date">Data da venda</Label>
            <Input
              id="transaction_date"
              type="text"
              value={data.transaction_date}
              onChange={(e) => setData('transaction_date', e.target.value)}
              placeholder="DD/MM/AAAA HH:mm"
            />
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
          </div>
        </CardContent>
      </Card>

      {/* Bloco produtos — busca + tabela editável (US-SELL-005) */}
      <Card>
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
                          <button
                            type="button"
                            onClick={() => handleRemoveProduct(idx)}
                            aria-label={`Remover ${p.name}`}
                            className="text-muted-foreground hover:text-destructive p-1"
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

      {/* Bloco pagamentos — split de pagamento + indicador saldo (US-SELL-006) */}
      <Card>
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
      <Card>
        <CardHeader>
          <CardTitle className="text-base">Resumo</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div className="space-y-1.5">
              <Label htmlFor="discount_type">Tipo de desconto</Label>
              <Select
                value={data.discount_type}
                onValueChange={(v) =>
                  setData('discount_type', v as typeof data.discount_type)
                }
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
              <Label htmlFor="discount_amount">Valor do desconto</Label>
              <Input
                id="discount_amount"
                type="number"
                inputMode="decimal"
                step="0.01"
                value={data.discount_amount}
                onChange={(e) => setData('discount_amount', Number(e.target.value))}
                disabled={!props.permissions.editDiscount}
              />
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

      {/* Mais opções — 10 campos colapsáveis em <details> com persistência localStorage */}
      <details
        open={advancedOpen}
        onToggle={handleAdvancedToggle}
        className="rounded-lg border border-border bg-card"
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

      {/* Debug bloco — só pra Wagner inspecionar contract recebido. Removível em US-SELL-005. */}
      <details className="rounded-lg border border-dashed border-border bg-muted/30 p-4 text-sm">
        <summary className="cursor-pointer font-medium text-muted-foreground">
          Debug · contract recebido do controller
        </summary>
        <div className="mt-3 space-y-1 text-muted-foreground">
          <div>
            <strong>defaultLocation:</strong> {props.defaultLocation?.name ?? '—'}
          </div>
          <div>
            <strong>walkInCustomer:</strong> {props.walkInCustomer.name}
          </div>
          <div>
            <strong>defaultDatetime:</strong> {props.defaultDatetime}
          </div>
          <div>
            <strong>permissions:</strong> editPrice={String(props.permissions.editPrice)},
            editDiscount={String(props.permissions.editDiscount)}
          </div>
          <div>
            <strong>businessLocations:</strong> {Object.keys(props.businessLocations).length}{' '}
            opções
          </div>
          <div>
            <strong>paymentTypes:</strong> {Object.keys(props.paymentTypes).length} opções
          </div>
          <div>
            <strong>has commission agent:</strong> {String(hasCommissionAgent)}
          </div>
          <div>
            <strong>has multiple price groups:</strong> {String(hasMultiplePriceGroups)}
          </div>
          <div>
            <strong>has types of service:</strong> {String(hasTypesOfService)}
          </div>
        </div>
      </details>
    </div>
  );
}

// Persistent Layout — auto-mem preference_persistent_layouts.
SellsCreate.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
