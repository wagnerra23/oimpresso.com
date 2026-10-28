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
import { PageHeader } from '@/Components/PageHeader';
import { router, useForm } from '@inertiajs/react';
import { useMemo, useState, type ReactNode } from 'react';
import { paraNumUf } from '@/Lib/numberPtBR';
import LinhasDeEstoque from '@/Pages/Estoque/_components/LinhasDeEstoque';
import {
  type LinhaEstoque,
  erroDaLinha,
  itemTransferencia,
  quantidadeBase,
  somaLinhas,
} from '@/Pages/Estoque/_components/linhas-estoque-dominio';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { NumericInputPtBR } from '@/Components/ui/numeric-input-ptbr';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Truck, ArrowLeft, Save, ArrowRight, AlertCircle } from 'lucide-react';

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
  const [linhas, setLinhas] = useState<LinhaEstoque[]>([]);
  const [tentou, setTentou] = useState(false);

  const form = useForm({
    transaction_date: default_datetime,
    ref_no: '',
    status: 'pending',
    location_id: '',
    transfer_location_id: '',
    additional_notes: '',
    shipping_charges: 0,
    final_total: 0,
  });

  const subtotal = useMemo(() => somaLinhas(linhas), [linhas]);

  const totalFinal = subtotal + (form.data.shipping_charges || 0);

  const linhasComErro = linhas.some((l) => erroDaLinha(l) !== null);
  const qtdTotal = linhas.reduce((acc, l) => acc + quantidadeBase(l), 0);

  // O saldo e os lotes da linha são da ORIGEM: trocou a origem, as linhas deixam de valer.
  const trocarOrigem = (valor: string) => {
    form.setData('location_id', valor);
    setLinhas([]);
  };

  // R-XFER-004: origem ≠ destino — bloqueia submit
  const origemDestinoIguais =
    !!form.data.location_id && form.data.location_id === form.data.transfer_location_id;

  const enviar = (e: React.FormEvent) => {
    e.preventDefault();
    setTentou(true);
    if (origemDestinoIguais || linhas.length === 0 || linhasComErro) return;
    // Formato que o `store()` lê (provado em EstoqueTransferenciaStoreTest, UC-TRCRT-03):
    // quantidade, custo e frete por `paraNumUf` (vírgula, sem milhar — passam por `num_uf`);
    // `final_total` entra CRU no banco (sem `num_uf`), então vai como número com ponto, em 2 casas.
    form.transform((dados) => ({
      ...dados,
      shipping_charges: paraNumUf(dados.shipping_charges || 0, 2),
      products: linhas.map(itemTransferencia),
      final_total: Math.round(totalFinal * 100) / 100,
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
        leading={<Truck className="mr-2 inline h-5 w-5 align-middle text-primary" aria-hidden="true" />}
        title="Nova transferência de estoque"
        subtitle="Origem → Destino dentro da mesma empresa (multi-tenant Tier 0)."
        actions={
          <div className="flex gap-2">
            <Button type="button" variant="outline" size="sm" onClick={() => router.visit('/stock-transfers')}>
              <ArrowLeft className="h-4 w-4 mr-1" /> Cancelar
            </Button>
            <Button type="submit" size="sm" disabled={form.processing || origemDestinoIguais || linhasComErro}>
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
                  <span className="inline-flex h-5 w-5 items-center justify-center rounded bg-border text-foreground text-[10px] font-semibold">DE</span>
                  Filial origem *
                </Label>
                <select
                  className="mt-1 w-full h-9 px-2 rounded-md border border-stone-200 text-[13px]"
                  value={form.data.location_id}
                  onChange={(e) => trocarOrigem(e.target.value)}
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
                  <span className="inline-flex h-5 w-5 items-center justify-center rounded bg-success-soft text-success-fg text-[10px] font-semibold">PARA</span>
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
          <LinhasDeEstoque
            localId={form.data.location_id ? Number(form.data.location_id) : null}
            linhas={linhas}
            onChange={setLinhas}
            verCusto={permissions.view_purchase_price}
            editarCusto={permissions.edit_price}
            comUnidade
          />
          {tentou && linhas.length === 0 && (
            <p className="mt-2 text-[12px] text-destructive-fg">Adicione ao menos um produto.</p>
          )}
          {linhas.length > 0 && form.data.location_id && form.data.transfer_location_id && (
            <p className="mt-3 text-[12px] text-muted-foreground">
              {qtdTotal.toLocaleString('pt-BR')} sai de {business_locations[Number(form.data.location_id)]} e{' '}
              {qtdTotal.toLocaleString('pt-BR')} entra em {business_locations[Number(form.data.transfer_location_id)]}.
              A transferência não cria nem destrói saldo.
            </p>
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
              <NumericInputPtBR
                aria-label="Frete (R$)"
                className="mt-1 h-9 text-[13px] tabular-nums"
                precision={2}
                value={form.data.shipping_charges}
                onChange={(n) => form.setData('shipping_charges', n)}
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
