// @memcofre
//   tela: /stock-transfers/create
//   module: Inventory / StockTransfer (raiz UltimatePOS)
//   tipo: FORM CREATE (MWART Wave2 B5)
//   rules: R-XFER-001 (Tier 0), R-XFER-004 (origem ≠ destino), R-XFER-005 (completed move estoque)
//   adrs: 0104, 0093, 0114, 0149
//
// Runbook: memory/requisitos/Inventory/RUNBOOK-stock-transfer-create.md
// Charter: ./Create.charter.md

import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import { router, useForm } from '@inertiajs/react';
import { useMemo, useState, type ReactNode } from 'react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Plus, Trash2, Search, Truck, ArrowLeft, Save, ArrowRight, AlertCircle } from 'lucide-react';

// ---------- Tipos ----------

interface OptionMap {
  [id: number]: string;
}

interface TransferLineDraft {
  product_name: string;
  product_id: number | null;
  variation_id: number | null;
  unit_id: number | null;
  unit_name: string;
  quantity: number;
  unit_price: number;
  enable_stock: 1 | 0;
}

interface Permissions {
  view_purchase_price: boolean;
  edit_price: boolean;
}

export interface StockTransferCreatePageProps {
  business_locations: OptionMap;
  statuses: Record<string, string>;
  default_datetime: string;
  permissions: Permissions;
}

// ---------- Helpers ----------

const brl = (v: number) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v ?? 0);

// ---------- Página ----------

function StockTransferCreate({
  business_locations,
  statuses,
  default_datetime,
  permissions,
}: StockTransferCreatePageProps) {
  const [busca, setBusca] = useState('');
  const [linhas, setLinhas] = useState<TransferLineDraft[]>([]);

  const form = useForm({
    transaction_date: default_datetime,
    ref_no: '',
    status: 'pending',
    location_id: '',
    transfer_location_id: '',
    additional_notes: '',
    shipping_charges: 0,
    final_total: 0,
    products: linhas,
  });

  const subtotal = useMemo(
    () => linhas.reduce((acc, l) => acc + l.quantity * l.unit_price, 0),
    [linhas]
  );

  const totalFinal = subtotal + (form.data.shipping_charges || 0);

  const adicionarLinha = () => {
    setLinhas((prev) => [
      ...prev,
      {
        product_name: busca || '',
        product_id: null,
        variation_id: null,
        unit_id: null,
        unit_name: 'un',
        quantity: 1,
        unit_price: 0,
        enable_stock: 1,
      },
    ]);
    setBusca('');
  };

  const removerLinha = (idx: number) => setLinhas((prev) => prev.filter((_, i) => i !== idx));

  const atualizarLinha = <K extends keyof TransferLineDraft>(
    idx: number,
    campo: K,
    valor: TransferLineDraft[K]
  ) => {
    setLinhas((prev) => prev.map((l, i) => (i !== idx ? l : { ...l, [campo]: valor })));
  };

  // R-XFER-004: origem ≠ destino — bloqueia submit
  const origemDestinoIguais =
    !!form.data.location_id && form.data.location_id === form.data.transfer_location_id;

  const enviar = (e: React.FormEvent) => {
    e.preventDefault();
    if (origemDestinoIguais) return;
    form.transform((dados) => ({
      ...dados,
      products: linhas,
      final_total: totalFinal,
    }));
    form.post('/stock-transfers', {
      forceFormData: true,
      preserveScroll: true,
    });
  };

  const localOptions = Object.entries(business_locations);

  return (
    <form onSubmit={enviar}>
      <PageHeader
        icon="truck"
        title="Nova transferência de estoque"
        description="Origem → Destino dentro da mesma empresa (multi-tenant Tier 0)."
        action={
          <div className="flex gap-2">
            <Button type="button" variant="outline" size="sm" onClick={() => router.visit('/stock-transfers')}>
              <ArrowLeft className="h-4 w-4 mr-1" /> Cancelar
            </Button>
            <Button type="submit" size="sm" disabled={form.processing || origemDestinoIguais}>
              <Save className="h-4 w-4 mr-1" /> {form.processing ? 'Salvando…' : 'Salvar transferência'}
            </Button>
          </div>
        }
      />

      <Card className="mt-4">
        <CardHeader className="pb-2 pt-3 px-4">
          <h3 className="text-[11px] uppercase tracking-widest text-stone-500 font-medium">Dados gerais</h3>
        </CardHeader>
        <CardContent className="px-4 pb-4">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div>
              <Label className="text-[12px] text-stone-600">Data *</Label>
              <Input
                type="datetime-local"
                className="mt-1 h-9 text-[13px]"
                value={form.data.transaction_date.replace(' ', 'T').slice(0, 16)}
                onChange={(e) => form.setData('transaction_date', e.target.value.replace('T', ' '))}
                required
              />
            </div>
            <div>
              <Label className="text-[12px] text-stone-600">Ref. Nº</Label>
              <Input
                className="mt-1 h-9 text-[13px]"
                placeholder="(auto-gerado)"
                value={form.data.ref_no}
                onChange={(e) => form.setData('ref_no', e.target.value)}
              />
            </div>
            <div>
              <Label className="text-[12px] text-stone-600">Status *</Label>
              <select
                className="mt-1 w-full h-9 px-2 rounded-md border border-stone-200 text-[13px]"
                value={form.data.status}
                onChange={(e) => form.setData('status', e.target.value)}
                required
              >
                {Object.entries(statuses).map(([key, label]) => (
                  <option key={key} value={key}>{label}</option>
                ))}
              </select>
            </div>
          </div>

          {/* Origem → Destino — destaque visual */}
          <div className="mt-4 p-3 rounded-md border border-stone-200 bg-stone-50/40">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-3 items-end">
              <div>
                <Label className="text-[12px] text-stone-600 flex items-center gap-1">
                  <span className="inline-flex h-5 w-5 items-center justify-center rounded bg-stone-200 text-[10px] font-semibold">DE</span>
                  Filial origem *
                </Label>
                <select
                  className="mt-1 w-full h-9 px-2 rounded-md border border-stone-200 text-[13px]"
                  value={form.data.location_id}
                  onChange={(e) => form.setData('location_id', e.target.value)}
                  required
                >
                  <option value="">Selecione filial origem…</option>
                  {localOptions.map(([id, name]) => (
                    <option key={id} value={id}>{name}</option>
                  ))}
                </select>
              </div>
              <div>
                <Label className="text-[12px] text-stone-600 flex items-center gap-1">
                  <ArrowRight className="h-3 w-3 text-stone-400" />
                  <span className="inline-flex h-5 w-5 items-center justify-center rounded bg-emerald-100 text-emerald-700 text-[10px] font-semibold">PARA</span>
                  Filial destino *
                </Label>
                <select
                  className="mt-1 w-full h-9 px-2 rounded-md border border-stone-200 text-[13px]"
                  value={form.data.transfer_location_id}
                  onChange={(e) => form.setData('transfer_location_id', e.target.value)}
                  required
                >
                  <option value="">Selecione filial destino…</option>
                  {localOptions
                    .filter(([id]) => id !== form.data.location_id)
                    .map(([id, name]) => (
                      <option key={id} value={id}>{name}</option>
                    ))}
                </select>
              </div>
            </div>
            {origemDestinoIguais && (
              <div className="mt-2 text-destructive-fg text-[12px] flex items-center gap-1">
                <AlertCircle className="h-3.5 w-3.5" />
                Origem e destino não podem ser iguais.
              </div>
            )}
          </div>
        </CardContent>
      </Card>

      <Card className="mt-3">
        <CardHeader className="pb-2 pt-3 px-4 flex flex-row items-center gap-2">
          <h3 className="text-[11px] uppercase tracking-widest text-stone-500 font-medium flex-1">Itens da transferência</h3>
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
                onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); adicionarLinha(); } }}
              />
            </div>
            <Button type="button" size="sm" variant="outline" onClick={adicionarLinha}>
              <Plus className="h-4 w-4 mr-1" /> Adicionar item
            </Button>
          </div>

          {linhas.length === 0 ? (
            <div className="py-10 text-center">
              <Truck className="h-10 w-10 mx-auto text-stone-300 mb-2" />
              <p className="text-stone-500 text-sm">Nenhum item adicionado.</p>
            </div>
          ) : (
            <table className="w-full border-collapse">
              <thead>
                <tr className="text-[10px] uppercase tracking-widest text-stone-500 border-b border-stone-200 bg-stone-50/40">
                  <th className="px-2 py-2 text-left font-medium">Produto</th>
                  <th className="px-2 py-2 text-right font-medium w-20">Qtd</th>
                  {permissions.view_purchase_price && (
                    <th className="px-2 py-2 text-right font-medium w-24">Custo unit.</th>
                  )}
                  {permissions.view_purchase_price && (
                    <th className="px-2 py-2 text-right font-medium w-24">Subtotal</th>
                  )}
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
                    {permissions.view_purchase_price && (
                      <td className="px-2 text-right">
                        <Input
                          type="number"
                          step="0.01"
                          className="h-8 text-[12.5px] text-right tabular-nums"
                          value={linha.unit_price}
                          onChange={(e) => atualizarLinha(idx, 'unit_price', Number(e.target.value))}
                        />
                      </td>
                    )}
                    {permissions.view_purchase_price && (
                      <td className="px-2 text-right tabular-nums">
                        {brl(linha.quantity * linha.unit_price)}
                      </td>
                    )}
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
            <h3 className="text-[11px] uppercase tracking-widest text-stone-500 font-medium">Frete + Total</h3>
          </CardHeader>
          <CardContent className="px-4 pb-4 space-y-3">
            <div>
              <Label className="text-[12px] text-stone-600">Frete (R$)</Label>
              <Input
                type="number"
                step="0.01"
                className="mt-1 h-9 text-[13px] tabular-nums"
                value={form.data.shipping_charges}
                onChange={(e) => form.setData('shipping_charges', Number(e.target.value))}
              />
            </div>
            {permissions.view_purchase_price && (
              <div className="space-y-1 text-[13px] pt-2 border-t border-stone-200">
                <div className="flex justify-between"><span className="text-stone-500">Subtotal itens</span><span className="tabular-nums">{brl(subtotal)}</span></div>
                <div className="flex justify-between"><span className="text-stone-500">Frete</span><span className="tabular-nums">+ {brl(form.data.shipping_charges)}</span></div>
                <div className="flex justify-between pt-2 mt-1 border-t border-stone-100">
                  <span className="font-semibold">Total</span>
                  <span className="tabular-nums font-semibold text-[15px]">{brl(totalFinal)}</span>
                </div>
              </div>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2 pt-3 px-4">
            <h3 className="text-[11px] uppercase tracking-widest text-stone-500 font-medium">Notas</h3>
          </CardHeader>
          <CardContent className="px-4 pb-4">
            <Textarea
              placeholder="Observações da transferência…"
              value={form.data.additional_notes}
              onChange={(e) => form.setData('additional_notes', e.target.value)}
              className="min-h-[80px] text-[13px]"
            />
          </CardContent>
        </Card>
      </div>
    </form>
  );
}

StockTransferCreate.layout = (page: ReactNode) => (
  <AppShellV2
    title="Nova transferência"
    breadcrumbItems={[
      { label: 'Estoque', href: '#' },
      { label: 'Transferências', href: '/stock-transfers' },
      { label: 'Nova' },
    ]}
  >
    {page}
  </AppShellV2>
);

export default StockTransferCreate;
