// useInboxFavs — favoritos da Caixa Unificada (Polish V2 §6 · inbox-extras.jsx).
//
// localStorage per-user per-browser (SEM DB — anti-hook do charter: filtros/
// preferências não persistem em DB, sem leakage cross-tenant). Conversas
// favoritas ordenam no topo da lista.

import { useCallback, useState } from 'react';
import { CU_LS, cuLsGet, cuLsSet } from './helpers';

export function useInboxFavs(): {
  favs: Set<number>;
  isFav: (id: number) => boolean;
  toggleFav: (id: number) => void;
} {
  const [favs, setFavs] = useState<Set<number>>(() => {
    try {
      const raw = cuLsGet(CU_LS.FAVORITES, '[]');
      return new Set((JSON.parse(raw) as number[]).filter(n => typeof n === 'number'));
    } catch {
      return new Set();
    }
  });

  const toggleFav = useCallback((id: number) => {
    setFavs(prev => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      cuLsSet(CU_LS.FAVORITES, JSON.stringify([...next]));
      return next;
    });
  }, []);

  const isFav = useCallback((id: number) => favs.has(id), [favs]);

  return { favs, isFav, toggleFav };
}
