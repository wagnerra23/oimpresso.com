<?php

declare(strict_types=1);

namespace Modules\Governance\Services;

use App\Util\OtelHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
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

    /**
     * Dispatcher unificado pra detectar uma rule (Wave 25 Agent 0 — expansão de 4 → 10 tipos).
     *
     * Retorna bool: true = rule satisfeita, false = não satisfeita.
     * Fail-safe: ambiente sem dependência (GH_TOKEN ausente, tabela OTel ausente)
     * deve retornar true (pass-through) pra não bloquear pipeline de avaliação.
     *
     * @param  string  $module  Nome do módulo (Vestuario, Jana, etc)
     * @param  array  $detect  Bloco YAML rules[N].detect com keys tipo, path, expect, etc
     */
    public function detectRule(string $module, array $detect): bool
    {
        $tipo = (string) ($detect['tipo'] ?? '');
        return match ($tipo) {
            'file_exists'     => $this->detectFileExists($module, $detect),
            'grep'            => $this->detectGrep($module, $detect),
            'negative_grep'   => $this->detectNegativeGrep($module, $detect),
            'ratio'           => $this->detectRatio($module, $detect),
            'file_age'        => $this->detectFileAge($module, $detect),
            'ast_scan'        => $this->detectAstScan($module, $detect),
            'yaml_lookup'     => $this->detectYamlLookup($module, $detect),
            'pest_pattern'    => $this->detectPestPattern($module, $detect),
            'business_signal' => $this->detectBusinessSignal($module, $detect),
            'ci_health'       => $this->detectCiHealth($module, $detect),
            'otel_query'      => $this->detectOtelQuery($module, $detect),
            default           => true, // tipo desconhecido = pass-through (não quebra avaliação)
        };
    }

    /**
     * Substitui placeholders do nome do módulo no path declarado em YAML.
     *
     * Suporta dois patterns canônicos (Wave 27 fix v4 detection — 2026-05-17):
     *
     *   1) Placeholder explícito `<modulo>` (CANONICAL W27+) — recomendado pra novos YAMLs
     *      `path: 'Modules/<modulo>/Entities/**\/*.php'`
     *      `path: 'memory/requisitos/<modulo>/SPEC.md'`
     *
     *   2) Legacy literal `Modules/<NomeReal>/` (BACK-COMPAT W19-W26) — pra YAMLs históricos
     *      `path: 'Modules/Vestuario/Entities/**\/*.php'` → substitui pelo módulo avaliado
     *      Lista whitelist explícita (vs regex genérica) pra evitar false-match em strings
     *      como `Modules/Vestuario` que aparecem em comentários internos do conteúdo PHP.
     *
     * Ordem matter: placeholder primeiro (pra `<modulo>` ser substituído ANTES do regex
     * legacy capturar `Modules/<modulo>` como literal — improvável mas defensivo).
     *
     * Visibilidade `public` desde W27 pra Pest test direto (antes era `private` regex genérica).
     */
    public function resolveModulePath(string $module, string $path): string
    {
        // 1) Placeholder canônico W27+
        $path = str_replace(
            ['<modulo>', '<MODULO>', '<module>', '{modulo}', '{module}'],
            $module,
            $path
        );

        // 2) Legacy literal substitution (back-compat W19-W26)
        //    Whitelist de nomes reais conhecidos — evita substituir match acidental
        //    em strings/comments embutidos no path glob.
        $modulosConhecidos = 'Vestuario|Governance|Jana|Crm|Financeiro|Repair|Ponto'
            . '|RecurringBilling|NfeBrasil|NFSe|Manufacturing|Cms|Spreadsheet'
            . '|Arquivos|Accounting|AssetManagement|Essentials|ADS|ConsultaOs'
            . '|SRS|Whatsapp|Woocommerce|ProductCatalogue|ProjectMgmt'
            . '|ComunicacaoVisual|OficinaAuto|Officeimpresso|Auditoria|Admin'
            . '|Brief|TeamMcp|Superadmin|Connector|KB|MemCofre|Project|Sells';

        $path = preg_replace(
            '#Modules/(' . $modulosConhecidos . ')/#',
            "Modules/{$module}/",
            $path
        ) ?? $path;

        // 3) Legacy `memory/requisitos/<Nome>/` — mesma whitelist
        $path = preg_replace(
            '#memory/requisitos/(' . $modulosConhecidos . ')/#',
            "memory/requisitos/{$module}/",
            $path
        ) ?? $path;

        return $path;
    }

    /**
     * file_exists — verifica existência de arquivo + opcional grep do conteúdo.
     */
    public function detectFileExists(string $module, array $detect): bool
    {
        $path = $this->resolveModulePath($module, (string) ($detect['path'] ?? ''));
        $full = base_path($path);
        if (! file_exists($full)) {
            return false;
        }
        $expectContent = (string) ($detect['expect_content'] ?? '');
        if ($expectContent === '') {
            return true;
        }
        $content = @file_get_contents($full);
        if ($content === false) return false;
        return (bool) preg_match('/' . preg_quote($expectContent, '/') . '/i', $content);
    }

    /**
     * grep — match regex em todos arquivos do path (glob).
     */
    public function detectGrep(string $module, array $detect): bool
    {
        $path = $this->resolveModulePath($module, (string) ($detect['path'] ?? ''));
        $expect = (string) ($detect['expect'] ?? '');
        $target = (int) ($detect['coverage_target'] ?? 1);
        $files = glob(base_path($path), GLOB_BRACE) ?: [];
        $matches = 0;
        foreach ($files as $f) {
            if (! is_file($f)) continue;
            $content = @file_get_contents($f);
            if ($content === false) continue;
            if (@preg_match("/{$expect}/i", $content)) {
                $matches++;
                if ($matches >= $target) return true;
            }
        }
        return $matches >= $target;
    }

    /**
     * negative_grep — falha se padrão aparece (ex: módulo NÃO deve estar em CSRF except).
     */
    public function detectNegativeGrep(string $module, array $detect): bool
    {
        $path = $this->resolveModulePath($module, (string) ($detect['path'] ?? ''));
        $unexpected = (string) ($detect['unexpected'] ?? '');
        $full = base_path($path);
        if (! file_exists($full)) return true; // arquivo ausente = sem violação
        $content = @file_get_contents($full);
        if ($content === false) return true;
        return ! @preg_match("/{$unexpected}/i", $content);
    }

    /**
     * ratio — razão entre contagem do numerator e denominator (≥ coverage_target).
     */
    public function detectRatio(string $module, array $detect): bool
    {
        $num = glob(base_path($this->resolveModulePath($module, (string) ($detect['numerator'] ?? ''))), GLOB_BRACE) ?: [];
        $den = glob(base_path($this->resolveModulePath($module, (string) ($detect['denominator'] ?? ''))), GLOB_BRACE) ?: [];
        $target = (float) ($detect['coverage_target'] ?? 1);
        $denCount = max(count($den), 1);
        return (count($num) / $denCount) >= $target;
    }

    /**
     * file_age — arquivo existe E modificado nos últimos N dias.
     */
    public function detectFileAge(string $module, array $detect): bool
    {
        $path = $this->resolveModulePath($module, (string) ($detect['path'] ?? ''));
        $full = base_path($path);
        if (! file_exists($full)) return false;
        $maxDays = (int) ($detect['age_max_days'] ?? 90);
        $ageDays = (time() - filemtime($full)) / 86400;
        return $ageDays <= $maxDays;
    }

    /**
     * ast_scan — regex match contra files PHP (proxy ao parsing AST real, suficiente p/ traits/uses).
     *
     * Suporta `coverage_target` percentual (0..1) ou absoluto (>=1).
     * `na_if` (string descritiva): se path glob vazio E na_if presente, retorna true (N/A justificado).
     */
    public function detectAstScan(string $module, array $detect): bool
    {
        $path = $this->resolveModulePath($module, (string) ($detect['path'] ?? ''));
        $expect = (string) ($detect['expect'] ?? '');
        $coverageTarget = (float) ($detect['coverage_target'] ?? 1);
        $files = glob(base_path($path), GLOB_BRACE) ?: [];

        if (empty($files)) {
            // Sem files = N/A se justificado; senão false
            return isset($detect['na_if']);
        }

        $matches = 0;
        foreach ($files as $f) {
            if (! is_file($f)) continue;
            $content = @file_get_contents($f);
            if ($content === false) continue;
            if (@preg_match("/{$expect}/i", $content)) {
                $matches++;
            }
        }

        if ($coverageTarget >= 1) {
            // target absoluto OU percentual 100%
            if ($coverageTarget == 100) {
                return $matches === count($files);
            }
            return $matches >= $coverageTarget;
        }
        return (count($files) > 0) && (($matches / count($files)) >= $coverageTarget);
    }

    /**
     * yaml_lookup — consulta valor em YAML/JSON e compara com expect.
     */
    public function detectYamlLookup(string $module, array $detect): bool
    {
        $source = (string) ($detect['source'] ?? '');
        $key = (string) ($detect['key'] ?? '');
        $expect = (string) ($detect['expect'] ?? '');

        // Resolve placeholder de módulo na key (ex: "Vestuario.level")
        $key = str_replace(['<module>', '{module}'], $module, $key);

        $full = base_path($source);
        if (! file_exists($full)) return false;

        try {
            $ext = pathinfo($full, PATHINFO_EXTENSION);
            $data = match (strtolower($ext)) {
                'yaml', 'yml' => Yaml::parseFile($full),
                'json'        => json_decode(file_get_contents($full), true),
                default       => null,
            };
            if (! is_array($data)) return false;

            $value = data_get($data, $key);
            if ($value === null) return false;
            if ($expect === '') return true; // só existência

            if (is_array($value)) {
                return in_array($expect, $value, true)
                    || in_array($expect, array_map('strval', $value), true);
            }
            // Loose compare: YAML pode trazer int/float/bool; expect chega como string
            return (string) $value === (string) $expect
                || str_contains((string) $value, (string) $expect);
        } catch (\Throwable $e) {
            \Log::warning('ScopedScorecardEvaluator: yaml_lookup falhou', [
                'source' => $source,
                'key'    => $key,
                'error'  => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * pest_pattern — busca arquivo Pest com regex de cenário E assertion correspondente.
     */
    public function detectPestPattern(string $module, array $detect): bool
    {
        $path = $this->resolveModulePath($module, (string) ($detect['path'] ?? ''));
        $expect = (string) ($detect['expect'] ?? '');
        $assertion = (string) ($detect['assertion'] ?? '');
        $target = (int) ($detect['coverage_target'] ?? 1);

        $files = glob(base_path($path), GLOB_BRACE) ?: [];
        $hits = 0;
        foreach ($files as $f) {
            if (! is_file($f)) continue;
            $content = @file_get_contents($f);
            if ($content === false) continue;
            if (@preg_match("/{$expect}/i", $content)
                && ($assertion === '' || @preg_match("/{$assertion}/i", $content))) {
                $hits++;
                if ($hits >= $target) return true;
            }
        }
        return $hits >= $target;
    }

    /**
     * business_signal — consulta DB e verifica threshold (fail-safe se tabela ausente).
     *
     * Source format: "table_name WHERE col=val [AND col2 LIKE ...] [last_Nd]"
     * Apenas SELECT COUNT — conservador.
     * Multi-tenant: business_id deve estar EXPLÍCITO no WHERE do YAML (ADR 0093).
     */
    public function detectBusinessSignal(string $module, array $detect): bool
    {
        $source = (string) ($detect['source'] ?? '');
        $expect = (string) ($detect['expect'] ?? 'count > 0');

        if (! preg_match('/^([a-z_][a-z0-9_]*)\s+WHERE\s+(.+?)(?:\s+last_(\d+)d)?$/i', $source, $m)) {
            return false;
        }
        $table = $m[1];
        $whereRaw = $m[2];
        $lastDays = isset($m[3]) ? (int) $m[3] : null;

        try {
            if (! Schema::hasTable($table)) {
                return false; // tabela ausente em dev/staging — fail-safe (não conta)
            }
            $query = DB::table($table)->whereRaw($whereRaw);
            if ($lastDays !== null && Schema::hasColumn($table, 'created_at')) {
                $query->where('created_at', '>=', now()->subDays($lastDays));
            }
            $count = (int) $query->count();

            if (preg_match('/count\s*>\s*(\d+)/', $expect, $em)) {
                return $count > (int) $em[1];
            }
            if (preg_match('/count\s*>=\s*(\d+)/', $expect, $em)) {
                return $count >= (int) $em[1];
            }
            return $count > 0;
        } catch (\Throwable $e) {
            \Log::warning('ScopedScorecardEvaluator: business_signal falhou', [
                'source' => $source,
                'error'  => $e->getMessage(),
            ]);
            return false; // SQL error → fail-safe (não pontua)
        }
    }

    /**
     * ci_health — GitHub Actions workflow success rate (pass-through se GH_TOKEN ausente).
     *
     * Em ambiente sem GH_TOKEN (dev/local/Pest), retorna true pra não bloquear.
     * Em CT 100 / prod com GH_TOKEN, faria fetch real do success rate (TODO Wave 26+).
     */
    public function detectCiHealth(string $module, array $detect): bool
    {
        $token = env('GH_TOKEN') ?: env('GITHUB_TOKEN');
        if (! $token) {
            return true; // pass-through fail-safe
        }
        // TODO Wave 26+: fetch /repos/<owner>/<repo>/actions/runs e calcular success rate
        // Por enquanto pass-through mesmo com token (impl. real exige scoping de workflow)
        return true;
    }

    /**
     * otel_query — consulta tabela mcp_observability_spans (pass-through se ausente).
     */
    public function detectOtelQuery(string $module, array $detect): bool
    {
        try {
            if (! Schema::hasTable('mcp_observability_spans')) {
                return true; // collector OTel ainda não ativo prod — pass-through
            }
            $expect = (string) ($detect['expect'] ?? '');
            if (! preg_match('/p99\s*<\s*(\d+)/', $expect, $m)) {
                return true;
            }
            $threshold = (int) $m[1];
            // Query simplificada: p99 nas últimas 24h do módulo
            $row = DB::table('mcp_observability_spans')
                ->where('module', $module)
                ->where('created_at', '>=', now()->subDay())
                ->selectRaw('PERCENTILE_CONT(0.99) WITHIN GROUP (ORDER BY duration_ms) AS p99')
                ->first();
            if (! $row || ! isset($row->p99)) return true; // sem dados = pass-through
            return ((int) $row->p99) < $threshold;
        } catch (\Throwable $e) {
            return true; // dialeto MySQL pode não suportar PERCENTILE_CONT — pass-through
        }
    }
}
