<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Util\OtelHelper;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
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
