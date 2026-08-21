/**
 * Miniatura do item na coluna Produto — 30px (handoff 21/08 §3.2).
 *
 * SUBSTITUI o `AvatarProduto` (monograma colorido) que a tela usava até o pacote de 18/08.
 * A troca não é de estilo, é de significado: no design system o `Avatar` é canon de PESSOA —
 * iniciais e cor por hash existem porque gente tem nome próprio e a cor vira apelido visual.
 * Produto tem FOTO. Um monograma "B4" no lugar da foto do banner não ancora nada: quem
 * procura reconhece a arte, não as iniciais do nome cadastrado.
 *
 * Sem foto, a miniatura vira espaço RESERVADO — borda tracejada declarando "falta imagem
 * aqui" — em vez de inventar um glifo que compete com o nome ao lado. É a diferença entre
 * "não temos foto deste item" e "este item é assim".
 *
 * ⚠️ Enquanto o cadastro não expõe URL de imagem, `url` chega sempre vazia e toda linha mostra
 * o espaço reservado. É o estado honesto: o handoff registra "ligar URLs reais quando o
 * cadastro tiver imagens" como pendência §15 item 4.
 */

import { Package } from 'lucide-react';

export interface MiniaturaProdutoProps {
  /** Nome do item — vira o `alt` da foto. Só isso; o texto legível está na célula ao lado. */
  nome: string;
  /** URL da foto do produto. Ausente enquanto o cadastro não guarda imagem. */
  url?: string | null;
  /** Lado em px. 30 na linha da tabela; 60 na tira de identidade do painel (§5). */
  tamanho?: number;
}

export function MiniaturaProduto({ nome, url, tamanho = 30 }: MiniaturaProdutoProps) {
  const lado = { width: tamanho, height: tamanho };

  if (url) {
    return (
      <span
        className="grid flex-shrink-0 place-items-center overflow-hidden rounded border border-border bg-muted"
        style={lado}
      >
        <img src={url} alt={nome} className="h-full w-full object-cover" />
      </span>
    );
  }

  return (
    <span
      className="grid flex-shrink-0 place-items-center rounded border border-dashed border-muted-foreground/45 bg-muted text-muted-foreground"
      style={lado}
      // `role="img"` + rótulo porque o tracejado sozinho não se explica a quem não vê a tela
      // (handoff §11): o leitor anuncia a AUSÊNCIA da foto em vez de pular a célula.
      role="img"
      aria-label="Produto sem imagem"
      title="Sem imagem"
    >
      <Package size={Math.round(tamanho * 0.5)} strokeWidth={1.75} aria-hidden="true" />
    </span>
  );
}

export default MiniaturaProduto;
