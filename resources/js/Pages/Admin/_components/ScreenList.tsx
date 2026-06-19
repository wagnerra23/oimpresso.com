import * as React from 'react';
import { Search, X, FileSearch } from 'lucide-react';
import { cn } from '@/Lib/utils';
import RoundBadge, { type ReviewStatus } from './RoundBadge';

/**
 * ScreenList — coluna 2 do tri-pane (port de kb/NodeList)
 *
 * Wave 30 Agent B (W30-B). Lista telas .tsx do módulo selecionado.
 * Filter chips pills `rounded-full` (anti-pattern `border-b-2`).
 */
export interface ScreenRow {
  module: string;
  path: string; // ex: "Admin/GovernanceV4" (sem .tsx)
  name: string; // ex: "GovernanceV4"
  status: ReviewStatus;
  current_round: number;
  last_review_at: string | null;
  screenshot_url: string | null;
  charter_path: string | null;
  ux_targets: string[];
  desvios_count: number;
}

export type StatusFilter = ReviewStatus | 'all';
export type RoundRangeFilter = 'all' | '1-3' | '4+';

export interface ScreenListFilters {
  q: string;
  status: StatusFilter;
  roundRange: RoundRangeFilter;
}

interface Props {
  screens: ScreenRow[];
  moduleLabel: string;
  filters: ScreenListFilters;
  onChangeFilters: (next: ScreenListFilters) => void;
  selectedPath: string | null;
  onSelectScreen: (path: string) => void;
  className?: string;
}

const STATUS_CHIPS: { key: StatusFilter; label: string }[] = [
  { key: 'all', label: 'Todos' },
  { key: 'pending-wagner', label: 'Pendente' },
  { key: 'approved', label: 'Aprovada' },
  { key: 'iterate', label: 'Iterar' },
  { key: 'rejected', label: 'Rejeitada' },
];

const ROUND_CHIPS: { key: RoundRangeFilter; label: string }[] = [
  { key: 'all', label: 'Todos rounds' },
  { key: '1-3', label: 'R1-R3' },
  { key: '4+', label: 'R4+' },
];

function matchRound(round: number, range: RoundRangeFilter): boolean {
  switch (range) {
    case '1-3':
      return round >= 1 && round <= 3;
    case '4+':
      return round >= 4;
    default:
      return true;
  }
}

export default function ScreenList({
  screens,
  moduleLabel,
  filters,
  onChangeFilters,
  selectedPath,
  onSelectScreen,
  className,
}: Props) {
  const setFilter = <K extends keyof ScreenListFilters>(key: K, value: ScreenListFilters[K]) => {
    onChangeFilters({ ...filters, [key]: value });
  };

  const filtered = React.useMemo(() => {
    const q = filters.q.trim().toLowerCase();
    return screens.filter((s) => {
      if (q && !`${s.path} ${s.name}`.toLowerCase().includes(q)) return false;
      if (filters.status !== 'all' && s.status !== filters.status) return false;
      if (!matchRound(s.current_round, filters.roundRange)) return false;
      return true;
    });
  }, [screens, filters]);

  const hasAnyFilter =
    filters.q.trim() !== '' || filters.status !== 'all' || filters.roundRange !== 'all';

  return (
    <section
      className={cn(
        'kb-list flex flex-col overflow-hidden border-r border-border bg-background',
        className,
      )}
      aria-label="Lista telas do módulo"
    >
      <header className="space-y-2 border-b border-border bg-card/40 px-3 py-2">
        <div className="flex items-baseline justify-between gap-2">
          <h2 className="truncate text-[13px] font-semibold text-foreground">
            {moduleLabel}
          </h2>
          <span className="text-[10.5px] tabular-nums text-muted-foreground">
            {filtered.length}/{screens.length}
          </span>
        </div>

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
            placeholder="Filtrar tela por nome ou path…"
            className="h-7 w-full rounded-md border border-border bg-background pl-7 pr-2 text-[12px] text-foreground placeholder:text-muted-foreground/70 focus:outline-none focus:ring-2 focus:ring-primary/40"
            aria-label="Buscar tela"
          />
        </div>

        <div className="flex flex-wrap items-center gap-1">
          {STATUS_CHIPS.map((c) => (
            <Chip
              key={c.key}
              active={filters.status === c.key}
              onClick={() => setFilter('status', c.key)}
            >
              {c.label}
            </Chip>
          ))}
          <span className="mx-1 h-3 w-px bg-border" aria-hidden />
          {ROUND_CHIPS.map((c) => (
            <Chip
              key={c.key}
              active={filters.roundRange === c.key}
              onClick={() => setFilter('roundRange', c.key)}
            >
              {c.label}
            </Chip>
          ))}
          {hasAnyFilter && (
            <button
              type="button"
              onClick={() =>
                onChangeFilters({ q: '', status: 'all', roundRange: 'all' })
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
            <FileSearch size={20} className="mx-auto mb-2 opacity-60" />
            Nenhuma tela bate com os filtros atuais.
          </li>
        ) : (
          filtered.map((s) => (
            <ScreenListItem
              key={s.path}
              screen={s}
              active={selectedPath === s.path}
              onClick={() => onSelectScreen(s.path)}
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

function ScreenListItem({
  screen,
  active,
  onClick,
}: {
  screen: ScreenRow;
  active: boolean;
  onClick: () => void;
}) {
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
            <div className="truncate text-[12.5px] font-semibold text-foreground">
              {screen.name}
            </div>
            <div className="truncate text-[10px] text-muted-foreground">
              {screen.path}
            </div>
          </div>
          <RoundBadge
            round={screen.current_round}
            status={screen.status}
            showIcon={false}
          />
        </div>
        <div className="flex flex-wrap items-center gap-1 text-[9.5px] text-muted-foreground">
          {screen.charter_path && (
            <span
              className="rounded-md border border-border bg-card px-1 py-0.5"
              title="Charter presente"
            >
              charter
            </span>
          )}
          {screen.screenshot_url && (
            <span
              className="rounded-md border border-border bg-card px-1 py-0.5"
              title="Screenshot 1440 disponível"
            >
              screenshot
            </span>
          )}
          {screen.desvios_count > 0 && (
            <span
              className="rounded-md border border-warning/20 bg-warning-soft px-1 py-0.5 text-warning-fg"
              title="Desvios catalogados último round"
            >
              {screen.desvios_count} desvios
            </span>
          )}
          {screen.last_review_at && (
            <span className="ml-auto tabular-nums" title="Último round">
              {new Date(screen.last_review_at).toLocaleDateString('pt-BR')}
            </span>
          )}
        </div>
      </button>
    </li>
  );
}
