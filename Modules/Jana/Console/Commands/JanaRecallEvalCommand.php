<?php

declare(strict_types=1);

namespace Modules\Jana\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Yaml\Yaml;
use Throwable;

/**
 * jana:recall-eval — eval DETERMINÍSTICO de recall (sem judge LLM).
 *
 * Golden set: tests/eval/recall-golden.yaml — 25-30 queries com `expected`
 * (slugs que DEVEM estar no top-K) e `violations` (slugs superseded/historical
 * que NÃO podem aparecer no top-N) + pares colididos do alias map (ADR 0274).
 *
 * Modos:
 *   --mode=mock (default) — valida ESTRUTURA local, sem Meilisearch: YAML parse,
 *       slug existe no disco, violations são de fato superseded/historical,
 *       pares colididos batem com governance/adr-alias-map.json. Roda em CI.
 *   --mode=real (CT 100, fase 2) — consulta o índice Meilisearch
 *       `mcp_memory_documents` (read-only) e mede recall@K + a métrica
 *       `recall_eval_violations` do scorecard SDD (ADR 0275, meta → 0).
 *
 * Exit: 0 = gate pass · 1 = gate fail.
 *
 * Refs: ADR 0270 D-4/D-5 (decaimento + medir caminho de leitura) · ADR 0274
 * (referência por slug) · ADR 0275 (scorecard SDD) · frente KL-C2 do plano
 * memory/sessions/2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md.
 */
class JanaRecallEvalCommand extends Command
{
    private const SUPERSEDED_STATUS = ['superseded', 'deprecated', 'historical', 'rejeitado'];

    private const SUPERSEDED_LIFECYCLE = ['substituido', 'historical', 'arquivado', 'deprecated'];

    protected $signature = 'jana:recall-eval
                            {--mode=mock : mock (estrutura local, sem Meilisearch) | real (CT 100, fase 2)}
                            {--golden= : Path alternativo do golden set YAML}
                            {--json : Output só JSON (CI artifact)}';

    protected $description = 'Eval determinístico de recall — golden set expected/violations + pares colididos (sem judge LLM)';

    /** @var string[] */
    private array $errors = [];

    public function handle(): int
    {
        $mode = (string) $this->option('mode');
        $goldenPath = (string) ($this->option('golden') ?: base_path('tests/eval/recall-golden.yaml'));

        $report = [
            'command' => 'jana:recall-eval',
            'mode' => $mode,
            'golden_path' => str_replace(base_path() . DIRECTORY_SEPARATOR, '', $goldenPath),
            'ran_at' => now()->toIso8601String(),
        ];

        $golden = $this->parseGolden($goldenPath);
        $queries = $golden['queries'] ?? [];
        $meta = $golden['meta'] ?? [];

        if ($golden !== null && $this->errors === []) {
            $this->validateEstrutura($meta, $queries);
        }

        $report['n_queries'] = is_array($queries) ? count($queries) : 0;
        $report['n_collision_queries'] = count(array_filter($queries, fn ($q) => isset($q['collision_number'])));
        $report['n_queries_with_violations'] = count(array_filter($queries, fn ($q) => ! empty($q['violations'])));
        $report['checks'] = [
            'yaml_parse' => $golden !== null,
            'estrutura_ok' => $this->errors === [],
        ];

        if ($mode === 'real' && $this->errors === []) {
            $report['real'] = $this->runReal($meta, $queries);
        } elseif ($mode === 'real') {
            $this->errors[] = 'modo real abortado: estrutura do golden set inválida (corrija o mock primeiro)';
        } elseif ($mode !== 'mock') {
            $this->errors[] = "modo desconhecido: {$mode} (use mock|real)";
        }

        $report['errors'] = $this->errors;
        $report['gate_status'] = $this->errors === [] && ($report['real']['recall_eval_violations'] ?? 0) === 0
            && ($report['real']['n_queries_recall_fail'] ?? 0) === 0 ? 'pass' : 'fail';

        $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($this->option('json')) {
            $this->line($json);
        } else {
            $this->line($json);
            $report['gate_status'] === 'pass'
                ? $this->info("recall-eval [{$mode}]: PASS ({$report['n_queries']} queries, {$report['n_collision_queries']} pares colididos)")
                : $this->error("recall-eval [{$mode}]: FAIL — " . count($this->errors) . ' erro(s)');
        }

        return $report['gate_status'] === 'pass' ? self::SUCCESS : self::FAILURE;
    }

    private function parseGolden(string $path): ?array
    {
        if (! is_file($path)) {
            $this->errors[] = "golden set não encontrado: {$path}";

            return null;
        }

        try {
            $golden = Yaml::parseFile($path);
        } catch (Throwable $e) {
            $this->errors[] = 'YAML parse falhou: ' . $e->getMessage();

            return null;
        }

        if (! is_array($golden) || ! is_array($golden['queries'] ?? null) || ! is_array($golden['meta'] ?? null)) {
            $this->errors[] = 'golden set precisa das chaves raiz `meta` (map) e `queries` (lista)';

            return null;
        }

        return $golden;
    }

    private function validateEstrutura(array $meta, array $queries): void
    {
        $topK = $meta['top_k'] ?? null;
        $window = $meta['violation_window'] ?? null;

        if (! is_int($topK) || $topK < 1 || ! is_int($window) || $window < 1 || $window > $topK) {
            $this->errors[] = 'meta.top_k / meta.violation_window inválidos (ints, 1 ≤ window ≤ top_k)';
        }

        $n = count($queries);
        if ($n < 25 || $n > 30) {
            $this->errors[] = "golden set deve ter 25-30 queries (tem {$n})";
        }

        $aliasMap = $this->loadAliasMap((string) ($meta['alias_map'] ?? 'governance/adr-alias-map.json'));
        $ids = [];
        $nCollision = 0;

        foreach ($queries as $i => $q) {
            $id = is_string($q['id'] ?? null) && $q['id'] !== '' ? $q['id'] : null;
            $ref = $id ?? "queries[{$i}]";

            if ($id === null) {
                $this->errors[] = "{$ref}: campo `id` obrigatório (string não-vazia)";
            } elseif (isset($ids[$id])) {
                $this->errors[] = "{$ref}: id duplicado";
            }
            $ids[$id ?? $i] = true;

            if (! is_string($q['query'] ?? null) || trim((string) ($q['query'] ?? '')) === '') {
                $this->errors[] = "{$ref}: campo `query` obrigatório (string não-vazia)";
            }

            $expected = $q['expected'] ?? null;
            $violations = $q['violations'] ?? null;

            if (! is_array($expected) || $expected === []) {
                $this->errors[] = "{$ref}: `expected` deve ser lista não-vazia de slugs";
                $expected = [];
            }
            if (! is_array($violations)) {
                $this->errors[] = "{$ref}: `violations` deve ser lista (pode ser vazia)";
                $violations = [];
            }

            foreach (array_merge($expected, $violations) as $slug) {
                if (! is_string($slug) || $this->resolveSlugPath($slug) === null) {
                    $this->errors[] = "{$ref}: slug com formato inválido: " . json_encode($slug);
                } elseif (! is_file($this->resolveSlugPath($slug))) {
                    $this->errors[] = "{$ref}: slug não existe no disco: {$slug}";
                }
            }

            if (array_intersect($expected, $violations) !== []) {
                $this->errors[] = "{$ref}: expected ∩ violations deve ser vazio";
            }

            foreach ($violations as $slug) {
                if (is_string($slug) && str_starts_with($slug, 'briefing:')) {
                    $this->errors[] = "{$ref}: violation deve ser ADR (porta BRIEFING nunca é superseded): {$slug}";
                } elseif (is_string($slug) && is_file((string) $this->resolveSlugPath($slug)) && ! $this->isSuperseded((string) $this->resolveSlugPath($slug))) {
                    $this->errors[] = "{$ref}: violation `{$slug}` não tem status/lifecycle superseded|historical no frontmatter";
                }
            }

            if (isset($q['collision_number'])) {
                $nCollision++;
                $num = (string) $q['collision_number'];
                $resolve = $q['must_resolve_slug'] ?? null;
                $pair = array_column($aliasMap[$num] ?? [], 'slug');

                if ($pair === []) {
                    $this->errors[] = "{$ref}: collision_number {$num} não consta no alias map (ADR 0274)";
                } elseif (! is_string($resolve) || ! in_array($resolve, $pair, true)) {
                    $this->errors[] = "{$ref}: must_resolve_slug deve ser um dos slugs do par {$num} no alias map";
                } elseif (! in_array($resolve, $expected, true)) {
                    $this->errors[] = "{$ref}: must_resolve_slug deve constar em `expected`";
                }
            }
        }

        if ($nCollision < 2) {
            $this->errors[] = "golden set exige ≥2 queries de pares colididos (tem {$nCollision})";
        }
    }

    /** @return array<string, array<int, array{slug: string}>> */
    private function loadAliasMap(string $relPath): array
    {
        $path = base_path($relPath);
        $map = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        if (! is_array($map['collisions'] ?? null)) {
            $this->errors[] = "alias map ilegível ou sem chave `collisions`: {$relPath}";

            return [];
        }

        return $map['collisions'];
    }

    private function resolveSlugPath(string $slug): ?string
    {
        if (preg_match('/^\d{4}-[a-z0-9-]+$/', $slug) === 1) {
            return base_path("memory/decisions/{$slug}.md");
        }
        if (preg_match('/^briefing:([A-Za-z][A-Za-z0-9]*)$/', $slug, $m) === 1) {
            return base_path("memory/requisitos/{$m[1]}/BRIEFING.md");
        }

        return null;
    }

    private function isSuperseded(string $path): bool
    {
        $head = implode("\n", array_slice(file($path) ?: [], 0, 40));
        $status = preg_match('/^status:\s*[\'"]?([\w-]+)/m', $head, $m) === 1 ? $m[1] : '';
        $lifecycle = preg_match('/^lifecycle:\s*[\'"]?([\w-]+)/m', $head, $m) === 1 ? $m[1] : '';

        return in_array($status, self::SUPERSEDED_STATUS, true)
            || in_array($lifecycle, self::SUPERSEDED_LIFECYCLE, true);
    }

    /**
     * Modo real (CT 100, fase 2): consulta read-only no índice Meilisearch e
     * computa recall@K + recall_eval_violations (ADR 0275). Não toca reranker.
     */
    private function runReal(array $meta, array $queries): array
    {
        $host = rtrim((string) config('scout.meilisearch.host', 'http://localhost:7700'), '/');
        $key = (string) config('scout.meilisearch.key', '');
        $index = (string) ($meta['meilisearch_index'] ?? 'mcp_memory_documents');
        $topK = (int) ($meta['top_k'] ?? 5);
        $window = (int) ($meta['violation_window'] ?? 3);
        $out = ['index' => $index, 'recall_eval_violations' => 0, 'n_queries_recall_fail' => 0, 'per_query' => []];

        foreach ($queries as $q) {
            try {
                $resp = Http::withToken($key)->timeout(15)->post("{$host}/indexes/{$index}/search", [
                    'q' => (string) $q['query'],
                    'limit' => $topK,
                    'attributesToRetrieve' => ['slug'],
                ]);
            } catch (Throwable $e) {
                $this->errors[] = "modo real: Meilisearch inacessível ({$e->getMessage()}) — modo real roda no CT 100 (fase 2)";

                return $out;
            }

            if ($resp->failed()) {
                $this->errors[] = "modo real: busca falhou pra `{$q['id']}` ({$resp->status()}) — modo real roda no CT 100 (fase 2)";

                return $out;
            }

            $slugs = array_values(array_filter(array_column($resp->json('hits', []), 'slug')));
            $missing = array_values(array_diff($q['expected'], $slugs));
            $hit = array_values(array_intersect($q['violations'] ?? [], array_slice($slugs, 0, $window)));

            $out['recall_eval_violations'] += count($hit);
            $out['n_queries_recall_fail'] += $missing === [] ? 0 : 1;
            $out['per_query'][] = ['id' => $q['id'], 'top_k' => $slugs, 'missing_expected' => $missing, 'violations_hit' => $hit];
        }

        return $out;
    }
}
