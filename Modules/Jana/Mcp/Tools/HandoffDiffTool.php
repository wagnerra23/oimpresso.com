<?php

declare(strict_types=1);

namespace Modules\Jana\Mcp\Tools;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * Auditoria 2026-05-13 §5 (P0) — Diff frame "o que mudou desde último handoff".
 *
 * Cognitive load atual: reler handoff inteiro (mediana 142 linhas / outlier 2151)
 * pra entender estado novo. Esta tool agrega eventos das últimas N horas/dias em
 * ~500 tokens estruturados (PRs mergeados + US fechadas + ADRs novas + cycles + arquivos).
 *
 * Diferente de `handoff-fetch-summarized` (G4 — resume 1 handoff específico via LLM):
 *  - este foca em DIFF entre 2 estados (delta, não absoluto)
 *  - síntese rule-based (zero LLM call por default) — Princípio 2 (tiered cost)
 *  - opcionalmente pode chamar LLM pra narrativa, mas default é determinístico
 *
 * Cache strategy (idêntica G4): MD5 sobre hash de eventos serializados.
 * Se nada mudou no intervalo, retorna mesmo output cacheado.
 *
 * Multi-tenant: repo-wide, sem business_id (governança projeto inteiro).
 */
class HandoffDiffTool extends Tool
{
    protected string $name = 'handoff-diff';

    protected string $title = 'O que mudou desde último handoff';

    protected string $description = 'Retorna delta de eventos desde uma data: PRs mergeados, US fechadas, ADRs novas, cycles e arquivos memory/ tocados. Substitui reler handoff inteiro pra retomar sessão. Default since=1d, formato markdown ~500 tokens.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'since' => $schema->string()
                ->description('Janela: `1d|3d|7d|14d` (default `1d`), OU data ISO `YYYY-MM-DD`, OU `last` pra usar data do último handoff em memory/handoffs/')
                ->default('1d'),
            'categorias' => $schema->array()
                ->description('Categorias a incluir: `prs|us|adrs|cycles|files`. Omite pra todas.'),
            'format' => $schema->string()
                ->description('Formato: `markdown` (default) ou `json`.')
                ->default('markdown'),
        ];
    }

    public function handle(Request $request): Response
    {
        $sinceParam = (string) $request->get('since', '1d');
        $categoriasParam = $request->get('categorias');
        $format = $request->get('format', 'markdown') === 'json' ? 'json' : 'markdown';

        // Resolve since → CarbonImmutable
        $sinceDate = $this->parseSince($sinceParam);
        if ($sinceDate === null) {
            return Response::text(
                "Erro: parâmetro `since` inválido. Use `1d|3d|7d|14d`, `last`, ou data ISO `YYYY-MM-DD`."
            );
        }

        // Default todas as categorias
        $categoriasValidas = ['prs', 'us', 'adrs', 'cycles', 'files'];
        $categorias = is_array($categoriasParam) && ! empty($categoriasParam)
            ? array_values(array_intersect($categoriasValidas, $categoriasParam))
            : $categoriasValidas;

        // Coleta eventos por categoria
        $eventos = [
            'prs' => in_array('prs', $categorias, true) ? $this->coletarPrs($sinceDate) : [],
            'us' => in_array('us', $categorias, true) ? $this->coletarUs($sinceDate) : [],
            'adrs' => in_array('adrs', $categorias, true) ? $this->coletarAdrs($sinceDate) : [],
            'cycles' => in_array('cycles', $categorias, true) ? $this->coletarCycles($sinceDate) : [],
            'files' => in_array('files', $categorias, true) ? $this->coletarFiles($sinceDate) : [],
        ];

        // Cache key: hash de eventos serializados
        $eventsJson = json_encode($eventos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $eventsHash = md5($eventsJson ?: '');

        $cached = $this->buscarCache($sinceParam, $eventsHash, $format);
        if ($cached !== null) {
            return Response::text($cached);
        }

        // Renderiza output
        $output = $format === 'json'
            ? $this->renderJson($sinceParam, $sinceDate, $eventos)
            : $this->renderMarkdown($sinceParam, $sinceDate, $eventos);

        // Salva cache (rule-based — zero LLM por default = custo R$ 0)
        $this->salvarCache($sinceParam, $eventsHash, $format, $output);

        return Response::text($output);
    }

    /**
     * Converte `since` em CarbonImmutable. Aceita `Nd`, ISO date, ou `last`.
     */
    protected function parseSince(string $since): ?CarbonImmutable
    {
        $since = trim($since);
        if ($since === '') {
            return CarbonImmutable::now()->subDay();
        }

        // Pattern Nd (1d, 3d, 7d, 14d, etc)
        if (preg_match('/^(\d+)d$/i', $since, $m)) {
            return CarbonImmutable::now()->subDays((int) $m[1]);
        }

        // `last` — data do último handoff (mais recente em memory/handoffs/)
        if (strtolower($since) === 'last') {
            $handoffDir = config('jana.handoffs_dir') ?? base_path('memory/handoffs');
            if (! is_dir($handoffDir)) {
                return CarbonImmutable::now()->subDay();
            }
            $latest = $this->ultimoHandoffDate($handoffDir);

            return $latest ?? CarbonImmutable::now()->subDay();
        }

        // ISO date
        try {
            return CarbonImmutable::parse($since)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Encontra data do último handoff em memory/handoffs/ (filename YYYY-MM-DD-HHMM).
     */
    protected function ultimoHandoffDate(string $dir): ?CarbonImmutable
    {
        $entries = @scandir($dir);
        if ($entries === false) {
            return null;
        }
        $datas = [];
        foreach ($entries as $entry) {
            if (! str_ends_with($entry, '.md') || str_starts_with($entry, '_')) {
                continue;
            }
            if (! preg_match('/^(\d{4}-\d{2}-\d{2})-(\d{4})-/', $entry, $m)) {
                continue;
            }
            try {
                $datas[] = CarbonImmutable::parse(
                    $m[1] . ' ' . substr($m[2], 0, 2) . ':' . substr($m[2], 2, 2)
                );
            } catch (\Throwable $e) {
                continue;
            }
        }
        if (empty($datas)) {
            return null;
        }
        usort($datas, fn ($a, $b) => $b->getTimestamp() <=> $a->getTimestamp());

        return $datas[0];
    }

    /**
     * Coleta PRs mergeados via `gh pr list --state merged --search "merged:>=<since>"`.
     * Best-effort: se `gh` não disponível ou falhar, retorna array vazio (não bloqueia).
     *
     * @return array<int, array{number: int, title: string, author: string}>
     */
    protected function coletarPrs(CarbonImmutable $since): array
    {
        try {
            $sinceStr = $since->format('Y-m-d');
            $result = Process::path(base_path())
                ->timeout(15)
                ->run([
                    'gh', 'pr', 'list',
                    '--state', 'merged',
                    '--search', "merged:>={$sinceStr}",
                    '--json', 'number,title,author',
                    '--limit', '50',
                ]);

            if (! $result->successful()) {
                return [];
            }

            $json = json_decode($result->output(), true);
            if (! is_array($json)) {
                return [];
            }

            return array_map(fn ($pr) => [
                'number' => (int) ($pr['number'] ?? 0),
                'title' => (string) ($pr['title'] ?? ''),
                'author' => (string) ($pr['author']['login'] ?? 'unknown'),
            ], $json);
        } catch (\Throwable $e) {
            Log::debug('HandoffDiffTool: gh pr list falhou', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Coleta US fechadas (status=done) via mcp_tasks `updated_at >= since`.
     *
     * @return array<int, array{task_id: string, title: string, owner: ?string}>
     */
    protected function coletarUs(CarbonImmutable $since): array
    {
        try {
            $rows = DB::table('mcp_tasks')
                ->where('status', 'done')
                ->where('updated_at', '>=', $since->toDateTimeString())
                ->orderByDesc('updated_at')
                ->limit(50)
                ->get(['task_id', 'title', 'owner']);

            return $rows->map(fn ($r) => [
                'task_id' => (string) ($r->task_id ?? ''),
                'title' => (string) ($r->title ?? ''),
                'owner' => $r->owner !== null ? (string) $r->owner : null,
            ])->toArray();
        } catch (\Throwable $e) {
            Log::debug('HandoffDiffTool: query mcp_tasks falhou', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Coleta ADRs criadas via `git log --diff-filter=A --since=<since> -- memory/decisions/`.
     *
     * @return array<int, array{file: string, title: string}>
     */
    protected function coletarAdrs(CarbonImmutable $since): array
    {
        try {
            $sinceStr = $since->format('Y-m-d');
            $result = Process::path(base_path())
                ->timeout(15)
                ->run([
                    'git', 'log',
                    "--since={$sinceStr}",
                    '--diff-filter=A',
                    '--name-only',
                    '--pretty=format:',
                    '--', 'memory/decisions/',
                ]);

            if (! $result->successful()) {
                return [];
            }

            $files = array_filter(
                array_map('trim', explode("\n", $result->output())),
                fn ($l) => $l !== '' && str_ends_with($l, '.md')
            );
            $files = array_values(array_unique($files));

            return array_map(function ($file) {
                $base = basename($file, '.md');
                // ADR pattern: 0123-slug-do-titulo.md
                $title = preg_match('/^(\d{4})-(.+)$/', $base, $m)
                    ? "ADR {$m[1]} " . str_replace('-', ' ', $m[2])
                    : $base;

                return [
                    'file' => $file,
                    'title' => $title,
                ];
            }, $files);
        } catch (\Throwable $e) {
            Log::debug('HandoffDiffTool: git log ADRs falhou', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Coleta cycles atualizados via mcp_cycles `updated_at >= since`.
     *
     * @return array<int, array{key: string, status: string, goal: ?string}>
     */
    protected function coletarCycles(CarbonImmutable $since): array
    {
        try {
            $rows = DB::table('mcp_cycles')
                ->where('updated_at', '>=', $since->toDateTimeString())
                ->orderByDesc('updated_at')
                ->limit(10)
                ->get(['key', 'status', 'goal']);

            return $rows->map(fn ($r) => [
                'key' => (string) ($r->key ?? ''),
                'status' => (string) ($r->status ?? ''),
                'goal' => $r->goal !== null ? (string) $r->goal : null,
            ])->toArray();
        } catch (\Throwable $e) {
            Log::debug('HandoffDiffTool: query mcp_cycles falhou', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Coleta arquivos memory/ tocados via `git log --since=<since> --name-only`.
     *
     * @return array<int, string>
     */
    protected function coletarFiles(CarbonImmutable $since): array
    {
        try {
            $sinceStr = $since->format('Y-m-d');
            $result = Process::path(base_path())
                ->timeout(15)
                ->run([
                    'git', 'log',
                    "--since={$sinceStr}",
                    '--name-only',
                    '--pretty=format:',
                    '--', 'memory/',
                ]);

            if (! $result->successful()) {
                return [];
            }

            $files = array_filter(
                array_map('trim', explode("\n", $result->output())),
                fn ($l) => $l !== ''
            );
            $files = array_values(array_unique($files));

            // Top 10 mais frequentes? Aqui só dedup + slice — suficiente pra diff frame
            return array_slice($files, 0, 10);
        } catch (\Throwable $e) {
            Log::debug('HandoffDiffTool: git log files falhou', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Renderiza output markdown estruturado (~500 tokens).
     *
     * @param array<string, array<int, mixed>> $eventos
     */
    protected function renderMarkdown(string $sinceParam, CarbonImmutable $sinceDate, array $eventos): string
    {
        $sinceFmt = $sinceDate->format('Y-m-d H:i');
        $out = "# Diff desde {$sinceParam} ({$sinceFmt})\n\n";

        // PRs mergeados
        $prs = $eventos['prs'];
        $out .= "## PRs mergeados (" . count($prs) . ")\n";
        if (empty($prs)) {
            $out .= "_nenhum_\n";
        } else {
            foreach (array_slice($prs, 0, 15) as $pr) {
                $out .= sprintf("- #%d %s (@%s)\n", $pr['number'], $pr['title'], $pr['author']);
            }
            if (count($prs) > 15) {
                $out .= sprintf("- _... +%d outros_\n", count($prs) - 15);
            }
        }

        // US fechadas
        $us = $eventos['us'];
        $out .= "\n## US fechadas (" . count($us) . ")\n";
        if (empty($us)) {
            $out .= "_nenhuma_\n";
        } else {
            foreach (array_slice($us, 0, 15) as $u) {
                $owner = $u['owner'] ? " [{$u['owner']}]" : '';
                $out .= sprintf("- %s %s%s\n", $u['task_id'], $u['title'], $owner);
            }
            if (count($us) > 15) {
                $out .= sprintf("- _... +%d outras_\n", count($us) - 15);
            }
        }

        // ADRs novas
        $adrs = $eventos['adrs'];
        $out .= "\n## ADRs novas (" . count($adrs) . ")\n";
        if (empty($adrs)) {
            $out .= "_nenhuma_\n";
        } else {
            foreach (array_slice($adrs, 0, 10) as $a) {
                $out .= sprintf("- %s\n", $a['title']);
            }
        }

        // Cycles
        $cycles = $eventos['cycles'];
        $out .= "\n## Cycles\n";
        if (empty($cycles)) {
            $out .= "_sem mudanças_\n";
        } else {
            foreach ($cycles as $c) {
                $goal = $c['goal'] ? " — {$c['goal']}" : '';
                $out .= sprintf("- %s [%s]%s\n", $c['key'], $c['status'], $goal);
            }
        }

        // Files
        $files = $eventos['files'];
        $out .= "\n## Arquivos memory/ tocados (top " . count($files) . ")\n";
        if (empty($files)) {
            $out .= "_nenhum_\n";
        } else {
            foreach ($files as $f) {
                $out .= "- {$f}\n";
            }
        }

        return $out;
    }

    /**
     * Renderiza output JSON estruturado.
     *
     * @param array<string, array<int, mixed>> $eventos
     */
    protected function renderJson(string $sinceParam, CarbonImmutable $sinceDate, array $eventos): string
    {
        $payload = [
            'since' => $sinceParam,
            'since_resolved' => $sinceDate->toIso8601String(),
            'counts' => [
                'prs' => count($eventos['prs']),
                'us' => count($eventos['us']),
                'adrs' => count($eventos['adrs']),
                'cycles' => count($eventos['cycles']),
                'files' => count($eventos['files']),
            ],
            'eventos' => $eventos,
        ];

        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    /**
     * Busca cache pra (since, events_hash, format).
     */
    protected function buscarCache(string $since, string $eventsHash, string $format): ?string
    {
        try {
            $col = $format === 'json' ? 'output_json' : 'output_md';
            $row = DB::table('mcp_handoff_diffs')
                ->where('since', $since)
                ->where('events_hash', $eventsHash)
                ->first([$col]);

            if ($row === null) {
                return null;
            }

            return $row->{$col} ?? null;
        } catch (\Throwable $e) {
            // Tabela ainda não migrada — não bloqueia
            return null;
        }
    }

    /**
     * Salva cache (upsert por since + events_hash).
     */
    protected function salvarCache(string $since, string $eventsHash, string $format, string $output): void
    {
        try {
            $col = $format === 'json' ? 'output_json' : 'output_md';

            $existing = DB::table('mcp_handoff_diffs')
                ->where('since', $since)
                ->where('events_hash', $eventsHash)
                ->first(['id']);

            if ($existing !== null) {
                DB::table('mcp_handoff_diffs')
                    ->where('id', $existing->id)
                    ->update([
                        $col => $output,
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('mcp_handoff_diffs')->insert([
                    'since' => $since,
                    'events_hash' => $eventsHash,
                    'output_md' => $format === 'markdown' ? $output : null,
                    'output_json' => $format === 'json' ? $output : null,
                    'tokens' => 0,
                    'cost_brl' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('HandoffDiffTool: erro salvando cache', [
                'since' => $since,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
