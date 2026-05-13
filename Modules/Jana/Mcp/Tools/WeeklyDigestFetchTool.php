<?php

declare(strict_types=1);

namespace Modules\Jana\Mcp\Tools;

use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * AUDITORIA-KNOWLEDGE-ARCHITECTURE-2026-05-13 §5 (G8 P2 quick win) — Weekly digest fetch.
 *
 * Retorna weekly digest pra Wagner/Claude consumir via MCP. Default: semana atual
 * (ou anterior se atual ainda não gerado). Aceita `week=YYYY-Www` específico ou
 * `latest=true` pra última row da tabela.
 *
 * Fonte: tabela `mcp_weekly_digests` (gerada por `jana:weekly-digest` segunda 09h).
 * Fallback: lê arquivo `memory/sessions/WEEKLY-DIGEST-YYYY-Www.md` se DB indisponível.
 *
 * Multi-tenant: digest é repo-wide (sem business_id), consistente com handoffs.
 */
class WeeklyDigestFetchTool extends Tool
{
    protected string $name = 'weekly-digest-fetch';

    protected string $title = 'Weekly digest da semana';

    protected string $description = 'Retorna o weekly digest Reflect-style (5 seções: Marco/Trabalho/Cycle/Decisões/Próxima semana). Default: semana mais recente disponível. Aceita `week=YYYY-Www` específica.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'week' => $schema->string()
                ->description('Semana ISO `YYYY-Www` (ex: `2026-W19`). Default: mais recente disponível.'),
            'metrics_only' => $schema->boolean()
                ->default(false)
                ->description('Se true, retorna só JSON com métricas (commits, prs, us_closed, adrs, cycle_progress_pct) sem markdown full.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $week = trim((string) $request->get('week', ''));
        $metricsOnly = (bool) $request->get('metrics_only', false);

        // 1. Tenta DB primeiro
        $row = $this->buscarRowDb($week);

        if ($row === null) {
            // 2. Fallback file (sem DB ou tabela ausente)
            return $this->fallbackFile($week, $metricsOnly);
        }

        if ($metricsOnly) {
            $metricsJson = $row->metrics ?? '{}';
            $metrics = json_decode((string) $metricsJson, true) ?: [];
            $weekVal = $row->week;
            $output = "# Weekly digest {$weekVal} — métricas\n\n```json\n" .
                json_encode($metrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) .
                "\n```\n\nRange: {$row->range_start} a {$row->range_end}";

            return Response::text($output);
        }

        return Response::text((string) $row->digest_markdown);
    }

    /**
     * Busca row em `mcp_weekly_digests`. Se $week vazio, pega a mais recente.
     */
    protected function buscarRowDb(string $week): ?object
    {
        if (! Schema::hasTable('mcp_weekly_digests')) {
            return null;
        }
        try {
            $q = DB::table('mcp_weekly_digests');
            if ($week !== '') {
                if (! preg_match('/^\d{4}-W\d{2}$/', $week)) {
                    return null;
                }
                $q->where('week', $week);
            } else {
                $q->orderByDesc('range_end');
            }

            return $q->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Lê arquivo `memory/sessions/WEEKLY-DIGEST-*.md` quando DB não tem.
     */
    protected function fallbackFile(string $week, bool $metricsOnly): Response
    {
        $dir = base_path('memory/sessions');
        if (! is_dir($dir)) {
            return Response::text(
                "# Weekly digest\n\nNenhum digest disponível. Rode `php artisan jana:weekly-digest` pra gerar."
            );
        }

        if ($week !== '') {
            $path = $dir . DIRECTORY_SEPARATOR . "WEEKLY-DIGEST-{$week}.md";
            if (! is_file($path)) {
                return Response::text(
                    "# Weekly digest\n\nDigest da semana `{$week}` não encontrado. Verifique semana ISO ou rode `php artisan jana:weekly-digest --week={$week}`."
                );
            }
        } else {
            // Pega o mais recente WEEKLY-DIGEST-*.md
            $entries = glob($dir . DIRECTORY_SEPARATOR . 'WEEKLY-DIGEST-*.md') ?: [];
            if (empty($entries)) {
                return Response::text(
                    "# Weekly digest\n\nNenhum digest gerado ainda. Rode `php artisan jana:weekly-digest`."
                );
            }
            sort($entries);
            $path = end($entries);
        }

        $content = @file_get_contents($path);
        if ($content === false) {
            return Response::text("# Weekly digest\n\nErro lendo arquivo {$path}.");
        }

        if ($metricsOnly) {
            // Tenta extrair metrics do frontmatter
            if (preg_match('/^metrics:\s*(\{.+\})$/m', $content, $m)) {
                $metrics = json_decode($m[1], true) ?: [];

                return Response::text(
                    "# Weekly digest — métricas (fallback file)\n\n```json\n" .
                    json_encode($metrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) .
                    "\n```"
                );
            }

            return Response::text("# Weekly digest\n\nMétricas não encontradas no frontmatter.");
        }

        return Response::text($content);
    }
}
