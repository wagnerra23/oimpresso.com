import { useCallback, useEffect, useState } from 'react';

/**
 * useKbRecent — últimos 8 nós abertos por dispositivo (localStorage)
 *
 * Port do `kb-page.jsx::recent` state (Cowork [CC]).
 * Storage key: `oimpresso.kb.recent.v1`
 */
const STORAGE_KEY = 'oimpresso.kb.recent.v1';
const MAX_RECENT = 8;

function loadRecent(): number[] {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return [];
    const parsed: unknown = JSON.parse(raw);
    return Array.isArray(parsed)
      ? parsed.filter((n): n is number => typeof n === 'number').slice(0, MAX_RECENT)
      : [];
  } catch {
    return [];
  }
}

export function useKbRecent() {
  const [recent, setRecent] = useState<number[]>(loadRecent);

  useEffect(() => {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(recent));
    } catch {
      /* quota */
    }
  }, [recent]);

  const pushRecent = useCallback((id: number) => {
    setRecent((current) => [id, ...current.filter((x) => x !== id)].slice(0, MAX_RECENT));
  }, []);

  return { recent, pushRecent };
}
