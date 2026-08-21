/**
 * Barra de ações em lote — flutua sobre a lista quando há seleção (handoff 21/08 §4.4).
 *
 * Fica presa ao rodapé da janela (`sticky bottom`), não ao fim da tabela: com 100 linhas por
 * página, uma barra no fim da tabela estaria fora da tela justamente enquanto a pessoa marca
 * as caixas. Ela aparece onde a mão já está.
 *
 * **"· N fora desta página" não é enfeite.** A caixa do cabeçalho marca a página SOMANDO à
 * seleção que já existe, então dá pra selecionar 10 na página 1, virar pra página 2 e marcar
 * mais 5. Sem essa frase, o operador leria "15 itens selecionados" vendo 5 marcados na tela e
 * concluiria que o sistema errou — ou pior, inativaria 15 achando que eram 5.
 *
 * ⚠️ O handoff lista três ações: Exportar seleção, Gerar etiquetas e Inativar. Só **Inativar**
 * é oferecida aqui, e a razão é dura: as outras duas não existem no servidor desta base.
 * `/products/download-excel` exporta o catálogo inteiro e ignora seleção; `/labels/show` só
 * aceita `product_id` de UM produto. No protótipo elas respondem com um aviso e não fazem nada
 * — o que é correto num protótipo e seria mentira numa tela de produção. Botão que não faz o
 * que promete custa mais que botão ausente: a pessoa clica, nada acontece, e a partir daí ela
 * desconfia dos que funcionam.
 */

import { X } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Inline } from '@/Components/layout';

export interface BulkBarProps {
  /** Total selecionado, incluindo o que está fora da página visível. */
  total: number;
  /** Quantos dos selecionados NÃO estão na página que a pessoa está vendo. */
  foraDaPagina: number;
  /** `undefined` quando o perfil não pode inativar produto — o botão não é montado. */
  onInativar?: () => void;
  onLimpar: () => void;
}

export function BulkBar({ total, foraDaPagina, onInativar, onLimpar }: BulkBarProps) {
  if (total === 0) return null;

  return (
    <div className="sticky bottom-4 z-20 mt-3 flex justify-center pointer-events-none">
      <Inline
        gap={3}
        className="pointer-events-auto rounded-lg border border-border bg-card px-3 py-2 shadow-lg"
        role="status"
        aria-live="polite"
      >
        <span className="text-[12.5px] tabular-nums text-foreground">
          <strong className="font-semibold">{total.toLocaleString('pt-BR')}</strong>{' '}
          {total === 1 ? 'item selecionado' : 'itens selecionados'}
          {foraDaPagina > 0 && (
            <span className="text-muted-foreground"> · {foraDaPagina} fora desta página</span>
          )}
        </span>

        {onInativar && (
          <Button variant="ghost" size="sm" onClick={onInativar} className="h-7 text-xs text-destructive-fg hover:bg-destructive-soft">
            Inativar
          </Button>
        )}

        <button
          type="button"
          onClick={onLimpar}
          aria-label="Limpar seleção"
          className="inline-flex h-6 w-6 items-center justify-center rounded text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
        >
          <X size={14} />
        </button>
      </Inline>
    </div>
  );
}

export default BulkBar;
