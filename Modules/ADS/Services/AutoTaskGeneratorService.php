<?php

namespace Modules\ADS\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Observabilidade D9.a (ADR 0155): scan + curriculum envolto em
 * `OtelHelper::span(` (Tracer ads.autotask.generate) quando invocado.
 *
 * T7 — Self-Instruct goal-directed (Wang et al., 2022).
 *
 * Não gera tarefas inventadas. Faz SCAN do estado real do projeto e propõe
 * ações concretas que casam com padrões já aprendidos com sucesso (Wilson > 0.7).
 *
 * Curriculum: easy first — só gera tarefas onde Brain A tem alta confiança.
 * Diversity: dedup por hash do conteúdo (evita spam de tasks duplicadas).
 * Budget: máx 3 tasks/hora, máx 10/dia (controla custo).
 *
 * Scanners ativos (estado da arte mínimo):
 *   1. ADRs sem frontmatter YAML
 *   2. Markdown links quebrados
 *   3. Session log gap (>3 dias sem session log)
 *   4. Sync MCP atrasado (último sync > 1h)
 */
class AutoTaskGeneratorService
{
    private const MAX_PER_HOUR = 3;
    private const MAX_PER_DAY  = 10;

    public function __construct(
        private readonly DecisionRouter $router,
        private readonly PatternLearningService $patterns,
    ) {}

    /**
     * Roda os scanners e gera tasks. Chamado pelo command horário.
     * @return array{scanned:int, generated:int, skipped:array}
     */
    public function generateTasks(int $businessId): array
    {
        $generated = 0;
        $skipped   = [];
        $candidates = [];

        if ($this->respectsBudget($businessId)) {
            $candidates = array_merge(
                $this->scanAdrFrontmatter(),
                $this->scanMarkdownLinks(),
                $this->scanSessionLogGap(),
                $this->scanMcpSyncStaleness(),
            );

            // Dedup: descarta candidatas com hash já existente nas últimas 24h
            $candidates = $this->dedupRecent($candidates, $businessId);

            // Curriculum: só gera tarefa se padrão já existe com confiança >= 0.6
            // (do contrário, daria spam de tasks que sempre escala pra Wagner)
            foreach ($candidates as $cand) {
                $confidence = $this->patternConfidence($cand['domain'], $cand['event_type']);
                if ($confidence < 0.6 && ! $cand['force']) {
                    $skipped[] = ['reason' => 'low_confidence', 'cand' => $cand['event_type']];
                    continue;
                }

                if ($generated >= self::MAX_PER_HOUR) {
                    $skipped[] = ['reason' => 'budget_hour', 'cand' => $cand['event_type']];
                    break;
                }

                $this->router->route(new RoutingInput(
                    businessId:    $businessId,
                    eventType:     $cand['event_type'],
                    eventSource:   'scheduler',
                    domain:        $cand['domain'],
                    filesAffected: $cand['files'] ?? [],
                    metadata:      array_merge($cand['metadata'] ?? [], ['auto_generated' => true]),
                ));
                $generated++;
            }
        } else {
            $skipped[] = ['reason' => 'budget_day_full'];
        }

        Log::channel('single')->info('ads.auto_task_generator.cycle', [
            'business_id' => $businessId,
            'scanned'     => count($candidates),
            'generated'   => $generated,
            'skipped'     => $skipped,
        ]);

        return [
            'scanned'   => count($candidates),
            'generated' => $generated,
            'skipped'   => $skipped,
        ];
    }

    private function respectsBudget(int $businessId): bool
    {
        $todayCount = DB::table('mcp_dual_brain_decisions')
            ->where('business_id', $businessId)
            ->where('auto_generated', true)
            ->where('created_at', '>=', now()->startOfDay())
            ->count();

        return $todayCount < self::MAX_PER_DAY;
    }

    private function dedupRecent(array $candidates, int $businessId): array
    {
        $recentHashes = DB::table('mcp_dual_brain_decisions')
            ->where('business_id', $businessId)
            ->where('auto_generated', true)
            ->where('created_at', '>=', now()->subHours(24))
            ->pluck('event_metadata')
            ->map(fn ($m) => $this->candidateHash(json_decode($m, true) ?? []))
            ->all();

        return array_filter($candidates, fn ($c) => ! in_array($this->candidateHash($c['metadata'] ?? []), $recentHashes, true));
    }

    private function candidateHash(array $metadata): string
    {
        return hash('sha256', json_encode([
            $metadata['scanner'] ?? '', $metadata['target'] ?? '',
        ]));
    }

    private function patternConfidence(string $domain, string $eventType): float
    {
        $row = DB::table('mcp_confidence_scores')
            ->where('domain', $domain)
            ->where('event_type', $eventType)
            ->first();
        return $row ? (float) $row->score : 0.5;
    }

    // === SCANNERS ===

    /**
     * Procura ADRs em memory/decisions/ ou memory/requisitos sem frontmatter YAML.
     */
    private function scanAdrFrontmatter(): array
    {
        $candidates = [];
        $paths = [
            base_path('memory/decisions'),
            base_path('memory/requisitos'),
        ];

        foreach ($paths as $root) {
            if (! is_dir($root)) continue;

            $files = $this->findMd($root, 50);
            foreach ($files as $file) {
                $head = $this->readHead($file, 200);
                if (! preg_match('/^---\s*\n.*?\n---/s', $head)) {
                    $candidates[] = [
                        'event_type' => 'adr_frontmatter_fix',
                        'domain'     => 'Memory',
                        'force'      => false,
                        'files'      => [str_replace(base_path() . '/', '', $file)],
                        'metadata'   => [
                            'scanner' => 'adr_frontmatter',
                            'target'  => $file,
                            'reason'  => 'ADR sem frontmatter YAML obrigatório',
                        ],
                    ];
                    if (count($candidates) >= 20) break 2;
                }
            }
        }
        return $candidates;
    }

    /**
     * Procura links Markdown com path relativo que aponta pra arquivo inexistente.
     */
    private function scanMarkdownLinks(): array
    {
        $candidates = [];
        $files = $this->findMd(base_path('memory'), 30);

        foreach ($files as $file) {
            $content = @file_get_contents($file);
            if (! $content) continue;

            preg_match_all('/\[[^\]]+\]\(([^)]+)\)/', $content, $matches);
            foreach ($matches[1] ?? [] as $link) {
                if (preg_match('/^https?:/', $link)) continue;
                if (str_starts_with($link, '#')) continue;

                $resolved = realpath(dirname($file) . '/' . $link);
                if ($resolved === false) {
                    $candidates[] = [
                        'event_type' => 'md_link_fix',
                        'domain'     => 'Memory',
                        'force'      => false,
                        'files'      => [str_replace(base_path() . '/', '', $file)],
                        'metadata'   => [
                            'scanner' => 'markdown_links',
                            'target'  => "{$file}::{$link}",
                            'reason'  => "Link quebrado: {$link}",
                        ],
                    ];
                    if (count($candidates) >= 5) return $candidates;
                }
            }
        }
        return $candidates;
    }

    /**
     * Detecta gap de session log (>3 dias sem registro).
     */
    private function scanSessionLogGap(): array
    {
        $sessionsDir = base_path('memory/sessions');
        if (! is_dir($sessionsDir)) return [];

        $files = glob($sessionsDir . '/*.md');
        if (empty($files)) return [];

        $latest = 0;
        foreach ($files as $f) {
            $mtime = filemtime($f);
            if ($mtime > $latest) $latest = $mtime;
        }

        $diasSinceLast = (time() - $latest) / 86400;
        if ($diasSinceLast > 3) {
            return [[
                'event_type' => 'session_log_creation',
                'domain'     => 'Memory',
                'force'      => true, // session log é seguro, força mesmo com confidence baixa
                'files'      => [],
                'metadata'   => [
                    'scanner' => 'session_log_gap',
                    'target'  => 'memory/sessions',
                    'reason'  => sprintf('Último session log foi há %.1f dias. Criar resumo do período.', $diasSinceLast),
                    'days'    => round($diasSinceLast, 1),
                ],
            ]];
        }
        return [];
    }

    /**
     * Detecta sync MCP atrasado (>1h sem chamar mcp:sync-memory).
     * Não temos timestamp do último sync — fallback: sempre propor 1× por dia.
     */
    private function scanMcpSyncStaleness(): array
    {
        $todaySync = DB::table('mcp_dual_brain_decisions')
            ->where('event_type', 'mcp_sync_memory')
            ->where('created_at', '>=', now()->startOfDay())
            ->exists();

        if ($todaySync) return [];

        return [[
            'event_type' => 'mcp_sync_memory',
            'domain'     => 'MCP',
            'force'      => false,
            'files'      => [],
            'metadata'   => [
                'scanner' => 'mcp_sync_daily',
                'target'  => 'sync diário',
                'reason'  => 'Nenhum sync MCP gerado hoje. Propor reindexação.',
            ],
        ]];
    }

    private function findMd(string $dir, int $limit): array
    {
        if (! is_dir($dir)) return [];
        $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        $files = [];
        foreach ($iter as $f) {
            if ($f->isFile() && str_ends_with($f->getFilename(), '.md')) {
                $files[] = $f->getPathname();
                if (count($files) >= $limit) break;
            }
        }
        return $files;
    }

    private function readHead(string $path, int $bytes): string
    {
        $h = @fopen($path, 'r');
        if (! $h) return '';
        $head = fread($h, $bytes);
        fclose($h);
        return $head ?: '';
    }
}
