import * as React from 'react';
import { Search, CornerDownLeft, Sparkles } from 'lucide-react';
import {
  CommandDialog,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
  CommandSeparator,
} from '@/Components/ui/command';
import type { KbCategory, KbNode } from '../_lib/types';
import { findCategory, fuzzyMatch } from '../_lib/helpers';

/**
 * KbCommandPalette — busca rápida ⌘K do KB (nome distinto do Components/CommandPalette global)
 *
 * Port do `kb-page.jsx::CommandPalette` (Cowork [CC]) usando shadcn `Command`
 * (cmdk underneath — feature parity + acessível).
 *
 * Comportamento:
 *  - Lista vazia: mostra primeiros 8 nós (recentes + pinned)
 *  - Com query: top 12 matches via fuzzyMatch
 *  - Empty AI fallback: oferece "Perguntar à IA: <query>" se sem matches
 *
 * Atalhos:
 *  - ↑↓ navegar (cmdk default)
 *  - Enter abrir
 *  - Esc fechar
 */
interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  nodes: KbNode[];
  categories: KbCategory[];
  onPickNode: (id: number) => void;
  /** se fornecido, fallback "Perguntar IA" aparece quando empty */
  onAskAI?: (query: string) => void;
}

export default function KbCommandPalette({
  open,
  onOpenChange,
  nodes,
  categories,
  onPickNode,
  onAskAI,
}: Props) {
  const [query, setQuery] = React.useState('');

  // Reset query ao abrir
  React.useEffect(() => {
    if (open) setQuery('');
  }, [open]);

  const results = React.useMemo<KbNode[]>(() => {
    if (!query.trim()) {
      // Empty query: top 8 pinned + most read
      const pinned = nodes.filter((n) => n.pinned);
      const others = [...nodes]
        .filter((n) => !n.pinned)
        .sort((a, b) => b.reads_count - a.reads_count);
      return [...pinned, ...others].slice(0, 8);
    }
    return nodes.filter((n) => fuzzyMatch(n, query)).slice(0, 12);
  }, [query, nodes]);

  return (
    <CommandDialog
      open={open}
      onOpenChange={onOpenChange}
      title="Busca rápida"
      description="Procure por título, etiqueta ou autor — Esc fecha."
    >
      <CommandInput
        placeholder="Procure por título, etiqueta, autor..."
        value={query}
        onValueChange={setQuery}
      />
      <CommandList>
        {results.length === 0 ? (
          <CommandEmpty>
            <div className="px-4 py-6 text-center space-y-3">
              <p className="text-[13px] text-muted-foreground m-0">
                Nenhum artigo bate com <b className="text-foreground">"{query}"</b>.
              </p>
              {onAskAI && query.trim() && (
                <button
                  type="button"
                  onClick={() => {
                    onAskAI(query);
                    onOpenChange(false);
                  }}
                  className="inline-flex items-center gap-2 rounded-md bg-primary/10 px-3 py-1.5 text-[12px] font-medium text-primary hover:bg-primary/15"
                >
                  <Sparkles size={12} /> Perguntar à IA: "{query}"
                </button>
              )}
              <p className="text-[10.5px] text-muted-foreground m-0">
                A IA busca em todo o KB, não só nos títulos.
              </p>
            </div>
          </CommandEmpty>
        ) : (
          <CommandGroup heading={query.trim() ? 'Resultados' : 'Sugestões'}>
            {results.map((n) => {
              const cat = findCategory(categories, n.category_id);
              const hue = cat?.hue ?? 240;
              return (
                <CommandItem
                  key={n.id}
                  value={`${n.title} ${n.slug} ${(n.tags ?? []).join(' ')} ${n.author_name ?? ''}`}
                  onSelect={() => {
                    onPickNode(n.id);
                    onOpenChange(false);
                  }}
                  className="flex items-start gap-2.5 py-2"
                >
                  <span
                    className="kb-hue-dot inline-block h-2 w-2 shrink-0 rounded-full mt-1.5"
                    style={{ '--kb-hue': hue } as React.CSSProperties}
                    aria-hidden
                  />
                  <div className="flex-1 min-w-0">
                    <b className="block text-[12.5px] font-semibold text-foreground truncate">
                      {n.title}
                    </b>
                    <span className="block text-[10.5px] text-muted-foreground truncate">
                      {cat?.label ?? '—'}
                      {n.author_name && ` · ${n.author_name}`}
                      {n.read_time_min && ` · ${n.read_time_min} min`}
                    </span>
                  </div>
                  <CornerDownLeft
                    size={12}
                    className="text-muted-foreground shrink-0 mt-1"
                    aria-hidden
                  />
                </CommandItem>
              );
            })}
          </CommandGroup>
        )}

        <CommandSeparator />

        <div className="flex items-center justify-between gap-2 px-3 py-2 text-[10.5px] text-muted-foreground border-t border-border">
          <span className="flex items-center gap-1">
            <Search size={10} />
            <kbd className="kb-kbd">↑↓</kbd> navegar
          </span>
          <span>
            <kbd className="kb-kbd">↵</kbd> abrir
          </span>
          <span>
            <kbd className="kb-kbd">esc</kbd> fechar
          </span>
        </div>
      </CommandList>
    </CommandDialog>
  );
}
