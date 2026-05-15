/**
 * KB Graph — layout helpers
 * ==========================
 *
 * Implementa 4 modos de layout client-side (Reactflow não traz layout engine, é só viewer):
 *
 *   - `dagre-tb`     — top-bottom hierárquico (dagre). Default no FOCUS MODE.
 *   - `dagre-lr`     — left-right hierárquico (dagre).
 *   - `force-radial` — força radial em volta do focus node. Default na visão geral.
 *   - `concentric`   — círculos concêntricos por tipo (igual ADS Graph.tsx). Fallback simples.
 *
 * **TODO[CL]: Instalar `@dagrejs/dagre@^1.1.4` quando ativarmos dagre-tb/dagre-lr de fato.**
 * Por ora `dagre-tb` cai em `concentric` se a lib não estiver presente.
 *
 * Performance esperada (até 5k nodes):
 *   - concentric: O(n) — instantâneo
 *   - force-radial: O(n) — ~10ms até 1k
 *   - dagre: O(n + m log n) — ~50ms até 1k, ~300ms até 5k
 *
 * Agent E (ONDA 5) — 2026-05-15
 */

import type { Node as RFNode, Edge as RFEdge } from 'reactflow';
import type { KbGraphNode, KbGraphEdge, GraphLayoutMode } from './graphTypes';

// ─── Constants ───────────────────────────────────────────────────────────────
const NODE_WIDTH = 200;
const NODE_HEIGHT = 60;
const RADIAL_BASE = 200;
const RADIAL_RING_STEP = 120;

// ─── Tokens de cor por tipo (OKLCH hue) ──────────────────────────────────────
// Referência: Index.charter.md §"Restrições design" + BRIEFING.md §"hue 240 PROJETOS pra KB".
// Cada tipo tem hue distinto pra discriminação rápida em viz com 50+ nodes.
//
// Hue convention:
//   article=240 (azul-roxo accent)
//   adr=60 (amarelo-mostarda governança)
//   session=145 (verde evento histórico)
//   charter=200 (cyan manifesto)
//   runbook=30 (laranja operacional)
//   briefing=280 (roxo executivo)
//   spec=250 (azul-cinza)
//   data (os/customer/nfe/equipment)=100 (verde-cinza ERP)
//   external_file=320 (magenta)
//   reference/comparativo=neutral
//
// Cores são derivadas de OKLCH(L, C, h):
//   - L=0.92 background suave (fundo do node)
//   - L=0.40 stroke (borda + texto)
//   - C=0.10 (saturação confortável)
export interface NodeColorTokens {
  bg: string;        // background CSS color
  stroke: string;    // border + text CSS color
  hue: number;       // 0-360
  label: string;     // pt-BR
}

export const NODE_COLORS: Record<string, NodeColorTokens> = {
  article:       { hue: 240, bg: 'oklch(0.96 0.04 240)', stroke: 'oklch(0.40 0.10 240)', label: 'Artigo' },
  adr:           { hue: 60,  bg: 'oklch(0.96 0.05 60)',  stroke: 'oklch(0.40 0.12 60)',  label: 'ADR' },
  session:       { hue: 145, bg: 'oklch(0.96 0.04 145)', stroke: 'oklch(0.40 0.10 145)', label: 'Session' },
  charter:       { hue: 200, bg: 'oklch(0.96 0.04 200)', stroke: 'oklch(0.40 0.10 200)', label: 'Charter' },
  runbook:       { hue: 30,  bg: 'oklch(0.96 0.05 30)',  stroke: 'oklch(0.40 0.12 30)',  label: 'Runbook' },
  briefing:      { hue: 280, bg: 'oklch(0.96 0.04 280)', stroke: 'oklch(0.40 0.10 280)', label: 'Briefing' },
  spec:          { hue: 250, bg: 'oklch(0.96 0.03 250)', stroke: 'oklch(0.40 0.08 250)', label: 'Spec' },
  comparativo:   { hue: 320, bg: 'oklch(0.96 0.04 320)', stroke: 'oklch(0.40 0.10 320)', label: 'Comparativo' },
  reference:     { hue: 0,   bg: 'oklch(0.94 0.00 0)',   stroke: 'oklch(0.40 0.00 0)',   label: 'Referência' },
  os:            { hue: 100, bg: 'oklch(0.96 0.04 100)', stroke: 'oklch(0.40 0.10 100)', label: 'OS' },
  customer:      { hue: 100, bg: 'oklch(0.96 0.04 100)', stroke: 'oklch(0.40 0.10 100)', label: 'Cliente' },
  product:       { hue: 100, bg: 'oklch(0.96 0.04 100)', stroke: 'oklch(0.40 0.10 100)', label: 'Produto' },
  nfe:           { hue: 100, bg: 'oklch(0.96 0.04 100)', stroke: 'oklch(0.40 0.10 100)', label: 'NFe' },
  equipment:     { hue: 100, bg: 'oklch(0.96 0.04 100)', stroke: 'oklch(0.40 0.10 100)', label: 'Equipamento' },
  external_file: { hue: 320, bg: 'oklch(0.96 0.04 320)', stroke: 'oklch(0.40 0.10 320)', label: 'Arquivo' },
};

// ─── Tokens de aresta (cor + traço) ──────────────────────────────────────────
export interface EdgeStyleTokens {
  stroke: string;
  strokeDasharray?: string;
  opacity: number;
  label: string;
}

export const EDGE_STYLES: Record<string, EdgeStyleTokens> = {
  'next-in-path':    { stroke: 'oklch(0.45 0.15 240)', opacity: 0.9, label: 'Próximo na trilha' },
  'fix-of-decision': { stroke: 'oklch(0.45 0.15 60)',  opacity: 0.9, label: 'Fix de decisão' },
  'supersedes':      { stroke: 'oklch(0.50 0.18 25)',  strokeDasharray: '6 3', opacity: 0.85, label: 'Substitui' },
  'charter-of':      { stroke: 'oklch(0.45 0.13 200)', opacity: 0.85, label: 'Charter de' },
  'references-data': { stroke: 'oklch(0.45 0.10 100)', strokeDasharray: '2 2', opacity: 0.7, label: 'Cita dado' },
  'ai-related':      { stroke: 'oklch(0.50 0.10 240)', strokeDasharray: '4 4', opacity: 0.5, label: 'IA-relacionado' },
  'cross-link':      { stroke: 'oklch(0.45 0.13 240)', opacity: 0.7, label: 'Cross-link' },
  'related-by-tag':  { stroke: 'oklch(0.55 0.08 240)', strokeDasharray: '1 3', opacity: 0.45, label: 'Mesma tag' },
};

// ─── Layout: concentric (fallback simples) ───────────────────────────────────
/**
 * Círculos concêntricos por tipo. Determinístico, instantâneo.
 * Adaptado do ADS Graph.tsx (precedent arquitetural).
 */
export function layoutConcentric(nodes: KbGraphNode[]): RFNode[] {
  // agrupa por tipo
  const groups: Record<string, KbGraphNode[]> = {};
  for (const n of nodes) {
    (groups[n.type] ??= []).push(n);
  }

  const cx = 600;
  const cy = 400;

  // ordem dos anéis: ADR no centro, então charter, então rest
  const ringOrder: string[] = [
    'adr', 'charter', 'briefing', 'spec', 'runbook', 'session',
    'article', 'comparativo', 'reference',
    'os', 'customer', 'product', 'nfe', 'equipment', 'external_file',
  ];

  const positions: RFNode[] = [];
  let ringIdx = 0;

  for (const type of ringOrder) {
    const group = groups[type];
    if (!group || group.length === 0) continue;

    const radius = ringIdx === 0 ? 0 : RADIAL_BASE + (ringIdx - 1) * RADIAL_RING_STEP;

    group.forEach((n, i) => {
      const angle = group.length > 1 ? (i / group.length) * 2 * Math.PI : 0;
      const x = ringIdx === 0
        ? cx + (i - (group.length - 1) / 2) * (NODE_WIDTH + 30)
        : cx + Math.cos(angle) * radius;
      const y = ringIdx === 0
        ? cy
        : cy + Math.sin(angle) * radius;

      positions.push(buildRFNode(n, { x, y }));
    });

    ringIdx++;
  }

  return positions;
}

// ─── Layout: force-radial em torno do focus ──────────────────────────────────
/**
 * Force-directed radial em torno de um focus node.
 * Sem lib externa — algoritmo simples baseado em anéis por hop-distance.
 */
export function layoutForceRadial(
  nodes: KbGraphNode[],
  edges: KbGraphEdge[],
  focusId: string | null,
): RFNode[] {
  if (!focusId) {
    return layoutConcentric(nodes);
  }

  // BFS pra calcular hop-distance do focus
  const adj = buildAdjacency(edges);
  const dist = new Map<string, number>();
  dist.set(focusId, 0);
  const queue: string[] = [focusId];
  while (queue.length > 0) {
    const cur = queue.shift()!;
    const curDist = dist.get(cur)!;
    for (const nb of adj.get(cur) ?? []) {
      if (!dist.has(nb)) {
        dist.set(nb, curDist + 1);
        queue.push(nb);
      }
    }
  }

  // Agrupa por hop
  const rings: KbGraphNode[][] = [];
  for (const n of nodes) {
    const d = dist.get(n.id);
    if (d === undefined) continue; // disconnected
    (rings[d] ??= []).push(n);
  }
  // Nodes desconectados ficam no último anel
  const disconnected = nodes.filter(n => !dist.has(n.id));
  if (disconnected.length > 0) rings.push(disconnected);

  const cx = 600;
  const cy = 400;
  const positions: RFNode[] = [];

  rings.forEach((ring, ringIdx) => {
    const radius = ringIdx === 0 ? 0 : RADIAL_BASE + (ringIdx - 1) * RADIAL_RING_STEP;
    ring.forEach((n, i) => {
      const angle = ring.length > 1 ? (i / ring.length) * 2 * Math.PI : 0;
      const x = ringIdx === 0 ? cx : cx + Math.cos(angle) * radius;
      const y = ringIdx === 0 ? cy : cy + Math.sin(angle) * radius;
      positions.push(buildRFNode(n, { x, y }, n.id === focusId));
    });
  });

  return positions;
}

// ─── Layout: dagre-tb (top-bottom hierárquico) ───────────────────────────────
/**
 * Dagre top-bottom — ideal pra mostrar árvore supersedes/charter-of (focus em ADR).
 * **TODO[CL]: instalar `@dagrejs/dagre@^1.1.4` pra ativar.**
 * Por ora cai em concentric.
 */
export function layoutDagre(
  nodes: KbGraphNode[],
  _edges: KbGraphEdge[],
  _direction: 'TB' | 'LR' = 'TB',
): RFNode[] {
  // TODO[CL]: substituir por chamada real ao dagre quando lib instalada:
  //
  //   import dagre from '@dagrejs/dagre';
  //   const g = new dagre.graphlib.Graph();
  //   g.setGraph({ rankdir: direction, ranksep: 100, nodesep: 40 });
  //   g.setDefaultEdgeLabel(() => ({}));
  //   for (const n of nodes) g.setNode(n.id, { width: NODE_WIDTH, height: NODE_HEIGHT });
  //   for (const e of edges) g.setEdge(e.source, e.target);
  //   dagre.layout(g);
  //   return nodes.map(n => {
  //     const pos = g.node(n.id);
  //     return buildRFNode(n, { x: pos.x - NODE_WIDTH / 2, y: pos.y - NODE_HEIGHT / 2 });
  //   });

  return layoutConcentric(nodes); // fallback
}

// ─── Public entry ────────────────────────────────────────────────────────────
export function layoutGraph(
  nodes: KbGraphNode[],
  edges: KbGraphEdge[],
  mode: GraphLayoutMode,
  focusId: string | null,
): RFNode[] {
  switch (mode) {
    case 'dagre-tb':    return layoutDagre(nodes, edges, 'TB');
    case 'dagre-lr':    return layoutDagre(nodes, edges, 'LR');
    case 'force-radial': return layoutForceRadial(nodes, edges, focusId);
    case 'concentric':
    default:             return layoutConcentric(nodes);
  }
}

// ─── Edges → RF Edges ────────────────────────────────────────────────────────
export function buildRFEdges(edges: KbGraphEdge[]): RFEdge[] {
  return edges.map(e => {
    const tokens = EDGE_STYLES[e.edge_type] ?? EDGE_STYLES['cross-link'];
    return {
      id: e.id,
      source: e.source,
      target: e.target,
      type: 'default',
      animated: e.edge_type === 'next-in-path',
      style: {
        stroke: tokens.stroke,
        strokeWidth: 1.5,
        strokeDasharray: tokens.strokeDasharray,
        opacity: tokens.opacity,
      },
      data: { edge_type: e.edge_type, weight: e.weight, generated_by: e.generated_by },
    };
  });
}

// ─── Helpers internos ────────────────────────────────────────────────────────
function buildAdjacency(edges: KbGraphEdge[]): Map<string, string[]> {
  const adj = new Map<string, string[]>();
  const get = (id: string): string[] => {
    let list = adj.get(id);
    if (!list) {
      list = [];
      adj.set(id, list);
    }
    return list;
  };
  for (const e of edges) {
    get(e.source).push(e.target);
    get(e.target).push(e.source);
  }
  return adj;
}

function buildRFNode(n: KbGraphNode, position: { x: number; y: number }, isFocus = false): RFNode {
  const colors = NODE_COLORS[n.type] ?? NODE_COLORS.reference;
  return {
    id: n.id,
    type: 'default',
    position,
    data: { kbNode: n, isFocus },
    style: {
      background: colors.bg,
      border: `${isFocus ? '2.5px' : '1px'} solid ${colors.stroke}`,
      borderRadius: 4,            // rounded-sm (charter §"Anti-padrões" — sem rounded-xl+)
      padding: '6px 10px',
      width: NODE_WIDTH,
      minHeight: NODE_HEIGHT,
      fontSize: 12,
      fontFamily: 'IBM Plex Sans, system-ui, sans-serif',
      color: colors.stroke,
      boxShadow: isFocus ? `0 0 0 3px ${colors.stroke}33` : 'none',
    },
  };
}

// ─── Filtrar grafo por depth a partir do focus ───────────────────────────────
/**
 * Retorna sub-grafo com nodes até `depth` hops do `focusId`.
 * Se `focusId=null`, retorna grafo inteiro.
 */
export function focusSubgraph(
  nodes: KbGraphNode[],
  edges: KbGraphEdge[],
  focusId: string | null,
  depth: number,
): { nodes: KbGraphNode[]; edges: KbGraphEdge[] } {
  if (!focusId || depth <= 0) return { nodes, edges };

  const adj = buildAdjacency(edges);
  const visited = new Set<string>([focusId]);
  let frontier = [focusId];
  for (let d = 0; d < depth; d++) {
    const next: string[] = [];
    for (const cur of frontier) {
      for (const nb of adj.get(cur) ?? []) {
        if (!visited.has(nb)) {
          visited.add(nb);
          next.push(nb);
        }
      }
    }
    frontier = next;
  }

  const filteredNodes = nodes.filter(n => visited.has(n.id));
  const filteredEdges = edges.filter(e => visited.has(e.source) && visited.has(e.target));
  return { nodes: filteredNodes, edges: filteredEdges };
}
