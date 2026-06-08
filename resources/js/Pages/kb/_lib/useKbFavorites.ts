import { useCallback, useEffect, useState } from 'react';

/**
 * useKbFavorites — favoritos por dispositivo (localStorage)
 *
 * Port do `kb-images-print.jsx::useKBFavorites` (Cowork [CC]) pra TS.
 * Quando user tiver permission `kb.favorite` + cloud sync (V2), trocar
 * pra POST /kb/nodes/{slug}/favorite.
 *
 * Storage key: `oimpresso.kb.favs.v1`
 */
const STORAGE_KEY = 'oimpresso.kb.favs.v1';

function loadFavs(): number[] {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return [];
    const parsed: unknown = JSON.parse(raw);
    return Array.isArray(parsed)
      ? parsed.filter((n): n is number => typeof n === 'number')
      : [];
  } catch {
    return [];
  }
}

export function useKbFavorites() {
  const [favs, setFavs] = useState<number[]>(loadFavs);

  useEffect(() => {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(favs));
    } catch {
      /* quota / private mode */
    }
  }, [favs]);

  const isFav = useCallback((id: number): boolean => favs.includes(id), [favs]);

  const toggleFav = useCallback((id: number) => {
    setFavs((current) =>
      current.includes(id)
        ? current.filter((x) => x !== id)
        : [id, ...current],
    );
  }, []);

  return { favs, isFav, toggleFav };
}
