// Largura e altura de um elemento, atualizadas quando ele muda de tamanho (janela redimensionada,
// menu lateral recolhido, painel aberto). Usado pela lista de produtos para encaixar as colunas no
// espaço da tabela e dar altura à grade — UC-PIDX-26 e UC-PIDX-27.

import { useEffect, useState, type RefObject } from 'react';

export interface Tamanho {
  largura: number;
  altura: number;
}

export function useTamanho(ref: RefObject<HTMLElement | null>): Tamanho {
  const [tamanho, setTamanho] = useState<Tamanho>({ largura: 0, altura: 0 });
  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    // `clientWidth`/`clientHeight`: a área de dentro, sem borda e sem a barra de rolagem.
    const medir = () =>
      setTamanho((t) =>
        t.largura === el.clientWidth && t.altura === el.clientHeight ? t : { largura: el.clientWidth, altura: el.clientHeight },
      );
    medir();
    if (typeof ResizeObserver === 'undefined') {
      window.addEventListener('resize', medir);
      return () => window.removeEventListener('resize', medir);
    }
    const observador = new ResizeObserver(medir);
    observador.observe(el);
    return () => observador.disconnect();
  }, [ref]);
  return tamanho;
}
