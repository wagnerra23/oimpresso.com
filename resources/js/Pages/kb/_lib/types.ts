/**
 * KB Unificado — TS types
 *
 * Alinhados ao contrato canônico `memory/requisitos/KB/SCHEMA-DB-V1.md` (§3-9, §11).
 * Quando Agent A entregar Controller → Inertia props vão usar EXATAMENTE estes tipos.
 *
 * Convenção:
 *   - snake_case nas chaves vindas do backend (compat com Eloquent/Inertia)
 *   - Camadas opcionais (helpers, mock) podem normalizar pra camelCase no client
 */

// ──────────────────────────────────────────────────────────────────
// Tipo "block" — bloco do body editável (kb_nodes.body_blocks JSON)
// Espelha kbBuildArticleText/blockRenderer do Cowork
// ──────────────────────────────────────────────────────────────────

export type KbBlockKind = 'para' | 'h2' | 'list' | 'callout' | 'image';

export type KbCalloutTone = 'info' | 'ok' | 'warn' | 'bad';

export interface KbBlockPara {
  kind: 'para';
  t: string;
}

export interface KbBlockH2 {
  kind: 'h2';
  t: string;
}

export interface KbBlockList {
  kind: 'list';
  items: string[];
}

export interface KbBlockCallout {
  kind: 'callout';
  tone: KbCalloutTone;
  t: string;
}

export interface KbBlockImage {
  kind: 'image';
  src: string;
  alt?: string;
  caption?: string;
}

export type KbBlock =
  | KbBlockPara
  | KbBlockH2
  | KbBlockList
  | KbBlockCallout
  | KbBlockImage;

// ──────────────────────────────────────────────────────────────────
// kb_nodes — núcleo do grafo
// ──────────────────────────────────────────────────────────────────

export type KbNodeType =
  | 'article'
  | 'adr'
  | 'session'
  | 'charter'
  | 'runbook'
  | 'briefing'
  | 'spec'
  | 'comparativo'
  | 'reference'
  | 'os'
  | 'customer'
  | 'product'
  | 'nfe'
  | 'equipment'
  | 'external_file';

export type KbNodeStatus = 'draft' | 'ok' | 'outdated' | 'deleted' | 'deprecated';

export type KbNivel = 'iniciante' | 'intermediario' | 'avancado';

export interface KbNode {
  id: number;
  business_id: number;
  type: KbNodeType;
  slug: string;
  title: string;
  excerpt: string | null;

  body_blocks: KbBlock[] | null;

  source_doc_id: number | null;
  source_entity_type: string | null;
  source_entity_id: number | null;

  is_editable: boolean;
  status: KbNodeStatus;
  pinned: boolean;

  category_id: number | null;
  subcategory_id: number | null;
  nivel: KbNivel | null;
  equip: string | null;
  tags: string[] | null;

  reads_count: number;
  helpful_count: number;
  outdated_votes: number;
  os_linked_count: number;

  author_user_id: number | null;
  author_name?: string | null; // derivado server-side via JOIN
  read_time_min: number | null;

  last_verified_at: string | null;
  created_at: string | null;
  updated_at: string | null;
  deleted_at: string | null;
}

// ──────────────────────────────────────────────────────────────────
// kb_edges — arestas do grafo
// ──────────────────────────────────────────────────────────────────

export type KbEdgeType =
  | 'next-in-path'
  | 'fix-of-decision'
  | 'supersedes'
  | 'charter-of'
  | 'references-data'
  | 'ai-related'
  | 'cross-link'
  | 'related-by-tag';

export interface KbEdge {
  id: number;
  business_id: number;
  from_node_id: number;
  to_node_id: number;
  edge_type: KbEdgeType;
  weight: number;
  payload: Record<string, unknown> | null;
  generated_by: 'manual' | 'bridge_job' | 'ai_embed' | 'tag_overlap' | 'user_action';
  created_at: string | null;
  updated_at: string | null;
}

// ──────────────────────────────────────────────────────────────────
// kb_categories + kb_subcategories
// ──────────────────────────────────────────────────────────────────

export interface KbCategory {
  id: number;
  business_id: number;
  slug: string;
  label: string;
  description: string | null;
  hue: number;
  icon: string | null;
  sort_order: number;
}

export interface KbSubcategory {
  id: number;
  business_id: number;
  category_id: number;
  slug: string;
  label: string;
  description: string | null;
  auto_match: Record<string, unknown> | null;
}

// ──────────────────────────────────────────────────────────────────
// Trilhas (paths)
// ──────────────────────────────────────────────────────────────────

export type KbPathStepType = 'leitura' | 'pratica' | 'decisao';

export interface KbPathStep {
  id: number;
  business_id: number;
  path_id: number;
  node_id: number;
  position: number;
  step_type: KbPathStepType;
  note: string | null;
  // expandido pelo Controller:
  node?: KbNode;
}

export interface KbPath {
  id: number;
  business_id: number;
  slug: string;
  title: string;
  audience: string | null;
  description: string | null;
  hue: number;
  status: 'draft' | 'published' | 'archived';
  author_user_id: number | null;
  steps?: KbPathStep[];
}

// ──────────────────────────────────────────────────────────────────
// Decision trees (troubleshooters)
// ──────────────────────────────────────────────────────────────────

export interface KbDecisionTreeStep {
  id: number;
  business_id: number;
  tree_id: number;
  position: number;
  question: string;
  yes_next_step_id: number | null;
  yes_fix: string | null;
  yes_fix_node_id: number | null;
  no_next_step_id: number | null;
  no_fix: string | null;
  no_fix_node_id: number | null;
}

export interface KbDecisionTree {
  id: number;
  business_id: number;
  slug: string;
  title: string;
  equip: string | null;
  when_to_use: string | null;
  hue: number;
  status: 'draft' | 'published' | 'archived';
  root_step_id: number | null;
  steps?: KbDecisionTreeStep[];
}

// ──────────────────────────────────────────────────────────────────
// KPIs / filters / paginator
// ──────────────────────────────────────────────────────────────────

export interface KbKpis {
  total: number;
  outdated: number;
  fresh_last_14d: number;
  total_reads: number;
  total_os_linked: number;
  most_read: { id: number; title: string; reads_count: number } | null;
  pinned_count: number;
  ultimo_sync: string | null;
}

export interface KbFilters {
  q?: string;
  type?: KbNodeType | '';
  category?: string;
  subcategory?: string;
  nivel?: KbNivel | '';
  equip?: string;
  tag?: string;
  sort?: 'recent' | 'popular' | 'helpful' | 'outdated';
}

export interface Paginator<T> {
  data: T[];
  current_page: number;
  last_page: number;
  total: number;
  per_page: number;
  from: number;
  to: number;
  links: Array<{ url: string | null; label: string; active: boolean }>;
}

// ──────────────────────────────────────────────────────────────────
// Props da página Index.v2.tsx — contrato pra Agent A
// ──────────────────────────────────────────────────────────────────

export interface KbIndexProps {
  /** Paginador de kb_nodes filtrados (Inertia::defer recomendado) */
  nodes?: Paginator<KbNode>;
  /** Categorias do business atual (não-defer — leve) */
  categories?: KbCategory[];
  /** Subcategorias (não-defer) */
  subcategories?: KbSubcategory[];
  /** Trilhas publicadas */
  paths?: KbPath[];
  /** Troubleshooters publicados */
  decision_trees?: KbDecisionTree[];
  /** KPIs agregados (Inertia::defer recomendado) */
  kpis?: KbKpis;
  /** Tags populares (top N) */
  tags_top?: Array<{ tag: string; count: number }>;
  /** Filtros aplicados */
  filters?: KbFilters;
  /** Pinned nodes pro empty state do leitor */
  pinned?: KbNode[];
  /** Permissions do user atual (capabilities) */
  can?: {
    write?: boolean;
    publish_path?: boolean;
    publish_troubleshoot?: boolean;
    ai_ask?: boolean;
    graph_view?: boolean;
    favorite?: boolean;
    comment?: boolean;
  };
  /** Repo GitHub pra montar links */
  github_repo?: string;
}

// ──────────────────────────────────────────────────────────────────
// Frescor (computado client-side a partir de updated_at/last_verified_at)
// ──────────────────────────────────────────────────────────────────

export type FreshnessLevel = 'fresh' | 'aging' | 'stale' | 'expired';

export interface FreshnessInfo {
  level: FreshnessLevel;
  label: string; // pt-BR: "novo" | "fresco" | "recente" | "parado" | "expirado"
}
