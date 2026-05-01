<?php

declare(strict_types=1);

namespace Modules\Copiloto\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Modules\Copiloto\Services\TaskRegistry\TaskCrudService;

/**
 * TaskRegistry Fase 1 (US-TR-005) — Tool tasks-update.
 *
 * Atualiza campos de uma task (status/owner/sprint/priority) no DB.
 * NÃO modifica o SPEC.md — DB-only. O SPEC permanece source-of-truth;
 * se o status no SPEC divergir, o próximo webhook sobrescreve.
 */
class TasksUpdateTool extends Tool
{
    protected string $name = 'tasks-update';

    protected string $title = 'Atualizar task (status/owner/sprint/priority)';

    protected string $description = 'Atualiza campos de uma US-* no DB (DB-only, NÃO modifica o SPEC.md). Use pra mudar status, reatribuir owner, mover de sprint. O próximo sync do SPEC sobrescreve — para mudança permanente, edite o SPEC.md.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->string()
                ->description('ID da task, ex: US-NFSE-001')
                ->required(),
            'status' => $schema->string()
                ->description('Novo status: todo|doing|review|done|blocked|cancelled'),
            'owner' => $schema->string()
                ->description('Novo owner (ex: eliana, wagner). Use "—" pra remover.'),
            'sprint' => $schema->string()
                ->description('Novo sprint (ex: B, 2026-W20). Use "—" pra remover.'),
            'priority' => $schema->string()
                ->description('Nova prioridade: p0|p1|p2|p3'),
            'author' => $schema->string()
                ->description('Quem está fazendo a mudança (para audit log). Default: wagner.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $taskId = trim((string) $request->get('task_id', ''));
        if ($taskId === '') {
            return Response::text('❌ task_id é obrigatório.');
        }

        $author = trim((string) $request->get('author', 'wagner')) ?: 'wagner';

        $campos = [];
        foreach (['status', 'owner', 'sprint', 'priority'] as $field) {
            $val = $request->get($field);
            if ($val !== null) {
                $v = trim((string) $val);
                $campos[$field] = ($v === '' || $v === '—' || $v === '-') ? null : $v;
            }
        }

        if (empty($campos)) {
            return Response::text('❌ Nenhum campo para atualizar. Informe ao menos um: status, owner, sprint, priority.');
        }

        // Valida status se fornecido
        if (isset($campos['status'])) {
            $validos = ['todo', 'doing', 'review', 'done', 'blocked', 'cancelled'];
            if (! in_array($campos['status'], $validos, true)) {
                return Response::text('❌ Status inválido: ' . $campos['status'] . '. Válidos: ' . implode(', ', $validos));
            }
        }

        // Valida priority se fornecida
        if (isset($campos['priority']) && ! in_array($campos['priority'], ['p0', 'p1', 'p2', 'p3'], true)) {
            return Response::text('❌ Priority inválida. Válidas: p0, p1, p2, p3.');
        }

        try {
            $result = app(TaskCrudService::class)->update($taskId, $campos, $author);
        } catch (\Throwable $e) {
            return Response::text('❌ ' . $e->getMessage());
        }

        $task   = $result['task'];
        $events = $result['events'];

        if (empty($events)) {
            return Response::text("ℹ️ Nenhuma mudança detectada — valores já são os mesmos em **{$task->task_id}**.");
        }

        $linhas  = "✅ **{$task->task_id}** atualizada por {$author}:\n\n";
        foreach ($events as $ev) {
            $from = $ev->from_value ? "`{$ev->from_value}`" : '_(vazio)_';
            $to   = $ev->to_value   ? "`{$ev->to_value}`"   : '_(removido)_';
            $linhas .= "- **{$ev->event_type}**: {$from} → {$to}\n";
        }
        $linhas .= "\n_DB atualizado. Para mudança permanente, edite também o SPEC.md._";

        return Response::text($linhas);
    }
}
