// Seção "Estoque" do formulário de produto (Novo e Editar).
// As duas caixas de seleção só existem no Novo: o formulário do Editar não envia esses campos.

import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import type { CamposProduto, DefinirCampo, ModoFormulario, OpcoesProduto } from './tipos';
import { opcoesDe } from './tipos';
import { avisoCodigoBarras } from './ean';

interface Props {
  modo: ModoFormulario;
  data: CamposProduto;
  definir: DefinirCampo;
  opcoes: OpcoesProduto;
}

export function BlocoEstoque({ modo, data, definir, opcoes }: Props) {
  const avisoEan = avisoCodigoBarras(data.sku, data.barcode_type);

  return (
    <Card>
      <CardHeader>
        <CardTitle>Estoque</CardTitle>
      </CardHeader>
      <CardContent className="grid grid-cols-2 gap-4">
        {modo === 'novo' && (
          <>
            <label className="flex items-center gap-2">
              <input
                type="checkbox"
                checked={data.enable_stock === 1}
                onChange={(e) => definir('enable_stock', e.target.checked ? 1 : 0)}
                className="rounded border-stone-300"
              />
              <span className="text-[13px]">Controlar estoque</span>
            </label>
            <label className="flex items-center gap-2">
              <input
                type="checkbox"
                checked={data.not_for_selling === 1}
                onChange={(e) => definir('not_for_selling', e.target.checked ? 1 : 0)}
                className="rounded border-stone-300"
              />
              <span className="text-[13px]">Não está à venda (apenas insumo)</span>
            </label>
          </>
        )}
        <div>
          <Label htmlFor="alert_quantity">Quantidade de alerta</Label>
          <Input
            id="alert_quantity"
            type="number"
            step="0.01"
            value={String(data.alert_quantity ?? '')}
            onChange={(e) => definir('alert_quantity', e.target.value)}
            className="tabular-nums"
          />
        </div>
        <div>
          <Label htmlFor="barcode_type">Tipo de código de barras</Label>
          <Select value={data.barcode_type ?? ''} onValueChange={(v) => definir('barcode_type', v)}>
            <SelectTrigger id="barcode_type">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {opcoesDe(opcoes.barcodeTypes).map(([k, v]) => (
                <SelectItem key={k} value={k}>
                  {v}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          {avisoEan && (
            <p data-testid="ean-aviso" className="text-[12px] text-warning-fg mt-1">
              {avisoEan}
            </p>
          )}
        </div>
      </CardContent>
    </Card>
  );
}
