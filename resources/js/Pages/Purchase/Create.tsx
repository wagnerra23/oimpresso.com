// @memcofre
//   tela: /purchases/create
//   module: Purchase (raiz UltimatePOS, não Modules/)
//   tipo: FORM CREATE (MWART Wave2 B5)
//   rules: R-PUR-001 (multi-tenant business_id Tier 0), R-PUR-002 (permitted_locations), R-PUR-003 (purchase.create)
//   adrs: 0104 (MWART), 0093 (Tier 0), 0114 (gate visual), 0149 (pattern reuse)
//
// Origem: Cowork "Compras" (prototipo-ui/prototipos/compras/visual-source.html).
// Runbook: memory/requisitos/Inventory/RUNBOOK-purchase-create.md
// Charter: ./Create.charter.md
// MVP1: dados gerais + itens (busca + tabela) + desconto/frete + total + notas. Pagamento V2.

import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import { router, useForm } from '@inertiajs/react';
import { useMemo, useState, type ReactNode } from 'react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Plus, Trash2, Search, ShoppingCart, ArrowLeft, Save } from 'lucide-react';

// ---------- Tipos ----------

type DiscountType = 'fixed' | 'percentage';
type PurchaseStatus = 'received' | 'pending' | 'ordered';

interface OptionMap {
  [id: number]: string;
}

interface TaxRate {
  id: number;
  name: string;
  amount: number;
}

interface CurrencyDetails {
  symbol: string;
  code: string;
  thousand_separator: string;
  decimal_separator: string;
  decimal: number;
}

interface Permissions {
  create_supplier: boolean;
  create_customer: boolean;
  edit_price: boolean;
  view_purchase_price: boolean;
}

interface PurchaseLineDraft {
  product_name: string;
  product_id: number | null;
  variation_id: number | null;
  unit_id: number | null;
  unit_name: string;
  quantity: number;
  pp_without_discount: number;
  discount_percent: number;
  item_tax: number;
  tax_id: number | null;
  purchase_price: number;
  purchase_price_inc_tax: number;
}

export interface PurchaseCreatePageProps {
  business_locations: OptionMap;
  taxes: TaxRate[];
  order_statuses: Record<string, string>;
  default_purchase_status: PurchaseStatus | null;
  payment_types: Record<string, string>;
  currency: CurrencyDetails;
  customer_groups: OptionMap;
  accounts: OptionMap;
  default_datetime: string;
  permissions: Permissions;
  common_settings: Record<string, unknown>;
}

// ---------- Helpers ----------

const brl = (v: number) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v ?? 0);

// ---------- Página ----------

function PurchaseCreate({
  business_locations,
  taxes,
  order_statuses,
  default_purchase_status,
  currency,
  permissions,
  default_datetime,
}: PurchaseCreatePageProps) {
  const [busca, setBusca] = useState('');
  const [linhas, setLinhas] = useState<PurchaseLineDraft[]>([]);

  const form = useForm({
    ref_no: '' as string,
    status: (default_purchase_status ?? 'pending') as PurchaseStatus,
    contact_id: '' as string,
    contact_name: '' as string,
    transaction_date: default_datetime,
    location_id: '' as string,
    discount_type: 'fixed' as DiscountType,
    discount_amount: 0,
    tax_id: '' as string,
    tax_amount: 0,
    shipping_charges: 0,
    additional_notes: '' as string,
    exchange_rate: 1,
    total_before_tax: 0,
    final_total: 0,
    purchases: [] as PurchaseLineDraft[],
  });

  // Totais reativos
  const totais = useMemo(() => {
    const subtotal = linhas.reduce(
      (acc, l) => acc + l.purchase_price_inc_tax * l.quantity,
      0
    );
    const descontoValor =
      form.data.discount_type === 'percentage'
        ? (subtotal * (form.data.discount_amount || 0)) / 100
        : form.data.discount_amount || 0;
    const totalImpostos = form.data.tax_amount || 0;
    const frete = form.data.shipping_charges || 0;
    const final = subtotal - descontoValor + totalImpostos + frete;
    return { subtotal, descontoValor, totalImpostos, frete, final };
  }, [
    linhas,
    form.data.discount_type,
    form.data.discount_amount,
    form.data.tax_amount,
    form.data.shipping_charges,
  ]);

  const adicionarLinhaVazia = () => {
    setLinhas((prev) => [
      ...prev,
      {
        product_name: busca || '',
        product_id: null,
        variation_id: null,
        unit_id: null,
        unit_name: 'un',
        quantity: 1,
        pp_without_discount: 0,
        discount_percent: 0,
        item_tax: 0,
        tax_id: null,
        purchase_price: 0,
        purchase_price_inc_tax: 0,
      },
    ]);
    setBusca('');
  };

  const removerLinha = (idx: number) => {
    setLinhas((prev) => prev.filter((_, i) => i !== idx));
  };

  const atualizarLinha = <K extends keyof PurchaseLineDraft>(
    idx: number,
    campo: K,
    valor: PurchaseLineDraft[K]
  ) => {
    setLinhas((prev) =>
      prev.map((l, i) => {
        if (i !== idx) return l;
        const next = { ...l, [campo]: valor };
        if (campo === 'pp_without_discount' || campo === 'discount_percent') {
          const base = Number(next.pp_without_discount) || 0;
          const desc = (Number(next.discount_percent) || 0) / 100;
          next.purchase_price = base * (1 - desc);
          next.purchase_price_inc_tax = next.purchase_price + (next.item_tax || 0);
        }
        return next;
      })
    );
  };

  const enviar = (e: React.FormEvent) => {
    e.preventDefault();
    form.transform((dados) => ({
      ...dados,
      purchases: linhas,
      total_before_tax: totais.subtotal,
      final_total: totais.final,
    }));
    form.post('/purchases', {
      forceFormData: true,
      preserveScroll: true,
    });
  };

  const localOptions = Object.entries(business_locations);

  return (
    <form onSubmit={enviar}>
      <PageHeader
        icon="shopping-cart"
        title="Nova compra"
        description="Registrar entrada de mercadoria — fornecedor, itens, totais."
        action={
          <div className="flex gap-2">
            <Button type="button" variant="outline" size="sm" onClick={() => router.visit('/purchases')}>
              <ArrowLeft className="h-4 w-4 mr-1" /> Cancelar
            </Button>
            <Button type="submit" size="sm" disabled={form.processing}>
              <Save className="h-4 w-4 mr-1" /> {form.processing ? 'Salvando…' : 'Salvar compra'}
            </Button>
          </div>
        }
      />

      {/* Card 1: dados gerais */}
      <Card className="mt-4">
        <CardHeader className="pb-2 pt-3 px-4">
          <h3 className="text-[11px] uppercase tracking-widest text-stone-500 font-medium">Dados gerais</h3>
        </CardHeader>
        <CardContent className="px-4 pb-4">
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
            <div>
              <Label htmlFor="location_id" className="text-[12px] text-stone-600">Filial *</Label>
              <select
                id="location_id"
                className="mt-1 w-full h-9 px-2 rounded-md border border-stone-200 text-[13px]"
                value={form.data.location_id}
                onChange={(e) => form.setData('location_id', e.target.value)}
                required
              >
                <option value="">Selecione uma filial…</option>
                {localOptions.map(([id, name]) => (
                  <option key={id} value={id}>{name}</option>
                ))}
              </select>
              {form.errors.location_id && <p className="text-rose-700 text-[11px] mt-1">{form.errors.location_id}</p>}
            </div>

            <div>
              <Label htmlFor="contact_id" className="text-[12px] text-stone-600">Fornecedor *</Label>
              <Input
                id="contact_id"
                placeholder="ID do fornecedor"
                className="mt-1 h-9 text-[13px]"
                value={form.data.contact_id}
                onChange={(e) => form.setData('contact_id', e.target.value)}
                required
              />
              {form.errors.contact_id && <p className="text-rose-700 text-[11px] mt-1">{form.errors.contact_id}</p>}
            </div>

            <div>
              <Label htmlFor="ref_no" className="text-[12px] text-stone-600">Ref. Nº</Label>
              <Input
                id="ref_no"
                placeholder="(auto-gerado se vazio)"
                className="mt-1 h-9 text-[13px]"
                value={form.data.ref_no}
                onChange={(e) => form.setData('ref_no', e.target.value)}
              />
            </div>

            <div>
              <Label htmlFor="transaction_date" className="text-[12px] text-stone-600">Data *</Label>
              <Input
                id="transaction_date"
                type="datetime-local"
                className="mt-1 h-9 text-[13px]"
                value={form.data.transaction_date.replace(' ', 'T').slice(0, 16)}
                onChange={(e) => form.setData('transaction_date', e.target.value.replace('T', ' '))}
                required
              />
              {form.errors.transaction_date && <p className="text-rose-700 text-[11px] mt-1">{form.errors.transaction_date}</p>}
            </div>

            <div>
              <Label htmlFor="status" className="text-[12px] text-stone-600">Status *</Label>
              <select
                id="status"
                className="mt-1 w-full h-9 px-2 rounded-md border border-stone-200 text-[13px]"
                value={form.data.status}
                onChange={(e) => form.setData('status', e.target.value as PurchaseStatus)}
                required
              >
                {Object.entries(order_statuses).map(([key, label]) => (
                  <option key={key} value={key}>{label}</option>
                ))}
              </select>
              {form.errors.status && <p className="text-rose-700 text-[11px] mt-1">{form.errors.status}</p>}
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Card 2: itens */}
      <Card className="mt-3">
        <CardHeader className="pb-2 pt-3 px-4 flex flex-row items-center gap-2">
          <h3 className="text-[11px] uppercase tracking-widest text-stone-500 font-medium flex-1">Itens da compra</h3>
          <span className="text-[11px] text-stone-500">{linhas.length} {linhas.length === 1 ? 'item' : 'itens'}</span>
        </CardHeader>
        <CardContent className="px-4 pb-4">
          <div className="flex gap-2 items-center mb-3">
            <div className="relative flex-1">
              <Search className="absolute left-2 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-stone-400" />
              <Input
                placeholder="Buscar produto pra adicionar…"
                className="pl-7 h-9 text-[13px]"
                value={busca}
                onChange={(e) => setBusca(e.target.value)}
                onKeyDown={(e) => {
                  if (e.key === 'Enter') {
                    e.preventDefault();
                    adicionarLinhaVazia();
                  }
                }}
              />
            </div>
            <Button type="button" size="sm" variant="outline" onClick={adicionarLinhaVazia}>
              <Plus className="h-4 w-4 mr-1" /> Adicionar item
            </Button>
          </div>

          {linhas.length === 0 ? (
            <div className="py-10 text-center">
              <ShoppingCart className="h-10 w-10 mx-auto text-stone-300 mb-2" />
              <p className="text-stone-500 text-sm">Nenhum item adicionado.</p>
              <p className="text-stone-400 text-[12px] mt-1">Busque um produto acima ou clique em "Adicionar item".</p>
            </div>
          ) : (
            <table className="w-full border-collapse">
              <thead>
                <tr className="text-[10px] uppercase tracking-widest text-stone-500 border-b border-stone-200 bg-stone-50/40">
                  <th className="px-2 py-2 text-left font-medium">Produto</th>
                  <th className="px-2 py-2 text-right font-medium w-20">Qtd</th>
                  <th className="px-2 py-2 text-right font-medium w-24">Custo unit.</th>
                  <th className="px-2 py-2 text-right font-medium w-20">Desc %</th>
                  <th className="px-2 py-2 text-right font-medium w-24">Subtotal</th>
                  <th className="px-2 py-2 w-10 text-right font-medium"></th>
                </tr>
              </thead>
              <tbody>
                {linhas.map((linha, idx) => (
                  <tr key={idx} className="h-11 text-[13px] border-b border-stone-100">
                    <td className="px-2">
                      <Input
                        className="h-8 text-[12.5px]"
                        value={linha.product_name}
                        onChange={(e) => atualizarLinha(idx, 'product_name', e.target.value)}
                        placeholder="Nome do produto"
                      />
                    </td>
                    <td className="px-2 text-right">
                      <Input
                        type="number"
                        step="0.01"
                        className="h-8 text-[12.5px] text-right tabular-nums"
                        value={linha.quantity}
                        onChange={(e) => atualizarLinha(idx, 'quantity', Number(e.target.value))}
                      />
                    </td>
                    <td className="px-2 text-right">
                      <Input
                        type="number"
                        step="0.01"
                        className="h-8 text-[12.5px] text-right tabular-nums"
                        value={linha.pp_without_discount}
                        onChange={(e) => atualizarLinha(idx, 'pp_without_discount', Number(e.target.value))}
                      />
                    </td>
                    <td className="px-2 text-right">
                      <Input
                        type="number"
                        step="0.01"
                        className="h-8 text-[12.5px] text-right tabular-nums"
                        value={linha.discount_percent}
                        onChange={(e) => atualizarLinha(idx, 'discount_percent', Number(e.target.value))}
                      />
                    </td>
                    <td className="px-2 text-right tabular-nums">
                      {brl(linha.purchase_price_inc_tax * linha.quantity)}
                    </td>
                    <td className="px-2 text-right">
                      <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        className="h-7 w-7 p-0 text-rose-600"
                        onClick={() => removerLinha(idx)}
                        title="Remover"
                      >
                        <Trash2 className="h-3.5 w-3.5" />
                      </Button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </CardContent>
      </Card>

      {/* Card 3: totais */}
      <div className="mt-3 grid grid-cols-1 md:grid-cols-2 gap-3">
        <Card>
          <CardHeader className="pb-2 pt-3 px-4">
            <h3 className="text-[11px] uppercase tracking-widest text-stone-500 font-medium">Descontos / Impostos / Frete</h3>
          </CardHeader>
          <CardContent className="px-4 pb-4 space-y-3">
            <div className="grid grid-cols-2 gap-3">
              <div>
                <Label className="text-[12px] text-stone-600">Tipo desconto</Label>
                <select
                  className="mt-1 w-full h-9 px-2 rounded-md border border-stone-200 text-[13px]"
                  value={form.data.discount_type}
                  onChange={(e) => form.setData('discount_type', e.target.value as DiscountType)}
                >
                  <option value="fixed">Valor fixo ({currency.symbol})</option>
                  <option value="percentage">Percentual (%)</option>
                </select>
              </div>
              <div>
                <Label className="text-[12px] text-stone-600">Valor desconto</Label>
                <Input
                  type="number"
                  step="0.01"
                  className="mt-1 h-9 text-[13px] tabular-nums"
                  value={form.data.discount_amount}
                  onChange={(e) => form.setData('discount_amount', Number(e.target.value))}
                />
              </div>
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div>
                <Label className="text-[12px] text-stone-600">Imposto global</Label>
                <select
                  className="mt-1 w-full h-9 px-2 rounded-md border border-stone-200 text-[13px]"
                  value={form.data.tax_id}
                  onChange={(e) => form.setData('tax_id', e.target.value)}
                >
                  <option value="">Nenhum</option>
                  {taxes.map((t) => (
                    <option key={t.id} value={t.id}>{t.name} ({t.amount}%)</option>
                  ))}
                </select>
              </div>
              <div>
                <Label className="text-[12px] text-stone-600">Frete ({currency.symbol})</Label>
                <Input
                  type="number"
                  step="0.01"
                  className="mt-1 h-9 text-[13px] tabular-nums"
                  value={form.data.shipping_charges}
                  onChange={(e) => form.setData('shipping_charges', Number(e.target.value))}
                />
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2 pt-3 px-4">
            <h3 className="text-[11px] uppercase tracking-widest text-stone-500 font-medium">Totais</h3>
          </CardHeader>
          <CardContent className="px-4 pb-4 text-[13px] space-y-1.5">
            <div className="flex justify-between"><span className="text-stone-500">Subtotal itens</span><span className="tabular-nums">{brl(totais.subtotal)}</span></div>
            <div className="flex justify-between"><span className="text-stone-500">Desconto</span><span className="tabular-nums text-rose-700">- {brl(totais.descontoValor)}</span></div>
            <div className="flex justify-between"><span className="text-stone-500">Impostos</span><span className="tabular-nums">+ {brl(totais.totalImpostos)}</span></div>
            {totais.frete > 0 && (
              <div className="flex justify-between"><span className="text-stone-500">Frete</span><span className="tabular-nums">+ {brl(totais.frete)}</span></div>
            )}
            <div className="flex justify-between border-t border-stone-200 pt-2 mt-2">
              <span className="font-semibold">Total final</span>
              <span className="tabular-nums font-semibold text-[15px]">{brl(totais.final)}</span>
            </div>
            {form.errors.final_total && <p className="text-rose-700 text-[11px] mt-1">{form.errors.final_total}</p>}
            {permissions.view_purchase_price === false && (
              <p className="text-stone-400 text-[11px] mt-2">Você não tem permissão pra ver preços de compra.</p>
            )}
          </CardContent>
        </Card>
      </div>

      {/* Card 4: notas */}
      <Card className="mt-3">
        <CardHeader className="pb-2 pt-3 px-4">
          <h3 className="text-[11px] uppercase tracking-widest text-stone-500 font-medium">Notas adicionais</h3>
        </CardHeader>
        <CardContent className="px-4 pb-4">
          <Textarea
            placeholder="Notas internas, instruções de recebimento, condições negociadas…"
            value={form.data.additional_notes}
            onChange={(e) => form.setData('additional_notes', e.target.value)}
            className="min-h-[80px] text-[13px]"
          />
        </CardContent>
      </Card>
    </form>
  );
}

PurchaseCreate.layout = (page: ReactNode) => (
  <AppShellV2
    title="Nova compra"
    breadcrumbItems={[
      { label: 'Compras', href: '/purchases' },
      { label: 'Nova' },
    ]}
  >
    {page}
  </AppShellV2>
);

export default PurchaseCreate;
