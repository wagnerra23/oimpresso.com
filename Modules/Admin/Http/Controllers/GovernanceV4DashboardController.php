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
 * GovernanceV4DashboardController — Wave 24 Agent B.
 *
 * Painel Wagner-only que mostra ranking intra-bucket dos módulos:
 *  - 4 buckets canônicos (ADR 0160): vertical_client_facing / cross_cutting_infra /
 *    ai_central / functional_horizontal
 *  - Cada módulo: score determinístico + meta bucket + trend 30d (sparkline)
 *  - Paired violations destacadas (anti-Goodhart Jellyfish 2025)
 *  - AI suggestions READ-ONLY (mostra mas NÃO substitui score oficial)
 *
 * Fontes:
 *  - `memory/governance/scorecards/<module>.yaml` (rubricas YAML, ADR 0156+0160)
 *  - `mcp_scorecard_runs` (W21/W24-A determinístico) — opcional, fallback YAML.last_grade
 *  - `mcp_scorecard_ai_suggestions` (esta Wave 24-B baseline 30d)
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
    public function __invoke(Request $request): Response
    {
        $meta = [
            'subdomain' => config('admin.subdomain', 'admin.oimpresso.com'),
            'environment' => app()->environment(),
            'bypass_local' => (bool) (config('admin.bypass_local') && app()->environment('local')),
            'generated_at' => now()->toIso8601String(),
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
            'ai_suggestions' => Inertia::defer(fn () => $this->buildAiSuggestionsPayload()),
            'paired_violations' => Inertia::defer(fn () => $this->buildPairedViolationsPayload()),
        ]);
    }

    /**
     * Lista módulos com score atual + trend 30d agrupados por bucket.
     *
     * @return array<string, array<int, array{slug:string, name:string, score:int, meta:int, trend:array<int,int>, paired_count:int}>>
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

                $score = $this->resolveCurrentScore($yaml);
                $trend = $this->resolveTrend30d((string) $yaml['slug'], $score);

                $byBucket[$bucketKey][] = [
                    'slug' => (string) $yaml['slug'],
                    'name' => (string) ($yaml['module'] ?? $yaml['slug']),
                    'score' => $score,
                    'meta' => (int) ($yaml['target_score'] ?? 85),
                    'trend' => $trend,
                    'paired_count' => count((array) ($yaml['paired_violations'] ?? [])),
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
     */
    protected function resolveCurrentScore(array $yaml): int
    {
        $slug = (string) ($yaml['slug'] ?? '');

        if ($slug && Schema::hasTable('mcp_scorecard_runs')) {
            $latest = DB::table('mcp_scorecard_runs')
                ->where('module', $slug)
                ->orderBy('created_at', 'desc')
                ->first();
            if ($latest && isset($latest->score_total)) {
                return (int) $latest->score_total;
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
            ->pluck('score_total')
            ->all();

        if (empty($rows)) {
            return [$current];
        }

        return array_map(fn ($v) => (int) $v, $rows);
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
