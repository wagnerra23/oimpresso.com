<?php

declare(strict_types=1);

namespace Modules\Jana\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Modules\Jana\Entities\Mcp\McpAutomation;

/**
 * ADR 0234 (Onda 1.1) — Tool automations-list.
 *
 * Lista as automações governadas (hooks/crons/rotinas) + estado vivo + drift.
 * Mesma família de skills-search (ADR 0076): read-only, sem custo de LLM,
 * registry global de infra de plataforma (sem business_id scope).
 *
 * Responde a pergunta do gap #11: "quais automações existem, o que disparam,
 * quando rodaram, deu ok, e tem drift?" — antes exigia git grep + ler Kernel.php.
 */
class AutomationsListTool extends Tool
{
    protected string $name = 'automations-list';

    protected string $title = 'Listar automações governadas (hooks/crons/rotinas)';

    protected string $description = 'Lista as automações do projeto (hooks .claude/hooks, crons do Kernel.php, rotinas .claude/*.json) com estado vivo (enabled, last_run, last_status) e drift detection. Filtra por tipo (hook_sessionstart|hook_pretooluse|hook_posttooluse|cron|routine|webhook), enabled (true/false), last_status (ok|warn|fail|skip) ou drift (none|orphan_file|missing_file). Read-only, sem custo de LLM. Registry de infra de plataforma (global, sem tenant). ADR 0234.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'tipo' => $schema->string()
                ->description('Filtra por tipo: hook_sessionstart|hook_pretooluse|hook_posttooluse|cron|routine|webhook. Omite pra todos.'),
            'enabled' => $schema->boolean()
                ->description('Filtra por habilitada (true) ou desabilitada (false). Omite pra todas.'),
            'last_status' => $schema->string()
                ->description('Filtra por último status: ok|warn|fail|skip. Omite pra todos.'),
            'drift' => $schema->string()
                ->description('Filtra por drift: none|orphan_file|missing_file. Ex: drift=missing_file mostra automações zumbis.'),
            'limit' => $schema->integer()
                ->min(1)
                ->max(200)
                ->default(100)
                ->description('Quantas automações retornar (default 100, max 200).'),
        ];
    }

    public function handle(Request $request): Response
    {
        $tipo       = $this->normalize($request->get('tipo'));
        $lastStatus = $this->normalize($request->get('last_status'));
        $driftFilter = $this->normalize($request->get('drift'));
        $enabledRaw = $request->get('enabled');
        $limit      = max(1, min(200, (int) $request->get('limit', 100)));

        $q = McpAutomation::query();

        if ($tipo !== null) {
            $q->where('tipo', $tipo);
        }
        if ($lastStatus !== null) {
            $q->where('last_status', $lastStatus);
        }
        if ($enabledRaw !== null) {
            $q->where('enabled', filter_var($enabledRaw, FILTER_VALIDATE_BOOLEAN));
        }

        $q->orderBy('tipo')->orderBy('slug');

        $rows = $q->limit($limit)->get();

        if ($rows->isEmpty()) {
            return Response::text('Nenhuma automação encontrada com esses filtros. (Rode `php artisan jana:automations:sync` se o registry estiver vazio.)');
        }

        // Resolve drift por automação (checa filesystem real — fonte de verdade).
        $rows = $rows->map(function (McpAutomation $a) {
            $a->setAttribute('_drift', $this->resolverDrift($a));

            return $a;
        });

        if ($driftFilter !== null) {
            $rows = $rows->filter(fn (McpAutomation $a) => $a->getAttribute('_drift') === $driftFilter)->values();
            if ($rows->isEmpty()) {
                return Response::text("Nenhuma automação com drift={$driftFilter}.");
            }
        }

        $filtros = array_filter([
            $tipo ? "tipo={$tipo}" : null,
            $enabledRaw !== null ? 'enabled=' . (filter_var($enabledRaw, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false') : null,
            $lastStatus ? "last_status={$lastStatus}" : null,
            $driftFilter ? "drift={$driftFilter}" : null,
        ]);
        $filtroStr = empty($filtros) ? 'todas' : implode(' · ', $filtros);

        $output = "Encontradas {$rows->count()} automação(ões) [{$filtroStr}]:\n\n";

        foreach ($rows as $a) {
            $estado = $a->enabled ? 'on' : 'OFF';
            $lastRun = $a->last_run_at ? $a->last_run_at->format('Y-m-d H:i') : 'nunca';
            $lastStatusStr = $a->last_status ?? '—';
            $driftStr = $a->getAttribute('_drift');
            $driftBadge = $driftStr === 'none' ? '' : "  ⚠️ drift={$driftStr}";

            $output .= sprintf(
                "**%s** [%s] [%s]%s\n  gatilho: %s\n  arquivo: %s\n  last_run: %s · last_status: %s%s%s\n",
                $a->slug,
                $a->tipo,
                $estado,
                $a->governed_by_adr ? "  (ADR {$a->governed_by_adr})" : '',
                $a->gatilho,
                $a->arquivo,
                $lastRun,
                $lastStatusStr,
                $a->owner ? "  · owner: {$a->owner}" : '',
                $driftBadge
            );
            if ($a->descricao) {
                $output .= '  ' . mb_substr($a->descricao, 0, 160) . "\n";
            }
            $output .= "\n";
        }

        return Response::text($output);
    }

    /**
     * Drift por automação: missing_file (arquivo declarado sumiu) tem precedência.
     * Crons vivem em Kernel.php (sempre presente) → none por construção.
     */
    private function resolverDrift(McpAutomation $a): string
    {
        if ($a->tipo === 'cron') {
            return 'none';
        }

        $abs = base_path($a->arquivo);
        if (! is_file($abs)) {
            return 'missing_file';
        }

        return 'none';
    }

    private function normalize(mixed $val): ?string
    {
        if ($val === null) {
            return null;
        }
        $v = trim((string) $val);

        return $v === '' ? null : $v;
    }
}
