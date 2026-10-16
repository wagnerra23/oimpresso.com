// Seção "Identificação" do formulário de produto (Novo e Editar).

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
import type {
  CamposProduto,
  DefinirCampo,
  ErrosProduto,
  ModoFormulario,
  OpcoesProduto,
  TipoProduto,
} from './tipos';
import { entradasDeOpcoes } from './opcoes';

interface Props {
  modo: ModoFormulario;
  data: CamposProduto;
  definir: DefinirCampo;
  errors: ErrosProduto;
  opcoes: OpcoesProduto;
  /** Editar: o rótulo do tipo, que não muda depois de criado. */
  tipoAtual?: string;
}

export function BlocoIdentificacao({ modo, data, definir, errors, opcoes, tipoAtual }: Props) {
  const novo = modo === 'novo';

  return (
    <Card>
      <CardHeader>
        <CardTitle>Identificação</CardTitle>
      </CardHeader>
      <CardContent className="grid grid-cols-2 gap-4">
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
          <Label htmlFor="type">{novo ? 'Tipo *' : 'Tipo'}</Label>
          {novo ? (
            <Select value={data.type} onValueChange={(v) => definir('type', v as TipoProduto)}>
              <SelectTrigger id="type">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {entradasDeOpcoes(opcoes.productTypes).map(([k, v]) => (
                  <SelectItem key={k} value={k}>
                    {v}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          ) : (
            <>
              <Input id="type" value={tipoAtual} disabled aria-disabled="true" />
              <p className="mt-1 text-[11px] text-stone-500">
                Tipo não pode ser alterado após criação.
              </p>
            </>
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
              {entradasDeOpcoes(opcoes.units).map(([k, v]) => (
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
              {entradasDeOpcoes(opcoes.categories).map(([k, v]) => (
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
              {entradasDeOpcoes(opcoes.brands).map(([k, v]) => (
                <SelectItem key={k} value={k}>
                  {v}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      </CardContent>
    </Card>
  );
}
