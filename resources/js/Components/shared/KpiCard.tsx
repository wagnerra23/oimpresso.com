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
        success: 'border-success/20 bg-success/5',
        warning: 'border-warning/20 bg-warning/5',
        danger: 'border-destructive/20 bg-destructive/5',
        info: 'border-info/20 bg-info/5',
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
        success: 'bg-success/10 text-success',
        warning: 'bg-warning/10 text-warning',
        danger: 'bg-destructive/10 text-destructive',
        info: 'bg-info/10 text-info',
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
  // ADR 0110 §Tipografia canon: KPI value = font-semibold (NÃO font-bold).
  // size=large 36px (text-4xl), size=default 24px (text-2xl), size=compact 20px (text-xl).
  const valueClass =
    size === 'compact'
      ? 'text-xl font-semibold'
      : size === 'large'
        ? 'text-4xl font-semibold'
        : 'text-2xl font-semibold';

  const content = (
    <>
      <div className="flex items-center justify-between gap-2">
        {/* ADR 0110 §Tipografia canon: KPI label = text-[11px] font-semibold uppercase tracking-widest */}
        <span className="text-[11px] font-semibold text-muted-foreground uppercase tracking-widest truncate">
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
      ? 'text-success'
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
