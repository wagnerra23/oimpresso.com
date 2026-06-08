import * as React from 'react';
import { cn } from '@/Lib/utils';
import { Search, X, AlertTriangle, ShieldAlert } from 'lucide-react';
import SparklineTrend from './SparklineTrend';
import {
  STATUS_TONE,
  type ModuleRow,
  type BucketKey,
  type StatusKey,
} from './governanceV4Types';

export type ScoreRangeKey = 'all' | '60-79' | '80-89' | '90+';

export interface ModuleListFilters {
  q: string;
  metaOnly: boolean;
  driftOnly: boolean;
  scoreRange: ScoreRangeKey;
  statusOnly: StatusKey | null;
}

interface Props {
  /** módulos do bucket selecionado (ou união se bucket='all') */
  modules: ModuleRow[];
  bucketLabel: string;
  bucketMeta: number;
  filters: ModuleListFilters;
  onChangeFilters: (next: ModuleListFilters) => void;
  driftedSlugs: Set<string>;
  selectedSlug: string | null;
  onSelectModule: (slug: string) => void;
  className?: string;
}

const SCORE_RANGE_CHIPS: { key: ScoreRangeKey; label: string }[] = [
  { key: 'all', label: 'Todos' },
  { key: '60-79', label: '60-79' },
  { key: '80-89', label: '80-89' },
  { key: '90+', label: '≥90' },
];

const STATUS_CHIPS: { key: StatusKey | 'all'; label: string }[] = [
  { key: 'all', label: 'Todos' },
  { key: 'ok', label: 'OK' },
  { key: 'warn', label: 'Atenção' },
  { key: 'crit', label: 'Crítico' },
];

function matchScoreRange(score: number, range: ScoreRangeKey): boolean {
  switch (range) {
    case '60-79':
      return score >= 60 && score < 80;
    case '80-89':
      return score >= 80 && score < 90;
    case '90+':
      return score >= 90;
    default:
      return true;
  }
}

/**
 * ModuleList — coluna 2 do tri-pane (port de kb/NodeList)
 *
 * Wave 29 (W29-C). Lista filtrável dos módulos do bucket selecionado.
 * Filter chips pills (charter: rounded-full, anti-pattern `border-b-2`).
 */
export default function ModuleList({
  modules,
  bucketLabel,
  bucketMeta,
  filters,
  onChangeFilters,
  driftedSlugs,
  selectedSlug,
  onSelectModule,
  className,
}: Props) {
  const setFilter = <K extends keyof ModuleListFilters>(key: K, value: ModuleListFilters[K]) => {
    onChangeFilters({ ...filters, [key]: value });
  };

  const filtered = React.useMemo(() => {
    const q = filters.q.trim().toLowerCase();
    return modules.filter((m) => {
      if (q && !`${m.slug} ${m.name}`.toLowerCase().includes(q)) return false;
      if (filters.metaOnly && m.score >= m.meta) return false;
      if (filters.driftOnly && !driftedSlugs.has(m.slug)) return false;
      if (filters.statusOnly && m.status !== filters.statusOnly) return false;
      if (!matchScoreRange(m.score, filters.scoreRange)) return false;
      return true;
    });
  }, [modules, filters, driftedSlugs]);

  const hasAnyFilter =
    filters.q.trim() !== '' ||
    filters.metaOnly ||
    filters.driftOnly ||
    filters.scoreRange !== 'all' ||
    filters.statusOnly !== null;

  return (
    <section
      className={cn(
        'kb-list flex flex-col overflow-hidden border-r border-border bg-background',
        className,
      )}
      aria-label="Lista módulos do bucket"
    >
      <header className="space-y-2 border-b border-border bg-card/40 px-3 py-2">
        <div className="flex items-baseline justify-between gap-2">
          <h2 className="text-[13px] font-semibold text-foreground truncate">
            {bucketLabel}
          </h2>
          <span className="text-[10.5px] text-muted-foreground tabular-nums">
            {filtered.length}/{modules.length} · meta ≥{bucketMeta}
          </span>
        </div>

        {/* search */}
        <div className="relative">
          <Search
            size={12}
            className="pointer-events-none absolute left-2 top-1/2 -translate-y-1/2 text-muted-foreground"
            aria-hidden
          />
          <input
            type="search"
            value={filters.q}
            onChange={(e) => setFilter('q', e.target.value)}
            placeholder="Filtrar módulo por slug ou nome…"
            className="h-7 w-full rounded-md border border-border bg-background pl-7 pr-2 text-[12px] text-foreground placeholder:text-muted-foreground/70 focus:outline-none focus:ring-2 focus:ring-primary/40"
            aria-label="Buscar módulo"
          />
        </div>

        {/* chips pills rounded-full */}
        <div className="flex flex-wrap items-center gap-1">
          <Chip
            active={filters.metaOnly}
            onClick={() => setFilter('metaOnly', !filters.metaOnly)}
          >
            <ShieldAlert size={10} />
            Abaixo meta
          </Chip>
          <Chip
            active={filters.driftOnly}
            onClick={() => setFilter('driftOnly', !filters.driftOnly)}
          >
            <AlertTriangle size={10} />
            Drift
          </Chip>
          <span className="mx-1 h-3 w-px bg-border" aria-hidden />
          {SCORE_RANGE_CHIPS.map((r) => (
            <Chip
              key={r.key}
              active={filters.scoreRange === r.key}
              onClick={() => setFilter('scoreRange', r.key)}
            >
              {r.label}
            </Chip>
          ))}
          <span className="mx-1 h-3 w-px bg-border" aria-hidden />
          {STATUS_CHIPS.map((s) => {
            const active =
              s.key === 'all' ? filters.statusOnly === null : filters.statusOnly === s.key;
            return (
              <Chip
                key={s.key}
                active={active}
                onClick={() =>
                  setFilter('statusOnly', s.key === 'all' ? null : (s.key as StatusKey))
                }
              >
                {s.label}
              </Chip>
            );
          })}
          {hasAnyFilter && (
            <button
              type="button"
              onClick={() =>
                onChangeFilters({
                  q: '',
                  metaOnly: false,
                  driftOnly: false,
                  scoreRange: 'all',
                  statusOnly: null,
                })
              }
              className="ml-auto inline-flex items-center gap-0.5 rounded-full px-2 py-0.5 text-[10.5px] text-muted-foreground hover:bg-accent focus:outline-none focus:ring-2 focus:ring-primary/40"
              title="Limpar filtros"
            >
              <X size={10} />
              limpar
            </button>
          )}
        </div>
      </header>

      <ul className="flex-1 overflow-y-auto p-1.5">
        {filtered.length === 0 ? (
          <li className="px-3 py-6 text-center text-[12px] text-muted-foreground">
            Nenhum módulo bate com os filtros atuais.
          </li>
        ) : (
          filtered.map((m) => (
            <ModuleListItem
              key={m.slug}
              module={m}
              active={selectedSlug === m.slug}
              hasDrift={driftedSlugs.has(m.slug)}
              onClick={() => onSelectModule(m.slug)}
            />
          ))
        )}
      </ul>
    </section>
  );
}

function Chip({
  active,
  onClick,
  children,
}: {
  active: boolean;
  onClick: () => void;
  children: React.ReactNode;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={cn(
        'inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10.5px] font-medium transition-colors',
        active
          ? 'border-primary/40 bg-primary/10 text-foreground'
          : 'border-border bg-card text-muted-foreground hover:bg-accent',
      )}
      aria-pressed={active}
    >
      {children}
    </button>
  );
}

function ModuleListItem({
  module,
  active,
  hasDrift,
  onClick,
}: {
  module: ModuleRow;
  active: boolean;
  hasDrift: boolean;
  onClick: () => void;
}) {
  const statusTone = STATUS_TONE[module.status];
  const statusCls =
    module.status === 'ok'
      ? 'text-emerald-700 dark:text-emerald-400 border-emerald-300 dark:border-emerald-700 bg-emerald-50 dark:bg-emerald-950/40'
      : module.status === 'warn'
        ? 'text-amber-700 dark:text-amber-400 border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/40'
        : 'text-destructive border-destructive/40 bg-destructive/10';

  return (
    <li>
      <button
        type="button"
        onClick={onClick}
        className={cn(
          'group flex w-full flex-col gap-1 rounded-md border px-2 py-2 text-left transition-colors',
          active
            ? 'border-primary/40 bg-primary/5'
            : 'border-transparent hover:bg-accent',
        )}
        aria-pressed={active}
      >
        <div className="flex items-center justify-between gap-2">
          <div className="min-w-0">
            <div className="text-[12.5px] font-semibold text-foreground truncate">
              {module.name}
            </div>
            <div className="text-[10px] text-muted-foreground truncate">
              {module.slug}
            </div>
          </div>
          <div className="shrink-0 text-right">
            <div className="text-[14px] font-bold tabular-nums text-foreground">
              {module.score}
            </div>
            <div className="text-[9.5px] text-muted-foreground tabular-nums">
              meta {module.meta}
            </div>
          </div>
        </div>
        <div className="flex items-center justify-between gap-2">
          <div className="flex items-center gap-1">
            <span
              className={cn(
                'inline-flex items-center gap-0.5 rounded-md border px-1 py-0.5 text-[9.5px] font-medium',
                statusCls,
              )}
              title={`Status: ${statusTone.label}`}
            >
              {statusTone.label}
            </span>
            {hasDrift && (
              <span
                className="inline-flex items-center gap-0.5 rounded-md border border-destructive/40 bg-destructive/10 px-1 py-0.5 text-[9.5px] font-medium text-destructive"
                title="Drift detectado nos últimos 7 dias"
              >
                <AlertTriangle size={9} />
                drift
              </span>
            )}
            {module.paired_count > 0 && (
              <span
                className="inline-flex items-center gap-0.5 rounded-md border border-amber-300 bg-amber-50 px-1 py-0.5 text-[9.5px] font-medium text-amber-800 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-300"
                title="Paired violations (anti-Goodhart)"
              >
                paired×{module.paired_count}
              </span>
            )}
            {module.p99_ms !== null && (
              <span
                className="inline-flex items-center gap-0.5 rounded-md border border-border bg-muted px-1 py-0.5 text-[9.5px] font-medium text-muted-foreground tabular-nums"
                title="p99 latência 7d"
              >
                {Math.round(module.p99_ms)}ms
              </span>
            )}
          </div>
          <SparklineTrend
            values={module.trend}
            width={70}
            height={18}
            className="text-foreground/60"
            ariaLabel={`Tendência 30d ${module.name}`}
          />
        </div>
      </button>
    </li>
  );
}
