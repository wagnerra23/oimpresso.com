import * as React from 'react';
import { X } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { cn } from '@/Lib/utils';

/**
 * PageFilters — container colapsável de filtros + FilterChip pra chips de filtros ativos.
 *
 * Layout:
 *   [chips de filtros aplicados — com botão X pra remover]
 *   [campos de filtro em grid]
 *   [botões: Limpar filtros · N resultados]
 *
 * Uso:
 *   <PageFilters
 *     activeChips={[
 *       { label: 'Mês: Abril/2026', onRemove: () => setMes(null) },
 *       { label: 'Estado: Pendente', onRemove: () => setEstado('') },
 *     ]}
 *     onReset={() => resetAll()}
 *   >
 *     <MonthPicker ... />
 *     <Select ... />
 *   </PageFilters>
 */
interface Chip {
  label: string;
  onRemove: () => void;
}

interface Props {
  children: React.ReactNode;
  activeChips?: Chip[];
  onReset?: () => void;
  className?: string;
  /** Número de colunas no grid de filtros (desktop). Default: 4. */
  cols?: 2 | 3 | 4;
}

const colsMap: Record<NonNullable<Props['cols']>, string> = {
  2: 'sm:grid-cols-2',
  3: 'sm:grid-cols-2 lg:grid-cols-3',
  4: 'sm:grid-cols-2 lg:grid-cols-4',
};

export default function PageFilters({
  children,
  activeChips = [],
  onReset,
  className,
  cols = 4,
}: Props) {
  const hasActive = activeChips.length > 0;

  return (
    <div
      data-slot="page-filters"
      className={cn('flex flex-col gap-3 rounded-lg border border-border bg-card p-4', className)}
    >
      {hasActive && (
        <div className="flex flex-wrap items-center gap-2">
          <span className="text-xs font-medium text-muted-foreground uppercase tracking-wide mr-1">
            Filtros ativos:
          </span>
          {activeChips.map((chip, i) => (
            <FilterChip key={i} label={chip.label} onRemove={chip.onRemove} />
          ))}
          {onReset && (
            <Button
              type="button"
              variant="ghost"
              size="sm"
              onClick={onReset}
              className="h-6 text-xs text-muted-foreground hover:text-foreground"
            >
              Limpar tudo
            </Button>
          )}
        </div>
      )}
      <div className={cn('grid grid-cols-1 gap-3', colsMap[cols])}>{children}</div>
    </div>
  );
}

function FilterChip({ label, onRemove }: Chip) {
  return (
    <Badge variant="secondary" className="gap-1 pr-1 font-normal">
      <span>{label}</span>
      <button
        type="button"
        onClick={onRemove}
        aria-label={`Remover filtro: ${label}`}
        className="rounded-full hover:bg-background/50 p-0.5 transition-colors"
      >
        <X size={10} />
      </button>
    </Badge>
  );
}

export { FilterChip };
