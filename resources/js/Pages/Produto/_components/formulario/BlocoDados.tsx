// Seção "Dados do produto" do formulário de produto (Novo e Editar), na forma do protótipo
// (produto-blade-forms.jsx, FormProduto): três colunas, com o código de barras, os locais do
// negócio, o controle de estoque, a garantia e a descrição junto da identificação.

import { useLayoutEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { Button } from '@/Components/ui/button';
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
import { CampoSubcategoria, CampoSubUnidades } from './BlocoSubcampos';
import { CadastroAuxiliar } from './CadastroAuxiliar';
import { CampoMidia } from './CampoMidia';
import type { Folheto } from './envioMidia';
import type { TipoAuxiliar } from './envioCadastroAuxiliar';
import { avisoCodigoBarras } from './ean';
import type { CamposProduto, DefinirCampo, ErrosProduto, ModoFormulario, OpcoesProduto } from './tipos';
import { opcoesDe } from './tipos';

interface Props {
  modo: ModoFormulario;
  data: CamposProduto;
  definir: DefinirCampo;
  errors: ErrosProduto;
  opcoes: OpcoesProduto;
  /** UC-PEDIT-29: o produto do Editar, com a imagem e o folheto gravados. */
  midia?: { produtoId: number; imagem: string | null; folheto: Folheto | null };
}

export function BlocoDados({ modo, data, definir, errors, opcoes, midia }: Props) {
  const novo = modo === 'novo';
  const avisoEan = avisoCodigoBarras(data.sku, data.barcode_type);
  // UC-PCAD-25/26 · UC-PEDIT-25/26: o servidor manda as listas vazias quando a empresa não usa.
  const temSubcategoria = Object.keys(opcoes.subcategoriasPorCategoria ?? {}).length > 0;
  const temSubUnidades = Object.keys(opcoes.subUnidadesPorUnidade ?? {}).length > 0;
  // UC-PCAD-28 · UC-PEDIT-28: "Bipar" põe o cursor no SKU, onde o leitor de código de barras digita.
  const skuRef = useRef<HTMLInputElement>(null);
  const [leitorPronto, setLeitorPronto] = useState(false);
  // UC-PCAD-28 · UC-PEDIT-28: "+" de unidade e de marca. O diálogo monta dentro do .cockpit para
  // herdar o tema (§5 2026-10-19).
  const [auxiliar, setAuxiliar] = useState<TipoAuxiliar | null>(null);
  const [cockpit, setCockpit] = useState<HTMLElement | null>(null);
  useLayoutEffect(() => setCockpit(document.querySelector<HTMLElement>('.cockpit')), []);
  const criado = (tipo: TipoAuxiliar, id: number) => {
    setAuxiliar(null);
    // Recarrega só a lista que ganhou o item; o resto do formulário fica como está.
    router.reload({ only: [tipo === 'unidade' ? 'units' : 'brands'] });
    if (tipo === 'unidade') {
      definir('unit_id', id);
      if (temSubUnidades) definir('sub_unit_ids', []);
    } else {
      definir('brand_id', id);
    }
  };

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
          <Inline gap={2}>
            <Input
              id="sku"
              ref={skuRef}
              value={data.sku}
              onChange={(e) => definir('sku', e.target.value)}
              onBlur={() => setLeitorPronto(false)}
              placeholder={leitorPronto ? 'Leitor pronto: bipe o produto' : undefined}
              className="font-mono"
            />
            <Button
              type="button"
              variant="outline"
              size="sm"
              aria-pressed={leitorPronto}
              title="Ler código de barras (o leitor digita no campo)"
              onClick={() => {
                skuRef.current?.focus();
                skuRef.current?.select();
                setLeitorPronto(true);
              }}
            >
              Bipar
            </Button>
          </Inline>
          {/* O aviso não ocupa lugar na tela: some no blur do SKU, que acontece no mousedown do
              próximo clique, e uma linha que sumisse ali faria a tela subir antes do mouseup (o 1º
              clique depois de bipar se perdia, medido 2026-11-16). Posição absoluta cobria o título
              das sub-unidades e, a 768px, o rótulo Marca. Na tela o aviso fica no próprio SKU
              (placeholder); aqui, só para leitor de tela. */}
          {leitorPronto && (
            <p role="status" className="sr-only">
              Leitor pronto: bipe o produto e o código cai no SKU.
            </p>
          )}
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
          <Inline gap={2}>
            <Select
              value={String(data.unit_id ?? '')}
              onValueChange={(v) => {
                definir('unit_id', Number(v));
                // As sub-unidades são da unidade: trocar a unidade desmarca as da anterior.
                if (temSubUnidades && Number(v) !== Number(data.unit_id)) definir('sub_unit_ids', []);
              }}
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
            <Button
              type="button"
              variant="outline"
              size="sm"
              aria-label="Cadastrar unidade"
              title={opcoes.podeCriarUnidade ? 'Cadastrar unidade' : 'Sem permissão para cadastrar unidade'}
              disabled={!opcoes.podeCriarUnidade}
              onClick={() => setAuxiliar('unidade')}
            >
              <Plus className="h-4 w-4" />
            </Button>
          </Inline>
          {errors.unit_id && <p className="text-[12px] text-destructive mt-1">{errors.unit_id}</p>}
        </div>
        {temSubUnidades && (
          <CampoSubUnidades data={data} definir={definir} porUnidade={opcoes.subUnidadesPorUnidade ?? {}} />
        )}
        <div>
          <Label htmlFor="brand_id">Marca</Label>
          <Inline gap={2}>
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
            <Button
              type="button"
              variant="outline"
              size="sm"
              aria-label="Cadastrar marca"
              title={opcoes.podeCriarMarca ? 'Cadastrar marca' : 'Sem permissão para cadastrar marca'}
              disabled={!opcoes.podeCriarMarca}
              onClick={() => setAuxiliar('marca')}
            >
              <Plus className="h-4 w-4" />
            </Button>
          </Inline>
        </div>
        <div>
          <Label htmlFor="category_id">Categoria</Label>
          <Select
            value={String(data.category_id ?? '')}
            onValueChange={(v) => {
              definir('category_id', Number(v));
              // A subcategoria é da categoria: trocar a categoria limpa a subcategoria.
              if (temSubcategoria && Number(v) !== Number(data.category_id)) definir('sub_category_id', '');
            }}
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
        {temSubcategoria && (
          <CampoSubcategoria data={data} definir={definir} porCategoria={opcoes.subcategoriasPorCategoria ?? {}} />
        )}
        {/* UC-PEDIT-34: no Editar também (o update() já sincroniza product_locations). */}
        {opcoes.businessLocations && (
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
        <CampoMidia
          modo={novo ? 'novo' : 'editar'}
          data={data}
          definir={definir}
          produtoId={midia?.produtoId}
          imagemGravada={midia?.imagem}
          folhetoGravado={midia?.folheto}
        />
        </Grid>
        <CadastroAuxiliar tipo={auxiliar} aoFechar={() => setAuxiliar(null)} aoCriar={criado} container={cockpit} />
      </CardContent>
    </Card>
  );
}
