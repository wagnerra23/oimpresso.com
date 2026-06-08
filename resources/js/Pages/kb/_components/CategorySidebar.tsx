import * as React from 'react';
import { ChevronRight, Star } from 'lucide-react';
import type {
  KbCategory,
  KbNode,
  KbSubcategory,
} from '../_lib/types';
import { cn } from '@/Lib/utils';

/**
 * CategorySidebar — coluna 1 do tri-pane
 *
 * Port do `kb-page.jsx::aside.kb-side` (Cowork [CC]).
 * Estrutura:
 *  - Categorias (com subcats expansíveis + contador)
 *  - Meus favoritos (top 8)
 *  - Recentes
 *  - Etiquetas populares (cloud)
 *  - Atalhos hint (Larissa)
 *
 * Tokens canon: hue dinâmico por categoria via inline style (oklch).
 * Nenhuma cor inventada — só lê do prop `category.hue`.
 */

interface Props {
  categories: KbCategory[];
  subcategories: KbSubcategory[];
  nodes: KbNode[]; // pra contar por categoria + derivar tags populares
  activeCategorySlug: string; // 'all' | category.slug
  activeSubcategorySlug: string | null;
  expandedCats: Record<string, boolean>;
  activeTag: string | null;
  favorites: number[];
  recent: number[];
  onPickCategory: (slug: string) => void;
  onPickSubcategory: (slug: string | null) => void;
  onToggleExpand: (slug: string) => void;
  onPickTag: (tag: string | null) => void;
  onPickNode: (id: number) => void;
  tagsTop: Array<{ tag: string; count: number }>;
}

const ALL_CAT_HUE = 240;

export default function CategorySidebar({
  categories,
  subcategories,
  nodes,
  activeCategorySlug,
  activeSubcategorySlug,
  expandedCats,
  activeTag,
  favorites,
  recent,
  onPickCategory,
  onPickSubcategory,
  onToggleExpand,
  onPickTag,
  onPickNode,
  tagsTop,
}: Props) {
  // Contadores
  const countByCat = React.useMemo(() => {
    const m: Record<string, number> = { all: nodes.length };
    categories.forEach((c) => {
      m[c.slug] = nodes.filter((n) => n.category_id === c.id).length;
    });
    return m;
  }, [nodes, categories]);

  const countBySubcat = React.useCallback(
    (categoryId: number, subId: number): number =>
      nodes.filter((n) => n.category_id === categoryId && n.subcategory_id === subId)
        .length,
    [nodes],
  );

  const favNodes = React.useMemo(
    () =>
      favorites
        .map((id) => nodes.find((n) => n.id === id))
        .filter((n): n is KbNode => Boolean(n))
        .slice(0, 8),
    [favorites, nodes],
  );

  const recentNodes = React.useMemo(
    () =>
      recent
        .map((id) => nodes.find((n) => n.id === id))
        .filter((n): n is KbNode => Boolean(n))
        .slice(0, 6),
    [recent, nodes],
  );

  const renderCat = (cat: KbCategory | null) => {
    const isAll = cat === null;
    const slug = isAll ? 'all' : cat.slug;
    const label = isAll ? 'Tudo' : cat.label;
    const hue = isAll ? ALL_CAT_HUE : cat.hue;
    const count = countByCat[slug] ?? 0;
    const subs = isAll ? [] : subcategories.filter((s) => s.category_id === cat.id);
    const hasSub = subs.length > 0;
    const expanded = expandedCats[slug] ?? false;
    const active = activeCategorySlug === slug;

    return (
      <li key={slug}>
        <button
          type="button"
          className={cn(
            'kb-side-btn w-full flex items-center gap-2 px-2 py-1.5 text-left text-[12.5px] rounded-r-md border-l-2 transition-colors',
            active
              ? 'bg-surface text-foreground font-semibold'
              : 'border-l-transparent text-muted-foreground hover:bg-muted hover:text-foreground',
          )}
          style={
            active
              ? ({
                  borderLeftColor: `oklch(0.55 0.13 ${hue})`,
                } as React.CSSProperties)
              : undefined
          }
          onClick={() => {
            if (hasSub) onToggleExpand(slug);
            onPickCategory(slug);
            onPickSubcategory(null);
          }}
          aria-pressed={active}
          aria-expanded={hasSub ? expanded : undefined}
        >
          {hasSub && (
            <ChevronRight
              size={12}
              className={cn(
                'shrink-0 text-muted-foreground transition-transform',
                expanded && 'rotate-90',
              )}
              aria-hidden
            />
          )}
          <span
            className="inline-block h-1.5 w-1.5 shrink-0 rounded-full"
            style={{ background: `oklch(0.62 0.13 ${hue})` }}
            aria-hidden
          />
          <span className="flex-1 truncate">{label}</span>
          <span className="font-mono text-[10px] text-muted-foreground bg-muted px-1.5 py-px rounded-full">
            {count}
          </span>
        </button>

        {hasSub && expanded && (
          <ul className="ml-5 mt-0.5 space-y-px">
            {subs.map((s) => {
              if (!cat) return null;
              const n = countBySubcat(cat.id, s.id);
              if (n === 0) return null;
              const subActive =
                activeCategorySlug === cat.slug && activeSubcategorySlug === s.slug;
              return (
                <li key={s.slug}>
                  <button
                    type="button"
                    onClick={(e) => {
                      e.stopPropagation();
                      onPickCategory(cat.slug);
                      onPickSubcategory(subActive ? null : s.slug);
                    }}
                    className={cn(
                      'w-full flex items-center gap-2 px-2 py-1 text-[11.5px] text-left rounded-md',
                      subActive
                        ? 'bg-muted text-foreground font-medium'
                        : 'text-muted-foreground hover:text-foreground hover:bg-muted/60',
                    )}
                    aria-pressed={subActive}
                  >
                    <span className="flex-1 truncate">{s.label}</span>
                    <span className="font-mono text-[10px] text-muted-foreground">
                      {n}
                    </span>
                  </button>
                </li>
              );
            })}
          </ul>
        )}
      </li>
    );
  };

  return (
    <aside
      className="kb-side flex flex-col gap-4 overflow-y-auto bg-muted/30 border-r border-border py-3"
      aria-label="Filtros de categoria"
    >
      {/* Categorias */}
      <section className="px-3">
        <h2 className="text-[9.5px] font-bold uppercase tracking-wider text-muted-foreground mb-2">
          Categorias
        </h2>
        <ul className="space-y-px list-none m-0 p-0">
          {renderCat(null)}
          {categories.map((c) => renderCat(c))}
        </ul>
      </section>

      {/* Favoritos */}
      <section className="px-3">
        <h2 className="text-[9.5px] font-bold uppercase tracking-wider text-muted-foreground mb-2">
          Meus favoritos
        </h2>
        {favNodes.length === 0 ? (
          <p className="text-[11px] text-muted-foreground italic m-0 px-1">
            Marque artigos com a estrela ou tecla B.
          </p>
        ) : (
          <ul className="space-y-0.5 list-none m-0 p-0">
            {favNodes.map((n) => (
              <li key={n.id}>
                <button
                  type="button"
                  onClick={() => onPickNode(n.id)}
                  className="w-full flex items-start gap-1.5 px-2 py-1 text-left text-[11px] text-foreground/85 rounded-md hover:bg-muted"
                >
                  <Star
                    size={10}
                    fill="currentColor"
                    className="shrink-0 mt-0.5 text-amber-500"
                    aria-hidden
                  />
                  <span className="truncate flex-1">{n.title}</span>
                </button>
              </li>
            ))}
          </ul>
        )}
      </section>

      {/* Recentes */}
      <section className="px-3">
        <h2 className="text-[9.5px] font-bold uppercase tracking-wider text-muted-foreground mb-2">
          Recentes
        </h2>
        {recentNodes.length === 0 ? (
          <p className="text-[11px] text-muted-foreground italic m-0 px-1">
            Nenhum acesso ainda.
          </p>
        ) : (
          <ul className="space-y-0.5 list-none m-0 p-0">
            {recentNodes.map((n) => (
              <li key={n.id}>
                <button
                  type="button"
                  onClick={() => onPickNode(n.id)}
                  className="w-full flex flex-col items-start gap-0.5 px-2 py-1 text-left rounded-md hover:bg-muted"
                >
                  <span className="text-[11.5px] text-foreground/90 line-clamp-2 leading-tight">
                    {n.title}
                  </span>
                  {n.read_time_min && (
                    <span className="text-[10px] font-mono text-muted-foreground">
                      {n.read_time_min}min
                    </span>
                  )}
                </button>
              </li>
            ))}
          </ul>
        )}
      </section>

      {/* Tags cloud */}
      <section className="px-3">
        <h2 className="text-[9.5px] font-bold uppercase tracking-wider text-muted-foreground mb-2">
          Etiquetas populares
        </h2>
        <div className="flex flex-wrap gap-1">
          {tagsTop.map(({ tag, count }) => {
            const isActive = activeTag === tag;
            return (
              <button
                key={tag}
                type="button"
                onClick={() => onPickTag(isActive ? null : tag)}
                className={cn(
                  'inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10.5px] font-medium',
                  isActive
                    ? 'border-primary/50 bg-primary/10 text-primary'
                    : 'border-border bg-card text-muted-foreground hover:border-primary/30 hover:text-foreground',
                )}
                aria-pressed={isActive}
              >
                {tag}
                <span className="font-mono text-[9.5px] text-muted-foreground/70">
                  {count}
                </span>
              </button>
            );
          })}
        </div>
      </section>

      {/* Atalhos hint */}
      <section className="px-3 mt-auto pt-3 border-t border-border/60">
        <h2 className="text-[9.5px] font-bold uppercase tracking-wider text-muted-foreground mb-2">
          Atalhos
        </h2>
        <dl className="grid grid-cols-[auto_1fr] gap-x-2 gap-y-1 text-[11px] m-0">
          <dt className="flex gap-1">
            <kbd className="kb-kbd">⌘K</kbd>
            <span className="text-muted-foreground">ou</span>
            <kbd className="kb-kbd">/</kbd>
          </dt>
          <dd className="text-muted-foreground m-0">Buscar</dd>
          <dt>
            <kbd className="kb-kbd">Esc</kbd>
          </dt>
          <dd className="text-muted-foreground m-0">Fechar</dd>
          <dt>
            <kbd className="kb-kbd">J</kbd>/<kbd className="kb-kbd">K</kbd>
          </dt>
          <dd className="text-muted-foreground m-0">Navegar</dd>
          <dt>
            <kbd className="kb-kbd">N</kbd>
          </dt>
          <dd className="text-muted-foreground m-0">Novo artigo</dd>
          <dt>
            <kbd className="kb-kbd">A</kbd>
          </dt>
          <dd className="text-muted-foreground m-0">Perguntar IA</dd>
          <dt>
            <kbd className="kb-kbd">B</kbd>
          </dt>
          <dd className="text-muted-foreground m-0">Favoritar</dd>
        </dl>
      </section>
    </aside>
  );
}
