/**
 * ScreenReview — mock data (fallback dev-mode + Pest fixture).
 *
 * W30 Agent C — Mock pra Page `Admin/ScreenReview.tsx` (W30-B) usado quando:
 *   1. Backend Inertia ainda não populou props (dev local sem seed)
 *   2. Pest frontend snapshot da tela de revisão visual PDCA
 *   3. Storybook (futuro) renderizar variações isoladas
 *
 * NÃO incluir PII real (Tier 0 IRREVOGÁVEL — `memory/proibicoes.md`). Tudo
 * é path sintético tela + status + nota técnica anonimizada. Wagner valida
 * pela tela real antes de soltar.
 *
 * Pattern espelhado de `mockGovernanceV4.ts` (W29 Agent C). Loop PDCA visual:
 *   pending-wagner → approved | rejected | iterate (round++)
 *
 * @see resources/js/Pages/Admin/ScreenReview.tsx (W30-B)
 * @see Modules/Admin/Http/Controllers/ScreenReviewController.php (W30-B)
 * @see .claude/skills/tela-smoke-pos-merge/SKILL.md (W30-A)
 * @see memory/decisions/0164-skill-tela-smoke-pos-merge.md (W30-A proposta)
 * @see memory/requisitos/Admin/SCREEN-REVIEW-RUNBOOK.md (W30-C operacional)
 */

// ──────────────────────────────────────────────────────────────────
// Types
// ──────────────────────────────────────────────────────────────────

export type ReviewStatus = 'pending-wagner' | 'approved' | 'rejected' | 'iterate';

export interface UxTargets {
  /** First Contentful Paint em ms (target conforme charter da tela). */
  first_paint?: number;
  /** Largest Contentful Paint em ms. */
  lcp?: number;
  /** Cumulative Layout Shift (CLS). */
  cls?: number;
  /** Tempo total request controller (ms). */
  controller_time?: number;
  /** Densidade info (linhas/min visíveis). */
  density?: number;
}

export interface MockScreen {
  /** Path canônico relativo a resources/js/Pages/ (sem .tsx). */
  path: string;
  /** Nome módulo (1º segmento do path). */
  module: string;
  /** Nome tela (último segmento do path). */
  name: string;
  /** Status PDCA atual. */
  status: ReviewStatus;
  /** Rodada atual do loop (incrementa em iterate). */
  current_round: number;
  /** ISO 8601 do último smoke executado (null se nunca). */
  last_smoke: string | null;
  /** Path screenshot do round atual no storage publico. */
  screenshot_url: string | null;
  /** UX targets canônicos do charter.md (se existe). */
  ux_targets: UxTargets;
  /** Quantidade de desvios catalogados no review.md (acumulado todos os rounds). */
  desvios_count: number;
  /** Indica se há `<Tela>.charter.md` adjacente no git. */
  has_charter: boolean;
  /** Indica se há `<Tela>.review.md` adjacente (loop iniciado). */
  has_review: boolean;
  /** Nota livre concatenada — última round (preview UI). */
  last_notes?: string;
}

export interface MockModuleSummary {
  /** Nome canônico (PascalCase ex: Admin, KB). */
  name: string;
  /** Total telas .tsx detectadas no glob. */
  total: number;
  /** Count por status. */
  pending: number;
  approved: number;
  rejected: number;
  iterate: number;
}

export interface MockScreenReviewMeta {
  generated_at: string;
  total_telas: number;
  pending_count: number;
  approved_count: number;
  rejected_count: number;
  iterate_count: number;
  /** Última data smoke batch (cron daily 09:00 BRT). */
  last_batch_at: string | null;
  /** Skill ativa? — fallback se .claude/skills/tela-smoke-pos-merge/SKILL.md ausente. */
  skill_enabled: boolean;
}

// ──────────────────────────────────────────────────────────────────
// Helpers relativos
// ──────────────────────────────────────────────────────────────────

function daysAgoIso(d: number): string {
  return new Date(Date.now() - d * 86_400_000).toISOString();
}

function hoursAgoIso(h: number): string {
  return new Date(Date.now() - h * 3_600_000).toISOString();
}

// ──────────────────────────────────────────────────────────────────
// Meta — header summary
// ──────────────────────────────────────────────────────────────────

export const MOCK_META: MockScreenReviewMeta = {
  generated_at: hoursAgoIso(0),
  total_telas: 18,
  pending_count: 5,
  approved_count: 9,
  rejected_count: 2,
  iterate_count: 2,
  last_batch_at: hoursAgoIso(22),
  skill_enabled: true,
};

// ──────────────────────────────────────────────────────────────────
// Modules summary — agrupado por 1º segmento path
// ──────────────────────────────────────────────────────────────────

export const MOCK_MODULES: MockModuleSummary[] = [
  { name: 'Admin', total: 5, pending: 2, approved: 3, rejected: 0, iterate: 0 },
  { name: 'KB', total: 4, pending: 0, approved: 4, rejected: 0, iterate: 0 },
  { name: 'Repair', total: 3, pending: 1, approved: 1, rejected: 1, iterate: 0 },
  { name: 'Sells', total: 2, pending: 1, approved: 0, rejected: 0, iterate: 1 },
  { name: 'Inbox', total: 2, pending: 0, approved: 1, rejected: 0, iterate: 1 },
  { name: 'Jana', total: 2, pending: 1, approved: 0, rejected: 1, iterate: 0 },
];

// ──────────────────────────────────────────────────────────────────
// Screens — 18 telas mock cobrindo todos os status PDCA
// ──────────────────────────────────────────────────────────────────

export const MOCK_SCREENS: MockScreen[] = [
  // ── Admin (5) — bucket cross-cutting infra
  {
    path: 'Admin/GovernanceV4',
    module: 'Admin',
    name: 'GovernanceV4',
    status: 'pending-wagner',
    current_round: 1,
    last_smoke: hoursAgoIso(2),
    screenshot_url: '/storage/screen-reviews/admin-governance-v4-r1-1440.png',
    ux_targets: { first_paint: 800, lcp: 1500, controller_time: 200 },
    desvios_count: 2,
    has_charter: true,
    has_review: false,
    last_notes: 'Round 1 — primeira smoke pós-W29. Aguarda Wagner.',
  },
  {
    path: 'Admin/GovernanceV4Dashboard',
    module: 'Admin',
    name: 'GovernanceV4Dashboard',
    status: 'approved',
    current_round: 3,
    last_smoke: daysAgoIso(2),
    screenshot_url: '/storage/screen-reviews/admin-governance-dashboard-r3-1440.png',
    ux_targets: { first_paint: 600, lcp: 1200 },
    desvios_count: 0,
    has_charter: true,
    has_review: true,
    last_notes: 'Round 3 aprovado — refator W27 + W28 estabilizou.',
  },
  {
    path: 'Admin/RagQualityDashboard',
    module: 'Admin',
    name: 'RagQualityDashboard',
    status: 'pending-wagner',
    current_round: 1,
    last_smoke: hoursAgoIso(20),
    screenshot_url: '/storage/screen-reviews/admin-rag-quality-r1-1440.png',
    ux_targets: { first_paint: 800, lcp: 1800, controller_time: 250 },
    desvios_count: 1,
    has_charter: false,
    has_review: false,
    last_notes: 'Sem charter ainda — Wagner pode aprovar tela e gerar charter retroativo.',
  },
  {
    path: 'Admin/Index',
    module: 'Admin',
    name: 'Index',
    status: 'approved',
    current_round: 2,
    last_smoke: daysAgoIso(10),
    screenshot_url: '/storage/screen-reviews/admin-index-r2-1440.png',
    ux_targets: { first_paint: 500 },
    desvios_count: 0,
    has_charter: true,
    has_review: true,
  },
  {
    path: 'Admin/FeatureFlags',
    module: 'Admin',
    name: 'FeatureFlags',
    status: 'approved',
    current_round: 1,
    last_smoke: daysAgoIso(5),
    screenshot_url: '/storage/screen-reviews/admin-feature-flags-r1-1440.png',
    ux_targets: { first_paint: 700, controller_time: 180 },
    desvios_count: 0,
    has_charter: true,
    has_review: true,
  },

  // ── KB (4) — todos approved (sprint mais maduro)
  {
    path: 'KB/Index',
    module: 'KB',
    name: 'Index',
    status: 'approved',
    current_round: 2,
    last_smoke: daysAgoIso(7),
    screenshot_url: '/storage/screen-reviews/kb-index-r2-1440.png',
    ux_targets: { first_paint: 600, lcp: 1100 },
    desvios_count: 0,
    has_charter: true,
    has_review: true,
  },
  {
    path: 'KB/Article',
    module: 'KB',
    name: 'Article',
    status: 'approved',
    current_round: 3,
    last_smoke: daysAgoIso(8),
    screenshot_url: '/storage/screen-reviews/kb-article-r3-1440.png',
    ux_targets: { first_paint: 550 },
    desvios_count: 0,
    has_charter: true,
    has_review: true,
  },
  {
    path: 'KB/Search',
    module: 'KB',
    name: 'Search',
    status: 'approved',
    current_round: 1,
    last_smoke: daysAgoIso(6),
    screenshot_url: '/storage/screen-reviews/kb-search-r1-1440.png',
    ux_targets: { first_paint: 650, lcp: 1300 },
    desvios_count: 0,
    has_charter: true,
    has_review: true,
  },
  {
    path: 'KB/Admin',
    module: 'KB',
    name: 'Admin',
    status: 'approved',
    current_round: 2,
    last_smoke: daysAgoIso(15),
    screenshot_url: '/storage/screen-reviews/kb-admin-r2-1440.png',
    ux_targets: { first_paint: 700 },
    desvios_count: 0,
    has_charter: true,
    has_review: true,
  },

  // ── Repair (3) — mix de status (1 rejected escalou Initiative)
  {
    path: 'Repair/Kanban',
    module: 'Repair',
    name: 'Kanban',
    status: 'pending-wagner',
    current_round: 1,
    last_smoke: hoursAgoIso(18),
    screenshot_url: '/storage/screen-reviews/repair-kanban-r1-1440.png',
    ux_targets: { first_paint: 850, density: 8 },
    desvios_count: 3,
    has_charter: true,
    has_review: false,
    last_notes: 'Cards densos demais — densidade 12 alvo, atual 8.',
  },
  {
    path: 'Repair/Show',
    module: 'Repair',
    name: 'Show',
    status: 'approved',
    current_round: 2,
    last_smoke: daysAgoIso(12),
    screenshot_url: '/storage/screen-reviews/repair-show-r2-1440.png',
    ux_targets: { first_paint: 500 },
    desvios_count: 0,
    has_charter: true,
    has_review: true,
  },
  {
    path: 'Repair/Index',
    module: 'Repair',
    name: 'Index',
    status: 'rejected',
    current_round: 3,
    last_smoke: daysAgoIso(4),
    screenshot_url: '/storage/screen-reviews/repair-index-r3-1440.png',
    ux_targets: { first_paint: 1200, controller_time: 450 },
    desvios_count: 5,
    has_charter: true,
    has_review: true,
    last_notes: 'Round 3 rejeitado — Initiative criada: governance auto P1.',
  },

  // ── Sells (2) — 1 iterate (loop ativo)
  {
    path: 'Sells/Index',
    module: 'Sells',
    name: 'Index',
    status: 'iterate',
    current_round: 2,
    last_smoke: hoursAgoIso(4),
    screenshot_url: '/storage/screen-reviews/sells-index-r2-1440.png',
    ux_targets: { first_paint: 900, lcp: 1700 },
    desvios_count: 4,
    has_charter: true,
    has_review: true,
    last_notes: 'Round 2 iterando — fix paginate desbloqueia approved.',
  },
  {
    path: 'Sells/Create',
    module: 'Sells',
    name: 'Create',
    status: 'pending-wagner',
    current_round: 1,
    last_smoke: hoursAgoIso(8),
    screenshot_url: '/storage/screen-reviews/sells-create-r1-1440.png',
    ux_targets: { first_paint: 750 },
    desvios_count: 1,
    has_charter: false,
    has_review: false,
  },

  // ── Inbox (2) — 1 approved, 1 iterate
  {
    path: 'Inbox/Index',
    module: 'Inbox',
    name: 'Index',
    status: 'approved',
    current_round: 2,
    last_smoke: daysAgoIso(3),
    screenshot_url: '/storage/screen-reviews/inbox-index-r2-1440.png',
    ux_targets: { first_paint: 600, controller_time: 200 },
    desvios_count: 0,
    has_charter: true,
    has_review: true,
  },
  {
    path: 'Inbox/Thread',
    module: 'Inbox',
    name: 'Thread',
    status: 'iterate',
    current_round: 3,
    last_smoke: hoursAgoIso(12),
    screenshot_url: '/storage/screen-reviews/inbox-thread-r3-1440.png',
    ux_targets: { first_paint: 800, lcp: 1400 },
    desvios_count: 2,
    has_charter: true,
    has_review: true,
    last_notes: 'Round 3 iterando — Inertia::defer aplicado, aguarda re-smoke.',
  },

  // ── Jana (2) — 1 rejected (Initiative ativa)
  {
    path: 'Jana/Chat',
    module: 'Jana',
    name: 'Chat',
    status: 'rejected',
    current_round: 2,
    last_smoke: daysAgoIso(2),
    screenshot_url: '/storage/screen-reviews/jana-chat-r2-1440.png',
    ux_targets: { first_paint: 1100, lcp: 2200 },
    desvios_count: 6,
    has_charter: true,
    has_review: true,
    last_notes: 'Latência IA visível na UI — refactor stream pendente.',
  },
  {
    path: 'Jana/Conversations',
    module: 'Jana',
    name: 'Conversations',
    status: 'pending-wagner',
    current_round: 1,
    last_smoke: hoursAgoIso(15),
    screenshot_url: '/storage/screen-reviews/jana-conversations-r1-1440.png',
    ux_targets: { first_paint: 700 },
    desvios_count: 0,
    has_charter: false,
    has_review: false,
  },
];

// ──────────────────────────────────────────────────────────────────
// Bundle default — facilita import único em componentes
// ──────────────────────────────────────────────────────────────────

export const MOCK_SCREEN_REVIEW = {
  meta: MOCK_META,
  modules: MOCK_MODULES,
  screens: MOCK_SCREENS,
} as const;

export default MOCK_SCREEN_REVIEW;
