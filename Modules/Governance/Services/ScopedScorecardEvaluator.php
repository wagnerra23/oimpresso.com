<?php

declare(strict_types=1);

namespace Modules\Governance\Services;

use App\Util\OtelHelper;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

/**
 * ScopedScorecardEvaluator — avalia scorecards bucket-scoped (ADR 0160 proposto).
 *
 * Wave 21 criou stub; Wave 24 Agent A (2026-05-16) implementa:
 *   - Carrega scorecard YAML por módulo (memory/governance/scorecards/<slug>.yaml)
 *   - Carrega bucket config (memory/governance/buckets/<bucket>.yaml)
 *   - Avalia paired indicators (cap 50% canônico)
 *   - Retorna breakdown completo: score_total + core + bucket_dimensions + paired_violations
 *
 * Cap 50% paired: se velocidade alta (≥75% peso) mas qualidade baixa (<50% peso),
 * a dimensão velocidade tem score capado em 50% — penaliza gaming "ship fast / break quality".
 *
 * NÃO substitui ModuleGradeService (rubrica v3 filesystem-driven) — complementa:
 * - ModuleGradeService = scan automático
 * - ScopedScorecardEvaluator = leitura YAML curated (Wagner edita manualmente).
 *
 * Snapshot diário via `governance:scorecard-snapshot` (cron daily 07:00 BRT).
 *
 * @see memory/governance/buckets/vertical_client_facing.yaml
 * @see memory/governance/scorecards/<slug>.yaml
 * @see Modules\Governance\Console\Commands\ScorecardSnapshotCommand
 */
class ScopedScorecardEvaluator
{
    private string $scorecardsPath;
    private string $bucketsPath;
    private string $modulesPath;

    public function __construct()
    {
        $this->scorecardsPath = base_path('memory/governance/scorecards');
        $this->bucketsPath    = base_path('memory/governance/buckets');
        $this->modulesPath    = base_path('Modules');
    }

    /**
     * Carrega scorecard YAML pra um módulo.
     *
     * @return array<string, mixed>  Vazio se arquivo não existe ou parse falha.
     */
    public function loadScorecardForModule(string $module): array
    {
        $slug = strtolower($module);
        $path = $this->scorecardsPath . DIRECTORY_SEPARATOR . $slug . '.yaml';

        if (! File::exists($path)) {
            return [];
        }

        try {
            $data = Yaml::parseFile($path);
            return is_array($data) ? $data : [];
        } catch (\Throwable $e) {
            \Log::warning('ScopedScorecardEvaluator: YAML parse falhou', [
                'module' => $module,
                'path'   => $path,
                'error'  => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Lê bucket do `module.json` em Modules/<X>/.
     *
     * Retorna `unknown` se módulo não declara `governance.bucket`.
     */
    public function resolveBucketForModule(string $module): string
    {
        $modulePath = $this->modulesPath . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . 'module.json';
        if (! File::exists($modulePath)) {
            return 'unknown';
        }
        try {
            $json = json_decode(File::get($modulePath), true);
            return (string) ($json['governance']['bucket'] ?? 'unknown');
        } catch (\Throwable $e) {
            return 'unknown';
        }
    }

    /**
     * Carrega bucket config YAML.
     *
     * @return array<string, mixed>  Vazio se arquivo não existe.
     */
    public function loadBucketConfig(string $bucket): array
    {
        $path = $this->bucketsPath . DIRECTORY_SEPARATOR . $bucket . '.yaml';
        if (! File::exists($path)) {
            return [];
        }
        try {
            $data = Yaml::parseFile($path);
            return is_array($data) ? $data : [];
        } catch (\Throwable $e) {
            \Log::warning('ScopedScorecardEvaluator: bucket YAML parse falhou', [
                'bucket' => $bucket,
                'path'   => $path,
                'error'  => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Avalia scorecard de um módulo (core + bucket dimensions + paired enforcement).
     *
     * @param  string  $module  Nome do módulo (ex 'Vestuario')
     * @param  array  $scorecard  YAML carregado via loadScorecardForModule()
     * @return array{module: string, bucket: string, score_total: int, core: array, bucket_dimensions: array, paired_violations: array, evaluated_at: string}
     */
    public function evaluateScorecard(string $module, array $scorecard): array
    {
        return OtelHelper::spanBiz('governance.scorecard.evaluate', function () use ($module, $scorecard): array {
            $bucket = $this->resolveBucketForModule($module);
            $bucketConfig = $this->loadBucketConfig($bucket);

            // Core dimensions — herda do scorecard ou usa bucket targets como max.
            $core = [];
            $coreScore = 0;
            $coreMaxTotal = 0;
            $bucketCore = $bucketConfig['core'] ?? [];
            $scorecardDims = $scorecard['dimensions'] ?? [];

            foreach ($bucketCore as $key => $cfg) {
                $peso = (int) ($cfg['peso'] ?? 0);
                $current = (int) ($scorecardDims[$key]['current'] ?? $scorecardDims[$key]['target'] ?? 0);
                $current = max(0, min($peso, $current));
                $core[$key] = [
                    'peso'    => $peso,
                    'score'   => $current,
                    'target'  => (int) ($cfg['target'] ?? $peso),
                ];
                $coreScore += $current;
                $coreMaxTotal += $peso;
            }

            // Bucket dimensions — extras específicas do bucket.
            $bucketDims = [];
            $bucketScore = 0;
            $bucketMaxTotal = 0;
            foreach (($bucketConfig['bucket_dimensions'] ?? []) as $dimKey => $dimCfg) {
                $peso = (int) ($dimCfg['peso'] ?? 0);
                $current = (int) ($scorecardDims[$dimKey]['current'] ?? $dimCfg['target'] ?? 0);
                $current = max(0, min($peso, $current));
                $bucketDims[$dimKey] = [
                    'peso'    => $peso,
                    'score'   => $current,
                    'target'  => (int) ($dimCfg['target'] ?? $peso),
                    'regras'  => $dimCfg['regras'] ?? [],
                ];
                $bucketScore += $current;
                $bucketMaxTotal += $peso;
            }

            $result = [
                'module'             => $module,
                'bucket'             => $bucket,
                'core'               => $core,
                'bucket_dimensions'  => $bucketDims,
                'paired_violations'  => [],
                'evaluated_at'       => now()->toIso8601String(),
            ];

            // Paired enforcement (cap 50% canônico Wave 24).
            foreach (($bucketConfig['paired'] ?? []) as $pair) {
                $violation = $this->checkPairedViolation($result, $pair);
                if ($violation) {
                    $result['paired_violations'][] = $violation;
                }
            }

            // Recalcula bucket_dimensions score após eventual cap (paired).
            $bucketScorePostCap = 0;
            foreach ($result['bucket_dimensions'] as $dim) {
                $bucketScorePostCap += (int) ($dim['score'] ?? 0);
            }

            $totalMax = $coreMaxTotal + $bucketMaxTotal;
            $totalScore = $coreScore + $bucketScorePostCap;
            $result['score_total'] = $totalMax > 0
                ? (int) round(($totalScore / $totalMax) * 100)
                : 0;
            $result['score_raw'] = $totalScore;
            $result['score_max'] = $totalMax;

            return $result;
        }, [
            'module' => $module,
        ]);
    }

    /**
     * Detecta violação paired (cap 50% canônico Wave 24).
     *
     * Heurística: se velocidade alta (≥75% peso) mas qualidade baixa (<50% peso),
     * cap 50% no dimensão velocidade (penaliza gaming "ship fast / break quality").
     *
     * @param  array  $result  Resultado parcial passado por referência implícita.
     * @param  array  $pair  { velocidade, qualidade, rule, racional }
     */
    public function checkPairedViolation(array &$result, array $pair): ?array
    {
        $velKey  = (string) ($pair['velocidade'] ?? '');
        $qualKey = (string) ($pair['qualidade'] ?? '');
        if ($velKey === '' || $qualKey === '') {
            return null;
        }

        $velScore  = $this->resolveRuleScore($result, $velKey);
        $qualScore = $this->resolveRuleScore($result, $qualKey);
        if ($velScore === null || $qualScore === null) {
            return null;
        }

        if ($velScore['percent'] >= 0.75 && $qualScore['percent'] < 0.5) {
            $dimKey = explode('.', $velKey)[0];
            if (isset($result['bucket_dimensions'][$dimKey])) {
                $peso = (int) ($result['bucket_dimensions'][$dimKey]['peso'] ?? 0);
                $capped = (int) round($peso * 0.5);
                $result['bucket_dimensions'][$dimKey]['score'] = $capped;
                $result['bucket_dimensions'][$dimKey]['capped_by_paired'] = true;
            }
            return [
                'pair'       => $velKey . ' x ' . $qualKey,
                'rule'       => (string) ($pair['rule'] ?? ''),
                'racional'   => (string) ($pair['racional'] ?? ''),
                'cap_applied' => '50%',
                'vel_percent'  => round($velScore['percent'], 3),
                'qual_percent' => round($qualScore['percent'], 3),
            ];
        }
        return null;
    }

    /**
     * Resolve score de uma regra interna a uma dimensão.
     *
     * Formato key: `<dimensao>.<regra>` (ex: `F1_pest_e2e.F1_b`).
     * Lê de bucket_dimensions[dim]['regras'][rule] ou estima
     * proporcional a partir do score atual da dimensão.
     *
     * @return array{score: int, peso: int, percent: float}|null
     */
    public function resolveRuleScore(array $result, string $key): ?array
    {
        if (! str_contains($key, '.')) {
            return null;
        }
        [$dim, $rule] = explode('.', $key, 2);
        $dimData = $result['bucket_dimensions'][$dim] ?? $result['core'][$dim] ?? null;
        if (! $dimData) {
            return null;
        }

        // Tenta regra específica em regras[<rule>].
        if (isset($dimData['regras'][$rule])) {
            $regra = $dimData['regras'][$rule];
            $pesoRule = (int) ($regra['peso'] ?? 0);
            $scoreRule = (int) ($regra['current'] ?? $regra['score'] ?? $pesoRule);
            $scoreRule = max(0, min($pesoRule, $scoreRule));
            return [
                'score'   => $scoreRule,
                'peso'    => $pesoRule,
                'percent' => $pesoRule > 0 ? ($scoreRule / $pesoRule) : 0.0,
            ];
        }

        // Fallback proporcional: usa razão score/peso da dimensão inteira.
        $totalScore = (int) ($dimData['score'] ?? 0);
        $totalPeso  = (int) ($dimData['peso'] ?? 1);
        return [
            'score'   => $totalScore,
            'peso'    => $totalPeso,
            'percent' => $totalPeso > 0 ? ($totalScore / $totalPeso) : 0.0,
        ];
    }
}
