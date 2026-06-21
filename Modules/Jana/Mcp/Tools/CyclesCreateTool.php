<?php

declare(strict_types=1);

namespace Modules\Jana\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Modules\Jana\Entities\Mcp\McpCycle;
use Modules\Jana\Entities\Mcp\McpCycleGoal;
use Modules\Jana\Entities\Mcp\McpProject;
use Modules\Jana\Mcp\Tools\Concerns\AuthorizesMcpMutation;

/**
 * ADR 0070 — cria cycle novo (Linear-style) com goals opcionais em batch.
 *
 * Complementa cycles-close: pivot estratégico → close + create no mesmo dia.
 * Aprendizado retro CYCLE-01 (sessão 2026-05-07) — cycle órfão por 5 dias
 * porque cycles-create não estava exposta como tool MCP.
 */
class CyclesCreateTool extends Tool
{
    use AuthorizesMcpMutation;

    protected string $name = 'cycles-create';

    protected string $title = 'Criar cycle (sprint)';

    protected string $description = 'Cria cycle novo no projeto. Aceita goals[] em batch (description + metric_name + target_value). Status default: planning. Use status=active pra ativar imediatamente. Uniq composto (project_id, key) — chave pode repetir entre projects diferentes.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()->description('Project key (default COPI)'),
            'key' => $schema->string()->description('Cycle key (ex: CYCLE-03). Uppercase. Uniq dentro do project.')->required(),
            'name' => $schema->string()->description('Nome curto descritivo do cycle')->required(),
            'start_date' => $schema->string()->description('YYYY-MM-DD início (default: hoje)'),
            'end_date' => $schema->string()->description('YYYY-MM-DD fim')->required(),
            'goal' => $schema->string()->description('Goal mestre outcome-oriented (1 frase)'),
            'goals_json' => $schema->string()->description('JSON array de goals: [{"description":"...","metric_name":"...","target_value":"...","sort_order":1}]'),
            'status' => $schema->string()->description('planning|active|closed (default: planning)'),
        ];
    }

    public function handle(Request $request): Response
    {
        if ($deny = $this->authorizeMcpMutation($request, 'jana.mcp.cycles.manage')) {
            return $deny;
        }

        $projectKey = strtoupper((string) $request->get('project', 'COPI'));
        $project = McpProject::where('key', $projectKey)->first();
        if (! $project) {
            return Response::text("❌ Project '{$projectKey}' não encontrado.");
        }

        $key = strtoupper(trim((string) $request->get('key', '')));
        if ($key === '') {
            return Response::text('❌ key é obrigatório (ex: CYCLE-03).');
        }

        $name = trim((string) $request->get('name', ''));
        if ($name === '') {
            return Response::text('❌ name é obrigatório.');
        }

        $endDate = trim((string) $request->get('end_date', ''));
        if ($endDate === '') {
            return Response::text('❌ end_date é obrigatório (YYYY-MM-DD).');
        }

        $startDate = trim((string) $request->get('start_date', '')) ?: today()->toDateString();
        $status = trim((string) $request->get('status', 'planning')) ?: 'planning';
        if (! in_array($status, McpCycle::STATUSES, true)) {
            return Response::text('❌ status inválido. Válidos: ' . implode(', ', McpCycle::STATUSES));
        }

        if (McpCycle::where('project_id', $project->id)->where('key', $key)->exists()) {
            return Response::text("❌ Cycle '{$key}' já existe em {$projectKey}. Use cycles-active pra ver.");
        }

        $goals = [];
        if ($goalsJson = $request->get('goals_json')) {
            try {
                $decoded = json_decode((string) $goalsJson, true, 32, JSON_THROW_ON_ERROR);
                if (! is_array($decoded)) {
                    throw new \RuntimeException('goals_json deve ser array.');
                }
                $goals = $decoded;
            } catch (\Throwable $e) {
                return Response::text('❌ goals_json inválido: ' . $e->getMessage());
            }
        }

        $cycleId = null;
        $goalsInserted = 0;

        DB::transaction(function () use ($project, $key, $name, $startDate, $endDate, $status, $request, $goals, &$cycleId, &$goalsInserted) {
            $cycle = McpCycle::create([
                'project_id' => $project->id,
                'key' => $key,
                'name' => $name,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'goal' => $request->get('goal'),
                'status' => $status,
            ]);
            $cycleId = $cycle->id;

            foreach ($goals as $i => $g) {
                if (! is_array($g) || empty($g['description'])) {
                    continue;
                }
                McpCycleGoal::create([
                    'cycle_id' => $cycle->id,
                    'description' => (string) $g['description'],
                    'metric_name' => $g['metric_name'] ?? null,
                    'target_value' => isset($g['target_value']) ? (string) $g['target_value'] : null,
                    'achieved_value' => isset($g['achieved_value']) ? (string) $g['achieved_value'] : null,
                    'status' => $g['status'] ?? 'open',
                    'sort_order' => $g['sort_order'] ?? ($i + 1),
                ]);
                $goalsInserted++;
            }
        });

        $md = "✅ Cycle **{$key}** criado em {$projectKey} (id={$cycleId}, status={$status}).\n\n";
        $md .= "📅 {$startDate} → {$endDate}\n\n";
        if ($goalsInserted > 0) {
            $md .= "🎯 **{$goalsInserted}** goals inseridos.\n\n";
        }
        $md .= "Próximo: `cycles-active project:{$projectKey}` pra ver. ";
        $md .= "Pra ativar: `cycles-create` com status=active OU UPDATE direto.";

        return Response::text($md);
    }
}
