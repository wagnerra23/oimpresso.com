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
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { Loader2, Search } from 'lucide-react';
import PageHeader from '@/Components/shared/PageHeader';
import EmptyState from '@/Components/shared/EmptyState';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
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

interface Tax {
  id: number;
  name: string;
  amount: number;
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
  taxes: Tax[];
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
      quantity: number;
      unit_price: number;
      discount: number;
    }>,
    payments: [] as Array<{
      amount: number;
      method: string;
      paid_on: string;
      account_id: number | null;
    }>,
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

  return (
    <div className="container mx-auto p-6 space-y-6 max-w-7xl">
      <PageHeader
        icon="shopping-cart"
        title="Adicionar venda"
        action={
          <div className="flex gap-2">
            <Button variant="outline" onClick={() => router.visit('/sells')}>
              Cancelar
            </Button>
            <Button disabled={processing}>
              {processing && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
              {processing ? 'Salvando…' : 'Salvar venda'}
            </Button>
          </div>
        }
      />

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
                {Object.entries(props.businessLocations).map(([id, name]) => (
                  <SelectItem key={id} value={id}>
                    {name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
        </CardContent>
      </Card>

      {/* Bloco produtos — placeholder até US-SELL-005 */}
      <Card>
        <CardHeader>
          <CardTitle className="text-base">Produtos</CardTitle>
        </CardHeader>
        <CardContent>
          <EmptyState
            icon="package"
            title="Nenhum produto ainda"
            description="Busca + tabela editável chegam em US-SELL-005."
          />
        </CardContent>
      </Card>

      {/* Bloco pagamentos — placeholder até US-SELL-006 */}
      <Card>
        <CardHeader>
          <CardTitle className="text-base">Pagamento</CardTitle>
        </CardHeader>
        <CardContent>
          <EmptyState
            icon="wallet"
            title="Nenhum pagamento adicionado"
            description="PaymentRow + cálculos chegam em US-SELL-006."
          />
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
                  {Object.entries(props.invoiceSchemes).map(([id, name]) => (
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
                  {props.taxes.map((tax) => (
                    <SelectItem key={tax.id} value={String(tax.id)}>
                      {tax.name} ({tax.amount}%)
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
                    {Object.entries(props.commissionAgents).map(([id, name]) => (
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
                    {Object.entries(props.priceGroups).map(([id, name]) => (
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
                    {Object.entries(props.shippingStatuses).map(([k, label]) => (
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
