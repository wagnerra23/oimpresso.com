/**
 * governanceV4Types.ts — tipos compartilhados Wave 29 (W29-C)
 *
 * Tipos da tela `Admin/GovernanceV4` tri-pane (copy kb_v2 blueprint).
 * Mantidos no diretório `_components/` pra colocação junto dos sub-components
 * que os consomem (BucketSidebar / ModuleList / ModuleReader / etc).
 *
 * Backend Source-of-truth: `Modules/Admin/Http/Controllers/GovernanceV4DashboardController.php`
 * (Wave 27 polish — `__invoke` aponta pra `GovernanceV4Dashboard.tsx`).
 *
 * Para W29-B: o controller `indexV2` será um método NOVO que renderiza
 * `Admin/GovernanceV4` com props enriquecidas (scorecards / initiatives / waveHistory
 * / healthSnapshot). Tipos abaixo cobrem ambos cenários (props parciais via `?:`).
 */

export type BucketKey =
  | 'vertical_client_facing'
  | 'cross_cutting_infra'
  | 'ai_central'
  | 'functional_horizontal';

export type StatusKey = 'ok' | 'warn' | 'crit';

export type InitiativeStatus = 'open' | 'in_progress' | 'done' | 'overdue';

export type DimensionId =
  | 'D1'
  | 'D2'
  | 'D3'
  | 'D4'
  | 'D5'
  | 'D6'
  | 'D7'
  | 'D8'
  | 'D9';

export interface BucketMeta {
  label: string;
  meta: number;
}

export interface BucketDef {
  key: BucketKey;
  label: string;
  meta: number;
  count: number;
}

export interface ModuleRow {
  slug: string;
  name: string;
  score: number;
  meta: number;
  status: StatusKey;
  trend: number[];
  paired_count: number;
  p99_ms: number | null;
  bucket?: BucketKey;
}

export interface DriftAlert {
  module: string;
  delta: number;
  from: number;
  to: number;
  snapshot_date: string;
  direction: 'up' | 'down';
}

export interface AiSuggestion {
  module: string;
  avg_delta: number;
  count: number;
  last_justificativa: string;
  last_confidence: number;
  last_at: string | null;
}

export interface PairedViolation {
  module: string;
  rule: string;
  reason: string;
}

export interface DimensionScore {
  id: DimensionId;
  label: string;
  score: number;
  weight: number;
  paired_indicator: boolean;
  paired_ok?: boolean;
}

export interface ModuleScorecard {
  slug: string;
  dimensions: DimensionScore[];
  last_run_at: string | null;
  yaml_path?: string;
}

export interface Initiative {
  id: number;
  module: string;
  title: string;
  status: InitiativeStatus;
  owner?: string;
  deadline_at: string | null;
  created_at: string;
  updated_at: string;
  notes?: string;
}

export interface WaveHistoryEntry {
  wave_id: string;
  label: string;
  delivered_at: string;
  pr_url?: string;
  summary?: string;
}

export interface HealthSnapshot {
  scorecard_last_run_at: string | null;
  scorecard_cron_ok: boolean;
  ai_baseline_window_days: number;
  ai_baseline_remaining_days: number;
  otel_collector_up: boolean;
  otel_collector_last_ping_at: string | null;
  buckets_with_data: number;
}

export interface GovernanceV4Meta {
  subdomain: string;
  environment: string;
  bypass_local: boolean;
  generated_at: string;
  drift_threshold_pts: number;
  buckets: Record<BucketKey, BucketMeta>;
  v4_enabled: boolean;
}

export interface GovernanceV4Can {
  create_initiative: boolean;
  override_bucket: boolean;
  refresh_now: boolean;
}

export interface GovernanceV4PageProps {
  meta: GovernanceV4Meta;
  modules?: Record<BucketKey, ModuleRow[]>;
  drifts?: DriftAlert[];
  scorecards?: Record<string, ModuleScorecard>;
  initiatives?: Initiative[];
  aiSuggestions?: AiSuggestion[];
  pairedViolations?: PairedViolation[];
  waveHistory?: WaveHistoryEntry[];
  healthSnapshot?: HealthSnapshot;
  can?: GovernanceV4Can;
}

export const BUCKET_ORDER: BucketKey[] = [
  'vertical_client_facing',
  'cross_cutting_infra',
  'ai_central',
  'functional_horizontal',
];

export const BUCKET_LABEL_FALLBACK: Record<BucketKey, string> = {
  vertical_client_facing: 'Vertical Client-Facing',
  cross_cutting_infra: 'Cross-Cutting Infra',
  ai_central: 'AI Central',
  functional_horizontal: 'Functional Horizontal',
};

export const STATUS_TONE: Record<
  StatusKey,
  { label: string; iconKey: string; tone: 'success' | 'warning' | 'destructive' }
> = {
  ok: { label: 'OK', iconKey: 'check', tone: 'success' },
  warn: { label: 'Atenção', iconKey: 'alert-triangle', tone: 'warning' },
  crit: { label: 'Crítico', iconKey: 'x', tone: 'destructive' },
};

export const INITIATIVE_TONE: Record<
  InitiativeStatus,
  { label: string; tone: 'default' | 'info' | 'success' | 'destructive' }
> = {
  open: { label: 'Aberta', tone: 'default' },
  in_progress: { label: 'Em curso', tone: 'info' },
  done: { label: 'Concluída', tone: 'success' },
  overdue: { label: 'Vencida', tone: 'destructive' },
};
