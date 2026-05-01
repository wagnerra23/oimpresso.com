<?php

declare(strict_types=1);

namespace Modules\Copiloto\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Modules\Copiloto\Entities\Mcp\McpTask;

/**
 * TaskRegistry Fase 0 — Tool tasks-list.
 *
 * Lista US-* da tabela mcp_tasks com filtros (owner/module/sprint/status/priority).
 * Source-of-truth permanece em git (memory/requisitos/<Mod>/SPEC.md).
 */
class TasksListTool extends Tool
{
    protected string $name = 'tasks-list';

    protected string $title = 'Listar tasks (US-*) do projeto';

    protected string $description = 'Lista user stories (US-*) extraídas dos SPECs canônicos do projeto. Filtra por owner (eliana/wagner/felipe/maira/luiz), módulo (NFSe/Copiloto/etc), sprint, status (todo/doing/review/done/blocked/cancelled) ou priority (p0-p3). Use pra ver "o que é meu" sem ler SPEC.md à mão.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'owner' => $schema->string()
                ->description('Owner (ex: eliana, wagner, felipe). Omite pra todos.'),
            'module' => $schema->string()
                ->description('Módulo (ex: NFSe, Copiloto, Financeiro). Omite pra todos.'),
            'sprint' => $schema->string()
                ->description('Sprint (ex: A, B, 2026-W18). Omite pra todos.'),
            'status' => $schema->string()
                ->description('Status: todo|doing|review|done|blocked|cancelled. Omite pra ativos (todo+doing+review+blocked).'),
            'priority' => $schema->string()
                ->description('Priority: p0|p1|p2|p3. Omite pra todas.'),
            'limit' => $schema->integer()
                ->min(1)
                ->max(100)
                ->default(20)
                ->description('Quantas tasks retornar (default 20, max 100)'),
        ];
    }

    public function handle(Request $request): Response
    {
        $owner    = $this->normalize($request->get('owner'));
        $module   = $this->normalize($request->get('module'));
        $sprint   = $this->normalize($request->get('sprint'));
        $status   = $this->normalize($request->get('status'));
        $priority = $this->normalize($request->get('priority'));
        $limit    = max(1, min(100, (int) $request->get('limit', 20)));

        $q = McpTask::query()
            ->owner($owner)
            ->module($module)
            ->sprint($sprint)
            ->priority($priority);

        if ($status !== null) {
            $q->status($status);
        } else {
            // Default: tasks ativas (esconde done + cancelled)
            $q->whereNotIn('status', ['done', 'cancelled']);
        }

        $q->orderByRaw("FIELD(priority, 'p0','p1','p2','p3')")
            ->orderByRaw("FIELD(status, 'doing','review','blocked','todo','done','cancelled')")
            ->orderBy('task_id');

        $rows = $q->limit($limit)->get();

        if ($rows->isEmpty()) {
            return Response::text("Nenhuma task encontrada com esses filtros.");
        }

        $filtros = array_filter([
            $owner ? "owner={$owner}" : null,
            $module ? "module={$module}" : null,
            $sprint ? "sprint={$sprint}" : null,
            $status ? "status={$status}" : 'status=ativas',
            $priority ? "priority={$priority}" : null,
        ]);
        $filtroStr = implode(' · ', $filtros);

        $output = "Encontradas {$rows->count()} task(s) [{$filtroStr}]:\n\n";

        foreach ($rows as $t) {
            $output .= sprintf(
                "**%s** [%s] [%s] (%s)%s%s%s\n  %s\n",
                $t->task_id,
                $t->status,
                $t->priority ?? 'p2',
                $t->owner ?? 'unowned',
                $t->module ? " · {$t->module}" : '',
                $t->sprint ? " · sprint {$t->sprint}" : '',
                $t->estimate_h ? " · ~{$t->estimate_h}h" : '',
                $t->title
            );
            if (! empty($t->blocked_by)) {
                $output .= '  ⛔ bloqueada por: ' . implode(', ', $t->blocked_by) . "\n";
            }
            $output .= "  _Use `tasks-detail task_id={$t->task_id}` pra ler completa._\n\n";
        }

        return Response::text($output);
    }

    private function normalize(mixed $val): ?string
    {
        if ($val === null) return null;
        $v = trim((string) $val);
        return $v === '' ? null : $v;
    }
}
