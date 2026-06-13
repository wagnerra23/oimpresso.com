import * as React from 'react';
import { AlertTriangle, TrendingDown, TrendingUp, ChevronRight } from 'lucide-react';
import { cn } from '@/Lib/utils';
import type { DriftAlert } from './governanceV4Types';

/**
 * DriftAlertBanner — banner persistente topo se drifts > 0
 *
 * Wave 29 (W29-C). Não dismissable (charter Wagner) — só some quando
 * `drifts.length === 0`. Mostra contador + top-3 drifts (maior delta absoluto)
 * com link "ver todos" que dispara handler externo (abre dialog ou navega
 * pra tab "Drifts" no ModuleReader).
 *
 * Tokens canon Cockpit V2: `bg-destructive/10` + `border-destructive/40` +
 * `text-destructive` — nunca `bg-red-500` cru.
 */
interface Props {
  drifts: DriftAlert[];
  thresholdPts: number;
  onPickModule?: (slug: string) => void;
  onViewAll?: () => void;
  className?: string;
}

export default function DriftAlertBanner({
  drifts,
  thresholdPts,
  onPickModule,
  onViewAll,
  className,
}: Props) {
  if (!drifts || drifts.length === 0) {
    // banner verde anti-pânico (charter Wave 27 + W29-C)
    return (
      <div
        role="status"
        className={cn(
          'flex items-center justify-between gap-3 rounded-md border border-success/20 bg-success-soft px-3 py-2 text-[12px] text-success-fg',
          className,
        )}
      >
        <span className="flex items-center gap-2">
          <span className="inline-flex h-1.5 w-1.5 rounded-full bg-success" />
          Sem drifts &gt;{thresholdPts}pts nos últimos 7 dias — saúde estável.
        </span>
      </div>
    );
  }

  const sorted = [...drifts].sort((a, b) => Math.abs(b.delta) - Math.abs(a.delta));
  const top = sorted.slice(0, 3);
  const remaining = sorted.length - top.length;

  return (
    <div
      role="alert"
      className={cn(
        'flex flex-col gap-2 rounded-md border border-destructive/40 bg-destructive/10 p-3 text-destructive sm:flex-row sm:items-start sm:gap-3',
        className,
      )}
    >
      <AlertTriangle size={16} className="mt-0.5 shrink-0" />
      <div className="min-w-0 flex-1">
        <div className="text-[12.5px] font-semibold">
          {drifts.length} drift{drifts.length === 1 ? '' : 's'} detectado{drifts.length === 1 ? '' : 's'}
          {' '}
          <span className="text-foreground/70 font-normal">
            (delta &gt; ±{thresholdPts}pts em 7d)
          </span>
        </div>
        <ul className="mt-1.5 flex flex-wrap gap-1.5">
          {top.map((d) => {
            const Dir = d.direction === 'down' ? TrendingDown : TrendingUp;
            return (
              <li key={`${d.module}-${d.snapshot_date}`}>
                <button
                  type="button"
                  onClick={() => onPickModule?.(d.module)}
                  className="inline-flex items-center gap-1 rounded-md border border-destructive/30 bg-card px-1.5 py-0.5 text-[11px] font-medium text-foreground hover:bg-accent focus:outline-none focus:ring-2 focus:ring-destructive/40"
                >
                  <Dir size={10} className="text-destructive" />
                  <span className="font-semibold">{d.module}</span>
                  <span className="text-muted-foreground tabular-nums">
                    {d.from}→{d.to}
                  </span>
                  <span className="font-bold tabular-nums text-destructive">
                    {d.delta > 0 ? '+' : ''}
                    {d.delta}
                  </span>
                </button>
              </li>
            );
          })}
          {remaining > 0 && (
            <li>
              <button
                type="button"
                onClick={onViewAll}
                className="inline-flex items-center gap-1 rounded-md border border-transparent bg-transparent px-1.5 py-0.5 text-[11px] font-medium text-destructive hover:underline focus:outline-none focus:ring-2 focus:ring-destructive/40"
              >
                +{remaining} mais
                <ChevronRight size={11} />
              </button>
            </li>
          )}
        </ul>
      </div>
    </div>
  );
}
