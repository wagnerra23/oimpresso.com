// UC-PCOSF-08/09 · abre e fecha o painel lateral da Ficha da OS (`_components/PainelDaFicha.tsx`) guardando a OS na URL.
import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { osDoPainel, urlDoPainel } from './ficha';

// A OS aberta vem da URL da página; entre o clique e a resposta, do próprio clique (o painel abre na hora).
// Abrir e fechar visitam a mesma tela com `only: ['ficha']`: o filtro e a rolagem ficam, e o voltar do navegador fecha.
export function usePainelDaFicha(caminho: string) {
  const { url } = usePage();
  const [abrindo, setAbrindo] = useState<{ os: number | null } | null>(null);
  const irPara = (os: number | null) => {
    setAbrindo({ os });
    router.visit(urlDoPainel(window.location.search, os, caminho), {
      only: ['ficha'], preserveState: true, preserveScroll: true,
      onFinish: () => setAbrindo((cur) => (cur?.os === os ? null : cur)),
    });
  };
  return {
    osAberta: abrindo ? abrindo.os : osDoPainel(new URL(url, window.location.origin).search),
    carregando: abrindo !== null && abrindo.os !== null,
    abrir: (os: number) => irPara(os),
    fechar: () => irPara(null),
  };
}
