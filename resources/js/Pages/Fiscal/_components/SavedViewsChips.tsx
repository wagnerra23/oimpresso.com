// SavedViewsChips.tsx — Saved views como chips horizontais (Onda 3 C).
//
// Substitui o <select> de saved views por chips estilo Linear Inbox.
// Cada chip mostra label + count + tone (bad/warn/ok) por urgência.

import type { ReactNode } from 'react';

export interface SavedViewChip {
  id: string;
  label: string;
  count: number;
  tone?: 'ok' | 'warn' | 'bad' | null;
  icon?: ReactNode;
}

interface SavedViewsChipsProps {
  views: SavedViewChip[];
  value: string;
  onChange: (id: string) => void;
  /** Custom view ativa (após filtro manual). Mostra chip extra. */
  customCount?: number;
  isCustom?: boolean;
}

export default function SavedViewsChips({
  views,
  value,
  onChange,
  customCount,
  isCustom,
}: SavedViewsChipsProps) {
  return (
    <div className="fx-saved-views" role="tablist" aria-label="Filtros salvos">
      {views.map((v) => {
        const active = !isCustom && value === v.id;
        const toneCls = v.tone ? ` t-${v.tone}` : '';
        return (
          <button
            key={v.id}
            type="button"
            role="tab"
            aria-selected={active}
            className={`fx-saved-view${active ? ' active' : ''}${toneCls}`}
            onClick={() => onChange(v.id)}
            title={`Aplicar filtro "${v.label}"`}
          >
            {v.icon}
            <span>{v.label}</span>
            <span className="n">{v.count}</span>
          </button>
        );
      })}
      {isCustom && typeof customCount === 'number' && (
        <span
          className="fx-saved-view active"
          aria-selected={true}
          title="Filtro custom (combinação manual de filtros)"
        >
          <span>Custom</span>
          <span className="n">{customCount}</span>
        </span>
      )}
    </div>
  );
}
