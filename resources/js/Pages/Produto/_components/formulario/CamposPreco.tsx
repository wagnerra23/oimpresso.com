// Preço do produto simples, dentro do card "Preço & Imposto" da tela Novo produto.
// Os campos do protótipo (FormProduto, produto-blade-forms.jsx): compra sem e com imposto, % de
// lucro, venda sem e com imposto. Os dois "com imposto" são só leitura, derivados da alíquota.
// A conta mora em precos.ts.

import { Grid } from '@/Components/layout';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import NumericInputPtBR from '@/Components/ui/numeric-input-ptbr';
import { formatDecimalPtBR } from '@/Lib/numberPtBR';
import { CASAS_PRECO, arredondar, mudarCompra, mudarMargem, mudarVenda, somarPercentual } from './precos';
import type { PrecoSimples } from './precos';

interface Props {
  valor: PrecoSimples;
  mudar: (p: PrecoSimples) => void;
  /** Alíquota (%) do imposto escolhido no mesmo card. */
  aliquota: number;
}

export function CamposPreco({ valor, mudar, aliquota }: Props) {
  const exibir = (n: number) => formatDecimalPtBR(arredondar(n), CASAS_PRECO);

  return (
    <Grid cols={5} gap={4} className="col-span-2" role="group" aria-label="Preço do produto">
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
