// @memcofre
//   tela: /kb/v2 (port tri-pane do Cowork [CC])
//   module: KB
//   stories: ONDA 2 KB-Unificado (BRIEFING.md, ADR 0149 proposal)
//   charter: resources/js/Pages/kb/Index.charter.md (v1.0 — 2026-05-15)
//   adrs: 0039 (cockpit), 0093 (multi-tenant Tier 0), 0104 (MWART), 0114 (gate visual)
//
// V2 ONDA 2 — port do protótipo Cowork prototipo-ui/prototipos/kb/kb-page.jsx
// pra Inertia React 19 + TS estrito + AppShellV2 + tokens canon OKLCH hue 240.
//
// NÃO substitui Index.tsx (V3 atual) — roda em paralelo em /kb/v2 pra Wagner
// aprovar gate visual antes do cutover (ADR 0114).
//
// Backend pendente (Agent A — ONDA 1): rotas /kb/v2, /kb/nodes, /kb/nodes/{slug},
// /kb/paths, /kb/decision-trees. Quando ausentes, página usa MOCK_NODES fallback.

import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import * as React from 'react';
import { toast } from 'sonner';
import {
  Sparkles,
  Search,
  Plus,
  Map as MapIcon,
  Wrench,
  HeartPulse,
  Network,
} from 'lucide-react';

import CategorySidebar from './_components/CategorySidebar';
import NodeList from './_components/NodeList';
import NodeReader from './_components/NodeReader';
import CommandPalette from './_components/CommandPalette';
import PathsDialog from './_components/PathsDialog';
import TroubleshooterDialog from './_components/TroubleshooterDialog';
import HealthPanel from './_components/HealthPanel';

import {
  computeMockKpis,
  computeMockTagsTop,
  deriveSubcategoryId,
  MOCK_CATEGORIES,
  MOCK_NODES,
  MOCK_PATHS,
  MOCK_SUBCATEGORIES,
  MOCK_TROUBLESHOOTERS,
} from './_lib/mockData';
import type {
  KbCategory,
  KbIndexProps,
  KbNode,
} from './_lib/types';
import { findCategoryBySlug, fuzzyMatch, numBR } from './_lib/helpers';
import { useKbFavorites } from './_lib/useKbFavorites';
import { useKbRecent } from './_lib/useKbRecent';
import { useKbKeyboardNav } from './_lib/useKbKeyboardNav';

import '../../../css/kb.css';

type SortOption = 'recent' | 'popular' | 'helpful' | 'outdated';

// ──────────────────────────────────────────────────────────────────
// Página
// ──────────────────────────────────────────────────────────────────

function KbIndexV2(props: KbIndexProps) {
  // ── Resolver props vs mock fallback ─────────────────────────────
  const usingMock = !props.nodes;
  const categories: KbCategory[] = props.categories ?? MOCK_CATEGORIES;
  const subcategories = props.subcategories ?? MOCK_SUBCATEGORIES;
  const paths = props.paths ?? MOCK_PATHS;
  const troubleshooters = MOCK_TROUBLESHOOTERS; // TODO[CL]: adapter pra props.decision_trees real
  const baseNodes: KbNode[] = (props.nodes?.data as KbNode[]) ?? MOCK_NODES;
  const pinnedNodes = props.pinned ?? baseNodes.filter((n) => n.pinned);
  const can = props.can ?? {
    write: true,
    publish_path: true,
    publish_troubleshoot: true,
    ai_ask: true,
    graph_view: true,
    favorite: true,
    comment: true,
  };

  // KPIs e tags top — vêm do server ou são computados
  const kpis = props.kpis ?? computeMockKpis(baseNodes);
  const tagsTop = props.tags_top ?? computeMockTagsTop(baseNodes, 16);

  // ── Estado de UI ────────────────────────────────────────────────
  const filtersInit = props.filters ?? {};
  const [searchInput, setSearchInput] = React.useState(filtersInit.q ?? '');
  const [activeCategorySlug, setActiveCategorySlug] = React.useState<string>(
    filtersInit.category ?? 'all',
  );
  const [activeSubcategorySlug, setActiveSubcategorySlug] = React.useState<
    string | null
  >(filtersInit.subcategory ?? null);
  const [activeTag, setActiveTag] = React.useState<string | null>(
    filtersInit.tag ?? null,
  );
  const [sortBy, setSortBy] = React.useState<SortOption>(
    (filtersInit.sort as SortOption) ?? 'recent',
  );
  const [expandedCats, setExpandedCats] = React.useState<Record<string, boolean>>(
    {},
  );
  const [activeNodeId, setActiveNodeId] = React.useState<number | null>(null);

  const [paletteOpen, setPaletteOpen] = React.useState(false);
  const [pathsOpen, setPathsOpen] = React.useState(false);
  const [troubleOpen, setTroubleOpen] = React.useState(false);
  const [healthOpen, setHealthOpen] = React.useState(false);
  const [aiOpen, setAiOpen] = React.useState(false); // TODO[CL]: ONDA 4 — AI dialog
  const [composerOpen, setComposerOpen] = React.useState(false); // TODO[CL]: ONDA 3 — composer

  // Mobile pane (cats | list | reader)
  const [mobileView, setMobileView] = React.useState<'cats' | 'list' | 'reader'>(
    'list',
  );

  const { favs, isFav, toggleFav } = useKbFavorites();
  const { recent, pushRecent } = useKbRecent();

  const searchInputRef = React.useRef<HTMLInputElement | null>(null);

  // ── Debounce da busca (350ms) — V1 client-side ───────────────────
  // Quando backend Agent A entregar, trocar pra `router.get('/kb/v2', { q })`
  // com `preserveScroll/preserveState/only:['nodes']`.
  const [debouncedQuery, setDebouncedQuery] = React.useState(searchInput);
  React.useEffect(() => {
    const t = setTimeout(() => setDebouncedQuery(searchInput), 350);
    return () => clearTimeout(t);
  }, [searchInput]);

  // ── Filtragem client-side em mock mode ──────────────────────────
  // Quando props.nodes vier, lista já vem filtrada do server. Mantemos
  // filtragem local APENAS pro sort secundário + tag filter (que server
  // pode ainda não suportar).
  const filteredNodes = React.useMemo<KbNode[]>(() => {
    let xs = baseNodes.filter((n) => n.status !== 'deleted');

    if (activeCategorySlug !== 'all') {
      const cat = findCategoryBySlug(categories, activeCategorySlug);
      if (cat) xs = xs.filter((n) => n.category_id === cat.id);
    }

    if (activeSubcategorySlug) {
      // derivação client-side via auto_match (mockData helper)
      xs = xs.filter((n) => {
        const derived = deriveSubcategoryId(n, subcategories);
        if (derived === null) return false;
        const sub = subcategories.find((s) => s.id === derived);
        return sub?.slug === activeSubcategorySlug;
      });
    }

    if (activeTag) {
      xs = xs.filter((n) => (n.tags ?? []).includes(activeTag));
    }

    if (debouncedQuery.trim()) {
      xs = xs.filter((n) => fuzzyMatch(n, debouncedQuery));
    }

    // sort: pinned topo sempre, depois critério
    return [...xs].sort((a, b) => {
      if (a.pinned && !b.pinned) return -1;
      if (b.pinned && !a.pinned) return 1;
      switch (sortBy) {
        case 'popular':
          return b.reads_count - a.reads_count;
        case 'helpful':
          return b.helpful_count - a.helpful_count;
        case 'outdated':
          return b.outdated_votes - a.outdated_votes;
        default:
          return (
            new Date(b.updated_at ?? 0).getTime() -
            new Date(a.updated_at ?? 0).getTime()
          );
      }
    });
  }, [
    baseNodes,
    activeCategorySlug,
    activeSubcategorySlug,
    activeTag,
    debouncedQuery,
    sortBy,
    categories,
    subcategories,
  ]);

  const activeNode = activeNodeId
    ? baseNodes.find((n) => n.id === activeNodeId) ?? null
    : null;

  const activeIdx = activeNode
    ? filteredNodes.findIndex((n) => n.id === activeNode.id)
    : -1;

  const prevNode = activeIdx > 0 ? filteredNodes[activeIdx - 1] : null;
  const nextNode =
    activeIdx >= 0 && activeIdx < filteredNodes.length - 1
      ? filteredNodes[activeIdx + 1]
      : null;

  // ── Cat heading label ──────────────────────────────────────────
  const categoryHeadingLabel =
    activeCategorySlug === 'all'
      ? 'Tudo'
      : findCategoryBySlug(categories, activeCategorySlug)?.label ?? 'Tudo';

  // ── Handlers ─────────────────────────────────────────────────
  const openNode = React.useCallback(
    (id: number) => {
      setActiveNodeId(id);
      pushRecent(id);
      setMobileView('reader');
      // TODO[CL]: ONDA 1 — POST /kb/nodes/{id}/view pra contador real
    },
    [pushRecent],
  );

  const closeNode = React.useCallback(() => {
    setActiveNodeId(null);
  }, []);

  const focusSearchInput = React.useCallback(() => {
    searchInputRef.current?.focus();
    searchInputRef.current?.select();
  }, []);

  const closeAllOverlays = React.useCallback(() => {
    setPaletteOpen(false);
    setPathsOpen(false);
    setTroubleOpen(false);
    setHealthOpen(false);
    setAiOpen(false);
    setComposerOpen(false);
  }, []);

  const pickByRef = React.useCallback(
    (ref: string) => {
      // Match #kb-{slug} pra slug do node
      const matchSlug = baseNodes.find((n) => n.slug === ref || n.slug.endsWith(ref));
      if (matchSlug) {
        openNode(matchSlug.id);
        return;
      }
      // Compat antigo Cowork: #a1 → tenta achar id="kb-a1-*"
      const compat = baseNodes.find((n) => n.slug.startsWith(`kb-${ref}-`));
      if (compat) {
        openNode(compat.id);
        return;
      }
      toast.info(`Referência não encontrada: ${ref}`);
    },
    [baseNodes, openNode],
  );

  const onPickTag = React.useCallback((tag: string | null) => {
    setActiveTag(tag);
    setActiveNodeId(null);
  }, []);

  // ── Keyboard nav ─────────────────────────────────────────────
  useKbKeyboardNav({
    paletteOpen,
    troubleOpen,
    aiOpen,
    pathsOpen,
    healthOpen,
    composerOpen,
    onOpenPalette: () => setPaletteOpen(true),
    onFocusSearch: focusSearchInput,
    onCloseAll: () => {
      if (paletteOpen || troubleOpen || aiOpen || pathsOpen || healthOpen || composerOpen) {
        closeAllOverlays();
      } else if (activeNode) {
        closeNode();
      }
    },
    onNext: () => {
      if (filteredNodes.length === 0) return;
      const i = activeIdx;
      const next = filteredNodes[Math.min(filteredNodes.length - 1, i + 1)];
      if (next) openNode(next.id);
    },
    onPrev: () => {
      if (filteredNodes.length === 0) return;
      const i = activeIdx;
      const prev = filteredNodes[Math.max(0, i === -1 ? 0 : i - 1)];
      if (prev) openNode(prev.id);
    },
    onEnter: () => {
      if (!activeNode && filteredNodes[0]) openNode(filteredNodes[0].id);
    },
    onNewArticle: () => setComposerOpen(true),
    onOpenAI: () => setAiOpen(true),
    onToggleFav: () => {
      if (activeNode) {
        toggleFav(activeNode.id);
        toast.success(
          isFav(activeNode.id)
            ? 'Removido dos favoritos'
            : 'Adicionado aos favoritos',
        );
      }
    },
    canWrite: can.write,
    canAiAsk: can.ai_ask,
    hasActiveNode: !!activeNode,
  });

  // ── Ações com toast (V1 mock; ONDA 1 vira fetch real) ─────────
  const voteHelpful = (id: number) => {
    // TODO[CL]: ONDA 1 — POST /kb/nodes/{id}/vote {kind:'helpful'}
    toast.success('Voto registrado — obrigado!');
  };
  const voteOutdated = (id: number) => {
    // TODO[CL]: ONDA 1 — POST /kb/nodes/{id}/vote {kind:'outdated'}
    toast.success('Marcado como possivelmente desatualizado');
  };
  const reverify = (id: number) => {
    // TODO[CL]: ONDA 1 — POST /kb/nodes/{slug}/reverify (requer kb.write)
    toast.success('Artigo re-verificado e marcado como fresco');
  };
  const attachToOS = (id: number) => {
    // TODO[CL]: ONDA 6 — POST /kb/nodes/{slug}/attach-to-current-os
    toast.success('Artigo anexado à OS ativa');
  };
  const summarizeAI = (id: number) => {
    // TODO[CL]: ONDA 4 — POST /kb/ai/summarize/{slug}
    toast.info('Resumo IA — em breve (ONDA 4)');
  };
  const onPresent = () => {
    // TODO[CL]: ONDA 5 — KBPresenter slides
    toast.info('Modo apresentação — em breve (ONDA 5)');
  };
  const onPrint = () => {
    // TODO[CL]: ONDA 5 — KBPrintSOP modal
    toast.info('Imprimir SOP — em breve (ONDA 5)');
  };
  const onHistory = () => {
    // TODO[CL]: ONDA 3 — GET /kb/nodes/{slug}/versions + restore
    toast.info('Histórico de versões — em breve (ONDA 3)');
  };
  const onEdit = () => {
    // TODO[CL]: ONDA 3 — composer
    setComposerOpen(true);
    toast.info('Composer — em breve (ONDA 3)');
  };

  // ──────────────────────────────────────────────────────────────
  return (
    <div className="flex flex-col gap-3 px-4 py-3 min-h-0 h-[calc(100vh-3.5rem)]">
      <PageHeader
        icon="book-open"
        title="Procedimentos Operacionais Padrão"
        description={
          usingMock
            ? `${numBR(baseNodes.length)} SOPs · ${numBR(kpis.total_reads)} leituras · ${numBR(kpis.total_os_linked)} OS vinculadas · MOCK (Agent A pendente)`
            : `${numBR(kpis.total)} SOPs · ${numBR(kpis.total_reads)} leituras · ${numBR(kpis.total_os_linked)} OS vinculadas`
        }
        action={
          <div className="flex items-center gap-1.5 flex-wrap">
            <Button
              variant="ghost"
              size="sm"
              className="h-8 text-xs"
              onClick={() => setPathsOpen(true)}
            >
              <MapIcon size={13} className="mr-1.5" />
              Trilhas
            </Button>
            {can.ai_ask && (
              <Button
                variant="ghost"
                size="sm"
                className="h-8 text-xs text-primary"
                onClick={() => setAiOpen(true)}
              >
                <Sparkles size={13} className="mr-1.5" />
                Perguntar ao KB
              </Button>
            )}
            <Button
              variant="ghost"
              size="sm"
              className="h-8 text-xs"
              onClick={() => setHealthOpen(true)}
            >
              <HeartPulse size={13} className="mr-1.5" />
              Dashboard
            </Button>
            <Button
              variant="ghost"
              size="sm"
              className="h-8 text-xs"
              onClick={() => setTroubleOpen(true)}
            >
              <Wrench size={13} className="mr-1.5" />
              Troubleshooter
            </Button>
            {can.graph_view && (
              <Button
                variant="ghost"
                size="sm"
                className="h-8 text-xs"
                onClick={() => {
                  // TODO[CL]: ONDA 5 — router.visit('/kb/graph')
                  toast.info('Visualização-grafo — ONDA 5');
                }}
              >
                <Network size={13} className="mr-1.5" />
                Grafo
              </Button>
            )}
            <Button
              variant="ghost"
              size="sm"
              className="h-8 text-xs"
              onClick={() => setPaletteOpen(true)}
            >
              <kbd className="kb-kbd mr-1.5">⌘K</kbd>
              Buscar
            </Button>
            {can.write && (
              <Button
                variant="default"
                size="sm"
                className="h-8 text-xs"
                onClick={() => setComposerOpen(true)}
              >
                <Plus size={13} className="mr-1" />
                Novo SOP
              </Button>
            )}
          </div>
        }
      />

      {/* KPIs movidos pro modal "Dashboard" (botão na header acima).
          Wagner 2026-05-17: cards no topo ocupavam ~150px sem ROI suficiente;
          Mais lido + Recentemente atualizados + Precisam revisão ja sao
          cobertos pelos 4 quadrantes do HealthPanel; Pinados é menor. */}

      {/* Search bar (always-on, separado do command palette ⌘K) */}
      <div className="relative">
        <Search
          size={14}
          className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none"
          aria-hidden
        />
        <Input
          ref={searchInputRef}
          placeholder="Filtrar SOPs por título, etiqueta ou autor (/, debounce 350ms)..."
          value={searchInput}
          onChange={(e) => setSearchInput(e.target.value)}
          className="h-9 pl-9 text-sm"
          aria-label="Buscar SOPs"
        />
        {searchInput && (
          <button
            type="button"
            onClick={() => setSearchInput('')}
            className="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground text-sm"
            title="Limpar busca"
            aria-label="Limpar busca"
          >
            ×
          </button>
        )}
      </div>

      {/* Tri-pane */}
      <div
        className="kb-tri rounded-md border border-border overflow-hidden flex-1 min-h-0"
        data-mobile-view={mobileView}
      >
        <CategorySidebar
          categories={categories}
          subcategories={subcategories}
          nodes={baseNodes}
          activeCategorySlug={activeCategorySlug}
          activeSubcategorySlug={activeSubcategorySlug}
          expandedCats={expandedCats}
          activeTag={activeTag}
          favorites={favs}
          recent={recent}
          onPickCategory={(slug) => {
            setActiveCategorySlug(slug);
            setActiveNodeId(null);
          }}
          onPickSubcategory={setActiveSubcategorySlug}
          onToggleExpand={(slug) =>
            setExpandedCats((prev) => ({ ...prev, [slug]: !prev[slug] }))
          }
          onPickTag={onPickTag}
          onPickNode={openNode}
          tagsTop={tagsTop}
        />

        <NodeList
          nodes={filteredNodes}
          categories={categories}
          activeNodeId={activeNodeId}
          onPickNode={openNode}
          sortBy={sortBy}
          onChangeSort={setSortBy}
          activeTag={activeTag}
          onClearTag={() => setActiveTag(null)}
          onClearAllFilters={() => {
            setActiveCategorySlug('all');
            setActiveSubcategorySlug(null);
            setActiveTag(null);
            setSearchInput('');
          }}
          categoryHeadingLabel={categoryHeadingLabel}
        />

        <NodeReader
          node={activeNode}
          allNodes={baseNodes}
          categories={categories}
          prevNode={prevNode}
          nextNode={nextNode}
          isFavorite={activeNode ? isFav(activeNode.id) : false}
          onClose={closeNode}
          onPrev={() => prevNode && openNode(prevNode.id)}
          onNext={() => nextNode && openNode(nextNode.id)}
          onToggleFav={() => activeNode && toggleFav(activeNode.id)}
          onVoteHelpful={() => activeNode && voteHelpful(activeNode.id)}
          onVoteOutdated={() => activeNode && voteOutdated(activeNode.id)}
          onReverify={() => activeNode && reverify(activeNode.id)}
          onAttachToOS={() => activeNode && attachToOS(activeNode.id)}
          onSummarizeAI={() => activeNode && summarizeAI(activeNode.id)}
          onPresent={onPresent}
          onPrint={onPrint}
          onHistory={onHistory}
          onEdit={onEdit}
          onPickRelated={openNode}
          onPickByRef={pickByRef}
          onPickTag={onPickTag}
          pinned={pinnedNodes}
          canWrite={can.write}
          canAiAsk={can.ai_ask}
        />
      </div>

      {/* Overlays */}
      <CommandPalette
        open={paletteOpen}
        onOpenChange={setPaletteOpen}
        nodes={baseNodes}
        categories={categories}
        onPickNode={openNode}
        onAskAI={
          can.ai_ask
            ? (q) => {
                // TODO[CL]: ONDA 4 — passar query pra AI Dialog
                setAiOpen(true);
                toast.info(`Perguntando à IA: "${q}" (ONDA 4)`);
              }
            : undefined
        }
      />

      <PathsDialog
        open={pathsOpen}
        onOpenChange={setPathsOpen}
        paths={paths}
        nodes={baseNodes}
        onPickNode={openNode}
      />

      <TroubleshooterDialog
        open={troubleOpen}
        onOpenChange={setTroubleOpen}
        troubleshooters={troubleshooters}
        onPickByRef={pickByRef}
        onAskAI={can.ai_ask ? () => setAiOpen(true) : undefined}
      />

      <HealthPanel
        open={healthOpen}
        onOpenChange={setHealthOpen}
        nodes={baseNodes}
        onPickNode={openNode}
      />

      {/* TODO[CL]: ONDA 4 — AI Dialog (Perguntar ao KB com citações) */}
      {/* TODO[CL]: ONDA 3 — Composer modal */}
    </div>
  );
}

KbIndexV2.layout = (page: React.ReactNode) => (
  <AppShellV2 title="SOPs" breadcrumbItems={[{ label: 'Conhecimento' }, { label: 'SOPs' }]}>
    {page}
  </AppShellV2>
);

export default KbIndexV2;

// Tipos consumíveis por Agent A:
//   - import type { KbIndexProps, KbNode, Paginator, KbCategory, KbFilters }
//     from '@/Pages/kb/_lib/types'
// Mock data exposto via:
//   - import { MOCK_NODES, MOCK_CATEGORIES, MOCK_PATHS, MOCK_TROUBLESHOOTERS }
//     from '@/Pages/kb/_lib/mockData'
