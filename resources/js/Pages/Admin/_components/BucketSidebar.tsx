import * as React from 'react';
import { ChevronRight, Layers, History, GitBranch } from 'lucide-react';
import { cn } from '@/Lib/utils';
import {
  BUCKET_ORDER,
  BUCKET_LABEL_FALLBACK,
  type BucketDef,
  type BucketKey,
  type WaveHistoryEntry,
} from './governanceV4Types';

/**
 * BucketSidebar — coluna 1 do tri-pane (port de kb/CategorySidebar)
 *
 * Wave 29 (W29-C). 4 buckets canônicos (ADR 0160) + accordion Wave history
 * (timeline cronológica W11-W28+). Sidebar fixa esquerda, scrollável quando
 * waveHistory cresce.
 *
 * Divergência semântica vs blueprint:
 *  - kb_v2: categorias + subcategorias (n níveis, hue dinâmico)
 *  - W29-C: 4 buckets canônicos (fixed) + 1 accordion Wave history
 */
interface Props {
  buckets: BucketDef[];
  selectedBucket: BucketKey | 'all';
  onSelect: (bucket: BucketKey | 'all') => void;
  waveHistory: WaveHistoryEntry[];
  waveExpanded: boolean;
  onToggleWaveHistory: () => void;
  className?: string;
}

export default function BucketSidebar({
  buckets,
  selectedBucket,
  onSelect,
  waveHistory,
  waveExpanded,
  onToggleWaveHistory,
  className,
}: Props) {
  const totalCount = buckets.reduce((acc, b) => acc + b.count, 0);

  // Garante ordem canon (ADR 0160) mesmo se backend reordenar
  const ordered: BucketDef[] = BUCKET_ORDER.map(
    (k) =>
      buckets.find((b) => b.key === k) ?? {
        key: k,
        label: BUCKET_LABEL_FALLBACK[k],
        meta: 80,
        count: 0,
      },
  );

  return (
    <aside
      className={cn(
        'kb-side flex flex-col overflow-y-auto border-r border-border bg-card p-3 gap-4',
        className,
      )}
      aria-label="Buckets canon + Wave history"
    >
      {/* Section: buckets */}
      <section>
        <div className="mb-2 flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-muted-foreground">
          <Layers size={11} />
          Buckets canon
        </div>
        <ul className="space-y-1">
          <li>
            <button
              type="button"
              onClick={() => onSelect('all')}
              className={cn(
                'group flex w-full items-center justify-between gap-2 rounded-md border px-2 py-1.5 text-left text-[12.5px] transition-colors',
                selectedBucket === 'all'
                  ? 'border-primary/40 bg-primary/10 text-foreground'
                  : 'border-transparent text-foreground hover:bg-accent',
              )}
            >
              <span className="font-medium">Todos os módulos</span>
              <span
                className={cn(
                  'rounded-full border px-1.5 py-0.5 text-[10px] font-semibold tabular-nums',
                  selectedBucket === 'all'
                    ? 'border-primary/40 bg-card text-foreground'
                    : 'border-border bg-muted text-muted-foreground',
                )}
              >
                {totalCount}
              </span>
            </button>
          </li>
          {ordered.map((b) => {
            const active = selectedBucket === b.key;
            return (
              <li key={b.key}>
                <button
                  type="button"
                  onClick={() => onSelect(b.key)}
                  className={cn(
                    'group flex w-full items-start justify-between gap-2 rounded-md border px-2 py-1.5 text-left transition-colors',
                    active
                      ? 'border-primary/40 bg-primary/10'
                      : 'border-transparent hover:bg-accent',
                  )}
                  aria-pressed={active}
                >
                  <div className="min-w-0">
                    <div className="text-[12.5px] font-medium text-foreground truncate">
                      {b.label}
                    </div>
                    <div className="text-[10px] text-muted-foreground">
                      meta ≥ {b.meta}
                    </div>
                  </div>
                  <span
                    className={cn(
                      'shrink-0 rounded-full border px-1.5 py-0.5 text-[10px] font-semibold tabular-nums',
                      active
                        ? 'border-primary/40 bg-card text-foreground'
                        : 'border-border bg-muted text-muted-foreground',
                    )}
                  >
                    {b.count}
                  </span>
                </button>
              </li>
            );
          })}
        </ul>
      </section>

      {/* Section: wave history accordion */}
      <section>
        <button
          type="button"
          onClick={onToggleWaveHistory}
          className="flex w-full items-center justify-between gap-2 rounded-md px-1 py-1 text-[10px] font-bold uppercase tracking-wider text-muted-foreground hover:bg-accent"
          aria-expanded={waveExpanded}
        >
          <span className="flex items-center gap-1.5">
            <History size={11} />
            Wave history
            <span className="rounded-md bg-muted px-1 text-[9px] tabular-nums normal-case">
              {waveHistory.length}
            </span>
          </span>
          <ChevronRight
            size={12}
            className={cn('transition-transform', waveExpanded && 'rotate-90')}
          />
        </button>
        {waveExpanded && (
          <ol className="mt-1.5 space-y-1 border-l border-border pl-2.5">
            {waveHistory.length === 0 && (
              <li className="text-[10.5px] italic text-muted-foreground py-1">
                sem entradas (backend W29-B pendente)
              </li>
            )}
            {waveHistory.map((w) => (
              <li key={w.wave_id} className="relative">
                <span
                  aria-hidden
                  className="absolute -left-[7px] top-1.5 inline-block h-1.5 w-1.5 rounded-full bg-primary/70"
                />
                <div className="rounded-md px-1.5 py-1 hover:bg-accent">
                  <div className="flex items-center justify-between gap-2 text-[11px]">
                    <span className="font-semibold text-foreground tabular-nums">
                      {w.wave_id}
                    </span>
                    {w.pr_url && (
                      <a
                        href={w.pr_url}
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex items-center gap-0.5 text-primary hover:underline focus:outline-none focus:ring-2 focus:ring-primary/40"
                        title="abrir PR"
                      >
                        <GitBranch size={9} />
                      </a>
                    )}
                  </div>
                  <div className="text-[10.5px] text-foreground truncate" title={w.label}>
                    {w.label}
                  </div>
                  {w.summary && (
                    <div
                      className="line-clamp-2 text-[9.5px] text-muted-foreground"
                      title={w.summary}
                    >
                      {w.summary}
                    </div>
                  )}
                </div>
              </li>
            ))}
          </ol>
        )}
      </section>
    </aside>
  );
}
