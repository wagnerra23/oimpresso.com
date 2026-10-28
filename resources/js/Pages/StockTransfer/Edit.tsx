// @memcofre
//   tela: /stock-transfers/{id}/edit
//   module: Inventory / StockTransfer (raiz UltimatePOS)
//   tipo: FORM EDIT (MWART — Blade stock_transfer/edit.blade.php)
//   rules: R-XFER-001 (Tier 0), R-XFER-003 (concluída não edita), R-XFER-005 (completed move estoque)
//   adrs: 0104, 0093
//
// Protótipo: prototipo-ui/cowork/Wagner/estoque-forms.jsx — FormTransferencia (create + edit).
// Runbook: memory/requisitos/Estoque/_telas/RUNBOOK-stock-transfer-edit.md
// Charter: ./Edit.charter.md · Casos: ./Edit.casos.md
//
// Como a Blade de edição: origem, destino e referência ficam travados; data, status, itens,
// frete e observação mudam. Quem grava é `StockTransferController@update` (PUT).

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
  somaLinhas,
} from '@/Pages/Estoque/_components/linhas-estoque-dominio';
import { Inline, Stack, Grid } from '@/Components/layout';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { NumericInputPtBR } from '@/Components/ui/numeric-input-ptbr';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Truck, ArrowLeft, Save } from 'lucide-react';

export interface StockTransferEditPageProps {
  transferencia: {
    id: number;
    ref_no: string;
    transaction_date: string;
    status: string;
    location_id: number;
    transfer_location_id: number | null;
    additional_notes: string;
    shipping_charges: number;
    linhas: LinhaEstoque[];
  };
  business_locations: Record<number, string>;
  statuses: Record<string, string>;
  permissions: { view_purchase_price: boolean; edit_price: boolean };
}

const brl = (v: number) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v ?? 0);
const rotulo = 'text-[12px] text-muted-foreground';
const tituloCard = 'text-[11px] font-medium uppercase tracking-widest text-muted-foreground';

function StockTransferEdit({ transferencia, business_locations, statuses, permissions }: StockTransferEditPageProps) {
  const [linhas, setLinhas] = useState<LinhaEstoque[]>(transferencia.linhas);
  const [tentou, setTentou] = useState(false);

  const form = useForm({
    transaction_date: transferencia.transaction_date,
    status: transferencia.status,
    additional_notes: transferencia.additional_notes,
    shipping_charges: transferencia.shipping_charges,
  });

  const subtotal = useMemo(() => somaLinhas(linhas), [linhas]);
  const totalFinal = subtotal + (form.data.shipping_charges || 0);
  const linhasComErro = linhas.some((l) => erroDaLinha(l) !== null);

  const enviar = (e: React.FormEvent) => {
    e.preventDefault();
    setTentou(true);
    if (linhas.length === 0 || linhasComErro) return;
    // Mesmo formato do Create (UC-TRCRT-03/06): números por `paraNumUf`, exceto
    // `base_unit_multiplier` (ponto, ver itemTransferencia) e `final_total` (entra cru).
    form.transform((dados) => ({
      ...dados,
      shipping_charges: paraNumUf(dados.shipping_charges || 0, 2),
      products: linhas.map(itemTransferencia),
      final_total: Math.round(totalFinal * 100) / 100,
    }));
    form.put(`/stock-transfers/${transferencia.id}`, { preserveScroll: true });
  };

  const origem = business_locations[transferencia.location_id] ?? '—';
  const destino = transferencia.transfer_location_id ? (business_locations[transferencia.transfer_location_id] ?? '—') : '—';

  return (
    <form onSubmit={enviar}>
      <PageHeader
        leading={<Truck className="mr-2 inline h-5 w-5 align-middle text-primary" aria-hidden="true" />}
        title={`Editar transferência ${transferencia.ref_no}`}
        subtitle={`${origem} → ${destino}`}
        actions={
          <Inline gap={2}>
            <Button type="button" variant="outline" size="sm" onClick={() => router.visit('/stock-transfers')}>
              <ArrowLeft className="mr-1 h-4 w-4" aria-hidden="true" /> Cancelar
            </Button>
            <Button type="submit" size="sm" disabled={form.processing || linhasComErro}>
              <Save className="mr-1 h-4 w-4" aria-hidden="true" /> {form.processing ? 'Salvando…' : 'Salvar alterações'}
            </Button>
          </Inline>
        }
      />

      <Card className="mt-4">
        <CardHeader className="px-4 pb-2 pt-3">
          <h3 className={tituloCard}>Dados da transferência</h3>
        </CardHeader>
        <CardContent className="px-4 pb-4">
          <Grid cols={3} gap={3}>
            <div>
              <Label htmlFor="data" className={rotulo}>Data *</Label>
              <Input
                id="data"
                type="datetime-local"
                className="mt-1 h-9 text-[13px]"
                value={form.data.transaction_date.replace(' ', 'T').slice(0, 16)}
                onChange={(e) => form.setData('transaction_date', e.target.value.replace('T', ' '))}
                required
              />
            </div>
            <div>
              <Label htmlFor="ref" className={rotulo}>Referência</Label>
              <Input id="ref" className="mt-1 h-9 text-[13px]" value={transferencia.ref_no} readOnly />
            </div>
            <div>
              <Label className={rotulo}>Status *</Label>
              <Select value={form.data.status} onValueChange={(v) => form.setData('status', v)}>
                <SelectTrigger aria-label="Status" className="mt-1 h-9 text-[13px]"><SelectValue /></SelectTrigger>
                <SelectContent>
                  {Object.entries(statuses).map(([k, v]) => <SelectItem key={k} value={k}>{v}</SelectItem>)}
                </SelectContent>
              </Select>
              {form.data.status === 'completed' && (
                <p className="mt-1 text-[12px] text-warning-fg">Concluída move o saldo agora e não pode mais ser editada.</p>
              )}
            </div>
            <div>
              <Label htmlFor="origem" className={rotulo}>Local de origem</Label>
              <Input id="origem" className="mt-1 h-9 text-[13px]" value={origem} readOnly />
            </div>
            <div>
              <Label htmlFor="destino" className={rotulo}>Local de destino</Label>
              <Input id="destino" className="mt-1 h-9 text-[13px]" value={destino} readOnly />
            </div>
          </Grid>
        </CardContent>
      </Card>

      <Card className="mt-3">
        <CardHeader className="px-4 pb-2 pt-3">
          <h3 className={tituloCard}>Produtos transferidos</h3>
        </CardHeader>
        <CardContent className="px-4 pb-4">
          <LinhasDeEstoque
            localId={transferencia.location_id}
            linhas={linhas}
            onChange={setLinhas}
            verCusto={permissions.view_purchase_price}
            editarCusto={permissions.edit_price}
            comUnidade
          />
          {tentou && linhas.length === 0 && (
            <p className="mt-2 text-[12px] text-destructive-fg">Adicione ao menos um produto.</p>
          )}
        </CardContent>
      </Card>

      <Grid cols={2} gap={3} className="mt-3">
        <Card>
          <CardHeader className="px-4 pb-2 pt-3">
            <h3 className={tituloCard}>Frete e total</h3>
          </CardHeader>
          <CardContent className="px-4 pb-4">
            <Stack gap={3}>
              <div>
                <Label className={rotulo}>Frete (R$)</Label>
                <NumericInputPtBR
                  aria-label="Frete (R$)"
                  className="mt-1 h-9 text-[13px] tabular-nums"
                  precision={2}
                  value={form.data.shipping_charges}
                  onChange={(n) => form.setData('shipping_charges', n)}
                />
                <p className="mt-1 text-[11.5px] text-muted-foreground">Entra no custo do material que chega no destino</p>
              </div>
              {permissions.view_purchase_price && (
                <Stack gap={1} className="border-t border-border pt-2 text-[13px]">
                  <Inline justify="between"><span className="text-muted-foreground">Subtotal itens</span><span className="tabular-nums">{brl(subtotal)}</span></Inline>
                  <Inline justify="between"><span className="text-muted-foreground">Frete</span><span className="tabular-nums">+ {brl(form.data.shipping_charges)}</span></Inline>
                  <Inline justify="between" className="mt-1 border-t border-border pt-2">
                    <span className="font-semibold">Total</span>
                    <span className="text-[15px] font-semibold tabular-nums">{brl(totalFinal)}</span>
                  </Inline>
                </Stack>
              )}
            </Stack>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="px-4 pb-2 pt-3">
            <h3 className={tituloCard}>Observação</h3>
          </CardHeader>
          <CardContent className="px-4 pb-4">
            <Textarea
              aria-label="Observação"
              value={form.data.additional_notes}
              onChange={(e) => form.setData('additional_notes', e.target.value)}
              className="min-h-[80px] text-[13px]"
            />
          </CardContent>
        </Card>
      </Grid>
    </form>
  );
}

StockTransferEdit.layout = (page: ReactNode) => (
  <AppShellV2
    title="Editar transferência"
    breadcrumbItems={[
      { label: 'Estoque', href: '#' },
      { label: 'Transferências', href: '/stock-transfers' },
      { label: 'Editar' },
    ]}
  >
    {page}
  </AppShellV2>
);

export default StockTransferEdit;
