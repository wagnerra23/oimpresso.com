<?php

declare(strict_types=1);

namespace Modules\Governance\Services;

use Symfony\Component\Yaml\Yaml;

/**
 * ScopedScorecardEvaluator — implementa Scoped Scorecards v4 (ADR 0160).
 *
 * Substitui rubrica monolítica v3 por rubrica POR BUCKET — cada Modules/<X>/
 * declara `governance.bucket` no module.json, e o avaliador carrega o YAML
 * `memory/scorecards/<bucket>.yaml` correspondente.
 *
 * Buckets canônicos Wave 19:
 *   - vestuario             — varejo físico vertical (foco ROTA LIVRE)
 *   - governance            — auto-recursivo (Governance + Jana sub-domínio policy)
 *   - jana                  — IA conversacional + memória persistente
 *   - functional_horizontal — Sells/Repair/Project/etc (módulos generalistas)
 *
 * Cada YAML expõe:
 *   - metadata: bucket, versao, ADR
 *   - core: D1 multi-tenant + D8 security (compartilhados entre buckets — Tier 0)
 *   - bucket_dimensions: dimensões específicas do vertical
 *   - paired_indicators: detecção de gaming (velocidade vs qualidade) — Wave 24
 *   - calculo: meta_bucket, threshold cor, normalização
 *
 * Multi-tenant Tier 0 ([ADR 0093](../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 * todo módulo é re-avaliado isolando seu próprio scope; service NÃO toca banco
 * (filesystem-only) — multi-tenant preserved by design.
 *
 * Dual-mode (Wave 21): convive com `ModuleGradeService::grade()` v3 enquanto
 * `config('governance.v4_enabled') === false` (default).
 *
 * @see memory/decisions/0160-scoped-scorecards-v4-bucket-yaml.md
 * @see memory/scorecards/<bucket>.yaml
 * @see Modules/Governance/Services/ModuleGradeService.php (gradeV4 entrypoint)
 */
class ScopedScorecardEvaluator
{
    /** Caminho base pra resolução de Modules/<X>/ e memory/scorecards/. */
    private string $basePath;

    public function __construct(?string $basePath = null)
    {
        $this->basePath = $basePath ?? base_path();
    }

    /**
     * Carrega scorecard YAML do bucket declarado em Modules/<X>/module.json.
     *
     * Retorna [] se:
     *  - module.json não existe (módulo inválido)
     *  - module.json não declara `governance.bucket`
     *  - YAML do bucket não existe (Wave 19 só publicou 4 buckets — outros nascem em ondas futuras)
     *
     * Caller (gradeV4) decide se cai pra v3 (back-compat) ou retorna score zero.
     *
     * @return array<string,mixed>
     */
    public function loadScorecardForModule(string $module): array
    {
        $moduleJsonPath = $this->basePath . "/Modules/{$module}/module.json";
        if (! file_exists($moduleJsonPath)) {
            return [];
        }

        $raw = @file_get_contents($moduleJsonPath);
        if ($raw === false) {
            return [];
        }
        $json = json_decode($raw, true);
        if (! is_array($json)) {
            return [];
        }

        $bucket = $json['governance']['bucket'] ?? null;
        if (! is_string($bucket) || $bucket === '') {
            return [];
        }

        $yamlPath = $this->basePath . "/memory/scorecards/{$bucket}.yaml";
        if (! file_exists($yamlPath)) {
            return [];
        }

        $parsed = Yaml::parseFile($yamlPath);

        return is_array($parsed) ? $parsed : [];
    }

    /**
     * Avalia scorecard carregado + retorna breakdown por dimensão.
     *
     * Estrutura retornada:
     *   [
     *     'module'             => string,
     *     'bucket'             => string,            // do YAML.metadata.bucket
     *     'core'               => [...],             // D1 + D8 (peso fixo entre buckets)
     *     'bucket_dimensions'  => [...],             // específicas do vertical
     *     'paired_violations'  => [...],             // gaming detectado (Wave 24 aplica cap)
     *     'score_total'        => int,               // soma core + bucket_dimensions
     *     'meta_bucket'        => int,               // meta declarada no YAML
     *   ]
     *
     * @param array<string,mixed> $scorecard
     * @return array<string,mixed>
     */
    public function evaluateScorecard(string $module, array $scorecard): array
    {
        $result = [
            'module'            => $module,
            'bucket'            => $scorecard['metadata']['bucket'] ?? 'unknown',
            'core'              => [],
            'bucket_dimensions' => [],
            'paired_violations' => [],
            'score_total'       => 0,
            'meta_bucket'       => (int) ($scorecard['calculo']['meta_bucket'] ?? 80),
        ];

        // Core dimensions (D1 multi-tenant + D8 security — Tier 0 cross-bucket)
        foreach (($scorecard['core'] ?? []) as $dimKey => $dim) {
            if (! is_array($dim)) {
                continue;
            }
            $result['core'][$dimKey] = $this->evaluateDimension($module, $dim);
        }

        // Bucket dimensions (específicas do vertical — ex: vestuario.sazonalidade)
        foreach (($scorecard['bucket_dimensions'] ?? []) as $dimKey => $dim) {
            if (! is_array($dim)) {
                continue;
            }
            $result['bucket_dimensions'][$dimKey] = $this->evaluateDimension($module, $dim);
        }

        // Paired indicators (detecção velocidade-vs-qualidade gameado)
        // Wave 21 (mínimo viável): listagem simbólica.
        // Wave 24: aplicar cap 50% se par detectado violando.
        foreach (($scorecard['paired_indicators'] ?? []) as $pair) {
            if (! is_array($pair)) {
                continue;
            }
            $violation = $this->checkPairedViolation($result, $pair);
            if ($violation !== null) {
                $result['paired_violations'][] = $violation;
            }
        }

        $coreScore   = $this->sumScores($result['core']);
        $bucketScore = $this->sumScores($result['bucket_dimensions']);

        $result['score_total'] = $coreScore + $bucketScore;

        return $result;
    }

    /**
     * Avalia uma dimensão (D1, D8, ou qualquer bucket_dimension) — itera rules
     * e soma pesos onde `detect` retorna true.
     *
     * @param array<string,mixed> $dim
     * @return array{peso:int, score:int, rules:array<int,array<string,mixed>>}
     */
    private function evaluateDimension(string $module, array $dim): array
    {
        $totalScore = 0;
        $totalPeso  = (int) ($dim['peso'] ?? 0);
        $breakdown  = [];

        foreach (($dim['rules'] ?? []) as $rule) {
            if (! is_array($rule)) {
                continue;
            }
            $rulePeso = (int) ($rule['peso'] ?? 0);
            $detected = $this->detectRule($module, $rule['detect'] ?? []);
            $score    = $detected ? $rulePeso : 0;
            $totalScore += $score;

            $breakdown[] = [
                'id'        => (string) ($rule['id'] ?? '?'),
                'descricao' => (string) ($rule['descricao'] ?? ''),
                'peso'      => $rulePeso,
                'score'     => $score,
                'detected'  => $detected,
            ];
        }

        return [
            'peso'  => $totalPeso,
            'score' => $totalScore,
            'rules' => $breakdown,
        ];
    }

    /**
     * Dispatcher de detection types — Wave 21 mínimo viável.
     *
     * Tipos implementados: file_exists, grep, ratio, file_age.
     * Tipos pendentes (pass-through TRUE, não bloqueia migração v3→v4):
     *   - ast_scan (Wave 22)
     *   - yaml_lookup (Wave 22)
     *   - ci_health (Wave 23 — depende otel)
     *   - otel_query (Wave 23)
     *   - sql_check (Wave 23)
     *
     * Pass-through TRUE é intencional: scorecards v4 ainda em maturação;
     * bloquear módulos por tipos não-implementados travaria adoção.
     *
     * @param array<string,mixed> $detect
     */
    private function detectRule(string $module, array $detect): bool
    {
        $tipo = (string) ($detect['tipo'] ?? '');

        return match ($tipo) {
            'file_exists' => $this->detectFileExists($module, $detect),
            'grep'        => $this->detectGrep($module, $detect),
            'ratio'       => $this->detectRatio($module, $detect),
            'file_age'    => $this->detectFileAge($module, $detect),
            default       => true, // pass-through Wave 21 — tipos pendentes não bloqueiam
        };
    }

    /**
     * file_exists — arquivo (ou glob) existe relativo ao basePath.
     *
     * @param array<string,mixed> $detect
     */
    private function detectFileExists(string $module, array $detect): bool
    {
        $path = $this->resolvePath($module, (string) ($detect['path'] ?? ''));
        if ($path === '') {
            return false;
        }
        $absolute = $this->basePath . '/' . ltrim($path, '/');

        // Suporta glob (ex: Modules/<X>/Services/*.php)
        if (str_contains($absolute, '*') || str_contains($absolute, '?')) {
            return count(glob($absolute) ?: []) > 0;
        }

        return file_exists($absolute);
    }

    /**
     * grep — content match em arquivo(s) (suporta glob no path).
     *
     * @param array<string,mixed> $detect
     */
    private function detectGrep(string $module, array $detect): bool
    {
        $path = $this->resolvePath($module, (string) ($detect['path'] ?? ''));
        if ($path === '') {
            return false;
        }
        $expect = (string) ($detect['expect'] ?? '');
        if ($expect === '') {
            return false;
        }

        $absolute = $this->basePath . '/' . ltrim($path, '/');
        $files    = (str_contains($absolute, '*') || str_contains($absolute, '?'))
            ? (glob($absolute) ?: [])
            : (file_exists($absolute) ? [$absolute] : []);

        foreach ($files as $f) {
            $content = @file_get_contents($f) ?: '';
            // Tentamos preg primeiro; fallback pra str_contains se regex inválida.
            $pattern = '/' . str_replace('/', '\/', $expect) . '/i';
            $match   = @preg_match($pattern, $content);
            if ($match === 1) {
                return true;
            }
            if ($match === false && stripos($content, $expect) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * ratio — razão `numerator/denominator` ≥ `coverage_target` (0..1).
     *
     * Usado pra cobertura tipo Pest/Controller (D2 v3 → Wave 21 reaproveita
     * mesma heurística mas declarada em YAML).
     *
     * @param array<string,mixed> $detect
     */
    private function detectRatio(string $module, array $detect): bool
    {
        $numPath = $this->resolvePath($module, (string) ($detect['numerator'] ?? ''));
        $denPath = $this->resolvePath($module, (string) ($detect['denominator'] ?? ''));
        if ($numPath === '' || $denPath === '') {
            return false;
        }

        $numAbs = $this->basePath . '/' . ltrim($numPath, '/');
        $denAbs = $this->basePath . '/' . ltrim($denPath, '/');

        $num = count(glob($numAbs) ?: []);
        $den = max(count(glob($denAbs) ?: []), 1);

        $target = (float) ($detect['coverage_target'] ?? 0);

        return ($num / $den) >= $target;
    }

    /**
     * file_age — arquivo modificado nos últimos `age_max_days` (frescor docs).
     *
     * @param array<string,mixed> $detect
     */
    private function detectFileAge(string $module, array $detect): bool
    {
        $path = $this->resolvePath($module, (string) ($detect['path'] ?? ''));
        if ($path === '') {
            return false;
        }
        $absolute = $this->basePath . '/' . ltrim($path, '/');
        if (! file_exists($absolute)) {
            return false;
        }
        $maxDays = (int) ($detect['age_max_days'] ?? 999);
        $ageDays = (time() - filemtime($absolute)) / 86400;

        return $ageDays <= $maxDays;
    }

    /**
     * Paired indicators (Wave 21 mínimo viável) — apenas observação,
     * sem aplicar cap. Wave 24 vai implementar a regra: se par
     * (velocidade↑, qualidade↓) detectado → score cap 50%.
     *
     * @param array<string,mixed> $result
     * @param array<string,mixed> $pair
     * @return array<string,mixed>|null
     */
    private function checkPairedViolation(array $result, array $pair): ?array
    {
        // Wave 21: stub. Retorna null sempre (sem violação). Wave 24 implementa.
        return null;
    }

    /**
     * Resolve placeholder Modules/Vestuario → Modules/<module> nos paths YAML.
     *
     * Convenção dos YAMLs Wave 19: paths declarados com 'Modules/Vestuario/...'
     * como template, substituídos pelo módulo sendo avaliado em runtime.
     */
    private function resolvePath(string $module, string $path): string
    {
        if ($path === '') {
            return '';
        }

        return str_replace('Modules/Vestuario', "Modules/{$module}", $path);
    }

    /**
     * Soma scores de um conjunto de dimensões.
     *
     * @param array<string,array{score:int}|mixed> $dimensions
     */
    private function sumScores(array $dimensions): int
    {
        $total = 0;
        foreach ($dimensions as $dim) {
            if (is_array($dim) && isset($dim['score'])) {
                $total += (int) $dim['score'];
            }
        }

        return $total;
    }
}
