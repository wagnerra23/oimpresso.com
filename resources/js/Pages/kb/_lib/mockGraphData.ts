/**
 * KB Graph — mock data (dev sem backend)
 * =======================================
 *
 * 50 nodes (15 ADRs + 10 sessions + 8 charters + 8 runbooks + 5 briefings + 4 specs)
 * ~150 edges (supersedes, charter-of, related-by-tag clusters semânticos, cross-link, ai-related)
 *
 * Usado:
 * - Storybook (futuro)
 * - Dev local com `?mock=1` na URL (fallback se backend não responder)
 * - Testes Pest browser (snapshot visual)
 *
 * Substituível por payload real de `/kb/graph/data` (SCHEMA-DB-V1 §11).
 *
 * Agent E (ONDA 5) — 2026-05-15
 */

import type { KbGraphNode, KbGraphEdge, KbGraphKpis, KbNodeStatus } from './graphTypes';

// ─── Helper de build ─────────────────────────────────────────────────────────
let edgeIdCounter = 1;
function edge(from: string, to: string, type: KbGraphEdge['edge_type'], opts: Partial<KbGraphEdge> = {}): KbGraphEdge {
  return {
    id: `edge-${edgeIdCounter++}-${from}-${to}-${type}`,
    source: from,
    target: to,
    edge_type: type,
    weight: opts.weight ?? 1.0,
    generated_by: opts.generated_by ?? 'manual',
    payload: opts.payload ?? null,
  };
}

// ─── ADRs (15) ───────────────────────────────────────────────────────────────
const ADRS: KbGraphNode[] = [
  ['adr-035', 'ADR 0035 — Stack IA canônica (laravel/ai)', 'ia,stack,canon', 'Copiloto'],
  ['adr-048', 'ADR 0048 — Framework agentes (Vizra rejeitada)', 'ia,framework', 'Copiloto'],
  ['adr-053', 'ADR 0053 — MCP server como produto', 'mcp,governance', 'Copiloto'],
  ['adr-061', 'ADR 0061 — Zero auto-mem privada', 'memoria,canon,governance', 'KB'],
  ['adr-070', 'ADR 0070 — Jira-style task management', 'tasks,governance', 'KB'],
  ['adr-093', 'ADR 0093 — Multi-tenant isolation Tier 0', 'multi-tenant,tier-0,governance', null],
  ['adr-094', 'ADR 0094 — Constituição v2 (7 camadas, 8 princípios)', 'governance,constituicao,tier-0', null],
  ['adr-101', 'ADR 0101 — Tests biz=1 (nunca cliente)', 'tests,multi-tenant', null],
  ['adr-104', 'ADR 0104 — Processo MWART canônico', 'mwart,frontend,canon', null],
  ['adr-114', 'ADR 0114 — Loop Cowork ↔ Claude Code', 'cowork,design,mwart', null],
  ['adr-129', 'ADR 0129 — FSM canon (tabular + RBAC)', 'fsm,sells,repair', null],
  ['adr-143', 'ADR 0143 — FSM Pipeline LIVE prod biz=1', 'fsm,sells,repair,prod', null],
  ['adr-119', 'ADR 0119 — Paralelismo sessões whats-active', 'sessoes,paralelismo', null],
  ['adr-149', 'ADR 0149 — KB Unificado grafo IA central', 'kb,knowledge-graph,ia,p0', 'KB'],
  ['adr-035b', 'ADR 0035b (proposed) — laravel/ai 0.7 upgrade', 'ia,stack,proposed', 'Copiloto'],
].map(([id, title, tagsStr, module]) => ({
  id: id as string,
  type: 'adr' as const,
  data: {
    label: title as string,
    slug: (id as string).replace('adr-', '0') + '-slug',
    excerpt: null,
    status: (id === 'adr-035b' ? 'draft' : id === 'adr-048' ? 'deprecated' : 'ok') as KbNodeStatus,
    pinned: ['adr-093', 'adr-094', 'adr-149'].includes(id as string),
    tags: (tagsStr as string).split(','),
    module: module as string | null,
    edges_count: 0,
    updated_at: '2026-05-15T10:00:00Z',
    last_verified_at: '2026-05-15T10:00:00Z',
  },
}));

// ─── Sessions (10) ───────────────────────────────────────────────────────────
const SESSIONS: KbGraphNode[] = [
  ['session-2026-05-12-fsm', '2026-05-12 — FSM rollout biz=1', 'fsm,prod,marco'],
  ['session-2026-05-13-audit', '2026-05-13 — Audit knowledge architecture', 'audit,knowledge,governance'],
  ['session-2026-05-13-agents', '2026-05-13 — Agents canônicos meta-degradação', 'agents,meta,degradacao'],
  ['session-2026-05-14-whatsapp', '2026-05-14 — WhatsApp drift maratona', 'whatsapp,drift,incident'],
  ['session-2026-05-15-kb-arte', '2026-05-15 — Arte claude-rules path-scoped', 'rules,governance,arte'],
  ['session-2026-05-15-kb-design', '2026-05-15 — KB unificado design ondas', 'kb,design,onda'],
  ['session-2026-05-09-financeiro', '2026-05-09 — Financeiro rejeitado batch', 'financeiro,reject,licao'],
  ['session-2026-05-08-recalibracao', '2026-05-08 — Recalibração 10x IA-pair', 'estimate,recalibracao'],
  ['session-2026-05-11-paralelizacao', '2026-05-11 — Paralelização frustrada', 'paralelizacao,handoff'],
  ['session-2026-05-15-kb-grafo', '2026-05-15 — KB grafo viz design', 'kb,grafo,viz'],
].map(([id, title, tagsStr]) => {
  const datePart = (id as string).match(/2026-05-\d+/)?.[0] ?? '2026-05-15';
  return {
    id: id as string,
    type: 'session' as const,
    data: {
      label: title as string,
      slug: (id as string).replace('session-', ''),
      excerpt: null,
      status: 'ok' as KbNodeStatus,
      pinned: false,
      tags: (tagsStr as string).split(','),
      module: null,
      edges_count: 0,
      updated_at: `${datePart}T18:00:00Z`,
    },
  };
});

// ─── Charters (8) ────────────────────────────────────────────────────────────
const CHARTERS: KbGraphNode[] = [
  ['charter-kb-index', 'Charter — Pages/kb/Index', 'kb,charter', 'KB'],
  ['charter-kb-graph', 'Charter — Pages/kb/Graph', 'kb,charter,graph', 'KB'],
  ['charter-sells-create', 'Charter — Pages/Sells/Create', 'sells,charter,fsm', 'Sells'],
  ['charter-repair-index', 'Charter — Pages/Repair/Index', 'repair,charter,kanban', 'Repair'],
  ['charter-inbox-index', 'Charter — Pages/Inbox/Index', 'inbox,charter,cockpit', 'Inbox'],
  ['charter-copiloto-chat', 'Charter — Pages/Copiloto/Chat', 'copiloto,charter,jana', 'Copiloto'],
  ['charter-financeiro-dashboard', 'Charter — Pages/Financeiro/Dashboard', 'financeiro,charter,dashboard', 'Financeiro'],
  ['charter-ads-graph', 'Charter — Pages/ads/Admin/Graph', 'ads,charter,graph', 'ADS'],
].map(([id, title, tagsStr, module]) => ({
  id: id as string,
  type: 'charter' as const,
  data: {
    label: title as string,
    slug: (id as string).replace('charter-', ''),
    excerpt: null,
    status: 'ok' as const,
    pinned: false,
    tags: (tagsStr as string).split(','),
    module: module as string,
    edges_count: 0,
    updated_at: '2026-05-15T08:00:00Z',
  },
}));

// ─── Runbooks (8) ────────────────────────────────────────────────────────────
const RUNBOOKS: KbGraphNode[] = [
  ['runbook-criar-modulo', 'Runbook — Criar módulo (8 peças)', 'modulo,runbook,scaffold', null],
  ['runbook-inertia-defer', 'Runbook — Inertia::defer pattern', 'inertia,performance,defer', null],
  ['runbook-acesso-ct100', 'Runbook — Acesso CT 100 Proxmox', 'infra,ct100,ssh', null],
  ['runbook-governance-gate', 'Runbook — Governance Gate CI', 'governance,ci,merge', null],
  ['runbook-replicar-prototipo', 'Runbook — Replicar protótipo Cowork', 'cowork,mwart,prototipo', null],
  ['runbook-baileys-troubleshoot', 'Runbook — Baileys troubleshoot ban', 'whatsapp,baileys,incident', 'Whatsapp'],
  ['runbook-fsm-bulk-start', 'Runbook — FSM bulk-start-pipeline', 'fsm,migration,sells', 'Sells'],
  ['runbook-pest-cross-tenant', 'Runbook — Pest cross-tenant biz=99', 'tests,pest,multi-tenant', null],
].map(([id, title, tagsStr, module]) => ({
  id: id as string,
  type: 'runbook' as const,
  data: {
    label: title as string,
    slug: (id as string).replace('runbook-', ''),
    excerpt: null,
    status: 'ok' as const,
    pinned: ['runbook-criar-modulo', 'runbook-inertia-defer'].includes(id as string),
    tags: (tagsStr as string).split(','),
    module: module as string | null,
    edges_count: 0,
    updated_at: '2026-05-15T08:00:00Z',
  },
}));

// ─── Briefings (5) ───────────────────────────────────────────────────────────
const BRIEFINGS: KbGraphNode[] = [
  ['briefing-kb', 'BRIEFING — Modules/KB', 'kb,briefing', 'KB'],
  ['briefing-whatsapp', 'BRIEFING — Modules/Whatsapp', 'whatsapp,briefing', 'Whatsapp'],
  ['briefing-financeiro', 'BRIEFING — Modules/Financeiro', 'financeiro,briefing', 'Financeiro'],
  ['briefing-copiloto', 'BRIEFING — Modules/Copiloto (Jana)', 'copiloto,briefing,jana', 'Copiloto'],
  ['briefing-sells', 'BRIEFING — Modules/Sells', 'sells,briefing,fsm', 'Sells'],
].map(([id, title, tagsStr, module]) => ({
  id: id as string,
  type: 'briefing' as const,
  data: {
    label: title as string,
    slug: (id as string).replace('briefing-', ''),
    excerpt: null,
    status: 'ok' as const,
    pinned: false,
    tags: (tagsStr as string).split(','),
    module: module as string,
    edges_count: 0,
    updated_at: '2026-05-15T08:00:00Z',
  },
}));

// ─── Specs (4) ───────────────────────────────────────────────────────────────
const SPECS: KbGraphNode[] = [
  ['spec-kb', 'SPEC — Modules/KB (US-KB-XXX)', 'kb,spec', 'KB'],
  ['spec-financeiro', 'SPEC — Modules/Financeiro', 'financeiro,spec', 'Financeiro'],
  ['spec-whatsapp', 'SPEC — Modules/Whatsapp', 'whatsapp,spec', 'Whatsapp'],
  ['spec-sells', 'SPEC — Modules/Sells', 'sells,spec', 'Sells'],
].map(([id, title, tagsStr, module]) => ({
  id: id as string,
  type: 'spec' as const,
  data: {
    label: title as string,
    slug: (id as string).replace('spec-', ''),
    excerpt: null,
    status: 'ok' as const,
    pinned: false,
    tags: (tagsStr as string).split(','),
    module: module as string,
    edges_count: 0,
    updated_at: '2026-05-15T08:00:00Z',
  },
}));

// ─── Todos os nodes ──────────────────────────────────────────────────────────
export const MOCK_NODES: KbGraphNode[] = [
  ...ADRS, ...SESSIONS, ...CHARTERS, ...RUNBOOKS, ...BRIEFINGS, ...SPECS,
];

// ─── Edges ───────────────────────────────────────────────────────────────────
export const MOCK_EDGES: KbGraphEdge[] = [
  // supersedes (entre ADRs) — dashed vermelho
  edge('adr-035b', 'adr-035', 'supersedes'),
  edge('adr-048', 'adr-035', 'supersedes'),

  // charter-of (charter ↔ ADR mãe) — solid cyan
  edge('charter-kb-index', 'adr-149', 'charter-of'),
  edge('charter-kb-graph', 'adr-149', 'charter-of'),
  edge('charter-sells-create', 'adr-143', 'charter-of'),
  edge('charter-sells-create', 'adr-129', 'charter-of'),
  edge('charter-repair-index', 'adr-129', 'charter-of'),
  edge('charter-inbox-index', 'adr-094', 'charter-of'),
  edge('charter-copiloto-chat', 'adr-035', 'charter-of'),
  edge('charter-ads-graph', 'adr-053', 'charter-of'),

  // cross-link (artigos citam ADRs/sessions/charters em #kb-XXX) — solid accent
  edge('briefing-kb', 'adr-149', 'cross-link'),
  edge('briefing-kb', 'spec-kb', 'cross-link'),
  edge('briefing-kb', 'charter-kb-index', 'cross-link'),
  edge('briefing-kb', 'charter-kb-graph', 'cross-link'),
  edge('briefing-whatsapp', 'runbook-baileys-troubleshoot', 'cross-link'),
  edge('briefing-whatsapp', 'session-2026-05-14-whatsapp', 'cross-link'),
  edge('briefing-financeiro', 'session-2026-05-09-financeiro', 'cross-link'),
  edge('briefing-sells', 'adr-143', 'cross-link'),
  edge('briefing-sells', 'adr-129', 'cross-link'),
  edge('briefing-sells', 'runbook-fsm-bulk-start', 'cross-link'),
  edge('briefing-copiloto', 'adr-035', 'cross-link'),
  edge('briefing-copiloto', 'adr-048', 'cross-link'),
  edge('runbook-replicar-prototipo', 'adr-114', 'cross-link'),
  edge('runbook-replicar-prototipo', 'adr-104', 'cross-link'),
  edge('runbook-governance-gate', 'adr-094', 'cross-link'),
  edge('runbook-pest-cross-tenant', 'adr-101', 'cross-link'),
  edge('runbook-pest-cross-tenant', 'adr-093', 'cross-link'),
  edge('runbook-inertia-defer', 'adr-104', 'cross-link'),
  edge('runbook-criar-modulo', 'adr-094', 'cross-link'),
  edge('runbook-fsm-bulk-start', 'adr-143', 'cross-link'),
  edge('session-2026-05-12-fsm', 'adr-143', 'cross-link'),
  edge('session-2026-05-12-fsm', 'adr-129', 'cross-link'),
  edge('session-2026-05-13-audit', 'adr-061', 'cross-link'),
  edge('session-2026-05-13-audit', 'adr-094', 'cross-link'),
  edge('session-2026-05-13-agents', 'adr-094', 'cross-link'),
  edge('session-2026-05-14-whatsapp', 'runbook-baileys-troubleshoot', 'cross-link'),
  edge('session-2026-05-15-kb-arte', 'adr-094', 'cross-link'),
  edge('session-2026-05-15-kb-design', 'adr-149', 'cross-link'),
  edge('session-2026-05-15-kb-grafo', 'adr-149', 'cross-link'),
  edge('session-2026-05-15-kb-grafo', 'charter-kb-graph', 'cross-link'),
  edge('session-2026-05-08-recalibracao', 'adr-094', 'cross-link'),

  // related-by-tag (clusters semânticos: governance, fsm, kb, whatsapp, mwart, multi-tenant)
  // governance cluster
  edge('adr-094', 'adr-093', 'related-by-tag', { weight: 0.95, generated_by: 'tag_overlap' }),
  edge('adr-094', 'adr-061', 'related-by-tag', { weight: 0.85, generated_by: 'tag_overlap' }),
  edge('adr-094', 'adr-070', 'related-by-tag', { weight: 0.70, generated_by: 'tag_overlap' }),
  edge('adr-094', 'adr-119', 'related-by-tag', { weight: 0.60, generated_by: 'tag_overlap' }),
  edge('adr-061', 'adr-053', 'related-by-tag', { weight: 0.75, generated_by: 'tag_overlap' }),

  // fsm cluster
  edge('adr-143', 'adr-129', 'related-by-tag', { weight: 0.95, generated_by: 'tag_overlap' }),
  edge('adr-143', 'session-2026-05-12-fsm', 'related-by-tag', { weight: 0.90, generated_by: 'tag_overlap' }),
  edge('adr-129', 'session-2026-05-12-fsm', 'related-by-tag', { weight: 0.80, generated_by: 'tag_overlap' }),
  edge('briefing-sells', 'spec-sells', 'related-by-tag', { weight: 0.85, generated_by: 'tag_overlap' }),

  // kb cluster
  edge('adr-149', 'briefing-kb', 'related-by-tag', { weight: 0.95, generated_by: 'tag_overlap' }),
  edge('adr-149', 'spec-kb', 'related-by-tag', { weight: 0.90, generated_by: 'tag_overlap' }),
  edge('adr-149', 'session-2026-05-15-kb-design', 'related-by-tag', { weight: 0.85, generated_by: 'tag_overlap' }),
  edge('adr-149', 'session-2026-05-15-kb-grafo', 'related-by-tag', { weight: 0.85, generated_by: 'tag_overlap' }),
  edge('charter-kb-index', 'charter-kb-graph', 'related-by-tag', { weight: 0.80, generated_by: 'tag_overlap' }),

  // whatsapp cluster
  edge('briefing-whatsapp', 'spec-whatsapp', 'related-by-tag', { weight: 0.85, generated_by: 'tag_overlap' }),
  edge('runbook-baileys-troubleshoot', 'session-2026-05-14-whatsapp', 'related-by-tag', { weight: 0.90, generated_by: 'tag_overlap' }),

  // mwart cluster
  edge('adr-104', 'adr-114', 'related-by-tag', { weight: 0.85, generated_by: 'tag_overlap' }),
  edge('runbook-replicar-prototipo', 'adr-104', 'related-by-tag', { weight: 0.80, generated_by: 'tag_overlap' }),
  edge('runbook-inertia-defer', 'adr-104', 'related-by-tag', { weight: 0.65, generated_by: 'tag_overlap' }),
  edge('session-2026-05-09-financeiro', 'adr-104', 'related-by-tag', { weight: 0.55, generated_by: 'tag_overlap' }),

  // multi-tenant cluster
  edge('adr-093', 'adr-101', 'related-by-tag', { weight: 0.90, generated_by: 'tag_overlap' }),
  edge('runbook-pest-cross-tenant', 'adr-093', 'related-by-tag', { weight: 0.85, generated_by: 'tag_overlap' }),
  edge('runbook-pest-cross-tenant', 'adr-101', 'related-by-tag', { weight: 0.85, generated_by: 'tag_overlap' }),

  // ia cluster
  edge('adr-035', 'adr-048', 'related-by-tag', { weight: 0.75, generated_by: 'tag_overlap' }),
  edge('adr-035', 'adr-053', 'related-by-tag', { weight: 0.65, generated_by: 'tag_overlap' }),
  edge('briefing-copiloto', 'charter-copiloto-chat', 'related-by-tag', { weight: 0.85, generated_by: 'tag_overlap' }),

  // ai-related (cosine similarity) — exemplos
  edge('session-2026-05-13-agents', 'session-2026-05-13-audit', 'ai-related', { weight: 0.78, generated_by: 'ai_embed' }),
  edge('session-2026-05-11-paralelizacao', 'session-2026-05-13-agents', 'ai-related', { weight: 0.65, generated_by: 'ai_embed' }),
  edge('session-2026-05-08-recalibracao', 'adr-094', 'ai-related', { weight: 0.55, generated_by: 'ai_embed' }),
  edge('runbook-fsm-bulk-start', 'session-2026-05-12-fsm', 'ai-related', { weight: 0.82, generated_by: 'ai_embed' }),
  edge('runbook-baileys-troubleshoot', 'briefing-whatsapp', 'ai-related', { weight: 0.72, generated_by: 'ai_embed' }),
  edge('charter-ads-graph', 'charter-kb-graph', 'ai-related', { weight: 0.85, generated_by: 'ai_embed' }),
  edge('adr-149', 'adr-053', 'ai-related', { weight: 0.62, generated_by: 'ai_embed' }),
  edge('adr-149', 'adr-061', 'ai-related', { weight: 0.58, generated_by: 'ai_embed' }),
];

// ─── Pré-compute edges_count nos nodes ───────────────────────────────────────
(function computeEdgesCount() {
  const counts = new Map<string, number>();
  for (const e of MOCK_EDGES) {
    counts.set(e.source, (counts.get(e.source) ?? 0) + 1);
    counts.set(e.target, (counts.get(e.target) ?? 0) + 1);
  }
  for (const n of MOCK_NODES) {
    n.data.edges_count = counts.get(n.id) ?? 0;
  }
})();

// ─── KPIs ────────────────────────────────────────────────────────────────────
export const MOCK_KPIS: KbGraphKpis = {
  total_nodes: MOCK_NODES.length,
  total_edges: MOCK_EDGES.length,
  by_type: {
    adr: ADRS.length,
    session: SESSIONS.length,
    charter: CHARTERS.length,
    runbook: RUNBOOKS.length,
    briefing: BRIEFINGS.length,
    spec: SPECS.length,
  },
  by_edge_type: MOCK_EDGES.reduce<Partial<Record<string, number>>>((acc, e) => {
    acc[e.edge_type] = (acc[e.edge_type] ?? 0) + 1;
    return acc;
  }, {}) as KbGraphKpis['by_edge_type'],
  outdated_count: MOCK_NODES.filter(n => n.data.status === 'outdated').length,
  draft_count: MOCK_NODES.filter(n => n.data.status === 'draft').length,
  last_bridge_at: '2026-05-15T10:00:00Z',
};
