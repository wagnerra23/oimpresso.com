/**
 * Avatar do item do catálogo — âncora visual da coluna Produto (handoff V2 §3.2).
 *
 * Na lista densa é o que o balcão acha primeiro, antes de ler o texto: mesmo produto, mesma
 * cor, sempre. Não é enfeite — é o que permite reconhecer "o banner" numa varredura de olho
 * sem soletrar o nome inteiro.
 *
 * ⚠️ POR QUE ISTO É CÓPIA, e não import
 *
 * A `Pages/Cliente/_components/Avatar.tsx` faz exatamente isto, com este hash e esta paleta.
 * Importar de lá seria melhor em qualquer outro projeto. Aqui não: o charter desta tela tem
 * restrição dura de [M] (2026-08-18) — "a tela `/contacts` não se toca em nenhuma hipótese,
 * nem o arquivo, nem componente compartilhado que ela consuma". Importar cria justamente o
 * compartilhamento que a restrição proíbe: o primeiro ajuste pedido aqui viraria pressão pra
 * editar um arquivo que a tela da cliente renderiza.
 *
 * O hash e as 12 rampas são cópia LITERAL, e é de propósito: o mesmo nome tem de sair com a
 * mesma cor nas duas listagens (catraca de regressão contra a golden master). Divergir aqui é
 * o defeito, não a duplicação.
 *
 * O conserto de verdade — promover o componente pra `Components/shared/` e as duas telas
 * consumirem — mexe na /contacts e precisa de decisão de [M]. Até lá, duas cópias iguais e
 * este comentário explicando por quê.
 */

import { useMemo } from 'react';
import { Inline } from '@/Components/layout';

/** djb2-like, unsigned. Idêntico ao `hashStr` da golden master. */
function hashStr(s: string): number {
  let h = 0;
  for (let i = 0; i < s.length; i++) {
    h = (h * 31 + s.charCodeAt(i)) >>> 0;
  }
  return h;
}

/** As 12 rampas da golden master, na mesma ordem — trocar a ordem já troca a cor de todo mundo. */
const RAMPAS: ReadonlyArray<string> = [
  'linear-gradient(135deg, oklch(0.65 0.18 25),   oklch(0.55 0.20 350))',
  'linear-gradient(135deg, oklch(0.65 0.18 60),   oklch(0.55 0.20 30))',
  'linear-gradient(135deg, oklch(0.65 0.18 110),  oklch(0.55 0.20 80))',
  'linear-gradient(135deg, oklch(0.65 0.18 150),  oklch(0.55 0.20 130))',
  'linear-gradient(135deg, oklch(0.65 0.18 180),  oklch(0.55 0.20 170))',
  'linear-gradient(135deg, oklch(0.65 0.18 210),  oklch(0.55 0.20 200))',
  'linear-gradient(135deg, oklch(0.65 0.18 240),  oklch(0.55 0.20 270))',
  'linear-gradient(135deg, oklch(0.65 0.18 290),  oklch(0.55 0.20 320))',
  'linear-gradient(135deg, oklch(0.55 0.15 47),   oklch(0.65 0.15 107))',
  'linear-gradient(135deg, oklch(0.55 0.15 280),  oklch(0.65 0.15 340))',
  'linear-gradient(135deg, oklch(0.55 0.15 200),  oklch(0.65 0.15 160))',
  'linear-gradient(135deg, oklch(0.55 0.15 0),    oklch(0.65 0.15 60))',
];

/**
 * Iniciais: uma palavra → duas primeiras letras; mais de uma → primeira de cada PONTA.
 *
 * Idêntico ao `avatarInitial` da golden master, e isso foi MEDIDO no protótipo rodando, não
 * deduzido do código: "Banner lona 440g 4x0" sai como **B4**, "Adesivo vinil branco brilho"
 * como **AB**, "Projeto / arte final" como **PF**. A função `monograma` que aparece no script
 * do pacote (primeira + SEGUNDA palavra, que daria "BL" e "AV") não é a que a tela usa — o
 * `Avatar` do DS ignora ela. Implementar o `monograma` teria dado cor e letra diferentes das
 * que o produto aprovou na tela.
 */
function iniciaisProduto(nome: string): string {
  const limpo = String(nome ?? '').trim();
  if (limpo === '') return '?';
  const palavras = limpo.split(/\s+/).filter(Boolean);
  if (palavras.length === 1) return palavras[0]!.slice(0, 2).toUpperCase();
  return ((palavras[0]![0] ?? '') + (palavras[palavras.length - 1]![0] ?? '')).toUpperCase();
}

export interface AvatarProdutoProps {
  nome: string;
  /**
   * Semente da cor. Passe o CÓDIGO, não o nome: renomear o produto não pode trocar a cor com
   * que a pessoa já aprendeu a reconhecê-lo na lista.
   */
  seed: string;
  /** Lado em px. 32 na linha da tabela (handoff V2 §3.1). */
  tamanho?: number;
  className?: string;
}

export function AvatarProduto({ nome, seed, tamanho = 32, className = '' }: AvatarProdutoProps) {
  const background = useMemo(() => RAMPAS[hashStr(seed) % RAMPAS.length], [seed]);
  const iniciais = useMemo(() => iniciaisProduto(nome), [nome]);

  return (
    // `Inline` do DS em vez de `flex` solto — ADR 0253. `gap 0` porque o conteúdo é um texto só.
    <Inline
      gap={0}
      justify="center"
      className={'rounded-md font-semibold flex-shrink-0 text-white ' + className}
      style={{ background, width: tamanho, height: tamanho, fontSize: Math.round(tamanho * 0.4) }}
      // Decorativo: o nome do produto está no texto ao lado, em elemento legível. Anunciar as
      // iniciais faria o leitor de tela ler "B4, Banner lona 440g".
      aria-hidden="true"
    >
      {iniciais}
    </Inline>
  );
}

export default AvatarProduto;
