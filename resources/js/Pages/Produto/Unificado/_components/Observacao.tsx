import { NotebookPen } from 'lucide-react';
import PopoverAncorado from './PopoverAncorado';
import type { ProdutoRow } from './catalogo';

/**
 * Chip de recado ao lado do nome — só nos itens que têm observação (handoff V3 §3.2).
 *
 * Era ÍCONE MUDO até 24/08, e a divergência #7 do V3 é exatamente essa: um quadradinho sem
 * texto não conta ao balcão que o item é "sob encomenda", então a pessoa abre o painel item a
 * item pra descobrir — que é o custo que o marcador existia pra evitar. Agora o chip mostra o
 * começo do texto (truncado na largura da célula) e o popover entrega a nota inteira.
 *
 * ⚠️ NÃO é "vermelho quando crítica" (§3.2): criticidade não existe no cadastro do UltimatePOS
 * — ver o aviso abaixo. Enquanto não houver campo, todo recado é neutro; pintar de vermelho por
 * heurística de texto seria adivinhação exibida como fato.
 *
 * ⚠️ Os badges "Sob encomenda" / "Exige aprovação" do pacote NÃO são montados: eles não
 * existem no cadastro do UltimatePOS — no protótipo são campo do dado de mentira. Deduzi-los
 * do texto seria adivinhação exibida como fato.
 */
export function Observacao({ produto }: { produto: ProdutoRow }) {
  return (
    <PopoverAncorado
      rotulo={`Observação de ${produto.name}`}
      largura={244}
      alinhar="start"
      className="inline-flex min-w-0 max-w-[160px] shrink items-center gap-1 rounded border border-border bg-muted px-1.5 py-0.5 text-muted-foreground transition-colors hover:border-foreground/30 hover:text-foreground"
      conteudo={
        <>
          <p className="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground mb-1.5">
            Observação
          </p>
          <p className="text-[12px] leading-relaxed whitespace-pre-line">{produto.obs}</p>
        </>
      }
    >
      <NotebookPen size={11} className="shrink-0" />
      {/* `truncate` aqui é acomodação, não ocultação: o texto inteiro está a um hover de
          distância no popover, e a nota é livre (pode ter 400 caracteres). */}
      <span className="truncate text-[11px] leading-tight">{produto.obs}</span>
    </PopoverAncorado>
  );
}

export default Observacao;
