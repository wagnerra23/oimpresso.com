// Painel do saldo por variação na coluna Estoque da lista (UC-PIDX-37 · D7).
import { qtdComUnidade } from '../../Unificado/_components/catalogo';
import { somaDasVariacoes, type LinhaProduto, type SaldoDaVariacao } from './tipos';
/** Painel do saldo por variação (UC-PIDX-37 · D7). O total é a soma das linhas, que o servidor
 *  calcula com a mesma conta da coluna Estoque; por isso bate com o saldo da linha. */
export default function SaldoPorVariacao({ linha, variacoes }: { linha: LinhaProduto; variacoes: SaldoDaVariacao[] }) {
  const unidade = linha.unidade_curta ?? linha.unidade ?? '';
  return (
    <div data-contract="produto-lista-saldo-variacoes-painel">
      <p className="mb-1.5 text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">Saldo por variação</p>
      <table className="w-full text-[12px]">
        <caption className="sr-only">Saldo por variação de {linha.nome}</caption>
        <tbody>
          {variacoes.map((v) => (
            <tr key={v.sku}>
              <td className="max-w-0 truncate py-0.5 pr-2" title={v.nome}>
                {v.nome}
              </td>
              <td className="py-0.5 pr-2 font-mono text-[11px] text-muted-foreground">{v.sku}</td>
              <td className={'py-0.5 text-right font-mono tabular-nums ' + (v.saldo <= 0 ? 'font-semibold text-destructive-fg' : '')}>
                {qtdComUnidade(v.saldo, unidade)}
              </td>
            </tr>
          ))}
        </tbody>
        <tfoot>
          <tr className="border-t border-border">
            <td colSpan={2} className="pt-1.5 font-semibold">
              Total
            </td>
            <td className="pt-1.5 text-right font-mono font-semibold tabular-nums">{qtdComUnidade(somaDasVariacoes(variacoes), unidade)}</td>
          </tr>
        </tfoot>
      </table>
    </div>
  );
}
