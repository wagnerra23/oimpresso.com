import * as React from 'react';
import { cn } from '@/Lib/utils';
import { ShieldAlert, ShieldCheck } from 'lucide-react';
import type { DimensionScore } from './governanceV4Types';

/**
 * DimensionProgressBar — uma barra D1-D9 com label + paired indicator
 *
 * Wave 29 (W29-C). Mostra:
 *  - label dimensão (D1-D9) + nota /100 + peso
 *  - barra horizontal com cor semântica
 *  - badge paired indicator (anti-Goodhart Jellyfish) quando aplicável
 *
 * Tokens canon Cockpit V2 (semantic colors, sem hardcode `bg-red-500`).
 */
interface Props {
  dimension: DimensionScore;
  /** target da dimensão (default 80) pra colorir status */
  target?: number;
  className?: string;
}

export default function DimensionProgressBar({
  dimension,
  target = 80,
  className,
}: Props) {
  const pct = Math.max(0, Math.min(100, dimension.score));
  const tone: 'ok' | 'warn' | 'crit' =
    pct >= target ? 'ok' : pct >= target - 10 ? 'warn' : 'crit';

  const barClass =
    tone === 'ok'
      ? 'bg-success/70'
      : tone === 'warn'
        ? 'bg-warning/70'
        : 'bg-destructive/70';

  const labelTone =
    tone === 'ok'
      ? 'text-success-fg'
      : tone === 'warn'
        ? 'text-warning-fg'
        : 'text-destructive';

  return (
    <div className={cn('space-y-1.5', className)}>
      <div className="flex items-center justify-between gap-2">
        <div className="flex items-center gap-2 min-w-0">
          <span className="text-[10px] font-bold uppercase tracking-wider text-muted-foreground shrink-0">
            {dimension.id}
          </span>
          <span className="text-[12px] text-foreground truncate" title={dimension.label}>
            {dimension.label}
          </span>
          {dimension.paired_indicator && (
            <span
              title={
                dimension.paired_ok
                  ? 'Indicador pareado OK (anti-Goodhart)'
                  : 'Indicador pareado em violação — score pode estar inflado'
              }
              className={cn(
                'inline-flex items-center gap-0.5 rounded-md border px-1 py-0.5 text-[9px] font-medium',
                dimension.paired_ok
                  ? 'border-success/20 bg-success-soft text-success-fg'
                  : 'border-destructive/40 bg-destructive/10 text-destructive',
              )}
            >
              {dimension.paired_ok ? (
                <ShieldCheck size={9} />
              ) : (
                <ShieldAlert size={9} />
              )}
              paired
            </span>
          )}
        </div>
        <div className="flex items-center gap-1.5 shrink-0">
          <span className={cn('text-[12px] font-semibold tabular-nums', labelTone)}>
            {pct}
          </span>
          <span className="text-[10px] text-muted-foreground">/{target}</span>
          {dimension.weight > 0 && (
            <span
              className="text-[9px] text-muted-foreground"
              title={`Peso: ${dimension.weight}`}
            >
              ·×{dimension.weight}
            </span>
          )}
        </div>
      </div>
      <div className="h-1.5 w-full rounded-full bg-muted overflow-hidden">
        <div
          className={cn('h-full rounded-full transition-[width]', barClass)}
          style={{ width: `${pct}%` }}
          aria-valuenow={pct}
          aria-valuemin={0}
          aria-valuemax={100}
          role="progressbar"
        />
      </div>
    </div>
  );
}
