// VdSource — pill colorida na coluna "Origem" de Sells/Index (Onda 3 KB-9.75 A1).
// Refs:
//  - prototipo-ui/vendas-page.jsx::VdSource (function 145-160 canon Cowork 2026-05-25)
//  - prototipo-ui/vendas.css L1651-1700 (tokens --vd-src-{balcao,oficina,online})
//  - memory/decisions/0192-auto-faturar-os-venda-jobsheet-observer.md (ADR)
//  - app/Http/Controllers/SellController.php::inertiaList (payload source/source_label/os_ref)
//
// Backend devolve `source`/`source_label`/`os_ref` no payload `/sells-list-json` desde
// Onda 2 (commit e98649989). source default 'balcao' retroativo (migration default)
// pra zero breaking change com vendas legacy.

import type { MouseEvent, ReactNode } from 'react';

export type VdSourceKind = 'balcao' | 'oficina' | 'online';

interface VdSourceProps {
  source: VdSourceKind | string;
  sourceLabel: string;
  osRef: string | null;
  /**
   * Callback opcional quando user clica em `↗ #OS-NNNN`. Onda 5 wirea isso pro
   * Repair drawer (cross-módulo) ou Onda 4+ pra abrir o card pulando saved view
   * "Por origem · Oficina". Default: navega pra `/repair/producao-oficina#osRef`.
   */
  onPickOs?: (osRef: string) => void;
}

export default function VdSource({ source, sourceLabel, osRef, onPickOs }: VdSourceProps): ReactNode {
  // Backend já entrega label canônico ('Balcão'/'Oficina'/'Online'). Garantia
  // de fallback caso source seja string desconhecida (defesa contra dados sujos).
  const kind = (['balcao', 'oficina', 'online'] as const).includes(source as VdSourceKind)
    ? (source as VdSourceKind)
    : 'balcao';
  const label = sourceLabel || (kind === 'oficina' ? 'Oficina' : kind === 'online' ? 'Online' : 'Balcão');
  const isOficina = kind === 'oficina';

  const handleOsClick = (e: MouseEvent<HTMLAnchorElement>) => {
    e.preventDefault();
    e.stopPropagation();
    if (osRef && onPickOs) onPickOs(osRef);
  };

  return (
    <span
      className={`vd-src vd-src-${kind}`}
      title={isOficina && osRef ? `Vinda do módulo Oficina · #${osRef}` : `Venda ${label}`}
    >
      <span className="vd-src-dot" />
      <span className="vd-src-lbl">{label}</span>
      {isOficina && osRef && (
        <a className="vd-src-os" href="#" onClick={handleOsClick}>
          ↗ #{osRef}
        </a>
      )}
    </span>
  );
}
