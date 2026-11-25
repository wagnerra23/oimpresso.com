import * as React from 'react';
import { X } from 'lucide-react';
import { Button } from '@/Components/ui/button';
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
  /** Nome do filtro ("Tipo"). Sem `value`, é o texto inteiro do chip ("Abaixo do alerta"). */
  label: string;
  /** Valor ativo ("Variável"), separado do rótulo como no `FilterChip` do DS. Opcional. */
  value?: React.ReactNode;
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
            <FilterChip key={i} label={chip.label} value={chip.value} onRemove={chip.onRemove} />
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

/**
 * Chip de filtro ativo — o `FilterChip` do DS (`prototipo-ui/design-system/components/FilterChip`):
 * pílula de 24px, fonte 11,5px, fundo `color-mix(accent 12%, surface)`, borda `accent 32%`, texto na
 * cor de destaque; rótulo em negrito (600) e o valor ao lado em peso normal; ✕ de 18px.
 *
 * Até 2026-11-25 era um `Badge` cinza: medido em produção em 2026-11-24 (`/products?type=variable`,
 * escuro), fundo quase da cor da faixa, texto branco 12px, sem borda, 22px de altura, "Tipo: Variável"
 * numa linha só — o filtro aplicado quase não aparecia.
 *
 * As cores vão em `style`, como no DS: os bundles `cowork-*-bundle.css` ficam fora de `@layer` e
 * venceriam um utilitário (§5 2026-10-14). Os tokens do DS só existem dentro do `.cockpit`; fora dele
 * cada um cai no token do tema do app.
 */
const ACCENT = 'var(--accent, var(--color-primary))';
const SURFACE = 'var(--surface, var(--color-card))';
const TEXT = 'var(--text, var(--color-foreground))';

function FilterChip({ label, value, onRemove }: Chip) {
  const temValor = value !== undefined && value !== null && value !== '';
  const nomeAcessivel = temValor && typeof value === 'string' ? `${label}: ${value}` : label;
  return (
    <span
      data-slot="filter-chip"
      className="inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap"
      style={{
        height: 24,
        padding: '0 4px 0 10px',
        borderRadius: 99,
        fontSize: 11.5,
        lineHeight: 1,
        background: `color-mix(in oklch, ${ACCENT} 12%, ${SURFACE})`,
        color: ACCENT,
        border: `1px solid color-mix(in oklch, ${ACCENT} 32%, transparent)`,
      }}
    >
      <span style={{ fontWeight: 600 }}>{label}</span>
      {temValor && (
        <span style={{ fontWeight: 400, color: `color-mix(in oklch, ${ACCENT} 80%, ${TEXT})` }}>{value}</span>
      )}
      <button
        type="button"
        onClick={onRemove}
        aria-label={`Remover filtro: ${nomeAcessivel}`}
        className="ml-px grid h-[18px] w-[18px] cursor-pointer place-items-center rounded-full opacity-70 transition-[background-color,opacity] hover:bg-[color-mix(in_oklch,var(--accent,var(--color-primary))_18%,transparent)] hover:opacity-100 focus-visible:opacity-100 focus-visible:outline-2 focus-visible:outline-offset-1"
        style={{ color: 'currentColor' }}
      >
        <X size={11} aria-hidden />
      </button>
    </span>
  );
}

export { FilterChip };
