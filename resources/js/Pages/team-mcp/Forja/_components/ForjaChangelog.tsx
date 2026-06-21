// Forja — aba Changelog. "O que shippou" projetado SÓ de fonte real:
// ADRs/SPECs (mcp_memory_documents) + sessões Claude Code (mcp_cc_sessions),
// mescladas e ordenadas por data desc no backend (ForjaChangelogService@build).
// PRs e Ondas são OMITIDOS quando não há fonte fácil no DB — sem dado fantasma.
//
// Reuso: estrutura de lista + DS v6 espelham ForjaTriage.tsx (ID mono, selo de
// ator, tabular-nums nas datas, layout via inline-flex/primitivos, data-testid).
// Filtro de kind é client-side (chips Tudo/PRs/ADRs/Sessões/Ondas).
// DS v6: SÓ tokens semânticos (primary/success/warning/info/destructive/muted/…),
// nunca paleta crua Tailwind; layout só inline-flex/inline-grid; máx rounded-lg.

import { useMemo, useState } from 'react';
import { History } from 'lucide-react';
import { cn } from '@/Lib/utils';

export interface ChangelogEntry {
  // Tipo do evento. Define o dot colorido e o filtro de chip.
  kind: 'pr' | 'adr' | 'session' | 'onda';
  // ID curto/mono (slug da ADR, "ADR NNNN", uuid curto da sessão, etc.).
  id: string;
  title: string;
  // Selo de ator ([CC]/[W]/'CL'/autor da ADR…).
  actor: string;
  // Data já formatada no backend (ISO 8601 ou dd/mm). Renderizada como veio.
  date: string;
}

interface Props {
  // changelog chega via Inertia::defer (ForjaController@changelog) → undefined no
  // 1º paint. Default-guard `= []` no destructuring pra NÃO crashar antes do defer
  // (skill inertia-defer-default; espelha ForjaTriage.tsx).
  changelog?: ChangelogEntry[];
}

// Dot colorido por kind — 100% tokens semânticos DS v6 (sem paleta crua, ui:lint
// R1 = 0). Auto-adaptam ao dark sem variantes dark: manuais.
const KIND_DOT: Record<ChangelogEntry['kind'], string> = {
  pr:      'bg-success',
  adr:     'bg-primary',
  session: 'bg-info-soft',
  onda:    'bg-warning-soft',
};

// Filtros (chips client-side). 'tudo' = sem filtro.
type Filter = 'tudo' | ChangelogEntry['kind'];
const FILTERS: ReadonlyArray<{ key: Filter; label: string }> = [
  { key: 'tudo',    label: 'Tudo' },
  { key: 'pr',      label: 'PRs' },
  { key: 'adr',     label: 'ADRs' },
  { key: 'session', label: 'Sessões' },
  { key: 'onda',    label: 'Ondas' },
];

export default function ForjaChangelog({ changelog = [] }: Props) {
  const [filter, setFilter] = useState<Filter>('tudo');

  const visible = useMemo(
    () => (filter === 'tudo' ? changelog : changelog.filter((e) => e.kind === filter)),
    [changelog, filter],
  );

  return (
    <div data-testid="forja-changelog">
      {/* Texto-âncora */}
      <p className="mt-1 max-w-3xl text-xs leading-relaxed text-muted-foreground">
        <strong className="text-foreground">O que shippou</strong> — PRs, ADRs, sessões e ondas,
        projetados de fonte real (sem dado fantasma) e ordenados do mais recente.
      </p>

      {/* Chips de filtro (client-side) */}
      <div className="mt-4 inline-flex flex-wrap items-center gap-1.5" role="tablist" data-testid="forja-changelog-filtros">
        {FILTERS.map((f) => {
          const active = filter === f.key;
          return (
            <button
              key={f.key}
              type="button"
              role="tab"
              aria-selected={active}
              onClick={() => setFilter(f.key)}
              className={cn(
                'inline-flex items-center rounded-md px-2.5 py-1 text-[11px] font-medium transition-colors',
                active
                  ? 'bg-primary text-primary-foreground'
                  : 'bg-muted text-muted-foreground hover:bg-muted/70',
              )}
            >
              {f.label}
            </button>
          );
        })}
      </div>

      {visible.length === 0 ? (
        <div className="mt-8 inline-flex w-full flex-col items-center justify-center gap-2 rounded-lg border border-dashed py-16 text-center text-muted-foreground">
          <History size={28} className="text-muted-foreground" />
          <p className="text-base font-medium text-foreground">Nada no changelog</p>
          <p className="text-sm">Nenhum evento real pra esse filtro ainda.</p>
        </div>
      ) : (
        <div className="mt-4 divide-y rounded-lg border">
          {visible.map((e, i) => (
            <div
              key={`${e.kind}-${e.id}-${i}`}
              className="inline-flex w-full items-center gap-3 px-4 py-3 transition-colors hover:bg-muted/40"
            >
              {/* Dot colorido por kind (rounded-sm — DS v6: máx rounded-lg) */}
              <span
                className={cn('size-2 shrink-0 rounded-sm', KIND_DOT[e.kind])}
                aria-hidden="true"
                data-testid="forja-changelog-dot"
              />

              {/* ID mono */}
              <span className="shrink-0 font-mono text-[11px] tabular-nums text-muted-foreground">
                {e.id}
              </span>

              {/* Título (cresce) */}
              <span className="min-w-0 flex-1 truncate text-sm font-medium text-foreground">
                {e.title}
              </span>

              {/* Selo de ator (pílula mono) */}
              {e.actor && (
                <span className="hidden shrink-0 rounded bg-muted px-1.5 py-0.5 font-mono text-[10px] text-muted-foreground sm:inline">
                  [{e.actor}]
                </span>
              )}

              {/* Data à direita (tabular-nums) */}
              <span className="shrink-0 text-[11px] tabular-nums text-muted-foreground">
                {e.date}
              </span>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
