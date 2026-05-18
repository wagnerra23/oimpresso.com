// FinConferidoToggle — Cowork KB-9.75 Financeiro Onda 5 R1 Curadoria
// (toggle "Conferido" persistido em localStorage — Eliana valida cada linha).
//
// Refs:
//  - prototipo-ui/financeiro-curation.jsx — useFinConferido + FinConferidoToggle
//  - SaleItemComments pattern (canon localStorage Sells)
//
// Storage: localStorage[oimpresso.financeiro.conferido] = ["R-2641", "P-3120", ...]
// Multi-user: scope por device/user (mesma persona Eliana). Onda futura plugará em
// backend `fin_review_log` table com sync MCP.

import { useCallback, useEffect, useState } from 'react';
import { CircleCheck } from 'lucide-react';

const LS_KEY = 'oimpresso.financeiro.conferido';

function loadConferidoSet(): Set<string> {
  if (typeof window === 'undefined') return new Set();
  try {
    const raw = window.localStorage.getItem(LS_KEY);
    if (!raw) return new Set();
    const arr = JSON.parse(raw);
    return new Set(Array.isArray(arr) ? arr : []);
  } catch (_) {
    return new Set();
  }
}

function saveConferidoSet(s: Set<string>): void {
  if (typeof window === 'undefined') return;
  try {
    window.localStorage.setItem(LS_KEY, JSON.stringify([...s]));
  } catch (_) {
    /* ls indisponível */
  }
}

export interface UseFinConferidoApi {
  has: (id: string | number) => boolean;
  toggle: (id: string | number) => void;
  count: number;
}

export function useFinConferido(): UseFinConferidoApi {
  const [set, setSet] = useState<Set<string>>(loadConferidoSet);

  useEffect(() => {
    saveConferidoSet(set);
  }, [set]);

  const has = useCallback((id: string | number) => set.has(String(id)), [set]);

  const toggle = useCallback((id: string | number) => {
    setSet((prev) => {
      const next = new Set(prev);
      const key = String(id);
      if (next.has(key)) next.delete(key);
      else next.add(key);
      return next;
    });
  }, []);

  return { has, toggle, count: set.size };
}

interface FinConferidoToggleProps {
  rowId: string | number;
  conferido: UseFinConferidoApi;
}

export function FinConferidoToggle({ rowId, conferido }: FinConferidoToggleProps) {
  const isOn = conferido.has(rowId);
  return (
    <button
      type="button"
      className={`fin-conferido-toggle ${isOn ? 'on' : ''}`}
      onClick={() => conferido.toggle(rowId)}
      title={isOn ? 'Desmarcar conferido (Eliana já bateu olho)' : 'Marcar como conferido'}
      aria-pressed={isOn}
    >
      <span className="fin-conf-check">
        {isOn ? <CircleCheck size={14} strokeWidth={2.5} /> : null}
      </span>
      <span className="fin-conf-lbl">{isOn ? 'Conferido' : 'Conferir'}</span>
      {!isOn && <small>Eliana valida</small>}
    </button>
  );
}

interface FinConferidoBadgeProps {
  rowId: string | number;
  conferido: UseFinConferidoApi;
}

/** Badge silencioso pra header da linha (mostra ✓ só quando conferido). */
export function FinConferidoBadge({ rowId, conferido }: FinConferidoBadgeProps) {
  if (!conferido.has(rowId)) return null;
  return (
    <span className="fin-conferido-badge" title="Conferido por Eliana">
      <CircleCheck size={12} strokeWidth={2.5} />
    </span>
  );
}

export default FinConferidoToggle;
