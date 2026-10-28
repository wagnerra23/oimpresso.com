// @memcofre
//   tela: /stock-adjustments/create
//   module: Inventory / StockAdjustment (raiz UltimatePOS)
//   tipo: FORM CREATE (MWART Wave2 B5)
//   rules: R-ADJ-001 (Tier 0), R-ADJ-002 (type), R-ADJ-003 (recovered<=total), R-ADJ-004 (purchase.create)
//   adrs: 0104, 0093, 0114, 0149

import AppShellV2 from '@/Layouts/AppShellV2';
import { PageHeader } from '@/Components/PageHeader';
import { router, useForm } from '@inertiajs/react';
import { useMemo, useState, type ReactNode } from 'react';
import { paraNumUf } from '@/Lib/numberPtBR';
import LinhasDeEstoque from '@/Pages/Estoque/_components/LinhasDeEstoque';
import {
  type LinhaEstoque,
  erroDaLinha,
  itemAjuste,
  somaLinhas,
} from '@/Pages/Estoque/_components/linhas-estoque-dominio';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { NumericInputPtBR } from '@/Components/ui/numeric-input-ptbr';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { ArrowLeft, Save, AlertCircle, Package } from 'lucide-react';

// ---------- Tipos ----------

type AdjustmentType = 'normal' | 'abnormal';

interface OptionMap {
  [id: number]: string;
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
  const [linhas, setLinhas] = useState<LinhaEstoque[]>([]);
  const [tentou, setTentou] = useState(false);

  const form = useForm({
    location_id: '',
    ref_no: '',
    transaction_date: default_datetime,
    adjustment_type: 'normal' as AdjustmentType,
    additional_notes: '',
    total_amount_recovered: 0,
    final_total: 0,
  });

  const totalFinal = useMemo(() => somaLinhas(linhas), [linhas]);
  const linhasComErro = linhas.some((l) => erroDaLinha(l) !== null);

  // R-ADJ-003: recovered ≤ total
  const recuperadoExcede = (form.data.total_amount_recovered || 0) > totalFinal;

  // O saldo e os lotes da linha são do local: trocou o local, as linhas deixam de valer.
  const trocarLocal = (valor: string) => {
    form.setData('location_id', valor);
    setLinhas([]);
  };

  const enviar = (e: React.FormEvent) => {
    e.preventDefault();
    setTentou(true);
    if (recuperadoExcede || linhas.length === 0 || linhasComErro) return;
    // Formato que o `store()` lê (provado em EstoqueAjusteStoreTest): quantidade, custo e
    // recuperado por `paraNumUf` (passam por `num_uf`); `final_total` entra CRU, com ponto em 2 casas.
    form.transform((dados) => ({
      ...dados,
      total_amount_recovered: paraNumUf(dados.total_amount_recovered || 0, 2),
      products: linhas.map(itemAjuste),
      final_total: Math.round(totalFinal * 100) / 100,
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
        leading={<Package className="mr-2 inline h-5 w-5 align-middle text-primary" aria-hidden="true" />}
        title="Novo ajuste de estoque"
        subtitle="Ajustes manuais (perda, quebra, inventário) registrados em audit trail."
        actions={
          <div className="flex gap-2">
            <Button type="button" variant="outline" size="sm" onClick={() => router.visit('/stock-adjustments')}>
              <ArrowLeft className="h-4 w-4 mr-1" /> Cancelar
            </Button>
            <Button type="submit" size="sm" disabled={form.processing || recuperadoExcede || linhasComErro}>
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
                onChange={(e) => trocarLocal(e.target.value)}
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
          <LinhasDeEstoque
            localId={form.data.location_id ? Number(form.data.location_id) : null}
            linhas={linhas}
            onChange={setLinhas}
            verCusto={permissions.view_purchase_price}
            editarCusto={permissions.edit_price}
          />
          {tentou && linhas.length === 0 && (
            <p className="mt-2 text-[12px] text-destructive-fg">Adicione ao menos um produto.</p>
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
                  <NumericInputPtBR
                    aria-label="Valor recuperado (R$)"
                    className={`mt-1 h-9 text-[13px] tabular-nums ${recuperadoExcede ? 'border-destructive/40' : ''}`}
                    precision={2}
                    value={form.data.total_amount_recovered}
                    onChange={(n) => form.setData('total_amount_recovered', n)}
                  />
                  {recuperadoExcede && (
                    <p className="text-destructive-fg text-[11px] mt-1 flex items-center gap-1">
                      <AlertCircle className="h-3 w-3" />
                      Recuperado não pode exceder o total ajustado ({brl(totalFinal)}).
                    </p>
                  )}
                </div>
                <div className="space-y-1 text-[13px] pt-2 border-t border-stone-200">
                  {/* Rótulo do protótipo (`estoque-forms.jsx`, fecho do FormAjuste) — o
                      número e o cálculo seguem intactos; só a palavra muda. */}
                  <div className="flex justify-between"><span className="text-stone-500">Valor ajustado</span><span className="tabular-nums">{brl(totalFinal)}</span></div>
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
