<?php

declare(strict_types=1);

namespace Modules\Jana\Mcp\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Modules\Jana\Entities\Mcp\McpCycle;
use Modules\Jana\Entities\Mcp\McpProject;

/**
 * ADR 0070 — DEPRECATED. Use `cycles-active` em vez.
 *
 * Mantido como alias compatível: redireciona pro CyclesActiveTool comportamento.
 * CURRENT.md foi removido em 2026-05-04 — fonte agora é mcp_cycles ativo.
 */
class TasksCurrentTool extends Tool
{
    protected string $name = 'tasks-current';

    protected string $title = '[DEPRECATED] Use cycles-active';

    protected string $description = '⚠️ DEPRECATED (ADR 0070): use `cycles-active` em vez. Mantido como alias durante migração — retorna o cycle ATIVO do project COPI.';

    public function handle(Request $request): Response
    {
        $project = McpProject::where('key', 'COPI')->first();
        if (! $project) {
            return Response::text("Project COPI não encontrado. Rode McpDefaultsSeeder.");
        }

        $cycle = McpCycle::where('project_id', $project->id)->where('status', 'active')->first();
        if (! $cycle) {
            return Response::text("Nenhum cycle ATIVO em COPI. Use `cycles-list` ou `cycles-create`.");
        }

        // Delega para CyclesActiveTool sem instanciá-lo (evita reflection na req).
        $tool = new CyclesActiveTool();
        return $tool->handle($request);
    }
}
