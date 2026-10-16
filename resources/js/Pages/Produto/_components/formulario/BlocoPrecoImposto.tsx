// Seção "Preço & Imposto" do formulário de produto (Novo e Editar).

import { Label } from '@/Components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import { OrigemMercadoriaField } from '../OrigemMercadoriaField';
import type { CamposProduto, DefinirCampo, ErrosProduto, OpcoesProduto } from './tipos';

interface Props {
  data: CamposProduto;
  definir: DefinirCampo;
  errors: ErrosProduto;
  opcoes: OpcoesProduto;
}

export function BlocoPrecoImposto({ data, definir, errors, opcoes }: Props) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>Preço & Imposto</CardTitle>
      </CardHeader>
      <CardContent className="grid grid-cols-2 gap-4">
        <div>
          <Label htmlFor="tax">Taxa de imposto</Label>
          <Select value={String(data.tax ?? '')} onValueChange={(v) => definir('tax', Number(v))}>
            <SelectTrigger id="tax">
              <SelectValue placeholder="Nenhuma" />
            </SelectTrigger>
            <SelectContent>
              {Object.entries(opcoes.taxes).map(([k, v]) => (
                <SelectItem key={k} value={k}>
                  {v}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div>
          <Label htmlFor="tax_type">Imposto inclui no preço?</Label>
          <Select
            value={data.tax_type}
            onValueChange={(v) => definir('tax_type', v as 'inclusive' | 'exclusive')}
          >
            <SelectTrigger id="tax_type">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="exclusive">Exclusivo (somar imposto)</SelectItem>
              <SelectItem value="inclusive">Inclusivo (já incluído)</SelectItem>
            </SelectContent>
          </Select>
        </div>
        <OrigemMercadoriaField
          value={data.origem_mercadoria}
          onChange={(v) => definir('origem_mercadoria', v)}
          error={errors.origem_mercadoria}
        />
      </CardContent>
    </Card>
  );
}
