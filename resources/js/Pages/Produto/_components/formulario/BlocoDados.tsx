// Seção "Dados do produto" do formulário de produto (Novo e Editar), na forma do protótipo
// (produto-blade-forms.jsx, FormProduto): três colunas, com o código de barras, os locais do
// negócio, o controle de estoque, a garantia e a descrição junto da identificação.

import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Checkbox } from '@/Components/ui/checkbox';
import { Textarea } from '@/Components/ui/textarea';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import { Grid, Inline } from '@/Components/layout';
import { CampoLocais } from './BlocoLocais';
import { avisoCodigoBarras } from './ean';
import type { CamposProduto, DefinirCampo, ErrosProduto, ModoFormulario, OpcoesProduto } from './tipos';
import { opcoesDe } from './tipos';

interface Props {
  modo: ModoFormulario;
  data: CamposProduto;
  definir: DefinirCampo;
  errors: ErrosProduto;
  opcoes: OpcoesProduto;
}

export function BlocoDados({ modo, data, definir, errors, opcoes }: Props) {
  const novo = modo === 'novo';
  const avisoEan = avisoCodigoBarras(data.sku, data.barcode_type);

  return (
    <Card>
      <CardHeader>
        <CardTitle>Dados do produto</CardTitle>
      </CardHeader>
      <CardContent>
        <Grid cols={3} gap={4}>
        <div>
          <Label htmlFor="name">{novo ? 'Nome do produto *' : 'Nome *'}</Label>
          <Input
            id="name"
            value={data.name}
            onChange={(e) => definir('name', e.target.value)}
            required
          />
          {errors.name && <p className="text-[12px] text-destructive mt-1">{errors.name}</p>}
        </div>
        <div>
          <Label htmlFor="sku">{novo ? 'SKU (deixe em branco pra gerar automático)' : 'SKU'}</Label>
          <Input
            id="sku"
            value={data.sku}
            onChange={(e) => definir('sku', e.target.value)}
            className="font-mono"
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
        <div>
          <Label htmlFor="unit_id">{novo ? 'Unidade *' : 'Unidade'}</Label>
          <Select
            value={String(data.unit_id ?? '')}
            onValueChange={(v) => definir('unit_id', Number(v))}
          >
            <SelectTrigger id="unit_id">
              <SelectValue placeholder="Selecione" />
            </SelectTrigger>
            <SelectContent>
              {opcoesDe(opcoes.units).map(([k, v]) => (
                <SelectItem key={k} value={k}>
                  {v}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div>
          <Label htmlFor="brand_id">Marca</Label>
          <Select
            value={String(data.brand_id ?? '')}
            onValueChange={(v) => definir('brand_id', Number(v))}
          >
            <SelectTrigger id="brand_id">
              <SelectValue placeholder="Selecione" />
            </SelectTrigger>
            <SelectContent>
              {opcoesDe(opcoes.brands).map(([k, v]) => (
                <SelectItem key={k} value={k}>
                  {v}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div>
          <Label htmlFor="category_id">Categoria</Label>
          <Select
            value={String(data.category_id ?? '')}
            onValueChange={(v) => definir('category_id', Number(v))}
          >
            <SelectTrigger id="category_id">
              <SelectValue placeholder="Selecione" />
            </SelectTrigger>
            <SelectContent>
              {opcoesDe(opcoes.categories).map(([k, v]) => (
                <SelectItem key={k} value={k}>
                  {v}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        {novo && opcoes.businessLocations && (
          <CampoLocais data={data} definir={definir} locais={opcoes.businessLocations} />
        )}
        {/* O formulário do Editar não envia o controle de estoque: a caixa só existe no Novo. */}
        {novo && (
          <Inline gap={2} className="self-end pb-2">
          <Checkbox
            id="enable_stock"
            checked={data.enable_stock === 1}
            onCheckedChange={(v) => definir('enable_stock', v === true ? 1 : 0)}
          />
          <Label htmlFor="enable_stock" className="text-[13px] font-normal">Controlar estoque</Label>
        </Inline>
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
        {opcoes.warranties && Object.keys(opcoes.warranties).length > 0 && (
          <div>
            <Label htmlFor="warranty_id">Garantia</Label>
            <Select
              value={String(data.warranty_id ?? '')}
              onValueChange={(v) => definir('warranty_id', Number(v))}
            >
              <SelectTrigger id="warranty_id">
                <SelectValue placeholder="Nenhuma" />
              </SelectTrigger>
              <SelectContent>
                {opcoesDe(opcoes.warranties).map(([k, v]) => (
                  <SelectItem key={k} value={k}>
                    {v}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
        )}
        <div className="col-span-2">
          <Label htmlFor="product_description">Descrição</Label>
          <Textarea
            id="product_description"
            rows={3}
            value={data.product_description ?? ''}
            onChange={(e) => definir('product_description', e.target.value)}
          />
        </div>
        </Grid>
      </CardContent>
    </Card>
  );
}
