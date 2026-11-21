/**
 * Gatilho de revelação progressiva da linha do catálogo (handoff V2 §4.9).
 *
 * A lista fica enxuta e o detalhe aparece só quando a pessoa demonstra interesse. Dois lugares
 * usam este componente hoje — saldo por local e observação — e o comportamento é o mesmo nos
 * dois, porque comportamento diferente pra gesto igual é o que faz o operador parar de tentar.
 * (O terceiro do pacote, a faixa de preço, aguarda decisão de schema.)
 *
 * ## Isto NÃO reimplementa posicionamento
 *
 * O `Popover` do DS (`@/Components/ui/popover`, Radix) é o dono de tudo que é mecânica de
 * overlay: portal pro `body` — que é o que escapa do `overflow-x` da tabela, senão o painel
 * seria clipado —, colisão com a borda da janela, **flip** pra cima quando não cabe embaixo,
 * reposicionamento na rolagem, Esc e clique-fora. Hand-rolar isso é justamente o que o
 * `REGISTRY_DS_COMPONENTES` proíbe ("dropdown posicionado na mão").
 *
 * O que sobra aqui, e é a única razão deste arquivo existir, é a **política de abertura** que o
 * Radix não tem pronta:
 *
 * - **Hover é atalho, clique FIXA.** O `Popover` do DS abre só no clique; o `HoverCard` abre só
 *   no hover e não fixa. O pacote pede os dois: hover pra consulta de relance, clique pra
 *   segurar. E hover sozinho não serve pra toque nem pra teclado — quem está no tablet do
 *   balcão ou navegando por Tab nunca dispara `mouseenter`.
 * - **A linha inteira abre o drawer**, então o clique no gatilho precisa parar de subir: sem
 *   isso, olhar o saldo por local abriria a ficha do produto por cima do que a pessoa pediu.
 *
 * Por isso o gatilho é `PopoverAnchor` + botão próprio, e não `PopoverTrigger`: o Trigger tem
 * toggle interno que brigaria com o `open` controlado — clicar com o painel já aberto por hover
 * mandaria fechar no mesmo gesto em que a gente quer fixar.
 */

import { useState } from 'react';
import type { ReactNode } from 'react';
import { Popover, PopoverAnchor, PopoverContent } from '@/Components/ui/popover';

export interface PopoverAncoradoProps {
  /** Conteúdo do gatilho — o que fica visível na linha. */
  children: ReactNode;
  /** Conteúdo do painel. Só é montado quando abre. */
  conteudo: ReactNode;
  /** Largura em px. 216 estoque · 244 observação (medidas do pacote). */
  largura?: number;
  /**
   * `end` alinha a borda direita do painel com a do gatilho (colunas à direita);
   * `start` alinha a esquerda (observação, que fica junto do nome).
   */
  alinhar?: 'start' | 'center' | 'end';
  /** Rótulo acessível — vira `aria-label` do gatilho e do painel. */
  rotulo: string;
  className?: string;
}

export function PopoverAncorado({
  children, conteudo, largura = 244, alinhar = 'end', rotulo, className = '',
}: PopoverAncoradoProps) {
  const [aberto, setAberto] = useState(false);
  const [fixado, setFixado] = useState(false);

  const fechar = () => { setAberto(false); setFixado(false); };

  return (
    <Popover
      open={aberto}
      // Só chega aqui como `false` (Esc / clique fora), porque o gatilho é Anchor, não Trigger.
      // Fechar por fora desfixa junto: senão o próximo hover abriria já "preso".
      onOpenChange={(v) => { if (!v) fechar(); }}
    >
      <PopoverAnchor asChild>
        <button
          type="button"
          aria-haspopup="dialog"
          aria-expanded={aberto}
          aria-label={rotulo}
          className={className}
          onMouseEnter={() => { if (!fixado) setAberto(true); }}
          onMouseLeave={() => { if (!fixado) setAberto(false); }}
          onFocus={() => { if (!fixado) setAberto(true); }}
          onClick={(ev) => {
            ev.stopPropagation();
            if (fixado) fechar();
            else { setAberto(true); setFixado(true); }
          }}
          // Enter/Espaço no gatilho não podem acionar a linha (que também é `role="button"`).
          onKeyDown={(ev) => { if (ev.key === 'Enter' || ev.key === ' ') ev.stopPropagation(); }}
        >
          {children}
        </button>
      </PopoverAnchor>

      <PopoverContent
        align={alinhar}
        sideOffset={4}
        collisionPadding={8}
        style={{ width: largura }}
        // `w-72 p-4` do default do DS é painel de formulário; aqui é leitura densa de linha.
        className="w-auto p-0 px-3 py-2.5 text-left"
        aria-label={rotulo}
        // Sem foco automático: o gatilho abre no hover, e roubar o foco faria a tela pular
        // debaixo de quem só passou o mouse. Esc e clique-fora seguem funcionando (Radix).
        onOpenAutoFocus={(ev) => ev.preventDefault()}
      >
        {conteudo}
      </PopoverContent>
    </Popover>
  );
}

export default PopoverAncorado;
