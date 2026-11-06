// Seção "Configurações" do formulário de produto (Novo e Editar), na forma do protótipo
// (produto-blade-forms.jsx): validade, "não está à venda", peso, medidas da embalagem e campos
// personalizados. Até 2026-11-04 era a seção recolhível "Mais opções"; no protótipo ela fica aberta.
//
// As linhas seguem as do protótipo: a 1ª tem a validade, "não está à venda" e o peso bruto; a 2ª, o
// peso líquido e as três medidas da embalagem. Numa grade só, sem a validade (empresa que não a usa),
// tudo subia uma casa e a altura caía sozinha na linha de baixo.

import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Checkbox } from '@/Components/ui/checkbox';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import { Grid, Inline, Stack } from '@/Components/layout';
import type { CamposProduto, DefinirCampo, ErrosProduto, ModoFormulario } from './tipos';

/** O Select do Radix não aceita item com valor vazio: "não aplicável" é `''` no envio. */
const NAO_APLICAVEL = 'nao-aplicavel';

interface Props {
  modo: ModoFormulario;
  data: CamposProduto;
  definir: DefinirCampo;
  /** Os erros de validação do peso líquido e das dimensões. */
  errors?: ErrosProduto;
  /**
   * Só no Novo, quando a empresa usa validade e a calcula pela fabricação. Com a validade
   * lançada na compra (`add_expiry`), o Blade esconde o campo e manda 12 meses.
   */
  mostrarValidade?: boolean;
}

export function BlocoConfiguracoes({ modo, data, definir, errors = {}, mostrarValidade }: Props) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>Configurações</CardTitle>
      </CardHeader>
      <CardContent>
        <Stack gap={4}>
        <Grid cols={4} gap={4}>
        {mostrarValidade && (
          <div>
            <Label htmlFor="expiry_period">Expira em</Label>
            {/* UC-PCAD-12: sem o período, o ProductController@store descarta o número. */}
            <Inline gap={2}>
              <Input
                id="expiry_period"
                type="number"
                value={data.expiry_period}
                onChange={(e) => definir('expiry_period', e.target.value)}
                placeholder="12"
                className="tabular-nums"
              />
              <Select
                value={data.expiry_period_type || NAO_APLICAVEL}
                onValueChange={(v) => definir('expiry_period_type', v === NAO_APLICAVEL ? '' : v)}
              >
                <SelectTrigger id="expiry_period_type" aria-label="Período da validade">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="months">meses</SelectItem>
                  <SelectItem value="days">dias</SelectItem>
                  <SelectItem value={NAO_APLICAVEL}>não aplicável</SelectItem>
                </SelectContent>
              </Select>
            </Inline>
          </div>
        )}
        {/* O formulário do Editar não envia "não está à venda": a caixa só existe no Novo. */}
        {modo === 'novo' && (
          <div>
            {/* Rótulo invisível da mesma altura do dos campos vizinhos: a caixa fica na linha do
                campo (30px, a altura do .cw-input), e não embaixo, junto do texto de ajuda. */}
            <Label aria-hidden className="invisible">
              Venda
            </Label>
            <Inline gap={2} className="h-[30px]">
              <Checkbox
                id="not_for_selling"
                checked={data.not_for_selling === 1}
                onCheckedChange={(v) => definir('not_for_selling', v === true ? 1 : 0)}
              />
              <Label htmlFor="not_for_selling" className="text-[13px] font-normal">Não está à venda (apenas insumo)</Label>
            </Inline>
          </div>
        )}
        {/* UC-PCAD-16 e UC-PEDIT-13: o "Peso" de sempre é o peso bruto, que o WooCommerce usa no frete. */}
        <div>
          <Label htmlFor="weight">Peso bruto (kg)</Label>
          <Input
            id="weight"
            type="number"
            step="0.001"
            min="0"
            value={String(data.weight ?? '')}
            onChange={(e) => definir('weight', e.target.value)}
            className="tabular-nums"
          />
          <p className="text-[12px] text-muted-foreground mt-1">Com embalagem. É o peso usado no frete.</p>
        </div>
        </Grid>
        <Grid cols={4} gap={4}>
        <CampoMedida
          id="peso_liquido"
          rotulo="Peso líquido (kg)"
          passo="0.001"
          valor={data.peso_liquido}
          mudar={(v) => definir('peso_liquido', v)}
          erro={errors.peso_liquido}
        />
        <CampoMedida
          id="largura_cm"
          rotulo="Largura (cm)"
          passo="0.01"
          valor={data.largura_cm}
          mudar={(v) => definir('largura_cm', v)}
          erro={errors.largura_cm}
        />
        <CampoMedida
          id="altura_cm"
          rotulo="Altura (cm)"
          passo="0.01"
          valor={data.altura_cm}
          mudar={(v) => definir('altura_cm', v)}
          erro={errors.altura_cm}
        />
        <CampoMedida
          id="comprimento_cm"
          rotulo="Comprimento (cm)"
          passo="0.01"
          valor={data.comprimento_cm}
          mudar={(v) => definir('comprimento_cm', v)}
          erro={errors.comprimento_cm}
        />
        </Grid>
        <Grid cols={4} gap={4}>
        <div>
          <Label htmlFor="product_custom_field1">Campo personalizado 1</Label>
          <Input
            id="product_custom_field1"
            value={data.product_custom_field1}
            onChange={(e) => definir('product_custom_field1', e.target.value)}
          />
        </div>
        <div>
          <Label htmlFor="product_custom_field2">Campo personalizado 2</Label>
          <Input
            id="product_custom_field2"
            value={data.product_custom_field2}
            onChange={(e) => definir('product_custom_field2', e.target.value)}
          />
        </div>
        </Grid>
        </Stack>
      </CardContent>
    </Card>
  );
}

/** Um número opcional do produto (peso em kg ou medida da embalagem em cm), com o erro do servidor. */
function CampoMedida({
  id,
  rotulo,
  passo,
  valor,
  mudar,
  erro,
}: {
  id: string;
  rotulo: string;
  passo: string;
  valor: number | string | undefined;
  mudar: (v: string) => void;
  erro?: string;
}) {
  return (
    <div>
      <Label htmlFor={id}>{rotulo}</Label>
      <Input
        id={id}
        type="number"
        step={passo}
        min="0"
        value={String(valor ?? '')}
        onChange={(e) => mudar(e.target.value)}
        aria-invalid={erro ? true : undefined}
        className="tabular-nums"
      />
      {erro && <p className="text-[12px] text-destructive mt-1">{erro}</p>}
    </div>
  );
}
