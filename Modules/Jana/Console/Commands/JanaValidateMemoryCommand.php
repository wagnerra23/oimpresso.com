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
 * ROTEAMENTO (2026-08-11): as famílias vêm de `scripts/memory-schemas/familias.json`,
 * a mesma fonte que o `scripts/memory-schemas/validate.mjs` consome. Antes disso esta
 * classe mantinha `$schemaMap` à mão e tinha DIVERGIDO: 7 famílias contra as 9 do .mjs
 * (faltavam `briefing` e `reference`). Não acrescente família aqui — acrescente no JSON.
 * Nada de PHP chamando node: cada lado lê o JSON no seu runtime, então este comando
 * continua PHP-first (nenhuma dependência de node, que no cron do Hostinger nem está
 * no PATH — proibicoes §5 2026-08-08).
 *
 * Grace period 14d:
 *   - JANA_VALIDATE_MEMORY_STRICT=false (default) → warning + exit 0
 *   - JANA_VALIDATE_MEMORY_STRICT=true (Wagner sign-off) → exit 1 se violação
 * Por FAMÍLIA: as marcadas `grace: true` no JSON (hoje `briefing` e `reference`) são
 * warn-only mesmo com strict — espelha o `grace: true` da matriz do memory-schema-gate.yml.
 * Sem isso, herdar as 2 famílias novas transformaria `--strict` num bloqueio sobre ~198
 * arquivos legados que o CI deixa passar de propósito (ADR 0314: gate novo nasce grace).
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
                            {--schema= : Schema único (keys em scripts/memory-schemas/familias.json); default: todos}
                            {--strict : Força exit 1 se violação (ignora ENV grace period)}
                            {--json : Output JSON em vez de tabela}';

    protected $description = 'Valida frontmatter YAML em memory/ contra JSON Schemas (ONDA 5 S1 gate C)';

    /** Path (relativo à raiz) da fonte única do roteamento — ver docblock da classe. */
    protected const FAMILIAS_JSON = 'scripts/memory-schemas/familias.json';

    /** Cache do mapa derivado do JSON. null = ainda não carregado. */
    protected ?array $schemaMap = null;

    /**
     * Mapa schema-key → ['file' => schema, 'glob' => padrão, 'grace' => bool].
     * DERIVADO de familias.json — não existe lista à mão nesta classe.
     *
     * Lança em vez de devolver mapa vazio: sem roteamento, varrer zero arquivo e
     * reportar "OK — 0 arquivo(s) válido(s)" seria verde por não-execução, que é o
     * defeito que este comando existe pra pegar.
     *
     * @throws \RuntimeException quando o JSON está ausente ou malformado
     */
    protected function schemaMap(): array
    {
        if ($this->schemaMap !== null) {
            return $this->schemaMap;
        }

        $path = base_path(self::FAMILIAS_JSON);
        if (! File::exists($path)) {
            throw new \RuntimeException("Roteamento ausente: {$path}. É a fonte única das famílias (compartilhada com scripts/memory-schemas/validate.mjs).");
        }

        $bruto = json_decode(File::get($path), true);
        if (! is_array($bruto) || ! is_array($bruto['familias'] ?? null) || $bruto['familias'] === []) {
            throw new \RuntimeException("Roteamento inválido em {$path}: esperava a chave 'familias' com ao menos uma entrada.");
        }

        $map = [];
        foreach ($bruto['familias'] as $i => $f) {
            foreach (['key', 'schema', 'glob'] as $campo) {
                if (! isset($f[$campo]) || ! is_string($f[$campo]) || $f[$campo] === '') {
                    throw new \RuntimeException("Família #{$i} em {$path} sem '{$campo}' string.");
                }
            }
            if (isset($map[$f['key']])) {
                throw new \RuntimeException("Key duplicada em {$path}: '{$f['key']}'.");
            }
            $map[$f['key']] = [
                'file' => $f['schema'],
                'glob' => $f['glob'],
                'grace' => ($f['grace'] ?? false) === true,
            ];
        }

        return $this->schemaMap = $map;
    }

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

        try {
            $schemaMap = $this->schemaMap();
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            return 1;
        }

        if ($schemaFilter && ! isset($schemaMap[$schemaFilter])) {
            $this->error("Schema desconhecido: {$schemaFilter}. Use: " . implode(', ', array_keys($schemaMap)));
            return 1;
        }

        $schemasToRun = $schemaFilter ? [$schemaFilter => $schemaMap[$schemaFilter]] : $schemaMap;

        $results = [];
        foreach ($schemasToRun as $key => $config) {
            $results[$key] = $this->validateBucket($key, $config, $pathFilter);
        }

        $totalErrors = array_sum(array_column($results, 'errors_count'));
        // Só famílias NÃO-grace podem bloquear — espelha `grace: true` da matriz do CI.
        // Contamos separado (em vez de zerar errors_count) pra a tabela seguir mostrando
        // a dívida real: silenciar o número seria esconder, não graciar.
        $blockingErrors = array_sum(array_column(
            array_filter($results, fn ($r) => ! ($r['grace'] ?? false)),
            'errors_count'
        ));
        $graceErrors = $totalErrors - $blockingErrors;
        $totalWarnings = array_sum(array_column($results, 'warnings_count'));
        $totalFiles = array_sum(array_column($results, 'files_count'));

        if ($this->option('json')) {
            $this->line(json_encode([
                'strict' => $strict,
                'total_files' => $totalFiles,
                'total_errors' => $totalErrors,
                'blocking_errors' => $blockingErrors,
                'grace_errors' => $graceErrors,
                'total_warnings' => $totalWarnings,
                'buckets' => $results,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->renderTable($results, $strict, $totalFiles, $totalErrors, $totalWarnings);
        }

        // Grace period: warning não bloqueia se strict=false
        if ($blockingErrors > 0 && $strict) {
            if (! $this->option('json')) {
                $this->error("BLOQUEIO — {$blockingErrors} violação(ões) com strict=true.");
            }
            return 1;
        }

        // Mensagens human-friendly só fora do modo JSON (mantém saída JSON parseable)
        if (! $this->option('json')) {
            if ($graceErrors > 0) {
                $this->warn("Grace por família — {$graceErrors} violação(ões) em famílias `grace` (não bloqueiam nem com strict; mesma regra da matriz do CI).");
            }
            if ($blockingErrors > 0) {
                $this->warn("Grace period (strict=false) — {$blockingErrors} violação(ões) reportada(s) como warning. Wagner ativa strict via JANA_VALIDATE_MEMORY_STRICT=true.");
            } elseif ($graceErrors === 0) {
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
                'grace' => $config['grace'] ?? false,
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
            'grace' => $config['grace'] ?? false,
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
            $grace = $r['grace'] ?? false;
            $rows[] = [
                $key . ($grace ? ' [grace]' : ''),
                $r['files_count'],
                $r['errors_count'],
                $r['warnings_count'],
                // Família grace nunca bloqueia — dizer FAIL nela seria afirmar um
                // enforcement que ela não tem (lápide §5 2026-07-16).
                $r['errors_count'] === 0 ? 'OK' : ($grace ? 'WARN' : 'FAIL'),
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
