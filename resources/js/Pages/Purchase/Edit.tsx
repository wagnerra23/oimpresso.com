// @memcofre
//   tela: /purchases/{id}/edit
//   module: Purchase
//   tipo: FORM EDIT (MWART Wave2 B5)
//   rules: R-PUR-001 (Tier 0), R-PUR-005 (canBeEdited), R-PUR-006 (no return exists), R-PUR-007 (purchase.update)
//   adrs: 0104, 0093, 0114, 0149
//
// Runbook: memory/requisitos/Inventory/RUNBOOK-purchase-edit.md
// Charter: ./Edit.charter.md
//
// Reaproveita ~80% pattern do Create.tsx (props, layout, repeater, totais).
// Diferença: prop `purchase` pré-popula form + método PUT em vez de POST.

import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import { router, useForm } from '@inertiajs/react';
import { useMemo, useState, type ReactNode } from 'react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
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
  edit_price: boolean;
  view_purchase_price: boolean;
}

interface PurchaseLineDraft {
  id: number | null;
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

interface PurchaseEditPayload {
  id: number;
  ref_no: string;
  status: PurchaseStatus;
  contact_id: number;
  contact_name: string;
  transaction_date: string;
  location_id: number;
  discount_type: DiscountType;
  discount_amount: number;
  tax_id: number | null;
  tax_amount: number;
  shipping_charges: number;
  additional_notes: string | null;
  exchange_rate: number;
  total_before_tax: number;
  final_total: number;
  purchase_lines: PurchaseLineDraft[];
}

export interface PurchaseEditPageProps {
  purchase: PurchaseEditPayload;
  business_locations: OptionMap;
  taxes: TaxRate[];
  order_statuses: Record<string, string>;
  currency: CurrencyDetails;
  permissions: Permissions;
}

// ---------- Helpers ----------

const brl = (v: number) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v ?? 0);

// ---------- Página ----------

function PurchaseEdit({
  purchase,
  business_locations,
  taxes,
  order_statuses,
  currency,
  permissions,
}: PurchaseEditPageProps) {
  const [busca, setBusca] = useState('');
  const [linhas, setLinhas] = useState<PurchaseLineDraft[]>(
    purchase.purchase_lines.length > 0 ? purchase.purchase_lines : []
  );

  const form = useForm({
    _method: 'PUT',
    ref_no: purchase.ref_no ?? '',
    status: purchase.status,
    contact_id: String(purchase.contact_id ?? ''),
    transaction_date: purchase.transaction_date,
    location_id: String(purchase.location_id ?? ''),
    discount_type: purchase.discount_type ?? 'fixed',
    discount_amount: purchase.discount_amount ?? 0,
    tax_id: purchase.tax_id ? String(purchase.tax_id) : '',
    tax_amount: purchase.tax_amount ?? 0,
    shipping_charges: purchase.shipping_charges ?? 0,
    additional_notes: purchase.additional_notes ?? '',
    exchange_rate: purchase.exchange_rate ?? 1,
    total_before_tax: purchase.total_before_tax ?? 0,
    final_total: purchase.final_total ?? 0,
    purchases: linhas,
  });

  const totais = useMemo(() => {
    const subtotal = linhas.reduce((acc, l) => acc + l.purchase_price_inc_tax * l.quantity, 0);
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
        id: null,
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

  const removerLinha = (idx: number) => setLinhas((prev) => prev.filter((_, i) => i !== idx));

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
    form.post(`/purchases/${purchase.id}`, {
      forceFormData: true,
      preserveScroll: true,
    });
  };

  const localOptions = Object.entries(business_locations);

  return (
    <form onSubmit={enviar}>
      <PageHeader
        icon="shopping-cart"
        title={`Editar compra ${purchase.ref_no || '#' + purchase.id}`}
        description="Ajuste de campos da compra (dentro do período permitido)."
        action={
          <div className="flex gap-2">
            <Button type="button" variant="outline" size="sm" onClick={() => router.visit(`/purchases/${purchase.id}`)}>
              <ArrowLeft className="h-4 w-4 mr-1" /> Cancelar
            </Button>
            <Button type="submit" size="sm" disabled={form.processing}>
              <Save className="h-4 w-4 mr-1" /> {form.processing ? 'Salvando…' : 'Salvar alterações'}
            </Button>
          </div>
        }
      />

      <Card className="mt-4">
        <CardHeader className="pb-2 pt-3 px-4">
          <h3 className="text-[11px] uppercase tracking-widest text-stone-500 font-medium">Dados gerais</h3>
        </CardHeader>
        <CardContent className="px-4 pb-4">
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
            <div>
              <Label className="text-[12px] text-stone-600">Filial *</Label>
              <Select
                value={form.data.location_id || '__none__'}
                onValueChange={(v) => form.setData('location_id', v === '__none__' ? '' : v)}
                required
              >
                <SelectTrigger aria-label="Filial" className="mt-1 w-full text-[13px]">
                  <SelectValue placeholder="Selecione…" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="__none__">Selecione…</SelectItem>
                  {localOptions.map(([id, name]) => (
                    <SelectItem key={id} value={String(id)}>{name}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {form.errors.location_id && <p className="text-destructive text-[11px] mt-1">{form.errors.location_id}</p>}
            </div>

            <div>
              <Label className="text-[12px] text-stone-600">Fornecedor (ID) *</Label>
              <Input
                className="mt-1 h-9 text-[13px]"
                value={form.data.contact_id}
                onChange={(e) => form.setData('contact_id', e.target.value)}
                required
              />
              {form.errors.contact_id && <p className="text-destructive text-[11px] mt-1">{form.errors.contact_id}</p>}
            </div>

            <div>
              <Label className="text-[12px] text-stone-600">Ref. Nº</Label>
              <Input
                className="mt-1 h-9 text-[13px]"
                value={form.data.ref_no}
                onChange={(e) => form.setData('ref_no', e.target.value)}
              />
            </div>

            <div>
              <Label className="text-[12px] text-stone-600">Data *</Label>
              <Input
                type="datetime-local"
                className="mt-1 h-9 text-[13px]"
                value={(form.data.transaction_date || '').replace(' ', 'T').slice(0, 16)}
                onChange={(e) => form.setData('transaction_date', e.target.value.replace('T', ' '))}
                required
              />
            </div>

            <div>
              <Label className="text-[12px] text-stone-600">Status *</Label>
              <Select
                value={form.data.status}
                onValueChange={(v) => form.setData('status', v as PurchaseStatus)}
                required
              >
                <SelectTrigger aria-label="Status" className="mt-1 w-full text-[13px]">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {Object.entries(order_statuses).map(([key, label]) => (
                    <SelectItem key={key} value={key}>{label}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          </div>
        </CardContent>
      </Card>

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
                placeholder="Buscar produto…"
                className="pl-7 h-9 text-[13px]"
                value={busca}
                onChange={(e) => setBusca(e.target.value)}
                onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); adicionarLinhaVazia(); } }}
              />
            </div>
            <Button type="button" size="sm" variant="outline" onClick={adicionarLinhaVazia}>
              <Plus className="h-4 w-4 mr-1" /> Adicionar item
            </Button>
          </div>

          {linhas.length === 0 ? (
            <div className="py-10 text-center">
              <ShoppingCart className="h-10 w-10 mx-auto text-stone-300 mb-2" />
              <p className="text-stone-500 text-sm">Nenhum item.</p>
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
                      <Button type="button" size="sm" variant="ghost" className="h-7 w-7 p-0 text-destructive" onClick={() => removerLinha(idx)} title="Remover">
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

      <div className="mt-3 grid grid-cols-1 md:grid-cols-2 gap-3">
        <Card>
          <CardHeader className="pb-2 pt-3 px-4">
            <h3 className="text-[11px] uppercase tracking-widest text-stone-500 font-medium">Desconto / Impostos / Frete</h3>
          </CardHeader>
          <CardContent className="px-4 pb-4 space-y-3">
            <div className="grid grid-cols-2 gap-3">
              <div>
                <Label className="text-[12px] text-stone-600">Tipo desconto</Label>
                <Select
                  value={form.data.discount_type}
                  onValueChange={(v) => form.setData('discount_type', v as DiscountType)}
                >
                  <SelectTrigger aria-label="Tipo desconto" className="mt-1 w-full text-[13px]">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="fixed">Valor fixo ({currency.symbol})</SelectItem>
                    <SelectItem value="percentage">Percentual (%)</SelectItem>
                  </SelectContent>
                </Select>
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
                <Select
                  value={form.data.tax_id || '__none__'}
                  onValueChange={(v) => form.setData('tax_id', v === '__none__' ? '' : v)}
                >
                  <SelectTrigger aria-label="Imposto global" className="mt-1 w-full text-[13px]">
                    <SelectValue placeholder="Nenhum" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="__none__">Nenhum</SelectItem>
                    {taxes.map((t) => (
                      <SelectItem key={t.id} value={String(t.id)}>{t.name} ({t.amount}%)</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
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
            <div className="flex justify-between"><span className="text-stone-500">Desconto</span><span className="tabular-nums text-destructive">- {brl(totais.descontoValor)}</span></div>
            <div className="flex justify-between"><span className="text-stone-500">Impostos</span><span className="tabular-nums">+ {brl(totais.totalImpostos)}</span></div>
            {totais.frete > 0 && (
              <div className="flex justify-between"><span className="text-stone-500">Frete</span><span className="tabular-nums">+ {brl(totais.frete)}</span></div>
            )}
            <div className="flex justify-between border-t border-stone-200 pt-2 mt-2">
              <span className="font-semibold">Total final</span>
              <span className="tabular-nums font-semibold text-[15px]">{brl(totais.final)}</span>
            </div>
            {permissions.view_purchase_price === false && (
              <p className="text-stone-400 text-[11px] mt-2">Sem permissão pra ver preços.</p>
            )}
          </CardContent>
        </Card>
      </div>

      <Card className="mt-3">
        <CardHeader className="pb-2 pt-3 px-4">
          <h3 className="text-[11px] uppercase tracking-widest text-stone-500 font-medium">Notas adicionais</h3>
        </CardHeader>
        <CardContent className="px-4 pb-4">
          <Textarea
            value={form.data.additional_notes}
            onChange={(e) => form.setData('additional_notes', e.target.value)}
            className="min-h-[80px] text-[13px]"
          />
        </CardContent>
      </Card>
    </form>
  );
}

PurchaseEdit.layout = (page: ReactNode) => (
  <AppShellV2
    title="Editar compra"
    breadcrumbItems={[
      { label: 'Compras', href: '/purchases' },
      { label: 'Editar' },
    ]}
  >
    {page}
  </AppShellV2>
);

export default PurchaseEdit;
