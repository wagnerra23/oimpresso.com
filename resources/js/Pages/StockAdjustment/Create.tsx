// @memcofre
//   tela: /stock-adjustments/create
//   module: Inventory / StockAdjustment (raiz UltimatePOS)
//   tipo: FORM CREATE (MWART Wave2 B5)
//   rules: R-ADJ-001 (Tier 0), R-ADJ-002 (type), R-ADJ-003 (recovered<=total), R-ADJ-004 (purchase.create)
//   adrs: 0104, 0093, 0114, 0149

import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import { router, useForm } from '@inertiajs/react';
import { useMemo, useState, type ReactNode } from 'react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Plus, Trash2, Search, PackageMinus, ArrowLeft, Save, AlertCircle } from 'lucide-react';

// ---------- Tipos ----------

type AdjustmentType = 'normal' | 'abnormal';

interface OptionMap {
  [id: number]: string;
}

interface AdjustmentLineDraft {
  product_name: string;
  product_id: number | null;
  variation_id: number | null;
  unit_name: string;
  quantity: number;
  unit_price: number;
  lot_no_line_id: number | null;
}

interface Permissions {
  view_purchase_price: boolean;
  edit_price: boolean;
}

export interface StockAdjustmentCreatePageProps {
  business_locations: OptionMap;
  default_datetime: string;
  permissions: Permissions;
}

// ---------- Helpers ----------

const brl = (v: number) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v ?? 0);

const TYPE_LABEL: Record<AdjustmentType, string> = {
  normal: 'Normal (correção)',
  abnormal: 'Anormal (perda/quebra)',
};

// ---------- Página ----------

function StockAdjustmentCreate({
  business_locations,
  default_datetime,
  permissions,
}: StockAdjustmentCreatePageProps) {
  const [busca, setBusca] = useState('');
  const [linhas, setLinhas] = useState<AdjustmentLineDraft[]>([]);

  const form = useForm({
    location_id: '',
    ref_no: '',
    transaction_date: default_datetime,
    adjustment_type: 'normal' as AdjustmentType,
    additional_notes: '',
    total_amount_recovered: 0,
    final_total: 0,
    products: linhas,
  });

  const totalFinal = useMemo(
    () => linhas.reduce((acc, l) => acc + l.quantity * l.unit_price, 0),
    [linhas]
  );

  // R-ADJ-003: recovered ≤ total
  const recuperadoExcede = (form.data.total_amount_recovered || 0) > totalFinal;

  const adicionarLinha = () => {
    setLinhas((prev) => [
      ...prev,
      {
        product_name: busca || '',
        product_id: null,
        variation_id: null,
        unit_name: 'un',
        quantity: 1,
        unit_price: 0,
        lot_no_line_id: null,
      },
    ]);
    setBusca('');
  };

  const removerLinha = (idx: number) => setLinhas((prev) => prev.filter((_, i) => i !== idx));

  const atualizarLinha = <K extends keyof AdjustmentLineDraft>(
    idx: number,
    campo: K,
    valor: AdjustmentLineDraft[K]
  ) => {
    setLinhas((prev) => prev.map((l, i) => (i !== idx ? l : { ...l, [campo]: valor })));
  };

  const enviar = (e: React.FormEvent) => {
    e.preventDefault();
    if (recuperadoExcede) return;
    form.transform((dados) => ({
      ...dados,
      products: linhas,
      final_total: totalFinal,
    }));
    form.post('/stock-adjustments', {
      forceFormData: true,
      preserveScroll: true,
    });
  };

  const localOptions = Object.entries(business_locations);

  return (
    <form onSubmit={enviar}>
      <PageHeader
        icon="package"
        title="Novo ajuste de estoque"
        description="Ajustes manuais (perda, quebra, inventário) registrados em audit trail."
        action={
          <div className="flex gap-2">
            <Button type="button" variant="outline" size="sm" onClick={() => router.visit('/stock-adjustments')}>
              <ArrowLeft className="h-4 w-4 mr-1" /> Cancelar
            </Button>
            <Button type="submit" size="sm" disabled={form.processing || recuperadoExcede}>
              <Save className="h-4 w-4 mr-1" /> {form.processing ? 'Salvando…' : 'Salvar ajuste'}
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
              <select
                className="mt-1 w-full h-9 px-2 rounded-md border border-stone-200 text-[13px]"
                value={form.data.location_id}
                onChange={(e) => form.setData('location_id', e.target.value)}
                required
              >
                <option value="">Selecione…</option>
                {localOptions.map(([id, name]) => (
                  <option key={id} value={id}>{name}</option>
                ))}
              </select>
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
              <Label className="text-[12px] text-stone-600">Tipo de ajuste *</Label>
              <select
                className={`mt-1 w-full h-9 px-2 rounded-md border text-[13px] ${
                  form.data.adjustment_type === 'abnormal' ? 'border-destructive/40 bg-destructive/5' : 'border-stone-200'
                }`}
                value={form.data.adjustment_type}
                onChange={(e) => form.setData('adjustment_type', e.target.value as AdjustmentType)}
                required
              >
                {Object.entries(TYPE_LABEL).map(([key, label]) => (
                  <option key={key} value={key}>{label}</option>
                ))}
              </select>
            </div>
          </div>
        </CardContent>
      </Card>

      <Card className="mt-3">
        <CardHeader className="pb-2 pt-3 px-4 flex flex-row items-center gap-2">
          <h3 className="text-[11px] uppercase tracking-widest text-stone-500 font-medium flex-1">Itens ajustados</h3>
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
              <PackageMinus className="h-10 w-10 mx-auto text-stone-300 mb-2" />
              <p className="text-stone-500 text-sm">Nenhum item adicionado.</p>
            </div>
          ) : (
            <table className="w-full border-collapse">
              <thead>
                <tr className="text-[10px] uppercase tracking-widest text-stone-500 border-b border-stone-200 bg-stone-50/40">
                  <th className="px-2 py-2 text-left font-medium">Produto</th>
                  <th className="px-2 py-2 text-right font-medium w-20">Qtd</th>
                  {permissions.view_purchase_price && (
                    <th className="px-2 py-2 text-right font-medium w-24">Preço unit.</th>
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
            <h3 className="text-[11px] uppercase tracking-widest text-stone-500 font-medium">Valor recuperado + Total</h3>
          </CardHeader>
          <CardContent className="px-4 pb-4 space-y-3">
            {permissions.view_purchase_price && (
              <>
                <div>
                  <Label className="text-[12px] text-stone-600">Valor recuperado (R$)</Label>
                  <Input
                    type="number"
                    step="0.01"
                    className={`mt-1 h-9 text-[13px] tabular-nums ${recuperadoExcede ? 'border-destructive/40' : ''}`}
                    value={form.data.total_amount_recovered}
                    onChange={(e) => form.setData('total_amount_recovered', Number(e.target.value))}
                  />
                  {recuperadoExcede && (
                    <p className="text-destructive-fg text-[11px] mt-1 flex items-center gap-1">
                      <AlertCircle className="h-3 w-3" />
                      Recuperado não pode exceder o total ajustado ({brl(totalFinal)}).
                    </p>
                  )}
                </div>
                <div className="space-y-1 text-[13px] pt-2 border-t border-stone-200">
                  <div className="flex justify-between"><span className="text-stone-500">Total ajustado</span><span className="tabular-nums">{brl(totalFinal)}</span></div>
                  <div className="flex justify-between"><span className="text-stone-500">Recuperado</span><span className="tabular-nums text-success-fg">- {brl(form.data.total_amount_recovered)}</span></div>
                  <div className="flex justify-between pt-2 mt-1 border-t border-stone-100">
                    <span className="font-semibold">Perda líquida</span>
                    <span className="tabular-nums font-semibold text-[15px] text-destructive-fg">{brl(Math.max(0, totalFinal - (form.data.total_amount_recovered || 0)))}</span>
                  </div>
                </div>
              </>
            )}
            {!permissions.view_purchase_price && (
              <p className="text-stone-400 text-[12px]">Sem permissão pra ver preços de compra.</p>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2 pt-3 px-4">
            <h3 className="text-[11px] uppercase tracking-widest text-stone-500 font-medium">Motivo / Notas</h3>
          </CardHeader>
          <CardContent className="px-4 pb-4">
            <Textarea
              placeholder="Razão do ajuste (perda, quebra, inventário)…"
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

StockAdjustmentCreate.layout = (page: ReactNode) => (
  <AppShellV2
    title="Novo ajuste de estoque"
    breadcrumbItems={[
      { label: 'Estoque', href: '#' },
      { label: 'Ajustes', href: '/stock-adjustments' },
      { label: 'Novo' },
    ]}
  >
    {page}
  </AppShellV2>
);

export default StockAdjustmentCreate;
