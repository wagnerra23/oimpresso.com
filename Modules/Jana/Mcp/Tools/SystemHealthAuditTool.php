<?php

declare(strict_types=1);

namespace Modules\Jana\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Artisan;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

/**
 * ADR 0133 — System health audit canônico.
 *
 * Tool MCP que retorna estado consolidado dos 5 audits Constituição v2:
 *   - observability_pipeline (Langfuse health)
 *   - eval_ci_gate           (workflow Pest eval R@3)
 *   - adr_stale_count        (ADRs canon com tech abandonada)
 *   - cost_dashboard_aggregation (mcp_usage_diaria populada)
 *   - test_coverage_gate     (pcov + coverage-clover em CI)
 *
 * Wrapper sobre `php artisan jana:system-audit --json`. Princípio 2 Constituição v2
 * (tiered cost): SQL + filesystem only, ZERO LLM call.
 *
 * Tool MCP é leitura por dev/IA on-demand. Cron schedule independente em
 * app/Console/Kernel.php (06:15 BRT daily) — log estruturado em channel single.
 */
class SystemHealthAuditTool extends Tool
{
    protected string $name = 'system-health-audit';

    protected string $title = 'Audit Constituição v2 — 5 dimensões';

    protected string $description = 'Retorna estado consolidado de 5 audits Constituição v2 (observabilidade Langfuse / eval CI gate / ADR stale / cost dashboard / test coverage). Wrapper sobre jana:system-audit --json. Princípio 2: zero LLM call, só SQL+FS. ADR 0133.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'format' => $schema->string()
                ->enum(['markdown', 'json'])
                ->default('markdown')
                ->description('Formato output: markdown (humano) ou json (machine-readable)'),
        ];
    }

    public function handle(Request $request): Response
    {
        $format = (string) $request->get('format', 'markdown');

        $user = $request->user();
        if ($user === null) {
            return Response::error('Autenticação requerida.');
        }

        Artisan::call('jana:system-audit', ['--json' => true]);
        $rawOutput = trim(Artisan::output());

        $payload = json_decode($rawOutput, true);

        if (! is_array($payload) || ! isset($payload['checks'])) {
            return Response::error("Output inválido do jana:system-audit: {$rawOutput}");
        }

        if ($format === 'json') {
            return Response::text($rawOutput);
        }

        return Response::text($this->renderMarkdown($payload));
    }

    protected function renderMarkdown(array $payload): string
    {
        $allOk = (bool) ($payload['ok'] ?? false);
        $checks = $payload['checks'] ?? [];
        $passed = collect($checks)->where('ok', true)->count();
        $total = count($checks);
        $checkedAt = $payload['checked_at'] ?? now()->toIso8601String();

        $emoji = $allOk ? '🟢' : '🔴';
        $output = "## {$emoji} System Audit — {$passed}/{$total} OK\n\n";
        $output .= "_Checked at_: `{$checkedAt}` · _ADR 0133_\n\n";

        $output .= "| OK | Check | Valor atual | Threshold |\n";
        $output .= "|----|-------|-------------|-----------|\n";

        foreach ($checks as $c) {
            $ok = ($c['ok'] ?? false) ? '✅' : '❌';
            $name = $c['name'] ?? '—';
            $value = (string) ($c['value'] ?? '—');
            $threshold = (string) ($c['threshold'] ?? '—');
            $output .= "| {$ok} | `{$name}` | {$value} | {$threshold} |\n";
        }

        $remediations = collect($checks)
            ->filter(fn ($c) => ! ($c['ok'] ?? false) && ! empty($c['remediation']))
            ->values();

        if ($remediations->isNotEmpty()) {
            $output .= "\n### Remediations\n\n";
            foreach ($remediations as $c) {
                $output .= "- **{$c['name']}**: {$c['remediation']}\n";
            }
        }

        $output .= "\n_Use `system-health-audit format:json` pra payload machine-readable._";

        return $output;
    }
}
