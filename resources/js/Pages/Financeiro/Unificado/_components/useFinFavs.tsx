// useFinFavs — Cowork KB-9.75 Financeiro Onda 7c
// (favoritos pessoais por business_id + atalho B).
//
// Refs:
//  - prototipo-ui/financeiro-output.jsx FinFavStar + bookmarkAtalho
//
// Hook localStorage-backed. Cada usuário/business tem seu próprio set de
// lançamentos favoritados — útil pra Eliana marcar "olha mais tarde" sem
// poluir DB (não muda business state, é só preferência pessoal de leitura).
//
// Multi-tenant safe: prefixo `oimpresso.fin.favs.<business_id>` evita
// vazamento entre tenants quando user logout/login em conta diferente.
//
// Atalho `B` (de "bookmark") quando selectedId não-null → toggle.

import { useState, useEffect, useCallback, useMemo } from 'react';

const STORAGE_PREFIX = 'oimpresso.fin.favs.';
const STORAGE_VERSION = 'v1';

interface UseFinFavsApi {
  favs: Set<number>;
  toggle: (id: number) => void;
  has: (id: number) => boolean;
  clear: () => void;
  count: number;
}

/**
 * Hook de favoritos pessoais. businessKey deve ser estável durante a sessão
 * (ex.: business_id ou nome canon) — quando muda, lê de outro slot.
 */
export function useFinFavs(businessKey: string | number = 'default'): UseFinFavsApi {
  const storageKey = `${STORAGE_PREFIX}${businessKey}.${STORAGE_VERSION}`;

  const [favs, setFavs] = useState<Set<number>>(() => {
    if (typeof window === 'undefined') return new Set();
    try {
      const raw = window.localStorage.getItem(storageKey);
      if (!raw) return new Set();
      const arr: number[] = JSON.parse(raw);
      return new Set(arr.filter((n) => Number.isFinite(n)));
    } catch {
      return new Set();
    }
  });

  // Persiste sempre que muda
  useEffect(() => {
    if (typeof window === 'undefined') return;
    try {
      window.localStorage.setItem(storageKey, JSON.stringify(Array.from(favs)));
    } catch {
      // ignora QuotaExceededError silencioso — não é crítico
    }
  }, [favs, storageKey]);

  const toggle = useCallback((id: number) => {
    setFavs((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  }, []);

  const has = useCallback((id: number) => favs.has(id), [favs]);

  const clear = useCallback(() => setFavs(new Set()), []);

  return useMemo(() => ({ favs, toggle, has, clear, count: favs.size }), [favs, toggle, has, clear]);
}

/**
 * Pin estrela inline (use na coluna 1 ou inline no nome do lançamento).
 * Não-clicável por design — toggle acontece via atalho `B` ou drawer.
 */
export function FinFavPin({ active }: { active: boolean }) {
  if (!active) return null;
  return (
    <span
      className="fin-fav-pin"
      title="Favoritado (pressione B com a linha selecionada pra remover)"
      aria-label="Favoritado"
    >
      ★
    </span>
  );
}
