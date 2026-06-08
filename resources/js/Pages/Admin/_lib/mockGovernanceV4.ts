/**
 * GovernanceV4 — mock data (fallback dev-mode + Pest fixture).
 *
 * Pattern espelhado do `kb/_lib/mockData.ts` (W28 KB unificado). Usado quando:
 *   1. Backend Inertia ainda não populou props (dev local sem seed)
 *   2. Pest frontend snapshot do `Admin/GovernanceV4` tela tri-pane
 *   3. Storybook (futuro) renderizar variações isoladas
 *
 * NÃO incluir PII real (Tier 0 IRREVOGÁVEL — `memory/proibicoes.md`). Tudo
 * é módulo nome + score sintético. Wagner valida pela tela antes de soltar.
 *
 * Buckets canon: ADR 0160 (4 buckets + meta 80-90 pts).
 * 34 módulos catalogados: scorecards YAML em `memory/governance/scorecards/`.
 *
 * @see resources/js/Pages/Admin/GovernanceV4.tsx
 * @see Modules/Admin/Http/Controllers/GovernanceV4DashboardController.php
 * @see memory/decisions/0160-governance-v4-scoped-scorecards-bucket-meta.md
 */

// ──────────────────────────────────────────────────────────────────
// Types
// ──────────────────────────────────────────────────────────────────

export type BucketSlug =
  | 'vertical_client_facing'
  | 'cross_cutting_infra'
  | 'ai_central'
  | 'functional_horizontal';

export type ModuleStatus = 'ok' | 'warn' | 'crit';
export type InitiativeStatus =
  | 'open'
  | 'in_progress'
  | 'done'
  | 'expired'
  | 'cancelled';

export interface MockBucket {
  id: BucketSlug;
  name: string;
  meta: number;
  count: number;
}

export interface MockModule {
  slug: string;
  bucket: BucketSlug;
  score_v3: number;
  meta_bucket: number;
  status: ModuleStatus;
  sparkline_30d: number[];
  drifts_count: number;
  initiatives_count: number;
}

export interface MockInitiative {
  id: number;
  module: string;
  bucket: BucketSlug;
  rule_id: string;
  titulo: string;
  status: InitiativeStatus;
  deadline: string;
  score_before: number;
  score_target: number;
  score_after: number | null;
  opened_at: string;
}

export interface MockDrift {
  module: string;
  delta: number;
  from: number;
  to: number;
  snapshot_date: string;
  direction: 'up' | 'down';
}

export interface MockAiSuggestion {
  module: string;
  avg_delta: number;
  count: number;
  last_justificativa: string;
  last_confidence: number;
  last_at: string;
}

export interface MockWaveEntry {
  wave: string;
  date: string;
  modules_touched: string[];
  score_delta: number;
  summary: string;
}

export interface MockHealthSnapshot {
  generated_at: string;
  v4_enabled: boolean;
  media_atual: number;
  meta_aspiracional: number;
  modules_total: number;
  modules_ok: number;
  modules_warn: number;
  modules_crit: number;
  drift_threshold_pts: number;
  open_initiatives: number;
  expired_initiatives: number;
}

// ──────────────────────────────────────────────────────────────────
// Helper relativos
// ──────────────────────────────────────────────────────────────────

function daysAgo(d: number): string {
  return new Date(Date.now() - d * 86_400_000).toISOString();
}

function daysFromNow(d: number): string {
  return new Date(Date.now() + d * 86_400_000).toISOString().slice(0, 10);
}

// ──────────────────────────────────────────────────────────────────
// Buckets — 4 canon ADR 0160
// ──────────────────────────────────────────────────────────────────

export const MOCK_BUCKETS: MockBucket[] = [
  { id: 'vertical_client_facing', name: 'Vertical Cliente', meta: 85, count: 5 },
  { id: 'cross_cutting_infra', name: 'Cross-cutting Infra', meta: 90, count: 7 },
  { id: 'ai_central', name: 'AI Central', meta: 85, count: 2 },
  { id: 'functional_horizontal', name: 'Functional Horizontal', meta: 80, count: 20 },
];

// ──────────────────────────────────────────────────────────────────
// Modules — 34 módulos (5 + 7 + 2 + 20)
// ──────────────────────────────────────────────────────────────────

export const MOCK_MODULES: MockModule[] = [
  // ── vertical_client_facing (5) — meta 85
  { slug: 'Vestuario', bucket: 'vertical_client_facing', score_v3: 88, meta_bucket: 85, status: 'ok', sparkline_30d: [82, 84, 85, 87, 88], drifts_count: 0, initiatives_count: 0 },
  { slug: 'ComunicacaoVisual', bucket: 'vertical_client_facing', score_v3: 76, meta_bucket: 85, status: 'warn', sparkline_30d: [70, 72, 74, 75, 76], drifts_count: 1, initiatives_count: 1 },
  { slug: 'OficinaAuto', bucket: 'vertical_client_facing', score_v3: 65, meta_bucket: 85, status: 'crit', sparkline_30d: [62, 63, 64, 65, 65], drifts_count: 2, initiatives_count: 3 },
  { slug: 'Repair', bucket: 'vertical_client_facing', score_v3: 91, meta_bucket: 85, status: 'ok', sparkline_30d: [86, 88, 89, 90, 91], drifts_count: 0, initiatives_count: 0 },
  { slug: 'Project', bucket: 'vertical_client_facing', score_v3: 83, meta_bucket: 85, status: 'warn', sparkline_30d: [80, 81, 82, 82, 83], drifts_count: 0, initiatives_count: 1 },

  // ── cross_cutting_infra (7) — meta 90
  { slug: 'Admin', bucket: 'cross_cutting_infra', score_v3: 94, meta_bucket: 90, status: 'ok', sparkline_30d: [89, 91, 92, 93, 94], drifts_count: 0, initiatives_count: 0 },
  { slug: 'Governance', bucket: 'cross_cutting_infra', score_v3: 92, meta_bucket: 90, status: 'ok', sparkline_30d: [88, 90, 91, 92, 92], drifts_count: 0, initiatives_count: 0 },
  { slug: 'Financeiro', bucket: 'cross_cutting_infra', score_v3: 86, meta_bucket: 90, status: 'warn', sparkline_30d: [84, 85, 86, 86, 86], drifts_count: 1, initiatives_count: 2 },
  { slug: 'NfeBrasil', bucket: 'cross_cutting_infra', score_v3: 89, meta_bucket: 90, status: 'warn', sparkline_30d: [87, 88, 88, 89, 89], drifts_count: 0, initiatives_count: 1 },
  { slug: 'MemCofre', bucket: 'cross_cutting_infra', score_v3: 78, meta_bucket: 90, status: 'crit', sparkline_30d: [76, 76, 77, 77, 78], drifts_count: 2, initiatives_count: 4 },
  { slug: 'RecurringBilling', bucket: 'cross_cutting_infra', score_v3: 95, meta_bucket: 90, status: 'ok', sparkline_30d: [92, 93, 94, 94, 95], drifts_count: 0, initiatives_count: 0 },
  { slug: 'Whatsapp', bucket: 'cross_cutting_infra', score_v3: 81, meta_bucket: 90, status: 'crit', sparkline_30d: [75, 77, 79, 80, 81], drifts_count: 3, initiatives_count: 2 },

  // ── ai_central (2) — meta 85
  { slug: 'Jana', bucket: 'ai_central', score_v3: 73, meta_bucket: 85, status: 'crit', sparkline_30d: [58, 65, 68, 70, 73], drifts_count: 1, initiatives_count: 2 },
  { slug: 'Copiloto', bucket: 'ai_central', score_v3: 87, meta_bucket: 85, status: 'ok', sparkline_30d: [82, 84, 86, 87, 87], drifts_count: 0, initiatives_count: 0 },

  // ── functional_horizontal (20) — meta 80
  { slug: 'Crm', bucket: 'functional_horizontal', score_v3: 87, meta_bucket: 80, status: 'ok', sparkline_30d: [82, 83, 85, 86, 87], drifts_count: 0, initiatives_count: 0 },
  { slug: 'Vendas', bucket: 'functional_horizontal', score_v3: 84, meta_bucket: 80, status: 'ok', sparkline_30d: [80, 81, 82, 83, 84], drifts_count: 0, initiatives_count: 0 },
  { slug: 'Compras', bucket: 'functional_horizontal', score_v3: 82, meta_bucket: 80, status: 'ok', sparkline_30d: [78, 80, 81, 81, 82], drifts_count: 0, initiatives_count: 0 },
  { slug: 'Estoque', bucket: 'functional_horizontal', score_v3: 86, meta_bucket: 80, status: 'ok', sparkline_30d: [83, 84, 85, 85, 86], drifts_count: 0, initiatives_count: 0 },
  { slug: 'Produtos', bucket: 'functional_horizontal', score_v3: 79, meta_bucket: 80, status: 'warn', sparkline_30d: [77, 78, 78, 79, 79], drifts_count: 0, initiatives_count: 1 },
  { slug: 'Pessoas', bucket: 'functional_horizontal', score_v3: 81, meta_bucket: 80, status: 'ok', sparkline_30d: [78, 79, 80, 80, 81], drifts_count: 0, initiatives_count: 0 },
  { slug: 'Ponto', bucket: 'functional_horizontal', score_v3: 75, meta_bucket: 80, status: 'crit', sparkline_30d: [72, 73, 73, 74, 75], drifts_count: 1, initiatives_count: 2 },
  { slug: 'Inbox', bucket: 'functional_horizontal', score_v3: 83, meta_bucket: 80, status: 'ok', sparkline_30d: [80, 81, 82, 82, 83], drifts_count: 0, initiatives_count: 0 },
  { slug: 'Pos', bucket: 'functional_horizontal', score_v3: 80, meta_bucket: 80, status: 'ok', sparkline_30d: [76, 77, 78, 79, 80], drifts_count: 0, initiatives_count: 0 },
  { slug: 'Caixa', bucket: 'functional_horizontal', score_v3: 78, meta_bucket: 80, status: 'warn', sparkline_30d: [76, 76, 77, 78, 78], drifts_count: 0, initiatives_count: 1 },
  { slug: 'Relatorios', bucket: 'functional_horizontal', score_v3: 72, meta_bucket: 80, status: 'crit', sparkline_30d: [70, 71, 71, 72, 72], drifts_count: 1, initiatives_count: 1 },
  { slug: 'Kb', bucket: 'functional_horizontal', score_v3: 88, meta_bucket: 80, status: 'ok', sparkline_30d: [82, 84, 86, 87, 88], drifts_count: 0, initiatives_count: 0 },
  { slug: 'Ads', bucket: 'functional_horizontal', score_v3: 70, meta_bucket: 80, status: 'crit', sparkline_30d: [68, 69, 69, 70, 70], drifts_count: 1, initiatives_count: 2 },
  { slug: 'ConsultaOs', bucket: 'functional_horizontal', score_v3: 85, meta_bucket: 80, status: 'ok', sparkline_30d: [80, 82, 83, 84, 85], drifts_count: 0, initiatives_count: 0 },
  { slug: 'Notificacoes', bucket: 'functional_horizontal', score_v3: 77, meta_bucket: 80, status: 'warn', sparkline_30d: [74, 75, 76, 77, 77], drifts_count: 0, initiatives_count: 1 },
  { slug: 'Backup', bucket: 'functional_horizontal', score_v3: 90, meta_bucket: 80, status: 'ok', sparkline_30d: [86, 87, 88, 89, 90], drifts_count: 0, initiatives_count: 0 },
  { slug: 'Auditoria', bucket: 'functional_horizontal', score_v3: 81, meta_bucket: 80, status: 'ok', sparkline_30d: [78, 79, 80, 80, 81], drifts_count: 0, initiatives_count: 0 },
  { slug: 'Importador', bucket: 'functional_horizontal', score_v3: 74, meta_bucket: 80, status: 'crit', sparkline_30d: [70, 71, 72, 73, 74], drifts_count: 1, initiatives_count: 1 },
  { slug: 'Integracoes', bucket: 'functional_horizontal', score_v3: 79, meta_bucket: 80, status: 'warn', sparkline_30d: [76, 77, 78, 78, 79], drifts_count: 0, initiatives_count: 1 },
  { slug: 'Configuracoes', bucket: 'functional_horizontal', score_v3: 84, meta_bucket: 80, status: 'ok', sparkline_30d: [80, 81, 82, 83, 84], drifts_count: 0, initiatives_count: 0 },
];

// ──────────────────────────────────────────────────────────────────
// Initiatives — open + closed mix (lifecycle Cortex/Port.io)
// ──────────────────────────────────────────────────────────────────

export const MOCK_INITIATIVES: MockInitiative[] = [
  {
    id: 1, module: 'Jana', bucket: 'ai_central', rule_id: 'F1.a',
    titulo: '[Jana] Rule F1.a abaixo do alvo (68→85)',
    status: 'in_progress', deadline: daysFromNow(7),
    score_before: 68, score_target: 85, score_after: null,
    opened_at: daysAgo(7),
  },
  {
    id: 2, module: 'Jana', bucket: 'ai_central', rule_id: 'D9.b',
    titulo: '[Jana] Rule D9.b abaixo do alvo (70→85)',
    status: 'open', deadline: daysFromNow(10),
    score_before: 70, score_target: 85, score_after: null,
    opened_at: daysAgo(4),
  },
  {
    id: 3, module: 'OficinaAuto', bucket: 'vertical_client_facing', rule_id: 'V6.b',
    titulo: '[OficinaAuto] Rule V6.b abaixo do alvo (60→85)',
    status: 'open', deadline: daysFromNow(3),
    score_before: 60, score_target: 85, score_after: null,
    opened_at: daysAgo(11),
  },
  {
    id: 4, module: 'MemCofre', bucket: 'cross_cutting_infra', rule_id: 'F1.c',
    titulo: '[MemCofre] Rule F1.c abaixo do alvo (75→90)',
    status: 'in_progress', deadline: daysFromNow(5),
    score_before: 75, score_target: 90, score_after: null,
    opened_at: daysAgo(9),
  },
  {
    id: 5, module: 'Whatsapp', bucket: 'cross_cutting_infra', rule_id: 'L2.a',
    titulo: '[Whatsapp] Rule L2.a abaixo do alvo (72→90)',
    status: 'open', deadline: daysFromNow(14),
    score_before: 72, score_target: 90, score_after: null,
    opened_at: daysAgo(0),
  },
  {
    id: 6, module: 'Financeiro', bucket: 'cross_cutting_infra', rule_id: 'V6.a',
    titulo: '[Financeiro] Rule V6.a abaixo do alvo (84→90)',
    status: 'done', deadline: daysFromNow(-2),
    score_before: 84, score_target: 90, score_after: 91,
    opened_at: daysAgo(16),
  },
  {
    id: 7, module: 'Ads', bucket: 'functional_horizontal', rule_id: 'F2.a',
    titulo: '[Ads] Rule F2.a abaixo do alvo (65→80)',
    status: 'expired', deadline: daysFromNow(-5),
    score_before: 65, score_target: 80, score_after: null,
    opened_at: daysAgo(19),
  },
];

// ──────────────────────────────────────────────────────────────────
// Drifts — últimos 7d, threshold >5pts
// ──────────────────────────────────────────────────────────────────

export const MOCK_DRIFTS: MockDrift[] = [
  { module: 'Whatsapp', delta: -8, from: 89, to: 81, snapshot_date: daysAgo(2), direction: 'down' },
  { module: 'Jana', delta: 7, from: 66, to: 73, snapshot_date: daysAgo(3), direction: 'up' },
  { module: 'OficinaAuto', delta: -6, from: 71, to: 65, snapshot_date: daysAgo(4), direction: 'down' },
  { module: 'Ponto', delta: -6, from: 81, to: 75, snapshot_date: daysAgo(5), direction: 'down' },
  { module: 'Importador', delta: 6, from: 68, to: 74, snapshot_date: daysAgo(6), direction: 'up' },
];

// ──────────────────────────────────────────────────────────────────
// AI Suggestions — READ-ONLY (Jellyfish anti-Goodhart 2025)
// ──────────────────────────────────────────────────────────────────

export const MOCK_AI_SUGGESTIONS: MockAiSuggestion[] = [
  {
    module: 'Jana', avg_delta: 4.5, count: 3,
    last_justificativa: 'Charter Jana documentada após hotfix #639; melhorou clareza de boundaries entre Agents.',
    last_confidence: 0.78, last_at: daysAgo(1),
  },
  {
    module: 'Whatsapp', avg_delta: -3.2, count: 2,
    last_justificativa: 'Baileys 7.x rc instável + 3 regressões em 14 dias — degradação confirmada vs alvo de uptime.',
    last_confidence: 0.85, last_at: daysAgo(2),
  },
  {
    module: 'MemCofre', avg_delta: -2.0, count: 1,
    last_justificativa: 'Falta Pest cobertura cross-tenant; ADR 0093 multi-tenant não enforced em factory tests.',
    last_confidence: 0.71, last_at: daysAgo(4),
  },
];

// ──────────────────────────────────────────────────────────────────
// Wave history — W11..W28 (snapshot governance moves)
// ──────────────────────────────────────────────────────────────────

export const MOCK_WAVE_HISTORY: MockWaveEntry[] = [
  { wave: 'W28', date: daysAgo(2), modules_touched: ['Governance', 'Admin'], score_delta: +3, summary: 'Initiative service Cortex/Port.io + lifecycle' },
  { wave: 'W27', date: daysAgo(5), modules_touched: ['Admin'], score_delta: +2, summary: 'GovernanceV4 polish drift visualization' },
  { wave: 'W26', date: daysAgo(8), modules_touched: ['Admin', 'Jana'], score_delta: +1, summary: 'Saturation observability aggregates daily' },
  { wave: 'W25', date: daysAgo(11), modules_touched: ['Admin'], score_delta: +2, summary: 'FormRequests RemediationRequest + AlertAck' },
  { wave: 'W24', date: daysAgo(14), modules_touched: ['Jana'], score_delta: +4, summary: 'AI Scorecard Judge baseline 30d' },
  { wave: 'W23', date: daysAgo(17), modules_touched: ['Governance'], score_delta: +3, summary: 'ScopedScorecardEvaluator rules canon' },
  { wave: 'W22', date: daysAgo(20), modules_touched: ['Governance', 'Admin'], score_delta: +2, summary: 'Bucket meta ADR 0160' },
  { wave: 'W21', date: daysAgo(23), modules_touched: ['Governance'], score_delta: +5, summary: 'mcp_scorecard_runs migration + seed' },
];

// ──────────────────────────────────────────────────────────────────
// Health snapshot — visão executiva 1-glance
// ──────────────────────────────────────────────────────────────────

export const MOCK_HEALTH_SNAPSHOT: MockHealthSnapshot = {
  generated_at: new Date().toISOString(),
  v4_enabled: true,
  media_atual: 81.4,
  meta_aspiracional: 85,
  modules_total: 34,
  modules_ok: 19,
  modules_warn: 8,
  modules_crit: 7,
  drift_threshold_pts: 5,
  open_initiatives: 5,
  expired_initiatives: 1,
};

// ──────────────────────────────────────────────────────────────────
// Helpers usados pela tela tri-pane
// ──────────────────────────────────────────────────────────────────

/** Agrupa módulos por bucket (espelha buildModulesPayload do Controller). */
export function groupModulesByBucket(
  modules: MockModule[],
): Record<BucketSlug, MockModule[]> {
  const out: Record<BucketSlug, MockModule[]> = {
    vertical_client_facing: [],
    cross_cutting_infra: [],
    ai_central: [],
    functional_horizontal: [],
  };
  for (const m of modules) {
    out[m.bucket].push(m);
  }
  // Ordena DESC por score (mesmo critério Controller)
  for (const key of Object.keys(out) as BucketSlug[]) {
    out[key].sort((a, b) => b.score_v3 - a.score_v3);
  }
  return out;
}

/** Filtra initiatives open/in_progress. */
export function filterOpenInitiatives(
  initiatives: MockInitiative[],
): MockInitiative[] {
  return initiatives.filter(
    (i) => i.status === 'open' || i.status === 'in_progress',
  );
}
