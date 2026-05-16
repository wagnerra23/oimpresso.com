import * as React from 'react';
import { Pin } from 'lucide-react';
import type { KbCategory, KbNode } from '../_lib/types';
import { findCategory, isNodeOutdated } from '../_lib/helpers';
import { cn } from '@/Lib/utils';

/**
 * NodeList — coluna 2 do tri-pane
 *
 * Port do `kb-page.jsx::section.kb-list` (Cowork [CC]).
 *
 * Mostra:
 *  - header com count + sort segmented (Recentes/Mais lidos/Mais úteis/A revisar)
 *  - filter pill ativo se houver tag/sub filtrado
 *  - rows de artigo: pílula cat hue, nível, equip, pinned, outdated, título,
 *    excerpt, meta (autor · updated · readTime · reads · OS linked)
 *  - empty state com botão "Limpar filtros"
 */

type SortOption = 'recent' | 'popular' | 'helpful' | 'outdated';

interface Props {
  nodes: KbNode[];
  categories: KbCategory[];
  activeNodeId: number | null;
  onPickNode: (id: number) => void;
  sortBy: SortOption;
  onChangeSort: (sort: SortOption) => void;
  activeTag: string | null;
  onClearTag: () => void;
  onClearAllFilters: () => void;
  /** label da categoria atual ("Tudo" ou cat.label) */
  categoryHeadingLabel: string;
}

const SORT_OPTIONS: Array<{ id: SortOption; label: string }> = [
  { id: 'recent', label: 'Recentes' },
  { id: 'popular', label: 'Mais lidos' },
  { id: 'helpful', label: 'Mais úteis' },
  { id: 'outdated', label: 'A revisar' },
];

export default function NodeList({
  nodes,
  categories,
  activeNodeId,
  onPickNode,
  sortBy,
  onChangeSort,
  activeTag,
  onClearTag,
  onClearAllFilters,
  categoryHeadingLabel,
}: Props) {
  const listRef = React.useRef<HTMLDivElement | null>(null);

  // Scroll do nó ativo pra visível quando muda
  React.useEffect(() => {
    if (!activeNodeId || !listRef.current) return;
    const el = listRef.current.querySelector<HTMLElement>(
      `[data-node-id="${activeNodeId}"]`,
    );
    if (el) {
      el.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }
  }, [activeNodeId]);

  return (
    <section
      className="kb-list flex flex-col min-h-0 bg-background border-r border-border"
      aria-label="Lista de artigos"
    >
      {/* Header */}
      <header className="px-4 py-2 flex items-center justify-between gap-2 border-b border-border bg-muted/30 shrink-0">
        <div className="flex items-baseline gap-2 min-w-0">
          <b className="text-[13px] font-semibold truncate">
            {categoryHeadingLabel}
          </b>
          <span className="text-[10.5px] text-muted-foreground">
            {nodes.length} {nodes.length === 1 ? 'artigo' : 'artigos'}
          </span>
        </div>
        <div
          role="radiogroup"
          aria-label="Ordenar por"
          className="inline-flex bg-card border border-border rounded-md p-px"
        >
          {SORT_OPTIONS.map((s) => (
            <button
              key={s.id}
              type="button"
              role="radio"
              aria-checked={sortBy === s.id}
              onClick={() => onChangeSort(s.id)}
              className={cn(
                'px-2 py-1 text-[10.5px] font-medium rounded-[4px] whitespace-nowrap transition-colors',
                sortBy === s.id
                  ? 'bg-foreground text-background'
                  : 'text-muted-foreground hover:text-foreground',
              )}
            >
              {s.label}
            </button>
          ))}
        </div>
      </header>

      {/* Filter pill */}
      {activeTag && (
        <div className="mx-4 mt-2 inline-flex self-start items-center gap-1.5 rounded-full bg-primary/10 px-2 py-0.5 text-[11px] text-primary">
          filtrando: <b className="font-semibold">{activeTag}</b>
          <button
            type="button"
            onClick={onClearTag}
            className="ml-0.5 leading-none text-[13px] hover:text-primary/70"
            aria-label="Limpar filtro de tag"
          >
            ×
          </button>
        </div>
      )}

      {/* List */}
      <div
        ref={listRef}
        className="kb-list-body flex-1 overflow-y-auto px-3 py-2 space-y-1.5"
      >
        {nodes.length === 0 ? (
          <div className="flex flex-col items-center justify-center gap-3 px-4 py-12 text-center">
            <p className="m-0 text-[12.5px] text-muted-foreground">
              Nenhum nó nesta combinação de filtros.
            </p>
            <button
              type="button"
              onClick={onClearAllFilters}
              className="text-[11.5px] text-primary hover:underline"
            >
              Limpar filtros
            </button>
          </div>
        ) : (
          nodes.map((n) => {
            const cat = findCategory(categories, n.category_id);
            const outdated = isNodeOutdated(n);
            const hue = cat?.hue ?? 240;
            const isActive = activeNodeId === n.id;
            return (
              <article
                key={n.id}
                data-node-id={n.id}
                onClick={() => onPickNode(n.id)}
                role="button"
                tabIndex={0}
                onKeyDown={(e) => {
                  if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    onPickNode(n.id);
                  }
                }}
                className={cn(
                  'group rounded-md border border-border border-l-[3px] bg-card px-3 py-2.5 cursor-pointer transition-all',
                  'hover:shadow-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                  isActive && 'shadow-sm',
                  outdated && 'bg-amber-50/40 dark:bg-amber-900/10',
                )}
                style={{
                  borderLeftColor: isActive
                    ? `oklch(0.55 0.13 ${hue})`
                    : n.pinned
                      ? `oklch(0.55 0.13 ${hue})`
                      : undefined,
                  ...(isActive
                    ? { background: `oklch(0.985 0.012 ${hue})` }
                    : {}),
                }}
              >
                <div className="flex flex-wrap items-center gap-1.5 mb-1">
                  {cat && (
                    <span
                      className="inline-flex items-center rounded-sm px-1.5 py-px text-[9.5px] font-semibold lowercase"
                      style={{
                        background: `oklch(0.94 0.05 ${hue})`,
                        color: `oklch(0.36 0.10 ${hue})`,
                      }}
                    >
                      {cat.label}
                    </span>
                  )}
                  {n.nivel && (
                    <span className="text-[10px] font-semibold lowercase text-muted-foreground">
                      {n.nivel === 'iniciante'
                        ? 'iniciante'
                        : n.nivel === 'intermediario'
                          ? 'intermediário'
                          : 'avançado'}
                    </span>
                  )}
                  {n.equip && n.equip !== '—' && (
                    <span className="text-[10px] font-mono bg-muted text-muted-foreground px-1.5 py-px rounded-sm">
                      {n.equip}
                    </span>
                  )}
                  {n.pinned && (
                    <span className="inline-flex items-center gap-0.5 text-[9.5px] font-semibold lowercase text-primary bg-primary/10 px-1.5 py-px rounded-sm">
                      <Pin size={9} aria-hidden /> fixo
                    </span>
                  )}
                  {outdated && (
                    <span className="text-[9.5px] font-semibold lowercase text-amber-700 bg-amber-100 dark:text-amber-300 dark:bg-amber-900/40 px-1.5 py-px rounded-sm">
                      revisar
                    </span>
                  )}
                </div>
                <h3 className="m-0 text-[13.5px] font-semibold leading-tight text-foreground line-clamp-2">
                  {n.title}
                </h3>
                {n.excerpt && (
                  <p className="m-0 mt-1 text-[12px] leading-snug text-muted-foreground line-clamp-2">
                    {n.excerpt}
                  </p>
                )}
                <div className="mt-1.5 flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-[10.5px] text-muted-foreground">
                  {n.author_name && <span>{n.author_name}</span>}
                  {n.author_name && (
                    <span className="text-border" aria-hidden>
                      ·
                    </span>
                  )}
                  {n.updated_at && (
                    <span>{relativeShort(n.updated_at)}</span>
                  )}
                  {n.read_time_min && (
                    <>
                      <span className="text-border" aria-hidden>
                        ·
                      </span>
                      <span className="font-mono">{n.read_time_min} min</span>
                    </>
                  )}
                  <span className="text-border" aria-hidden>
                    ·
                  </span>
                  <span>{n.reads_count} leituras</span>
                  {n.os_linked_count > 0 && (
                    <>
                      <span className="text-border" aria-hidden>
                        ·
                      </span>
                      <span className="text-blue-600 dark:text-blue-400 font-medium">
                        {n.os_linked_count} OS vinculadas
                      </span>
                    </>
                  )}
                </div>
              </article>
            );
          })
        )}
      </div>
    </section>
  );
}

function relativeShort(iso: string): string {
  const d = new Date(iso).getTime();
  if (Number.isNaN(d)) return iso;
  const diffMs = Date.now() - d;
  const day = 86_400_000;
  const days = Math.floor(diffMs / day);
  if (days < 1) return 'hoje';
  if (days === 1) return 'há 1 dia';
  if (days < 7) return `há ${days} dias`;
  if (days < 30) return `há ${Math.floor(days / 7)} sem.`;
  if (days < 365) return `há ${Math.floor(days / 30)} mês`;
  return `há ${Math.floor(days / 365)} ano(s)`;
}
