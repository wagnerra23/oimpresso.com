import { useCallback, useEffect, useState } from 'react';

/**
 * useKbPathProgress — progresso de trilhas por dispositivo (localStorage)
 *
 * Port do `kb-paths.jsx::KBPathsDialog` state interno.
 * Cada path_id mapeia stepIndex → boolean(done).
 *
 * Storage key: `oimpresso.kb.paths.v1`
 *
 * TODO[CL]: V2 com cloud sync — quando user tem permission
 * `kb.path.progress.cloud_sync`, espelhar em kb_path_user_progress (não criado V1).
 */
const STORAGE_KEY = 'oimpresso.kb.paths.v1';

type ProgressMap = Record<number, Record<number, boolean>>;

function loadProgress(): ProgressMap {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return {};
    const parsed: unknown = JSON.parse(raw);
    return parsed && typeof parsed === 'object' && !Array.isArray(parsed)
      ? (parsed as ProgressMap)
      : {};
  } catch {
    return {};
  }
}

export function useKbPathProgress() {
  const [progress, setProgress] = useState<ProgressMap>(loadProgress);

  useEffect(() => {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(progress));
    } catch {
      /* quota */
    }
  }, [progress]);

  const toggleStep = useCallback((pathId: number, stepIdx: number) => {
    setProgress((current) => {
      const cur = current[pathId] ?? {};
      return {
        ...current,
        [pathId]: { ...cur, [stepIdx]: !cur[stepIdx] },
      };
    });
  }, []);

  const isStepDone = useCallback(
    (pathId: number, stepIdx: number): boolean =>
      Boolean(progress[pathId]?.[stepIdx]),
    [progress],
  );

  const getStats = useCallback(
    (pathId: number, totalSteps: number) => {
      const done = progress[pathId] ?? {};
      const c = Object.values(done).filter(Boolean).length;
      return {
        done: c,
        total: totalSteps,
        pct: totalSteps > 0 ? Math.round((c / totalSteps) * 100) : 0,
      };
    },
    [progress],
  );

  return { progress, toggleStep, isStepDone, getStats };
}
