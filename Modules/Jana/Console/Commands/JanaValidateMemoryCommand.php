<?php

declare(strict_types=1);

namespace Modules\Jana\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;

/**
 * ONDA 5 S1 — Schema rígido CI híbrido (gate C).
 *
 * Valida frontmatter YAML dos arquivos canônicos em memory/ + Page Charters
 * contra os JSON Schemas em scripts/memory-schemas/*.schema.json.
 *
 * Complementa o workflow CI (.github/workflows/memory-schema-gate.yml — gate A AJV).
 * Aqui é gate LOCAL pré-push. NÃO está agendado no Kernel (auditoria de sentinelas
 * 2026-06-20: o claim anterior "integra cron daily ~06:30 BRT" era FALSO — grep no
 * Kernel.php não retorna este comando). Drift fora-do-PR (edit manual, SSH) depende
 * de rodar local/CI; agendar com --strict é decisão pendente [W].
 *
 * Grace period 14d:
 *   - JANA_VALIDATE_MEMORY_STRICT=false (default) → warning + exit 0
 *   - JANA_VALIDATE_MEMORY_STRICT=true (Wagner sign-off) → exit 1 se violação
 *
 * Uso:
 *   php artisan jana:validate-memory
 *   php artisan jana:validate-memory --path=memory/decisions/
 *   php artisan jana:validate-memory --schema=adr
 *   php artisan jana:validate-memory --strict (force strict ignorando ENV)
 *   php artisan jana:validate-memory --json (output machine-readable)
 *
 * Ver: memory/requisitos/Jana/ONDA-5-DOSSIER-2026-05-13.md §5 S1.
 */
class JanaValidateMemoryCommand extends Command
{
    protected $signature = 'jana:validate-memory
                            {--path= : Pasta/arquivo específico (default: memory/ + resources/js/Pages/**/*.charter.md)}
                            {--schema= : Schema único (adr|spec|runbook|session|handoff|charter); default detecta por path}
                            {--strict : Força exit 1 se violação (ignora ENV grace period)}
                            {--json : Output JSON em vez de tabela}';

    protected $description = 'Valida frontmatter YAML em memory/ contra JSON Schemas (ONDA 5 S1 gate C)';

    /**
     * Mapa schema-key → arquivo + glob default.
     * Centralizado pra reuso entre detection + --schema flag.
     */
    protected array $schemaMap = [
        'adr' => [
            'file' => 'scripts/memory-schemas/adr.schema.json',
            'glob' => 'memory/decisions/[0-9][0-9][0-9][0-9]-*.md',
        ],
        'spec' => [
            'file' => 'scripts/memory-schemas/spec.schema.json',
            'glob' => 'memory/requisitos/*/SPEC.md',
        ],
        'runbook' => [
            'file' => 'scripts/memory-schemas/runbook.schema.json',
            'glob' => 'memory/requisitos/**/RUNBOOK*.md',
        ],
        'session' => [
            'file' => 'scripts/memory-schemas/session.schema.json',
            'glob' => 'memory/sessions/[0-9][0-9][0-9][0-9]-*.md',
        ],
        'handoff' => [
            'file' => 'scripts/memory-schemas/handoff.schema.json',
            'glob' => 'memory/handoffs/[0-9][0-9][0-9][0-9]-*.md',
        ],
        'charter' => [
            'file' => 'scripts/memory-schemas/charter.schema.json',
            'glob' => 'resources/js/Pages/**/*.charter.md',
        ],
    ];

    public function handle(): int
    {
        if (! class_exists(Validator::class)) {
            $this->error('Pacote justinrainbow/json-schema não disponível. Esperado via composer.lock transitive (5.3.4+).');
            return 1;
        }

        $strictFlag = (bool) $this->option('strict');
        $envStrict = filter_var(env('JANA_VALIDATE_MEMORY_STRICT', false), FILTER_VALIDATE_BOOLEAN);
        $strict = $strictFlag || $envStrict;

        $schemaFilter = $this->option('schema');
        $pathFilter = $this->option('path');

        $schemasToRun = $schemaFilter
            ? [$schemaFilter => $this->schemaMap[$schemaFilter] ?? null]
            : $this->schemaMap;

        if ($schemaFilter && ! isset($this->schemaMap[$schemaFilter])) {
            $this->error("Schema desconhecido: {$schemaFilter}. Use: " . implode(', ', array_keys($this->schemaMap)));
            return 1;
        }

        $results = [];
        foreach ($schemasToRun as $key => $config) {
            if (! $config) {
                continue;
            }
            $results[$key] = $this->validateBucket($key, $config, $pathFilter);
        }

        $totalErrors = array_sum(array_column($results, 'errors_count'));
        $totalWarnings = array_sum(array_column($results, 'warnings_count'));
        $totalFiles = array_sum(array_column($results, 'files_count'));

        if ($this->option('json')) {
            $this->line(json_encode([
                'strict' => $strict,
                'total_files' => $totalFiles,
                'total_errors' => $totalErrors,
                'total_warnings' => $totalWarnings,
                'buckets' => $results,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->renderTable($results, $strict, $totalFiles, $totalErrors, $totalWarnings);
        }

        // Grace period: warning não bloqueia se strict=false
        if ($totalErrors > 0 && $strict) {
            if (! $this->option('json')) {
                $this->error("BLOQUEIO — {$totalErrors} violação(ões) com strict=true.");
            }
            return 1;
        }

        // Mensagens human-friendly só fora do modo JSON (mantém saída JSON parseable)
        if (! $this->option('json')) {
            if ($totalErrors > 0) {
                $this->warn("Grace period (strict=false) — {$totalErrors} violação(ões) reportada(s) como warning. Wagner ativa strict via JANA_VALIDATE_MEMORY_STRICT=true.");
            } else {
                $this->info("OK — {$totalFiles} arquivo(s) válido(s).");
            }
        }

        return 0;
    }

    /**
     * Roda validação pra um bucket (schema_key) e retorna estrutura agregada.
     */
    protected function validateBucket(string $key, array $config, ?string $pathFilter): array
    {
        $schemaPath = base_path($config['file']);
        if (! File::exists($schemaPath)) {
            return [
                'schema' => $key,
                'files_count' => 0,
                'errors_count' => 0,
                'warnings_count' => 0,
                'violations' => [],
                'note' => "Schema ausente em {$schemaPath}",
            ];
        }

        $schema = json_decode(File::get($schemaPath));

        // Determina escopo de arquivos a varrer
        $files = $this->collectFiles($config['glob'], $pathFilter);

        $violations = [];
        $errorsCount = 0;
        $warningsCount = 0;

        foreach ($files as $file) {
            $relPath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file);
            $relPath = str_replace('\\', '/', $relPath);

            $front = $this->extractFrontmatter($file);

            if ($front === null) {
                $warningsCount++;
                $violations[] = [
                    'file' => $relPath,
                    'level' => 'warn',
                    'errors' => ['frontmatter ausente (legacy — grace period)'],
                ];
                continue;
            }

            $validator = new Validator();
            $data = $this->arrayToObject($front);
            $validator->validate($data, $schema, Constraint::CHECK_MODE_NORMAL);

            if (! $validator->isValid()) {
                $errorsCount++;
                $violations[] = [
                    'file' => $relPath,
                    'level' => 'error',
                    'errors' => array_map(
                        fn ($e) => trim(($e['property'] ?: '/') . ': ' . $e['message']),
                        $validator->getErrors()
                    ),
                ];
            }
        }

        return [
            'schema' => $key,
            'files_count' => count($files),
            'errors_count' => $errorsCount,
            'warnings_count' => $warningsCount,
            'violations' => $violations,
        ];
    }

    /**
     * Coleta arquivos a validar. Estratégia:
     *   - Se `--path` setado → varre RECURSIVAMENTE esse path procurando *.md cujo
     *     basename bate o padrão filename do glob (ex `RUNBOOK*.md`).
     *   - Sem `--path` → glob real a partir do base_path com o pattern canônico.
     *
     * @return string[] absolute paths
     */
    protected function collectFiles(string $glob, ?string $pathFilter): array
    {
        $base = base_path();

        if ($pathFilter) {
            // Override mode — varre $pathFilter recursivamente e filtra por basename pattern do glob
            $root = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $pathFilter);
            if (! is_dir($root) && ! is_file($root)) {
                return [];
            }
            $basenamePattern = basename($glob); // ex: "[0-9][0-9][0-9][0-9]-*.md" ou "RUNBOOK*.md"
            $files = is_file($root) ? [$root] : $this->walkDir($root, $basenamePattern);
        } else {
            $pattern = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $glob);
            if (str_contains($glob, '**')) {
                $files = $this->globRecursive($pattern);
            } else {
                $files = glob($pattern) ?: [];
            }
        }

        // Exclui index/readme/underscore-prefixed
        $files = array_filter($files, function ($f) {
            $basename = basename($f);
            return ! str_starts_with($basename, '_')
                && ! in_array(strtolower($basename), ['readme.md', 'index.md'], true);
        });

        return array_values($files);
    }

    /**
     * Walk recursivo retornando arquivos cujo basename bate fnmatch pattern.
     */
    protected function walkDir(string $root, string $basenamePattern): array
    {
        $files = [];
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($rii as $file) {
            if ($file->isDir()) {
                continue;
            }
            if (fnmatch($basenamePattern, $file->getFilename())) {
                $files[] = $file->getPathname();
            }
        }
        return $files;
    }

    /**
     * Glob recursivo simulando `**` via SPL iterator.
     * Suffix normalmente é tipo "/RUNBOOK*.md" ou "/*.charter.md" → casamos com fnmatch.
     */
    protected function globRecursive(string $pattern): array
    {
        $parts = explode('**', $pattern, 2);
        $prefix = rtrim($parts[0], DIRECTORY_SEPARATOR);
        $suffix = ltrim($parts[1] ?? '', DIRECTORY_SEPARATOR);

        if (! is_dir($prefix)) {
            return [];
        }

        // Suffix usual: "RUNBOOK*.md" ou "*.charter.md" (já sem leading /).
        // fnmatch faz glob-match pra basename do arquivo.
        $files = [];
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($prefix, \FilesystemIterator::SKIP_DOTS));
        foreach ($rii as $file) {
            if ($file->isDir()) {
                continue;
            }
            $basename = $file->getFilename();
            if (fnmatch($suffix, $basename)) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * Extrai frontmatter YAML do início do .md. Retorna array ou null.
     */
    protected function extractFrontmatter(string $file): ?array
    {
        $content = File::get($file);
        // Aceita BOM UTF-8 no início + EOL diferente + EOF logo após o fechador
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        if (! preg_match('/^---\r?\n(.*?)\r?\n---(\r?\n|$)/s', $content, $m)) {
            return null;
        }
        $yaml = $m[1];

        try {
            if (class_exists(\Symfony\Component\Yaml\Yaml::class)) {
                $parsed = \Symfony\Component\Yaml\Yaml::parse($yaml);
                return is_array($parsed) ? $parsed : null;
            }
        } catch (\Throwable $e) {
            return ['__parse_error' => $e->getMessage()];
        }

        // Fallback minimalista (não esperado em runtime Laravel — Symfony Yaml sempre presente)
        return null;
    }

    /**
     * Converte array recursivo em stdClass (justinrainbow/json-schema espera objeto).
     * Se frontmatter for vazio/scalar/invalid, devolve stdClass vazio (validator aponta required missing).
     */
    protected function arrayToObject(array $arr): \stdClass
    {
        if (empty($arr)) {
            return new \stdClass();
        }
        // JSON_INVALID_UTF8_SUBSTITUTE — alguns ADRs antigos têm bytes binários no title
        // (ex: !!binary YAML tag base64-decoded vira raw bytes). Substituímos por U+FFFD
        // pra permitir validation; bytes problemáticos viram char replacement no validator.
        $flags = JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;
        $encoded = json_encode($arr, $flags);
        if ($encoded === false) {
            return new \stdClass();
        }
        $decoded = json_decode($encoded);
        // Se top level for array (raríssimo — YAML raiz é hash), wrap em object
        if (is_array($decoded)) {
            return (object) ['__list_root' => $decoded];
        }
        return $decoded instanceof \stdClass ? $decoded : new \stdClass();
    }

    /**
     * Renderiza tabela markdown-friendly pra terminal.
     */
    protected function renderTable(array $results, bool $strict, int $totalFiles, int $totalErrors, int $totalWarnings): void
    {
        $rows = [];
        foreach ($results as $key => $r) {
            $rows[] = [
                $key,
                $r['files_count'],
                $r['errors_count'],
                $r['warnings_count'],
                $r['errors_count'] === 0 ? 'OK' : 'FAIL',
            ];
        }
        $this->table(['Schema', 'Files', 'Errors', 'Warnings', 'Status'], $rows);

        $modeMsg = $strict ? 'STRICT (bloqueia)' : 'GRACE PERIOD (warning)';
        $this->line('');
        $this->line("Modo: {$modeMsg}");
        $this->line("Total: {$totalFiles} arquivo(s) · {$totalErrors} erro(s) · {$totalWarnings} aviso(s)");

        foreach ($results as $r) {
            if (empty($r['violations'])) {
                continue;
            }
            $this->line('');
            $this->line("<comment>== {$r['schema']} ==</comment>");
            foreach ($r['violations'] as $v) {
                $tag = $v['level'] === 'error' ? '<error>ERR</error>' : '<comment>WARN</comment>';
                $this->line("{$tag} {$v['file']}");
                foreach ($v['errors'] as $e) {
                    $this->line("       └─ {$e}");
                }
            }
        }
    }
}
