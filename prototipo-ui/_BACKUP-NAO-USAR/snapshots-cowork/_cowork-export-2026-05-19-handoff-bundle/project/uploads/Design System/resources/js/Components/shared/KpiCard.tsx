import * as React from 'react';
import { cva, type VariantProps } from 'class-variance-authority';
import { ArrowDownRight, ArrowUpRight, Minus } from 'lucide-react';
import { Icon } from '@/Components/Icon';
import { cn } from '@/Lib/utils';

/**
 * KpiCard — card de indicador semântico, reutilizável em qualquer dashboard.
 *
 * Tom visual por `tone`:
 *   default  → cinza neutro (KPI de contagem)
 *   success  → verde (métricas positivas, presentes)
 *   warning  → âmbar (atencao, atrasos)
 *   danger   → vermelho (faltas, erros)
 *   info     → azul (informativo, banco de horas positivo)
 *
 * Compacto (`compact`) pra grids de 4+ colunas.
 * `delta` mostra variação +/- com seta e cor automática.
 *
 * Uso:
 *   <KpiCard
 *     label="Colaboradores presentes"
 *     value={42}
 *     icon="users"
 *     tone="success"
 *     delta={{ value: 3, label: 'vs ontem' }}
 *   />
 */
const kpiCardVariants = cva(
  'flex flex-col gap-2 rounded-xl border bg-card p-4 shadow-sm transition-colors',
  {
    variants: {
      tone: {
        default: 'border-border',
        success: 'border-emerald-500/20 bg-emerald-500/5',
        warning: 'border-amber-500/20 bg-amber-500/5',
        danger: 'border-destructive/20 bg-destructive/5',
        info: 'border-blue-500/20 bg-blue-500/5',
      },
      size: {
        default: 'p-4 gap-2',
        compact: 'p-3 gap-1',
        large: 'p-6 gap-3',
      },
    },
    defaultVariants: {
      tone: 'default',
      size: 'default',
    },
  },
);

const iconContainerVariants = cva(
  'flex items-center justify-center rounded-lg shrink-0',
  {
    variants: {
      tone: {
        default: 'bg-muted text-muted-foreground',
        success: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
        warning: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
        danger: 'bg-destructive/10 text-destructive',
        info: 'bg-blue-500/10 text-blue-600 dark:text-blue-400',
      },
      size: {
        default: 'h-9 w-9',
        compact: 'h-7 w-7',
        large: 'h-11 w-11',
      },
    },
    defaultVariants: { tone: 'default', size: 'default' },
  },
);

interface Props extends VariantProps<typeof kpiCardVariants> {
  label: string;
  value: string | number;
  icon?: string;
  description?: string;
  delta?: { value: number; label?: string; direction?: 'up' | 'down' | 'neutral' };
  deltaIsGood?: boolean; // se true, up=verde, down=vermelho. Se false, inverte.
  action?: React.ReactNode;
  className?: string;
  /** Se passado, o card vira botão clicável (útil como filtro toggle). */
  onClick?: () => void;
  /** Visual de "selecionado" quando card é clicável e representa filtro ativo. */
  selected?: boolean;
}

export default function KpiCard({
  label,
  value,
  icon,
  description,
  delta,
  deltaIsGood = true,
  action,
  tone,
  size,
  className,
  onClick,
  selected,
}: Props) {
  const iconSize = size === 'compact' ? 14 : size === 'large' ? 22 : 18;
  const valueClass =
    size === 'compact'
      ? 'text-xl font-semibold'
      : size === 'large'
        ? 'text-4xl font-bold'
        : 'text-2xl font-bold';

  const content = (
    <>
      <div className="flex items-center justify-between gap-2">
        <span className="text-xs font-medium text-muted-foreground uppercase tracking-wide truncate">
          {label}
        </span>
        {icon && (
          <div className={cn(iconContainerVariants({ tone, size }))}>
            <Icon name={icon} size={iconSize} />
          </div>
        )}
      </div>
      <div className="flex items-baseline gap-2 min-w-0">
        <span className={cn(valueClass, 'text-foreground tabular-nums truncate')}>{value}</span>
        {delta && <Delta {...delta} isGood={deltaIsGood} />}
      </div>
      {description && (
        <p className="text-xs text-muted-foreground leading-snug">{description}</p>
      )}
      {action && <div className="mt-1">{action}</div>}
    </>
  );

  const classes = cn(
    kpiCardVariants({ tone, size }),
    onClick && 'text-left hover:border-primary/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring cursor-pointer',
    selected && 'border-primary ring-1 ring-primary/40',
    className,
  );

  if (onClick) {
    return (
      <button
        type="button"
        onClick={onClick}
        aria-pressed={selected}
        data-slot="kpi-card"
        data-tone={tone ?? 'default'}
        className={classes}
      >
        {content}
      </button>
    );
  }

  return (
    <div data-slot="kpi-card" data-tone={tone ?? 'default'} className={classes}>
      {content}
    </div>
  );
}

function Delta({
  value,
  label,
  direction,
  isGood,
}: {
  value: number;
  label?: string;
  direction?: 'up' | 'down' | 'neutral';
  isGood: boolean;
}) {
  const dir = direction ?? (value > 0 ? 'up' : value < 0 ? 'down' : 'neutral');
  const Icon_ = dir === 'up' ? ArrowUpRight : dir === 'down' ? ArrowDownRight : Minus;
  const good = (dir === 'up' && isGood) || (dir === 'down' && !isGood);
  const neutral = dir === 'neutral';
  const color = neutral
    ? 'text-muted-foreground'
    : good
      ? 'text-emerald-600 dark:text-emerald-400'
      : 'text-destructive';

  const sign = value > 0 ? '+' : '';
  return (
    <span className={cn('inline-flex items-center gap-0.5 text-xs font-medium tabular-nums', color)}>
      <Icon_ size={12} />
      {sign}
      {value}
      {label && <span className="text-muted-foreground font-normal ml-1">{label}</span>}
    </span>
  );
}
