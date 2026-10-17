// Larguras que a pessoa arrastou na lista de produtos, lembradas no navegador — UC-PIDX-16.
//
// Antes (até 2026-10-17) a largura valia só enquanto a página estava aberta: voltar da edição de um
// produto (a edição é Blade, recarrega a página) ou apertar F5 voltava às larguras do protótipo, e a
// ROTA LIVRE refazia o ajuste a cada visita. Agora fica guardada como a escolha de colunas e a
// densidade. Sem largura ajustada, a chave sai do navegador ("Voltar às larguras de saída").

import { useEffect, useState, type Dispatch, type SetStateAction } from 'react';
import { CHAVE_LARGURAS, largurasValidas } from './tipos';

function lerGuardadas(): Record<string, number> {
  try {
    const v = localStorage.getItem(CHAVE_LARGURAS);
    return v === null ? {} : largurasValidas(JSON.parse(v));
  } catch {
    return {};
  }
}

export function useLargurasGuardadas(): [Record<string, number>, Dispatch<SetStateAction<Record<string, number>>>] {
  const [larguras, setLarguras] = useState<Record<string, number>>(lerGuardadas);
  useEffect(() => {
    try {
      if (Object.keys(larguras).length === 0) localStorage.removeItem(CHAVE_LARGURAS);
      else localStorage.setItem(CHAVE_LARGURAS, JSON.stringify(larguras));
    } catch {
      // navegador sem storage: a largura vale só nesta visita
    }
  }, [larguras]);
  return [larguras, setLarguras];
}
