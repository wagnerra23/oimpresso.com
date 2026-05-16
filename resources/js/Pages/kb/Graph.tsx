/**
 * KB — Visualização Grafo (`/kb/graph`)
 * =====================================
 *
 * Tela coração do KB Unificado (ADR 0149 ONDA 5):
 * "ver meus dados e arquivos mais importantes" — Wagner, 2026-05-15.
 *
 * ─── Decisão de lib (Agent E, 2026-05-15) ───────────────────────────────────
 *
 * Lib escolhida: **Reactflow 11.11.4** (xyflow).
 *
 * Justificativa:
 *   1. JÁ INSTALADA no package.json (`reactflow@^11.11.4`) — zero npm install.
 *   2. JÁ USADA em `resources/js/Pages/ads/Admin/Graph.tsx` como precedent arquitetural
 *      (formato nodes/edges idêntico ao endpoint /kb/graph/data — SCHEMA-DB-V1 §11).
 *   3. Ergonomia React (componentes, hooks, TS types) imbatível pra time MCP.
 *   4. Manutenção ativa (xyflow lançou v12 em 2025; v11 estável produção).
 *   5. Performance suficiente até ~5k nodes / 20k edges com `nodesDraggable=true`
 *      (validado em benchmarks da própria lib — https://reactflow.dev/learn/troubleshooting/performance).
 *   6. Bundle: reactflow + reactflow/dist/style.css = ~150KB minified+gzipped — abaixo do limite 500KB.
 *
 * Alternativas avaliadas e rejeitadas:
 *   - **Cytoscape.js + cytoscape-react** — clássico de grafo científico, ~10k+ nodes ok,
 *     mas wrapper React menos polido, layouts requerem libs externas separadas (cose-bilkent),
 *     bundle maior (~280KB cytoscape + ~80KB cose-bilkent).
 *   - **D3 force-directed** — flexível mas reinventa wheel (zoom, pan, fit-view, mini-map,
 *     controls — tudo manual). Perda de tempo desnecessária.
 *   - **Sigma.js** — performance >1k nodes excelente (WebGL), mas API low-level e
 *     wrapper React (`react-sigma`) defasado em 2025.
 *   - **G6 (AntV)** — completo mas doc parcial em inglês, time MCP brasileiro perde tempo.
 *   - **xyflow v12** — major bump 2025, breaking changes; v11 mais estável agora.
 *
 * Critério decisivo: **lib já instalada + precedent existente** > qualquer ganho marginal.
 *
 * **TODO[CL]: instalar `@dagrejs/dagre@^1.1.4`** quando ativar modo `dagre-tb` real
 * (hoje cai em `concentric` — graphLayout.ts §layoutDagre).
 *
 * Performance esperada com payload real biz=1 Wagner (~700 nodes + ~3000 edges):
 *   - First render: <800ms (Inertia::defer carrega payload async)
 *   - Layout client-side: <100ms (concentric/force-radial)
 *   - Click→detail: <50ms (estado local React, sem fetch)
 *   - Double-click→focus subgraph: <150ms (BFS depth=2 cap, re-layout)
 *
 * ─── Restrições Tier 0 (Index.charter.md §"Restrições Tier 0 IRREVOGÁVEIS") ──
 *
 * - `business_id` global scope no Controller (ADR 0093)
 * - `Inertia::defer()` em props caras (RUNBOOK-inertia-defer-pattern)
 * - F3 MWART canônico 5 fases (ADR 0104) — esqueleto = F1; F2 BACKEND BASELINE
 *   ainda não existe (Agent A vai criar KbGraphController em ONDA 5 backend)
 * - Pest tests biz=1 + cross-tenant biz=99 (ADR 0101) — pendente Agent C
 *
 * Agent E (ONDA 5) — 2026-05-15
 */

import * as React from 'react';
import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import KpiGrid from '@/Components/shared/KpiGrid';
import KpiCard from '@/Components/shared/KpiCard';
import { Button } from '@/Components/ui/button';
import { Search } from 'lucide-react';
import GraphCanvas from './_components/GraphCanvas';
import GraphFilters from './_components/GraphFilters';
import GraphLegend from './_components/GraphLegend';
import GraphNodeDetail from './_components/GraphNodeDetail';
import {
  layoutGraph, buildRFEdges, focusSubgraph,
} from './_lib/graphLayout';
import {
  ALL_NODE_TYPES, ALL_EDGE_TYPES,
  type KbGraphPageProps, type GraphFilterState, type KbGraphEdge,
} from './_lib/graphTypes';
import { MOCK_NODES, MOCK_EDGES, MOCK_KPIS } from './_lib/mockGraphData';

const num = (v: number) => new Intl.NumberFormat('pt-BR').format(v ?? 0);

// ─── Props com fallback pra mock data ────────────────────────────────────────
type Props = Partial<KbGraphPageProps>;

const Graph: React.FC<Props> & { layout?: (p: ReactNode) => ReactNode } = (props) => {
  // Fallback pra mock data quando Controller ainda não existir (Agent A — ONDA 5 backend)
  // TODO[CL]: remover fallback quando KbGraphController estiver em produção
  const nodes = props.nodes ?? MOCK_NODES;
  const edges = props.edges ?? MOCK_EDGES;
  const kpis = props.kpis ?? MOCK_KPIS;
  const isUsingMock = !props.nodes;

  // ─── Estado dos filtros ────────────────────────────────────────────────────
  const [filters, setFilters] = React.useState<GraphFilterState>(() => ({
    visibleNodeTypes: new Set(ALL_NODE_TYPES),
    visibleEdgeTypes: new Set(ALL_EDGE_TYPES),
    query: props.filters?.q ?? '',
    focusNodeId: props.filters?.focus_id ?? null,
    depth: props.filters?.depth ?? 2,
    layoutMode: 'force-radial',
  }));

  const [selectedNodeId, setSelectedNodeId] = React.useState<string | null>(null);

  const handleFilterChange = React.useCallback((next: Partial<GraphFilterState>) => {
    setFilters(prev => ({ ...prev, ...next }));
  }, []);

  // ─── Filtragem ─────────────────────────────────────────────────────────────
  const filtered = React.useMemo(() => {
    // 1. Filtro por tipo de nó
    let filteredNodes = nodes.filter(n => filters.visibleNodeTypes.has(n.type));

    // 2. Filtro por busca textual
    if (filters.query.trim().length > 0) {
      const q = filters.query.toLowerCase();
      filteredNodes = filteredNodes.filter(n => {
        const haystack = [
          n.data.label,
          n.data.slug,
          ...(n.data.tags ?? []),
          n.data.module ?? '',
          n.data.excerpt ?? '',
        ].join(' ').toLowerCase();
        return haystack.includes(q);
      });
    }

    // 3. Filtro por tipo de aresta
    const visibleNodeIds = new Set(filteredNodes.map(n => n.id));
    let filteredEdges = edges.filter(
      e => filters.visibleEdgeTypes.has(e.edge_type)
        && visibleNodeIds.has(e.source)
        && visibleNodeIds.has(e.target),
    );

    // 4. Focus subgraph (se houver focus)
    if (filters.focusNodeId) {
      const sub = focusSubgraph(filteredNodes, filteredEdges, filters.focusNodeId, filters.depth);
      filteredNodes = sub.nodes;
      filteredEdges = sub.edges;
    }

    return { nodes: filteredNodes, edges: filteredEdges };
  }, [nodes, edges, filters]);

  // ─── Re-layout ─────────────────────────────────────────────────────────────
  const rfNodes = React.useMemo(
    () => layoutGraph(filtered.nodes, filtered.edges, filters.layoutMode, filters.focusNodeId),
    [filtered.nodes, filtered.edges, filters.layoutMode, filters.focusNodeId],
  );
  const rfEdges = React.useMemo(() => buildRFEdges(filtered.edges), [filtered.edges]);

  // ─── Node selecionado (pra GraphNodeDetail) ─────────────────────────────────
  const selectedNode = React.useMemo(
    () => filtered.nodes.find(n => n.id === selectedNodeId) ?? null,
    [filtered.nodes, selectedNodeId],
  );

  const focusNodeLabel = React.useMemo(() => {
    if (!filters.focusNodeId) return null;
    return nodes.find(n => n.id === filters.focusNodeId)?.data.label ?? null;
  }, [nodes, filters.focusNodeId]);

  const edgesForSelected = React.useMemo(() => {
    if (!selectedNode) return { in: [] as KbGraphEdge[], out: [] as KbGraphEdge[] };
    return {
      in: edges.filter(e => e.target === selectedNode.id),
      out: edges.filter(e => e.source === selectedNode.id),
    };
  }, [edges, selectedNode]);

  // ─── Callbacks do canvas ───────────────────────────────────────────────────
  const handleNodeClick = React.useCallback((nodeId: string) => {
    setSelectedNodeId(nodeId);
  }, []);

  const handleNodeDoubleClick = React.useCallback((nodeId: string) => {
    setFilters(prev => ({
      ...prev,
      focusNodeId: nodeId,
      layoutMode: 'dagre-tb',  // double-click → modo hierárquico
    }));
    setSelectedNodeId(nodeId);
  }, []);

  const handlePaneClick = React.useCallback(() => {
    setSelectedNodeId(null);
  }, []);

  // Trigger "Focar aqui" do GraphNodeDetail
  const handleFocusFromDetail = React.useCallback((nodeId: string) => {
    setFilters(prev => ({ ...prev, focusNodeId: nodeId, layoutMode: 'dagre-tb' }));
  }, []);

  // Trigger navegação entre nodes via lista de edges no detail panel
  const handleNavigateToNode = React.useCallback((nodeId: string) => {
    setSelectedNodeId(nodeId);
    // Não muda focusNodeId — apenas troca o detalhe lateral
  }, []);

  // ─── Keyboard shortcut: / pra focar busca, Esc pra limpar focus ────────────
  React.useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      const target = e.target as HTMLElement;
      const isTyping = target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.isContentEditable;
      if (e.key === '/' && !isTyping) {
        e.preventDefault();
        const searchInput = document.getElementById('graph-search') as HTMLInputElement | null;
        searchInput?.focus();
      }
      if (e.key === 'Escape' && filters.focusNodeId) {
        setFilters(prev => ({ ...prev, focusNodeId: null }));
        setSelectedNodeId(null);
      }
    };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, [filters.focusNodeId]);

  // ─── Render ────────────────────────────────────────────────────────────────
  return (
    <div className="flex flex-col h-[calc(100vh-4rem)] overflow-hidden">
      <Head title="KB — Grafo de Conhecimento" />

      {/* Header + KPIs */}
      <div className="px-4 pt-3 pb-2 border-b border-border space-y-2 shrink-0">
        <PageHeader
          icon="git-branch"
          title="KB — Grafo de Conhecimento"
          description="Visualize as conexões entre ADRs, sessões, charters, runbooks e briefings. Clique num nó pra ver detalhe; clique duplo pra focar a árvore. Pressione / pra buscar."
          action={
            isUsingMock && (
              <span className="text-[10px] uppercase tracking-wide px-2 py-1 rounded-sm bg-amber-100 text-amber-800 border border-amber-200">
                modo mock
              </span>
            )
          }
        />

        <KpiGrid cols={4}>
          <KpiCard
            icon="file-text"
            tone="info"
            label="Nós no grafo"
            value={num(kpis.total_nodes)}
            description="ADRs + sessions + charters + runbooks + briefings + specs"
          />
          <KpiCard
            icon="git-branch"
            tone="default"
            label="Conexões"
            value={num(kpis.total_edges)}
            description="supersedes + charter-of + cross-link + related-by-tag"
          />
          <KpiCard
            icon="alert-triangle"
            tone={kpis.outdated_count > 0 ? 'warning' : 'success'}
            label="Desatualizados"
            value={num(kpis.outdated_count)}
            description="Status=outdated (precisam revisão)"
          />
          <KpiCard
            icon="clock"
            tone="default"
            label="Último bridge"
            value={kpis.last_bridge_at ? new Date(kpis.last_bridge_at).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) : '—'}
            description="KbBridgeFromMcpJob (cron 15min)"
          />
        </KpiGrid>

        <GraphLegend
          visibleNodeTypes={filters.visibleNodeTypes}
          visibleEdgeTypes={filters.visibleEdgeTypes}
        />
      </div>

      {/* Tri-pane: filters | canvas | detail */}
      <div className="flex flex-1 overflow-hidden">
        <GraphFilters
          filters={filters}
          onChange={handleFilterChange}
          focusNodeLabel={focusNodeLabel}
          totalNodes={nodes.length}
          visibleNodes={filtered.nodes.length}
        />

        <div className="flex-1 relative overflow-hidden">
          {filtered.nodes.length === 0 ? (
            <EmptyState onReset={() => handleFilterChange({
              visibleNodeTypes: new Set(ALL_NODE_TYPES),
              visibleEdgeTypes: new Set(ALL_EDGE_TYPES),
              query: '',
              focusNodeId: null,
            })} />
          ) : (
            <GraphCanvas
              rfNodes={rfNodes}
              rfEdges={rfEdges}
              onNodeClick={handleNodeClick}
              onNodeDoubleClick={handleNodeDoubleClick}
              onPaneClick={handlePaneClick}
            />
          )}
        </div>

        {selectedNode && (
          <GraphNodeDetail
            node={selectedNode}
            allNodes={nodes}
            edgesIn={edgesForSelected.in}
            edgesOut={edgesForSelected.out}
            onFocus={handleFocusFromDetail}
            onNavigate={handleNavigateToNode}
            onClose={() => setSelectedNodeId(null)}
          />
        )}
      </div>
    </div>
  );
};

// ─── Empty state ─────────────────────────────────────────────────────────────
function EmptyState({ onReset }: { onReset: () => void }) {
  return (
    <div className="flex flex-col items-center justify-center h-full text-center p-6 gap-3">
      <div className="rounded-full bg-muted/30 p-4">
        <Search className="w-8 h-8 text-muted-foreground" />
      </div>
      <h2 className="text-lg font-semibold">Nenhum nó visível nesta combinação</h2>
      <p className="text-sm text-muted-foreground max-w-md">
        Os filtros aplicados não retornaram resultados. Tente limpar a busca,
        habilitar mais tipos de nó/aresta, ou remover o foco.
      </p>
      <Button variant="default" size="sm" onClick={onReset}>
        Limpar todos os filtros
      </Button>
    </div>
  );
}

Graph.layout = (page: ReactNode) => (
  <AppShellV2
    title="KB — Grafo"
    breadcrumbItems={[{ label: 'KB', href: '/kb' }, { label: 'Grafo' }]}
  >
    {page}
  </AppShellV2>
);

export default Graph;
