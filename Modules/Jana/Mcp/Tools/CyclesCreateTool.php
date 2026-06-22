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
use Modules\Jana\Entities\Mcp\McpTask;
use Modules\Jana\Mcp\Tools\Concerns\AuthorizesMcpMutation;

/**
 * ADR 0070 — cria cycle novo (Linear-style) com goals opcionais em batch.
 *
 * Complementa cycles-close: pivot estratégico → close + create no mesmo dia.
 * Aprendizado retro CYCLE-01 (sessão 2026-05-07) — cycle órfão por 5 dias
 * porque cycles-create não estava exposta como tool MCP.
 *
 * G18 (porta de saída do loop, proposta anti-dup/0294): `propose_backlog=N` lista os
 * top-N candidatos do BACKLOG (tasks sem cycle, status backlog/todo) ordenados por
 * PRIORIDADE — pra o cycle deixar de ser "escrito à mão". READ-ONLY: só propõe no
 * texto, NÃO atribui (zero mutação em massa); o humano puxa via tasks-update.
 */
class CyclesCreateTool extends Tool
{
    use AuthorizesMcpMutation;

    protected string $name = 'cycles-create';

    protected string $title = 'Criar cycle (sprint)';

    protected string $description = 'Cria cycle novo no projeto. Aceita goals[] em batch (description + metric_name + target_value). Status default: planning. Use status=active pra ativar imediatamente. Uniq composto (project_id, key) — chave pode repetir entre projects diferentes. propose_backlog=N sugere os top-N candidatos do backlog por prioridade (read-only).';

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
            'propose_backlog' => $schema->string()->description('G18: nº de candidatos do backlog (sem cycle, por prioridade) a SUGERIR no texto. Read-only, não atribui. Default 0 (off).'),
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

        $md .= $this->proposeBacklog($project->id, $projectKey, (int) $request->get('propose_backlog', 0));

        $md .= "Próximo: `cycles-active project:{$projectKey}` pra ver. ";
        $md .= "Pra ativar: `cycles-create` com status=active OU UPDATE direto.";

        return Response::text($md);
    }

    /**
     * G18 — corte do backlog priorizado (READ-ONLY). Lista os top-N candidatos
     * (tasks sem cycle, status backlog/todo) ordenados por prioridade. Não atribui:
     * o humano confirma e puxa via tasks-update (evita mutação em massa cega).
     */
    private function proposeBacklog(int $projectId, string $projectKey, int $n): string
    {
        if ($n <= 0) {
            return '';
        }
        $n = min($n, 50);

        $candidatos = McpTask::query()
            ->where('project_id', $projectId)
            ->whereNull('cycle_id')
            ->whereIn('status', ['backlog', 'todo'])
            ->orderByRaw('CASE WHEN priority IS NULL OR priority = ? THEN 1 ELSE 0 END', [''])
            ->orderBy('priority')
            ->orderBy('id')
            ->limit($n)
            ->get();

        if ($candidatos->isEmpty()) {
            return "📋 Backlog: nenhum candidato (sem tasks de backlog/todo sem cycle em {$projectKey}).\n\n";
        }

        $md = "## 📋 Candidatos do backlog (corte por prioridade — G18)\n\n";
        $md .= "_Top {$candidatos->count()} de backlog do {$projectKey} sem cycle, por prioridade. **Read-only** (não atribuídos) — puxe pro cycle com `tasks-update task_id:X cycle:{$projectKey}-...`._\n\n";
        foreach ($candidatos as $c) {
            $p = $c->priority ? "`{$c->priority}`" : '`—`';
            $md .= "- {$p} **{$c->task_id}** {$c->title} ({$c->module})\n";
        }

        return $md . "\n";
    }
}
