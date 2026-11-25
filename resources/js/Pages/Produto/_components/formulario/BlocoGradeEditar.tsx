// Grade do produto variável na tela Editar (UC-PEDIT-32), dentro de "Impostos e preços". Mesma tabela
// do Novo (BlocoGrade.tsx, forma do protótipo produto-blade-forms.jsx), sobre as variações gravadas:
// cada linha tem o sub-SKU e os cinco preços, com a conta do Blade (precos.ts). O valor da variação
// fica só leitura: acrescentar, tirar ou renomear valor segue na tela antiga por enquanto. O envio
// mora em gradeEditar.ts.
import NumericInputPtBR from '@/Components/ui/numeric-input-ptbr';
import { Input } from '@/Components/ui/input';
import { formatDecimalPtBR } from '@/Lib/numberPtBR';
import { CASAS_PRECO, arredondar, mudarCompra, mudarMargem, mudarVenda, somarPercentual } from './precos';
import type { GradeGravada, LinhaGradeEditada } from './gradeEditar';

interface Props {
  gravada: GradeGravada;
  linhas: LinhaGradeEditada[];
  mudar: (l: LinhaGradeEditada[]) => void;
  aliquota: number;
}

export function BlocoGradeEditar({ gravada, linhas, mudar, aliquota }: Props) {
  const exibir = (n: number) => formatDecimalPtBR(arredondar(n), CASAS_PRECO);
  const trocar = (i: number, l: LinhaGradeEditada) => mudar(linhas.map((x, j) => (j === i ? l : x)));

  return (
    <div className="col-span-full" role="group" aria-label="Grade da variação">
      <h4 className="m-0 mb-3 text-[11px] font-medium uppercase tracking-[.06em] text-muted-foreground">
        Variações{gravada.modelo ? ` · ${gravada.modelo}` : ''}
      </h4>
      <div className="overflow-x-auto">
        <table className="w-full text-[13px]" aria-label="Valores da variação">
          <thead>
            <tr className="text-left text-[12px] text-muted-foreground">
              <th className="py-1 pr-2 font-medium">Valor</th>
              <th className="py-1 pr-2 font-medium">Sub-SKU</th>
              <th className="py-1 pr-2 font-medium text-right">Compra sem imposto</th>
              <th className="py-1 pr-2 font-medium text-right">Compra com imposto</th>
              <th className="py-1 pr-2 font-medium text-right">% de lucro</th>
              <th className="py-1 pr-2 font-medium text-right">Venda sem imposto</th>
              <th className="py-1 font-medium text-right">Venda com imposto</th>
            </tr>
          </thead>
          <tbody>
            {linhas.map((l, i) => {
              const n = i + 1;
              const valor = gravada.linhas.find((g) => g.variacaoId === l.variacaoId)?.valor ?? '';
              return (
                <tr key={l.variacaoId}>
                  <td className="py-1 pr-2 font-medium">{valor}</td>
                  <td className="py-1 pr-2">
                    <Input
                      aria-label={`Sub-SKU ${n}`}
                      className="font-mono text-[12px]"
                      value={l.subSku}
                      onChange={(e) => trocar(i, { ...l, subSku: e.target.value })}
                    />
                  </td>
                  <td className="py-1 pr-2">
                    <NumericInputPtBR
                      aria-label={`Compra sem imposto ${n}`}
                      value={l.preco.compra}
                      onChange={(x) => trocar(i, { ...l, preco: mudarCompra(l.preco, x) })}
                      precision={CASAS_PRECO}
                      className="text-right"
                    />
                  </td>
                  <td className="py-1 pr-2 text-right tabular-nums">{exibir(somarPercentual(l.preco.compra, aliquota))}</td>
                  <td className="py-1 pr-2">
                    <NumericInputPtBR
                      aria-label={`% de lucro ${n}`}
                      value={l.preco.margem}
                      onChange={(x) => trocar(i, { ...l, preco: mudarMargem(l.preco, x) })}
                      precision={CASAS_PRECO}
                      className="text-right"
                    />
                  </td>
                  <td className="py-1 pr-2">
                    <NumericInputPtBR
                      aria-label={`Venda sem imposto ${n}`}
                      value={l.preco.venda}
                      onChange={(x) => trocar(i, { ...l, preco: mudarVenda(l.preco, x) })}
                      precision={CASAS_PRECO}
                      className="text-right"
                    />
                  </td>
                  <td className="py-1 text-right tabular-nums">{exibir(somarPercentual(l.preco.venda, aliquota))}</td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
      <p className="mt-2 text-[12px] text-muted-foreground">
        Só as linhas que você mudar são gravadas. Para acrescentar, tirar ou renomear um valor, use a tela
        antiga (<a href="?classico=1" className="underline">abrir</a>).
      </p>
    </div>
  );
}
