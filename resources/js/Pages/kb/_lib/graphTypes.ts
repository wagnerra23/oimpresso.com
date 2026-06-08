/**
 * KB Graph — TS types
 * ===================
 *
 * Alinhados ao contrato SCHEMA-DB-V1 §4 (kb_edges) + §11 endpoint `/kb/graph/data`.
 * Source-of-truth do contrato fica em memory/requisitos/KB/SCHEMA-DB-V1.md.
 *
 * Convenção: campos vindos do backend Laravel em snake_case (preserva o JSON original);
 * helpers/aggregates frontend em camelCase.
 *
 * Agent E (ONDA 5) — 2026-05-15
 */

// ─── Tipos de nó (SCHEMA-DB-V1 §3 kb_nodes.type) ─────────────────────────────
export type KbNodeType =
  | 'article'         // operacional (editável)
  | 'adr'             // bridge canônica → mcp_memory_documents
  | 'session'         // bridge canônica
  | 'charter'         // bridge canônica
  | 'runbook'         // bridge canônica
  | 'briefing'        // bridge canônica
  | 'spec'            // bridge canônica
  | 'comparativo'     // bridge canônica
  | 'reference'       // bridge canônica
  | 'os'              // dado ERP (Modules\Repair\JobSheet)
  | 'customer'        // dado ERP (App\Contact)
  | 'product'         // dado ERP (App\Product)
  | 'nfe'             // dado ERP (Modules\NfeBrasil\Models\NfeEmissao)
  | 'equipment'       // dado ERP
  | 'external_file';  // arquivo upload (manual, contrato)

// ─── Tipos de aresta (SCHEMA-DB-V1 §4 kb_edges.edge_type) ────────────────────
export type KbEdgeType =
  | 'next-in-path'      // sequência ordenada de trilha
  | 'fix-of-decision'   // decision tree fix → artigo
  | 'supersedes'        // ADR substitui outra ADR
  | 'charter-of'        // charter.md ↔ ADR mãe
  | 'references-data'   // artigo cita OS/cliente/NFe real
  | 'ai-related'        // cosine similarity de embeddings (auto)
  | 'cross-link'        // #kb-XXX em body_blocks
  | 'related-by-tag';   // overlap de tags/cat/equip

export type KbNodeStatus = 'draft' | 'ok' | 'outdated' | 'deleted' | 'deprecated';

// ─── Payload bruto do endpoint /kb/graph/data ─────────────────────────────────
// Formato igual ao precedent `Modules/KB/Http/Controllers/Admin/GraphController.php`
// (ADS knowledge graph) — Inertia::render('kb/Graph', { nodes, edges, kpis }).
export interface KbGraphNode {
  id: string;                  // formato "<type>-<id-db>" ex: "adr-149", "session-2026-05-12"
  type: KbNodeType;
  data: {
    label: string;             // título exibido no node
    slug: string;
    excerpt?: string | null;
    status?: KbNodeStatus;
    pinned?: boolean;
    tags?: string[] | null;
    module?: string | null;    // ex: "KB", "Whatsapp"
    reads_count?: number;
    helpful_count?: number;
    outdated_votes?: number;
    last_verified_at?: string | null;
    updated_at?: string | null;
    /** Conta edges incidentes (in + out) — backend pode pré-computar pra performance UI */
    edges_count?: number;
  };
  // posição opcional vinda do backend (fallback é layout client-side)
  position?: { x: number; y: number };
}

export interface KbGraphEdge {
  id: string;                  // formato "edge-<from>-<to>-<type>"
  source: string;              // KbGraphNode.id
  target: string;              // KbGraphNode.id
  edge_type: KbEdgeType;
  weight?: number;             // 0-1 pra ai-related/related-by-tag
  generated_by?: 'manual' | 'bridge_job' | 'ai_embed' | 'tag_overlap' | 'user_action';
  payload?: Record<string, unknown> | null;
}

export interface KbGraphKpis {
  total_nodes: number;
  total_edges: number;
  // por tipo de nó
  by_type: Partial<Record<KbNodeType, number>>;
  // por tipo de aresta
  by_edge_type: Partial<Record<KbEdgeType, number>>;
  // saúde
  outdated_count: number;
  draft_count: number;
  last_bridge_at: string | null;
}

// ─── Props do Page Inertia ───────────────────────────────────────────────────
export interface KbGraphPageProps {
  nodes: KbGraphNode[];
  edges: KbGraphEdge[];
  kpis: KbGraphKpis;
  /** Filtros aplicados server-side (URL query params persistidos) */
  filters: {
    types?: KbNodeType[];
    edge_types?: KbEdgeType[];
    q?: string;
    focus_id?: string | null;
    depth?: number;
  };
}

// ─── Filtros locais (estado interno do componente) ───────────────────────────
export interface GraphFilterState {
  visibleNodeTypes: Set<KbNodeType>;
  visibleEdgeTypes: Set<KbEdgeType>;
  query: string;
  focusNodeId: string | null;
  depth: number;               // depth do focus mode (1-3 razoável)
  layoutMode: GraphLayoutMode;
}

export type GraphLayoutMode = 'dagre-tb' | 'dagre-lr' | 'force-radial' | 'concentric';

// ─── Helpers de tipo ─────────────────────────────────────────────────────────
export const NODE_TYPES_GOVERNANCE: ReadonlyArray<KbNodeType> = [
  'adr', 'session', 'charter', 'runbook', 'briefing', 'spec', 'comparativo', 'reference',
];

export const NODE_TYPES_ERP: ReadonlyArray<KbNodeType> = [
  'os', 'customer', 'product', 'nfe', 'equipment',
];

export const NODE_TYPES_EDITABLE: ReadonlyArray<KbNodeType> = ['article', 'external_file'];

export const ALL_NODE_TYPES: ReadonlyArray<KbNodeType> = [
  'article', 'adr', 'session', 'charter', 'runbook', 'briefing', 'spec',
  'comparativo', 'reference', 'os', 'customer', 'product', 'nfe', 'equipment',
  'external_file',
];

export const ALL_EDGE_TYPES: ReadonlyArray<KbEdgeType> = [
  'next-in-path', 'fix-of-decision', 'supersedes', 'charter-of',
  'references-data', 'ai-related', 'cross-link', 'related-by-tag',
];
