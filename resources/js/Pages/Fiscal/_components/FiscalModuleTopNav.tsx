// FiscalModuleTopNav.tsx — Sub-tabs internas do Fiscal (nome distinto do shared/ModuleTopNav — implementação própria) (Pendentes/Histórico em DF-e,
// Cert/Séries/Ambiente/SPED em Config). Onda 2 G+H.
//
// Port do fiscal-page.jsx §ModuleTopNav. Usa CSS .fx-subtabs já existente
// (canon shared, não cria novo token).

import type { ReactNode } from 'react';

export interface FiscalModuleTopNavItem {
  id: string;
  label: string;
  icon?: ReactNode;
  count?: number;
  tone?: 'ok' | 'warn' | 'bad' | null;
}

interface FiscalModuleTopNavProps {
  items: FiscalModuleTopNavItem[];
  value: string;
  onChange: (id: string) => void;
}

export default function FiscalModuleTopNav({ items, value, onChange }: FiscalModuleTopNavProps) {
  return (
    <nav className="fx-subtabs" role="tablist" aria-label="Sub-páginas">
      {items.map((it) => (
        <button
          key={it.id}
          type="button"
          role="tab"
          aria-selected={value === it.id}
          className={`fx-subtab${value === it.id ? ' active' : ''}`}
          onClick={() => onChange(it.id)}
        >
          {it.icon}
          <span>{it.label}</span>
          {typeof it.count === 'number' && it.count > 0 && (
            <span className={`n${it.tone ? ` t-${it.tone}` : ''}`}>{it.count}</span>
          )}
        </button>
      ))}
    </nav>
  );
}
