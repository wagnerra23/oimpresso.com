// Preço do produto simples, dentro do card "Impostos e preços" das telas Novo e Editar.
// Os campos do protótipo (FormProduto, produto-blade-forms.jsx): compra sem e com imposto, % de
// lucro, venda sem e com imposto. Os dois "com imposto" são só leitura, derivados da alíquota.
// A conta mora em precos.ts.

import { Grid } from '@/Components/layout';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import NumericInputPtBR from '@/Components/ui/numeric-input-ptbr';
import { formatDecimalPtBR } from '@/Lib/numberPtBR';
import { CASAS_PRECO, abaixoDaMargemMinima, arredondar, mudarCompra, mudarMargem, mudarVenda, somarPercentual } from './precos';
import type { PrecoSimples } from './precos';

interface Props {
  valor: PrecoSimples;
  mudar: (p: PrecoSimples) => void;
  /** Alíquota (%) do imposto escolhido no mesmo card. */
  aliquota: number;
  /** Margem mínima da empresa em % (UC-FLX-35, Fluxos). Null = regra desligada, sem aviso. */
  margemMinima?: number | null;
}

export function CamposPreco({ valor, mudar, aliquota, margemMinima }: Props) {
  const exibir = (n: number) => formatDecimalPtBR(arredondar(n), CASAS_PRECO);

  return (
    <Grid cols={5} gap={4} className="col-span-full" role="group" aria-label="Preço do produto">
      <div>
        <Label htmlFor="single_dpp">Compra sem imposto</Label>
        <NumericInputPtBR
          id="single_dpp"
          value={valor.compra}
          onChange={(n) => mudar(mudarCompra(valor, n))}
          precision={CASAS_PRECO}
          className="text-right"
        />
      </div>
      <div>
        <Label htmlFor="single_dpp_inc_tax">Compra com imposto</Label>
        <Input
          id="single_dpp_inc_tax"
          readOnly
          tabIndex={-1}
          value={exibir(somarPercentual(valor.compra, aliquota))}
          className="text-right"
        />
      </div>
      <div>
        <Label htmlFor="profit_percent">% de lucro</Label>
        <NumericInputPtBR
          id="profit_percent"
          value={valor.margem}
          onChange={(n) => mudar(mudarMargem(valor, n))}
          precision={CASAS_PRECO}
          className="text-right"
        />
        {abaixoDaMargemMinima(valor, margemMinima) && (
          <p data-testid="aviso-margem-minima" className="text-[12px] text-warning-fg mt-1">
            Abaixo da margem mínima da empresa ({formatDecimalPtBR(margemMinima as number, 2)}%, em Fluxos): orçamento com
            este produto só vira venda com aprovação.
          </p>
        )}
      </div>
      <div>
        <Label htmlFor="single_dsp">Venda sem imposto</Label>
        <NumericInputPtBR
          id="single_dsp"
          value={valor.venda}
          onChange={(n) => mudar(mudarVenda(valor, n))}
          precision={CASAS_PRECO}
          className="text-right"
        />
      </div>
      <div>
        <Label htmlFor="single_dsp_inc_tax">Venda com imposto</Label>
        <Input
          id="single_dsp_inc_tax"
          readOnly
          tabIndex={-1}
          value={exibir(somarPercentual(valor.venda, aliquota))}
          className="text-right"
        />
      </div>
    </Grid>
  );
}
