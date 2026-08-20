import { NotebookPen } from 'lucide-react';
import PopoverAncorado from './PopoverAncorado';
import type { ProdutoRow } from './catalogo';

/**
 * Ícone de recado ao lado do nome — só nos itens que têm observação (handoff V2 §4.7).
 *
 * Passar o mouse mostra o texto; clicar fixa. Fora do popover o texto não aparece em lugar
 * nenhum da lista: cabê-lo na linha exigiria uma quarta linha em toda linha da tabela, e a
 * observação existe justamente porque é exceção.
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
      className="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded border border-border bg-muted text-muted-foreground transition-colors hover:text-foreground hover:border-foreground/30"
      conteudo={
        <>
          <p className="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground mb-1.5">
            Observação
          </p>
          <p className="text-[12px] leading-relaxed whitespace-pre-line">{produto.obs}</p>
        </>
      }
    >
      <NotebookPen size={12} />
    </PopoverAncorado>
  );
}

export default Observacao;
