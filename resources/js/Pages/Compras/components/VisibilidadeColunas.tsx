// VisibilidadeColunas.tsx — dropdown checkbox pra mostrar/esconder colunas.
// State persiste em localStorage por chave (`compras-cols-v1`).

import { useEffect, useRef, useState } from 'react';

export interface ColumnDef {
  id: string;
  label: string;
  /** Coluna obrigatória (não pode esconder) */
  required?: boolean;
}

interface VisibilidadeColunasProps {
  storageKey?: string;
  columns: ColumnDef[];
  value: Record<string, boolean>;
  onChange: (next: Record<string, boolean>) => void;
}

export default function VisibilidadeColunas({
  columns,
  value,
  onChange,
}: VisibilidadeColunasProps) {
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) return;
    const onClick = (e: MouseEvent) => {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
    };
    const onEsc = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setOpen(false);
    };
    document.addEventListener('mousedown', onClick);
    document.addEventListener('keydown', onEsc);
    return () => {
      document.removeEventListener('mousedown', onClick);
      document.removeEventListener('keydown', onEsc);
    };
  }, [open]);

  const toggle = (id: string) => {
    onChange({ ...value, [id]: !value[id] });
  };

  return (
    <div className="relative inline-block" ref={ref}>
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        className="btn"
        title="Mostrar/esconder colunas"
      >
        ⊟ Visibilidade da coluna
      </button>

      {open && (
        <div
          role="menu"
          className="absolute right-0 z-50 mt-1 w-56 rounded-md border border-stone-200 bg-white py-1 text-sm shadow-lg"
        >
          {columns.map((col) => (
            <label
              key={col.id}
              className={`flex items-center gap-2 px-3 py-1.5 hover:bg-stone-50 ${
                col.required ? 'cursor-not-allowed opacity-60' : 'cursor-pointer'
              }`}
            >
              <input
                type="checkbox"
                checked={col.required ? true : !!value[col.id]}
                disabled={col.required}
                onChange={() => !col.required && toggle(col.id)}
                className="rounded border-stone-300 text-primary-600 focus:ring-primary-500"
              />
              <span className="flex-1 truncate text-stone-700">{col.label}</span>
            </label>
          ))}
        </div>
      )}
    </div>
  );
}

/**
 * Helper React hook — sincroniza state com localStorage.
 */
export function useColumnVisibility(
  storageKey: string,
  defaults: Record<string, boolean>
): [Record<string, boolean>, (next: Record<string, boolean>) => void] {
  const [value, setValue] = useState<Record<string, boolean>>(() => {
    if (typeof window === 'undefined') return defaults;
    try {
      const raw = localStorage.getItem(storageKey);
      if (!raw) return defaults;
      const parsed = JSON.parse(raw) as Record<string, boolean>;
      return { ...defaults, ...parsed };
    } catch {
      return defaults;
    }
  });

  const setAndPersist = (next: Record<string, boolean>) => {
    setValue(next);
    try {
      localStorage.setItem(storageKey, JSON.stringify(next));
    } catch {
      // ignore quota errors
    }
  };

  return [value, setAndPersist];
}
