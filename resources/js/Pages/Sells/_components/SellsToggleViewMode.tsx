// US-SELL-015 — Toggle "Lista | Grade Avançada" no header de /sells.
// Refs: ADR 0136 (split Lista vs Grade Avançada — viewMode persist localStorage)
//       ADR 0110 (Cockpit Pattern V2 — typography canon, semantic colors)
//
// Segmented control compacto inspirado no shadcn (não temos toggle-group
// instalado em Components/ui — implementação inline simples sem dependência
// nova). Pattern visual: 2 botões agrupados num pill border arredondado,
// botão ativo com bg-background sólido + sombra leve, inativo translúcido.
//
// PT-BR enforce: labels "Lista" e "Grade Avançada" são UX visível.

import { LayoutList, Table2 } from 'lucide-react';

export type SellsViewMode = 'lista' | 'grade-avancada';

interface SellsToggleViewModeProps {
  viewMode: SellsViewMode;
  onChange: (mode: SellsViewMode) => void;
  /** Desabilita o toggle (ex: durante fetch). Default: false. */
  disabled?: boolean;
}

export default function SellsToggleViewMode({
  viewMode,
  onChange,
  disabled = false,
}: SellsToggleViewModeProps) {
  return (
    <div
      role="group"
      aria-label="Modo de visualização da lista de vendas"
      className="inline-flex items-center rounded-lg border border-border bg-muted/40 p-0.5"
    >
      <ToggleButton
        active={viewMode === 'lista'}
        onClick={() => onChange('lista')}
        disabled={disabled}
        icon={<LayoutList size={14} />}
        label="Lista"
        title="Visão enxuta — 5 colunas + drawer (padrão)"
      />
      <ToggleButton
        active={viewMode === 'grade-avancada'}
        onClick={() => onChange('grade-avancada')}
        disabled={disabled}
        icon={<Table2 size={14} />}
        label="Grade Avançada"
        title="Grade densa — 30+ colunas, multiseleção, agrupamento (em construção)"
      />
    </div>
  );
}

function ToggleButton({
  active,
  onClick,
  disabled,
  icon,
  label,
  title,
}: {
  active: boolean;
  onClick: () => void;
  disabled: boolean;
  icon: React.ReactNode;
  label: string;
  title: string;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      disabled={disabled}
      title={title}
      aria-pressed={active}
      className={
        'inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-50 ' +
        (active
          ? 'bg-background text-foreground shadow-sm'
          : 'text-muted-foreground hover:text-foreground')
      }
    >
      {icon}
      {label}
    </button>
  );
}
