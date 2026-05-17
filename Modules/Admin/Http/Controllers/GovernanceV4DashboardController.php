<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Util\OtelHelper;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Admin\Http\Requests\CreateInitiativeRequest;
use Modules\Admin\Http\Requests\OverrideBucketRequest;
use Modules\Admin\Services\AdminAuditLogger;
use Modules\Governance\Services\InitiativeService;
use Symfony\Component\Yaml\Yaml;

/**
 * GovernanceV4DashboardController — Wave 27 polish (expandido de Wave 24).
 *
 * Painel Wagner-only que mostra ranking intra-bucket dos módulos:
 *  - 4 buckets canônicos (ADR 0160): vertical_client_facing / cross_cutting_infra /
 *    ai_central / functional_horizontal
 *  - Cada módulo: score determinístico + meta bucket + status (ok/warn/crit) + sparkline 30d
 *  - Drift alerts (delta >5pts entre snapshots consecutivos) — Wave 27
 *  - p99 latência observabilidade per-module — Wave 27 (mcp_observability_aggregates_daily)
 *  - Paired violations destacadas (anti-Goodhart Jellyfish 2025)
 *  - AI suggestions READ-ONLY (mostra mas NÃO substitui score oficial)
 *
 * Fontes:
 *  - `memory/governance/scorecards/<module>.yaml` (rubricas YAML, ADR 0156+0160)
 *  - `mcp_scorecard_runs` (W21/W24-A determinístico) — coluna real é `score` (não score_total)
 *  - `mcp_scorecard_ai_suggestions` (Wave 24-B baseline 30d)
 *  - `mcp_observability_aggregates_daily` (Wave 26 — opcional, fallback graceful)
 *
 * Inertia::defer pra props caras (RUNBOOK-inertia-defer-pattern.md).
 *
 * Auth gate: tailscale-only → auth → is-wagner (3 ANDs + fallback env).
 *
 * @see memory/decisions/0160-governance-v4-scoped-scorecards-bucket-meta.md
 * @see memory/decisions/0122-admin-center-ct100.md
 * @see Modules/Jana/Services/Scorecard/AiScorecardJudge.php
 */
class GovernanceV4DashboardController extends Controller
{
    /** Delta absoluto em pts entre snapshots consecutivos pra considerar drift. */
    private const DRIFT_THRESHOLD_PTS = 5;

    public function __invoke(Request $request): Response
    {
        $meta = [
            'subdomain' => config('admin.subdomain', 'admin.oimpresso.com'),
            'environment' => app()->environment(),
            'bypass_local' => (bool) (config('admin.bypass_local') && app()->environment('local')),
            'generated_at' => now()->toIso8601String(),
            'drift_threshold_pts' => self::DRIFT_THRESHOLD_PTS,
            'buckets' => [
                'vertical_client_facing' => ['label' => 'Vertical Client-Facing', 'meta' => 85],
                'cross_cutting_infra' => ['label' => 'Cross-Cutting Infra', 'meta' => 90],
                'ai_central' => ['label' => 'AI Central', 'meta' => 85],
                'functional_horizontal' => ['label' => 'Functional Horizontal', 'meta' => 80],
            ],
        ];

        // Inertia::defer — props caras lazy-loaded (D-14 pattern 300ms→50ms)
        return Inertia::render('Admin/GovernanceV4Dashboard', [
            'meta' => $meta,
            'modules' => Inertia::defer(fn () => $this->buildModulesPayload()),
            'drift_alerts' => Inertia::defer(fn () => $this->buildDriftAlertsPayload()),
            'ai_suggestions' => Inertia::defer(fn () => $this->buildAiSuggestionsPayload()),
            'paired_violations' => Inertia::defer(fn () => $this->buildPairedViolationsPayload()),
        ]);
    }

    /**
     * Lista módulos com score atual + trend 30d + status + p99 lat agrupados por bucket.
     *
     * @return array<string, array<int, array{slug:string, name:string, score:int, meta:int, status:string, trend:array<int,int>, paired_count:int, p99_ms:?float}>>
     */
    protected function buildModulesPayload(): array
    {
        return OtelHelper::span('admin.governance_v4.modules_payload', [
            'component' => 'governance_v4_dashboard',
        ], function () {
            $byBucket = [
                'vertical_client_facing' => [],
                'cross_cutting_infra' => [],
                'ai_central' => [],
                'functional_horizontal' => [],
            ];

            $yamlDir = base_path('memory/governance/scorecards');
            if (! is_dir($yamlDir)) {
                return $byBucket;
            }

            // Pré-carrega p99 30d por módulo (1 query — evita N+1)
            $p99ByModule = $this->loadP99ByModule();

            foreach (glob($yamlDir.'/*.yaml') ?: [] as $path) {
                $filename = basename($path);
                if (str_starts_with($filename, '_')) {
                    continue; // _template.yaml etc
                }

                $yaml = $this->safeYamlParse($path);
                if (! is_array($yaml) || empty($yaml['slug'])) {
                    continue;
                }

                $bucketKey = $this->mapBucket($yaml);
                if (! isset($byBucket[$bucketKey])) {
                    continue;
                }

                $slug = (string) $yaml['slug'];
                $score = $this->resolveCurrentScore($yaml);
                $metaScore = (int) ($yaml['target_score'] ?? $meta_default = 85);
                $trend = $this->resolveTrend30d($slug, $score);

                $byBucket[$bucketKey][] = [
                    'slug' => $slug,
                    'name' => (string) ($yaml['module'] ?? $slug),
                    'score' => $score,
                    'meta' => $metaScore,
                    'status' => $this->computeStatus($score, $metaScore),
                    'trend' => $trend,
                    'paired_count' => count((array) ($yaml['paired_violations'] ?? [])),
                    'p99_ms' => $p99ByModule[$slug] ?? null,
                ];
            }

            // Ordena cada bucket por score DESC
            foreach ($byBucket as $key => $list) {
                usort($byBucket[$key], fn ($a, $b) => $b['score'] <=> $a['score']);
            }

            return $byBucket;
        });
    }

    /**
     * Detecta drift (delta absoluto entre snapshots consecutivos > THRESHOLD).
     * Retorna módulos com pior delta primeiro pra Wagner agir.
     *
     * @return array<int, array{module:string, delta:int, from:int, to:int, snapshot_date:string, direction:string}>
     */
    protected function buildDriftAlertsPayload(): array
    {
        if (! Schema::hasTable('mcp_scorecard_runs')) {
            return [];
        }

        return OtelHelper::span('admin.governance_v4.drift_alerts', [
            'component' => 'governance_v4_dashboard',
            'threshold' => self::DRIFT_THRESHOLD_PTS,
        ], function () {
            $since = CarbonImmutable::now()->subDays(30);

            // Últimos N snapshots por módulo — ASC pra computar deltas pareados
            $rows = DB::table('mcp_scorecard_runs')
                ->where('created_at', '>=', $since)
                ->orderBy('module')
                ->orderBy('created_at', 'asc')
                ->get(['module', 'score', 'created_at']);

            $byModule = [];
            foreach ($rows as $r) {
                $byModule[(string) $r->module][] = [
                    'score' => (int) $r->score,
                    'at' => (string) $r->created_at,
                ];
            }

            $alerts = [];
            foreach ($byModule as $mod => $snaps) {
                if (count($snaps) < 2) {
                    continue;
                }

                // Procura MAIOR delta absoluto entre consecutivos
                $worst = null;
                for ($i = 1; $i < count($snaps); $i++) {
                    $from = $snaps[$i - 1]['score'];
                    $to = $snaps[$i]['score'];
                    $delta = $to - $from;
                    if (abs($delta) > self::DRIFT_THRESHOLD_PTS &&
                        ($worst === null || abs($delta) > abs($worst['delta']))) {
                        $worst = [
                            'delta' => $delta,
                            'from' => $from,
                            'to' => $to,
                            'snapshot_date' => $snaps[$i]['at'],
                        ];
                    }
                }

                if ($worst !== null) {
                    $alerts[] = [
                        'module' => $mod,
                        'delta' => $worst['delta'],
                        'from' => $worst['from'],
                        'to' => $worst['to'],
                        'snapshot_date' => $worst['snapshot_date'],
                        'direction' => $worst['delta'] < 0 ? 'down' : 'up',
                    ];
                }
            }

            // Pior delta primeiro (queda mais grave no topo)
            usort($alerts, fn ($a, $b) => abs($b['delta']) <=> abs($a['delta']));

            return $alerts;
        });
    }

    /**
     * Lista sugestões AI dos últimos 30 dias agregadas por módulo.
     *
     * @return array<int, array{module:string, avg_delta:float, count:int, last_justificativa:string, last_confidence:float, last_at:?string}>
     */
    protected function buildAiSuggestionsPayload(): array
    {
        if (! Schema::hasTable('mcp_scorecard_ai_suggestions')) {
            return [];
        }

        $since = CarbonImmutable::now()->subDays(30);

        $rows = DB::table('mcp_scorecard_ai_suggestions')
            ->where('created_at', '>=', $since)
            ->orderBy('created_at', 'desc')
            ->get();

        $byModule = [];
        foreach ($rows as $r) {
            $mod = (string) $r->module;
            if (! isset($byModule[$mod])) {
                $byModule[$mod] = [
                    'module' => $mod,
                    'sum_delta' => 0,
                    'count' => 0,
                    'last_justificativa' => (string) $r->ai_justificativa,
                    'last_confidence' => (float) $r->confidence,
                    'last_at' => (string) $r->created_at,
                ];
            }
            $byModule[$mod]['sum_delta'] += (int) $r->ai_suggested_delta;
            $byModule[$mod]['count']++;
        }

        $out = [];
        foreach ($byModule as $mod => $data) {
            $out[] = [
                'module' => $data['module'],
                'avg_delta' => $data['count'] > 0 ? round($data['sum_delta'] / $data['count'], 2) : 0.0,
                'count' => $data['count'],
                'last_justificativa' => $data['last_justificativa'],
                'last_confidence' => $data['last_confidence'],
                'last_at' => $data['last_at'],
            ];
        }

        usort($out, fn ($a, $b) => abs($b['avg_delta']) <=> abs($a['avg_delta']));

        return $out;
    }

    /**
     * Paired violations agregadas (anti-Goodhart) — leitura YAML.
     *
     * @return array<int, array{module:string, rule:string, reason:string}>
     */
    protected function buildPairedViolationsPayload(): array
    {
        $yamlDir = base_path('memory/governance/scorecards');
        if (! is_dir($yamlDir)) {
            return [];
        }

        $out = [];
        foreach (glob($yamlDir.'/*.yaml') ?: [] as $path) {
            $yaml = $this->safeYamlParse($path);
            if (! is_array($yaml) || empty($yaml['slug'])) {
                continue;
            }
            $violations = (array) ($yaml['paired_violations'] ?? []);
            foreach ($violations as $v) {
                $out[] = [
                    'module' => (string) $yaml['slug'],
                    'rule' => (string) (is_array($v) ? ($v['rule'] ?? 'unknown') : $v),
                    'reason' => (string) (is_array($v) ? ($v['reason'] ?? '') : ''),
                ];
            }
        }

        return $out;
    }

    /**
     * Carrega p99 latência por módulo (Wave 26 — mcp_observability_aggregates_daily).
     * Fallback graceful pra array vazio se tabela ainda não criada.
     *
     * @return array<string, float> módulo => p99_ms
     */
    protected function loadP99ByModule(): array
    {
        if (! Schema::hasTable('mcp_observability_aggregates_daily')) {
            return [];
        }

        $since = CarbonImmutable::now()->subDays(7); // p99 janela 7d (mais recente)

        try {
            $rows = DB::table('mcp_observability_aggregates_daily')
                ->where('snapshot_date', '>=', $since->toDateString())
                ->select('module', DB::raw('MAX(p99_ms) as p99_max'))
                ->groupBy('module')
                ->get();

            $out = [];
            foreach ($rows as $r) {
                $out[(string) $r->module] = (float) $r->p99_max;
            }

            return $out;
        } catch (\Throwable $e) {
            // Schema Wave 26 pode divergir — falha silenciosa em vez de quebrar dashboard
            return [];
        }
    }

    /**
     * Mapeia YAML pra bucket — tolerante a layouts antigos sem `bucket` explicito.
     */
    protected function mapBucket(array $yaml): string
    {
        // Layout novo (ADR 0160): `bucket:` no top-level
        $bucket = $yaml['bucket'] ?? null;

        // Fallback layout antigo: classify by `type`
        if (! $bucket || ! is_string($bucket)) {
            $type = $yaml['type'] ?? 'domain';
            $bucket = match ($type) {
                'meta' => 'cross_cutting_infra',
                'infra' => 'cross_cutting_infra',
                'domain' => 'vertical_client_facing',
                default => 'functional_horizontal',
            };
        }

        // Normaliza buckets antigos (Ruim/Médio/Bom/Excelente) pra ADR 0160 names
        $legacy = ['Ruim', 'Médio', 'Bom', 'Excelente'];
        if (in_array($bucket, $legacy, true)) {
            $type = $yaml['type'] ?? 'domain';

            return match ($type) {
                'meta', 'infra' => 'cross_cutting_infra',
                default => 'vertical_client_facing',
            };
        }

        return $bucket;
    }

    /**
     * Resolve score atual — preferir `mcp_scorecard_runs` se disponível, fallback YAML.
     * Coluna real é `score` (Wave 24-A migration) — não `score_total`.
     */
    protected function resolveCurrentScore(array $yaml): int
    {
        $slug = (string) ($yaml['slug'] ?? '');

        if ($slug && Schema::hasTable('mcp_scorecard_runs')) {
            $latest = DB::table('mcp_scorecard_runs')
                ->where('module', $slug)
                ->orderBy('created_at', 'desc')
                ->first();
            if ($latest && isset($latest->score)) {
                return (int) $latest->score;
            }
        }

        return (int) ($yaml['last_grade'] ?? 0);
    }

    /**
     * Trend 30 dias (sparkline). Usa `mcp_scorecard_runs` se existir, senão array vazio.
     *
     * @return array<int, int>
     */
    protected function resolveTrend30d(string $slug, int $current): array
    {
        if (! Schema::hasTable('mcp_scorecard_runs')) {
            return [$current];
        }

        $since = CarbonImmutable::now()->subDays(30);
        $rows = DB::table('mcp_scorecard_runs')
            ->where('module', $slug)
            ->where('created_at', '>=', $since)
            ->orderBy('created_at', 'asc')
            ->pluck('score')
            ->all();

        if (empty($rows)) {
            return [$current];
        }

        return array_map(fn ($v) => (int) $v, $rows);
    }

    /**
     * Computa status visual (ok/warn/crit) por delta vs meta.
     *  - ok: score >= meta
     *  - warn: meta-5 <= score < meta
     *  - crit: score < meta-5
     */
    protected function computeStatus(int $score, int $meta): string
    {
        if ($score >= $meta) {
            return 'ok';
        }
        if ($score >= $meta - 5) {
            return 'warn';
        }

        return 'crit';
    }

    // ────────────────────────────────────────────────────────────────────
    // Wave 29 (W29-B) — Tri-pane V2 + Initiative endpoints + Bucket override
    // ────────────────────────────────────────────────────────────────────

    /**
     * Wave 29 Tri-pane Dashboard V2 — feature-flagged via `governance.v4_enabled`.
     *
     * Diferenças vs `__invoke` (Wave 27):
     *  - 7 props deferred (modules / scorecards / drifts / initiatives /
     *    aiSuggestions / waveHistory / healthSnapshot) — RUNBOOK-inertia-defer-pattern
     *  - meta inclui media_atual + meta_aspiracional (97.75) + distribuicao
     *  - can.* permissions pré-computadas (frontend desabilita UI inline)
     *  - Renderiza Page tsx `Admin/GovernanceV4` (Wave 29-C cria)
     *
     * SUPERADMIN governance repo-wide — toda query DB cross-tenant intencional
     * (governance avalia código, não dados de negócio). Stack middleware
     * tailscale-only + auth + is-wagner já garante Wagner-only.
     *
     * @see resources/js/Pages/Admin/GovernanceV4.tsx (Wave 29-C)
     * @see memory/decisions/0160-governance-v4-scoped-scorecards-bucket-meta.md
     */
    public function indexV2(Request $request): Response
    {
        return Inertia::render('Admin/GovernanceV4', [
            'meta' => [
                'v4_enabled' => (bool) config('governance.v4_enabled', false),
                'media_atual' => $this->computeMediaAtual(),
                'meta_aspiracional' => 97.75,
                'distribuicao' => $this->computeDistribuicao(),
                'generated_at' => now()->toIso8601String(),
                'drift_threshold_pts' => self::DRIFT_THRESHOLD_PTS,
                'buckets' => [
                    'vertical_client_facing' => ['label' => 'Vertical Client-Facing', 'meta' => 85],
                    'cross_cutting_infra' => ['label' => 'Cross-Cutting Infra', 'meta' => 90],
                    'ai_central' => ['label' => 'AI Central', 'meta' => 85],
                    'functional_horizontal' => ['label' => 'Functional Horizontal', 'meta' => 80],
                ],
            ],
            // Inertia::defer obrigatório (RUNBOOK-inertia-defer-pattern — 300ms→50ms)
            'modules' => Inertia::defer(fn () => $this->buildModulesPayload()),
            'scorecards' => Inertia::defer(fn () => $this->buildScorecardsPayload()),
            'drifts' => Inertia::defer(fn () => $this->buildDriftAlertsPayload()),
            'initiatives' => Inertia::defer(fn () => $this->buildInitiativesPayload()),
            'aiSuggestions' => Inertia::defer(fn () => $this->buildAiSuggestionsPayload()),
            'waveHistory' => Inertia::defer(fn () => $this->buildWaveHistoryPayload()),
            'healthSnapshot' => Inertia::defer(fn () => $this->buildHealthSnapshotPayload()),
            'can' => [
                // Stack middleware (tailscale-only + auth + is-wagner) já restringe
                // pra Wagner-only. Flags são UX-affordance pra frontend.
                'create_initiative' => true,
                'override_bucket' => true,
                'refresh_now' => true,
            ],
        ]);
    }

    /**
     * Criar Initiative manualmente via Admin (Wagner-only). Reutiliza
     * `InitiativeService::createFromScorecardBreach` (idempotent) — se já houver
     * Initiative open/in_progress pro par (module, rule_id), retorna a existente
     * em vez de duplicar.
     *
     * Audit log obrigatório (AdminAuditLogger.D7.a redact PII + D9.a OTel span).
     */
    public function createInitiative(CreateInitiativeRequest $request, InitiativeService $svc, AdminAuditLogger $audit)
    {
        $validated = $request->validated();

        $initiative = $svc->createFromScorecardBreach(
            module: (string) $validated['module'],
            bucket: (string) $validated['bucket'],
            ruleId: (string) $validated['rule_id'],
            scoreBefore: (int) $validated['score_before'],
            scoreTarget: (int) $validated['score_target'],
            deadlineDays: (int) ($validated['deadline_days'] ?? 14),
            metadata: [
                'created_via' => 'admin_governance_v4_manual',
                'created_by_user_id' => Auth::id(),
            ],
        );

        // Owner manual (override) — Service não aceita owner, então atualiza após.
        if ($initiative->owner_user_id === null && Auth::check()) {
            $initiative->update(['owner_user_id' => Auth::id()]);
        }

        $audit->log('governance.v4.initiative.create', [
            'initiative_id' => $initiative->id,
            'module' => $initiative->module,
            'rule_id' => $initiative->rule_id,
            'idempotent_existing' => ! $initiative->wasRecentlyCreated,
        ], $request);

        return back()->with('success', sprintf(
            'Initiative #%d %s — %s.',
            $initiative->id,
            $initiative->wasRecentlyCreated ? 'criada' : 'já existia (idempotent)',
            $initiative->titulo,
        ));
    }

    /**
     * Override de bucket — APENAS registra intent + instrui Wagner sobre PR manual.
     *
     * Bucket é fonte-de-verdade `Modules/<X>/module.json` (`governance.bucket`).
     * Mover via Admin diretamente quebraria audit-trail e workflow MWART. Endpoint:
     *   1. Valida payload (módulo existe + buckets canônicos + razão ≥20 chars)
     *   2. Loga no `mcp_admin_audit_log` (kind `governance.v4.bucket.override.intent`)
     *   3. Retorna instrução clara pra PR com label `bucket-change-approved`
     */
    public function overrideBucket(OverrideBucketRequest $request, AdminAuditLogger $audit)
    {
        $validated = $request->validated();
        $module = (string) $validated['module'];
        $oldBucket = (string) $validated['old_bucket'];
        $newBucket = (string) $validated['new_bucket'];

        $audit->log('governance.v4.bucket.override.intent', [
            'module' => $module,
            'old_bucket' => $oldBucket,
            'new_bucket' => $newBucket,
            'razao' => (string) $validated['razao'],
        ], $request);

        $msg = sprintf(
            'Pra mover %s de %s pra %s, abra PR editando Modules/%s/module.json governance.bucket + adicione label `bucket-change-approved`. Intent registrada no audit log.',
            $module,
            $oldBucket,
            $newBucket,
            $module,
        );

        return back()->with('info', $msg);
    }

    // ────────────────────────────────────────────────────────────────────
    // Wave 29 helpers — payloads tri-pane
    // ────────────────────────────────────────────────────────────────────

    /**
     * Média score atual de todos módulos (último snapshot por módulo).
     * Cross-tenant intencional — governance é repo-wide.
     */
    protected function computeMediaAtual(): float
    {
        if (! Schema::hasTable('mcp_scorecard_runs')) {
            return 0.0;
        }

        // SUPERADMIN governance repo-wide — sem business_id (mcp_scorecard_runs cross-tenant)
        $latestPerModule = DB::table('mcp_scorecard_runs as a')
            ->select('a.module', 'a.score')
            ->whereIn('a.id', function ($q) {
                $q->selectRaw('MAX(id)')
                    ->from('mcp_scorecard_runs')
                    ->groupBy('module');
            })
            ->get();

        if ($latestPerModule->isEmpty()) {
            return 0.0;
        }

        return round((float) $latestPerModule->avg('score'), 2);
    }

    /**
     * Distribuição atual por bucket (count + media).
     *
     * @return array<string, array{count: int, media: float}>
     */
    protected function computeDistribuicao(): array
    {
        $out = [
            'vertical_client_facing' => ['count' => 0, 'media' => 0.0],
            'cross_cutting_infra' => ['count' => 0, 'media' => 0.0],
            'ai_central' => ['count' => 0, 'media' => 0.0],
            'functional_horizontal' => ['count' => 0, 'media' => 0.0],
        ];

        $modules = $this->buildModulesPayload();
        foreach ($modules as $bucketKey => $list) {
            if (! isset($out[$bucketKey])) {
                continue;
            }
            $out[$bucketKey]['count'] = count($list);
            $out[$bucketKey]['media'] = count($list) > 0
                ? round(array_sum(array_column($list, 'score')) / count($list), 2)
                : 0.0;
        }

        return $out;
    }

    /**
     * Lê rubricas YAML scorecards + breakdown 9 dimensões por bucket.
     *
     * @return array<int, array{slug: string, bucket: string, dimensions: array<string, mixed>}>
     */
    protected function buildScorecardsPayload(): array
    {
        $yamlDir = base_path('memory/governance/scorecards');
        if (! is_dir($yamlDir)) {
            return [];
        }

        $out = [];
        foreach (glob($yamlDir.'/*.yaml') ?: [] as $path) {
            $filename = basename($path);
            if (str_starts_with($filename, '_')) {
                continue;
            }

            $yaml = $this->safeYamlParse($path);
            if (! is_array($yaml) || empty($yaml['slug'])) {
                continue;
            }

            $out[] = [
                'slug' => (string) $yaml['slug'],
                'bucket' => $this->mapBucket($yaml),
                'dimensions' => (array) ($yaml['dimensions'] ?? []),
                'rules' => (array) ($yaml['rules'] ?? []),
                'paired_violations' => (array) ($yaml['paired_violations'] ?? []),
            ];
        }

        return $out;
    }

    /**
     * Initiatives open/in_progress (lista pra coluna direita tri-pane).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function buildInitiativesPayload(): array
    {
        if (! Schema::hasTable('mcp_governance_initiatives')) {
            return [];
        }

        return app(InitiativeService::class)
            ->listOpen()
            ->map(fn ($i) => $i->toArray())
            ->all();
    }

    /**
     * Histórico Waves W11→W28+ (hardcoded — fonte canon `_INDEX-LIFECYCLE.md`).
     * Hardcode intencional: dados estáveis low-churn, evita parse markdown caro.
     *
     * @return array<int, array{wave: string, date: string, theme: string}>
     */
    protected function buildWaveHistoryPayload(): array
    {
        // Fonte: memory/decisions/_INDEX-LIFECYCLE.md + memory/sessions/2026-05-1*
        // Atualizar quando novas waves grandes mergeadas.
        return [
            ['wave' => 'W11', 'date' => '2026-05-13', 'theme' => 'Fundação scorecard determinístico'],
            ['wave' => 'W14', 'date' => '2026-05-16', 'theme' => 'Admin Center polish + LGPD redact'],
            ['wave' => 'W21', 'date' => '2026-05-16', 'theme' => 'scorecard_runs snapshot diario'],
            ['wave' => 'W24', 'date' => '2026-05-17', 'theme' => 'Governance V4 dashboard intra-bucket'],
            ['wave' => 'W25', 'date' => '2026-05-17', 'theme' => 'Saturation Admin D8 + Remediation'],
            ['wave' => 'W26', 'date' => '2026-05-17', 'theme' => 'OTel observability aggregates daily'],
            ['wave' => 'W27', 'date' => '2026-05-17', 'theme' => 'Drift alerts + p99 latência module'],
            ['wave' => 'W28', 'date' => '2026-05-17', 'theme' => 'Initiative service Cortex/Port-style'],
            ['wave' => 'W29', 'date' => '2026-05-17', 'theme' => 'Tri-pane V2 + Initiative endpoints + bucket override'],
        ];
    }

    /**
     * Snapshot saúde sistema governance (ultima snapshot, AI baseline status, OTel ping).
     *
     * @return array{last_scorecard_snapshot: ?string, ai_baseline_days_remaining: int, otel_collector_status: string, last_grade_v4_run: ?string}
     */
    protected function buildHealthSnapshotPayload(): array
    {
        return [
            'last_scorecard_snapshot' => Schema::hasTable('mcp_scorecard_runs')
                ? (string) (DB::table('mcp_scorecard_runs')->max('created_at') ?? '')
                : null,
            'ai_baseline_days_remaining' => $this->aiBaselineDaysRemaining(),
            'otel_collector_status' => $this->pingOtelCollector(),
            'last_grade_v4_run' => Cache::get('governance.v4.last_run'),
        ];
    }

    /**
     * Dias restantes do AI baseline 30d (Wave 24-B). Quando expirar, AI judge
     * pode ser promovido de READ-ONLY pra weight-bearing (com Wagner aprovar ADR).
     */
    protected function aiBaselineDaysRemaining(): int
    {
        if (! Schema::hasTable('mcp_scorecard_ai_suggestions')) {
            return 30;
        }

        $first = DB::table('mcp_scorecard_ai_suggestions')->min('created_at');
        if (! $first) {
            return 30;
        }

        $startedAt = CarbonImmutable::parse((string) $first);
        $endsAt = $startedAt->addDays(30);
        $remaining = (int) max(0, now()->diffInDays($endsAt, false));

        return $remaining;
    }

    /**
     * Ping OTel collector (CT 100) — fallback graceful "unknown" se config ausente
     * ou unreachable. NÃO bloqueia render do dashboard.
     */
    protected function pingOtelCollector(): string
    {
        $url = config('otel.collector_health_url');
        if (! $url) {
            return 'not_configured';
        }

        try {
            $response = Http::timeout(2)->get((string) $url);
            return $response->successful() ? 'ok' : 'unhealthy';
        } catch (\Throwable $e) {
            Log::debug('governance.v4.otel_ping_failed', ['error' => $e->getMessage()]);
            return 'unreachable';
        }
    }

    /**
     * Parse YAML defensivo — symfony/yaml já é dep do Laravel.
     */
    protected function safeYamlParse(string $path): ?array
    {
        try {
            if (! class_exists(Yaml::class)) {
                // Fallback parser super simples (suficiente pra detectar slug/bucket)
                $content = (string) file_get_contents($path);
                $out = [];
                if (preg_match('/^slug:\s*"?([^"\n]+)"?/m', $content, $m)) {
                    $out['slug'] = trim($m[1]);
                }
                if (preg_match('/^module:\s*"?([^"\n]+)"?/m', $content, $m)) {
                    $out['module'] = trim($m[1]);
                }
                if (preg_match('/^bucket:\s*"?([^"\n]+)"?/m', $content, $m)) {
                    $out['bucket'] = trim($m[1]);
                }
                if (preg_match('/^type:\s*"?([^"\n]+)"?/m', $content, $m)) {
                    $out['type'] = trim($m[1]);
                }
                if (preg_match('/^last_grade:\s*(\d+)/m', $content, $m)) {
                    $out['last_grade'] = (int) $m[1];
                }
                if (preg_match('/^target_score:\s*(\d+)/m', $content, $m)) {
                    $out['target_score'] = (int) $m[1];
                }

                return $out;
            }

            return (array) Yaml::parseFile($path);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
