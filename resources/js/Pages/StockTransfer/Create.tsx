// @memcofre
//   tela: /stock-transfers/create
//   module: Inventory / StockTransfer (raiz UltimatePOS)
//   tipo: FORM CREATE (MWART Wave2 B5)
//   rules: R-XFER-001 (Tier 0), R-XFER-004 (origem ≠ destino), R-XFER-005 (completed move estoque)
//   adrs: 0104, 0093, 0114, 0149
//
// Runbook: memory/requisitos/Estoque/_telas/RUNBOOK-stock-transfer-create.md
// Charter: ./Create.charter.md

import AppShellV2 from '@/Layouts/AppShellV2';
import { PageHeader } from '@/Components/PageHeader';
import { Icon } from '@/Components/Icon';
import { router, useForm } from '@inertiajs/react';
import { useMemo, useState, type ReactNode } from 'react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { ArrowLeft, Save, ArrowRight, AlertCircle } from 'lucide-react';
import { paraNumUf } from '@/Lib/numberPtBR';
import ItensMovimentacaoEstoque, { linhaInvalida, linhaParaTransferencia, type LinhaDeEstoque } from '@/Components/shared/ItensMovimentacaoEstoque';

// ---------- Tipos ----------

interface OptionMap {
  [id: number]: string;
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
  // Cada linha é um produto real do cadastro (produto + variação + saldo da origem) — antes era
  // um texto livre sem `product_id`, e o salvar não tinha o que movimentar.
  const [linhas, setLinhas] = useState<LinhaDeEstoque[]>([]);

  const form = useForm({
    transaction_date: default_datetime,
    ref_no: '',
    status: 'pending',
    location_id: '',
    transfer_location_id: '',
    additional_notes: '',
    shipping_charges: 0,
    final_total: 0,
    products: [] as Record<string, string | number>[],
  });

  const subtotal = useMemo(
    () => linhas.reduce((acc, l) => acc + l.quantity * l.unit_price, 0),
    [linhas]
  );

  const totalFinal = subtotal + (form.data.shipping_charges || 0);

  // Como na Blade: sem item, ou com quantidade acima do saldo da origem, não salva.
  const itensInvalidos = linhas.length === 0 || linhas.some(linhaInvalida);

  // R-XFER-004: origem ≠ destino — bloqueia submit
  const origemDestinoIguais =
    !!form.data.location_id && form.data.location_id === form.data.transfer_location_id;

  const enviar = (e: React.FormEvent) => {
    e.preventDefault();
    if (origemDestinoIguais || itensInvalidos) return;
    // Números em `paraNumUf` (vírgula decimal, sem milhar): é o que o `num_uf` do servidor lê sem
    // ambiguidade. `final_total` vai como número, igual ao campo oculto da Blade.
    form.transform((dados) => ({
      ...dados,
      products: linhas.map(linhaParaTransferencia),
      shipping_charges: paraNumUf(Number(dados.shipping_charges) || 0, 2),
      final_total: Number(totalFinal.toFixed(4)),
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
        leading={<Icon name="truck" size={18} className="mr-2 inline-block align-[-2px] text-primary" />}
        title="Nova transferência de estoque"
        subtitle="Passa mercadoria de uma filial para outra da mesma empresa."
        actions={
          <div className="flex gap-2">
            <Button type="button" variant="outline" size="sm" onClick={() => router.visit('/stock-transfers')}>
              <ArrowLeft className="h-4 w-4 mr-1" /> Cancelar
            </Button>
            <Button type="submit" size="sm" disabled={form.processing || origemDestinoIguais || itensInvalidos}>
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
                  onChange={(e) => {
                    form.setData('location_id', e.target.value);
                    setLinhas([]);
                  }}
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
          <ItensMovimentacaoEstoque
            origemId={form.data.location_id}
            rotuloSemLocal="Escolha a filial de origem primeiro"
            linhas={linhas}
            aoMudar={setLinhas}
            verCusto={permissions.view_purchase_price}
            editarPreco={permissions.edit_price}
            desabilitado={form.processing}
          />
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
              {/* `help` do campo no protótipo (`estoque-forms.jsx`, FormTransferencia).
                  Explica por que o frete entra no fecho — copy, não cálculo. */}
              <p className="mt-1 text-[11.5px] text-muted-foreground">
                Entra no custo do material que chega no destino
              </p>
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
