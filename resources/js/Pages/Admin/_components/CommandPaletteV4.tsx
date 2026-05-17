import * as React from 'react';
import { Layers, GitBranch, ClipboardList, BookOpen } from 'lucide-react';
import {
  CommandDialog,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
  CommandSeparator,
} from '@/Components/ui/command';
import type {
  Initiative,
  ModuleRow,
  WaveHistoryEntry,
} from './governanceV4Types';

/**
 * CommandPaletteV4 — ⌘K cross-search (extends kb/CommandPalette pattern)
 *
 * Wave 29 (W29-C). 4 grupos:
 *  - Módulos (top 200 cache local)
 *  - Initiatives abertas
 *  - Waves (W11-W28+)
 *  - ADRs canônicas (estáticas + algumas dinâmicas via prop opcional)
 *
 * Atalhos: ↑↓ navegar, Enter abrir, Esc fechar.
 */
interface AdrEntry {
  number: string;
  title: string;
  url?: string;
}

interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  modules: ModuleRow[];
  initiatives: Initiative[];
  waves: WaveHistoryEntry[];
  adrs?: AdrEntry[];
  onPickModule: (slug: string) => void;
  onPickInitiative?: (id: number) => void;
  onPickWave?: (waveId: string) => void;
  onPickAdr?: (adr: AdrEntry) => void;
}

const DEFAULT_ADRS: AdrEntry[] = [
  { number: '0160', title: 'Governance v4 scoped scorecards + buckets' },
  { number: '0161', title: 'Aposentar hacks 0159 redundantes' },
  { number: '0162', title: 'OTel collector prod observability' },
  { number: '0163', title: 'Metas alcançadas ondas 19-28' },
  { number: '0093', title: 'Multi-tenant isolation Tier 0' },
  { number: '0094', title: 'Constituição v2 7 camadas + 8 princípios' },
  { number: '0104', title: 'Processo MWART canônico — único caminho' },
  { number: '0110', title: 'Cockpit Pattern V2 canon list-detail' },
];

function fuzzy(q: string, hay: string): boolean {
  const a = q.trim().toLowerCase();
  if (!a) return true;
  return hay.toLowerCase().includes(a);
}

export default function CommandPaletteV4({
  open,
  onOpenChange,
  modules,
  initiatives,
  waves,
  adrs,
  onPickModule,
  onPickInitiative,
  onPickWave,
  onPickAdr,
}: Props) {
  const [query, setQuery] = React.useState('');

  React.useEffect(() => {
    if (open) setQuery('');
  }, [open]);

  const allAdrs = adrs && adrs.length > 0 ? adrs : DEFAULT_ADRS;

  const modulesFiltered = React.useMemo(
    () =>
      modules
        .filter((m) => fuzzy(query, `${m.slug} ${m.name}`))
        .slice(0, query.trim() ? 12 : 8),
    [modules, query],
  );
  const initiativesFiltered = React.useMemo(
    () =>
      initiatives
        .filter((i) => i.status !== 'done')
        .filter((i) => fuzzy(query, `${i.title} ${i.module}`))
        .slice(0, 8),
    [initiatives, query],
  );
  const wavesFiltered = React.useMemo(
    () =>
      waves
        .filter((w) => fuzzy(query, `${w.wave_id} ${w.label} ${w.summary ?? ''}`))
        .slice(0, 8),
    [waves, query],
  );
  const adrsFiltered = React.useMemo(
    () =>
      allAdrs
        .filter((a) => fuzzy(query, `${a.number} ${a.title}`))
        .slice(0, 8),
    [allAdrs, query],
  );

  const totalResults =
    modulesFiltered.length +
    initiativesFiltered.length +
    wavesFiltered.length +
    adrsFiltered.length;

  return (
    <CommandDialog
      open={open}
      onOpenChange={onOpenChange}
      title="Busca cross-domínio"
      description="Procure módulos, initiatives, waves ou ADRs — Esc fecha."
    >
      <CommandInput
        placeholder="Buscar módulo / initiative / wave / ADR…"
        value={query}
        onValueChange={setQuery}
      />
      <CommandList>
        {totalResults === 0 ? (
          <CommandEmpty>
            <div className="px-4 py-6 text-center text-[12px] text-muted-foreground">
              Nada bate com <b className="text-foreground">"{query}"</b>.
            </div>
          </CommandEmpty>
        ) : (
          <>
            {modulesFiltered.length > 0 && (
              <CommandGroup heading="Módulos">
                {modulesFiltered.map((m) => (
                  <CommandItem
                    key={`mod-${m.slug}`}
                    value={`module ${m.slug} ${m.name}`}
                    onSelect={() => {
                      onPickModule(m.slug);
                      onOpenChange(false);
                    }}
                    className="flex items-center gap-2"
                  >
                    <Layers size={12} className="text-muted-foreground" />
                    <span className="font-medium">{m.name}</span>
                    <span className="text-[10.5px] text-muted-foreground">
                      {m.slug}
                    </span>
                    <span className="ml-auto text-[11px] font-semibold tabular-nums">
                      {m.score}
                    </span>
                  </CommandItem>
                ))}
              </CommandGroup>
            )}

            {initiativesFiltered.length > 0 && (
              <>
                <CommandSeparator />
                <CommandGroup heading="Initiatives abertas">
                  {initiativesFiltered.map((i) => (
                    <CommandItem
                      key={`ini-${i.id}`}
                      value={`initiative ${i.module} ${i.title}`}
                      onSelect={() => {
                        onPickInitiative?.(i.id);
                        onOpenChange(false);
                      }}
                      className="flex items-center gap-2"
                    >
                      <ClipboardList size={12} className="text-muted-foreground" />
                      <span className="font-medium truncate">{i.title}</span>
                      <span className="text-[10.5px] text-muted-foreground shrink-0">
                        {i.module}
                      </span>
                    </CommandItem>
                  ))}
                </CommandGroup>
              </>
            )}

            {wavesFiltered.length > 0 && (
              <>
                <CommandSeparator />
                <CommandGroup heading="Waves">
                  {wavesFiltered.map((w) => (
                    <CommandItem
                      key={`wave-${w.wave_id}`}
                      value={`wave ${w.wave_id} ${w.label}`}
                      onSelect={() => {
                        onPickWave?.(w.wave_id);
                        onOpenChange(false);
                      }}
                      className="flex items-center gap-2"
                    >
                      <GitBranch size={12} className="text-muted-foreground" />
                      <span className="font-semibold tabular-nums">{w.wave_id}</span>
                      <span className="text-[12px] text-foreground truncate">
                        {w.label}
                      </span>
                    </CommandItem>
                  ))}
                </CommandGroup>
              </>
            )}

            {adrsFiltered.length > 0 && (
              <>
                <CommandSeparator />
                <CommandGroup heading="ADRs">
                  {adrsFiltered.map((a) => (
                    <CommandItem
                      key={`adr-${a.number}`}
                      value={`adr ${a.number} ${a.title}`}
                      onSelect={() => {
                        onPickAdr?.(a);
                        onOpenChange(false);
                      }}
                      className="flex items-center gap-2"
                    >
                      <BookOpen size={12} className="text-muted-foreground" />
                      <span className="font-semibold tabular-nums">
                        ADR {a.number}
                      </span>
                      <span className="text-[12px] text-foreground truncate">
                        {a.title}
                      </span>
                    </CommandItem>
                  ))}
                </CommandGroup>
              </>
            )}
          </>
        )}
      </CommandList>
    </CommandDialog>
  );
}
