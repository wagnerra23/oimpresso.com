<?php

declare(strict_types=1);

namespace Modules\Jana\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Modules\Jana\Mcp\Tools\Concerns\AuthorizesMcpMutation;
use Modules\Jana\Services\TaskRegistry\TaskCrudService;

/**
 * TaskRegistry Fase 1 (US-TR-005) — Tool tasks-update.
 *
 * Atualiza campos de uma task (status/owner/sprint/priority) no DB.
 * NÃO modifica o SPEC.md — DB-only.
 *
 * ADR 0144 (2026-05-13): mudança via tasks-update é DURÁVEL. O webhook
 * de sync do SPEC.md NÃO sobrescreve mais status/owner/sprint/priority
 * em tasks já existentes — DB virou canon de estado vivo, SPEC virou
 * template descritivo. Não precisa mais editar o SPEC pra fixar o status.
 */
class TasksUpdateTool extends Tool
{
    use AuthorizesMcpMutation;

    protected string $name = 'tasks-update';

    protected string $title = 'Atualizar task (status/owner/sprint/priority)';

    protected string $description = 'Atualiza campos de uma US-* no DB. Mudança é DURÁVEL — o webhook de sync do SPEC.md não sobrescreve status/owner/sprint/priority em tasks existentes (ADR 0144). Use pra mudar status, reatribuir owner, mover de sprint, mudar priority. O SPEC.md continua sendo fonte da descrição/título/labels e do estado inicial de USs novas.';

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
            'module' => $schema->string()
                ->description('Mover task pra outro módulo (ex: COPI→JANA pós-rename ADR 0088). Use uppercase.'),
            'acceptance_ref' => $schema->string()
                ->description('Prova de DoD pra fechar a task (URL do PR / commit SHA / path de teste Pest / evidência smoke). Recomendado ao mover pra done — Fase 2 ADR 0278. Use "—" pra limpar.'),
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

        // A5 (ADR 0278): principal CONFIÁVEL = dono do token MCP (não o $author
        // auto-declarado/spoofável). Alimenta o sinal de mutação claim-less no
        // TaskCrudService. Mesmo idiom do TasksClaimTool (human_principal do lease).
        $user = $request->user();
        $principal = $user !== null
            ? (string) ($user->username ?? $user->email ?? ('user#' . $user->getAuthIdentifier()))
            : null;

        $campos = [];
        foreach (['status', 'owner', 'sprint', 'priority', 'module', 'acceptance_ref'] as $field) {
            $val = $request->get($field);
            if ($val !== null) {
                $v = trim((string) $val);
                if ($field === 'module') {
                    $v = strtoupper($v);
                }
                $campos[$field] = ($v === '' || $v === '—' || $v === '-') ? null : $v;
            }
        }

        if (empty($campos)) {
            return Response::text('❌ Nenhum campo para atualizar. Informe ao menos um: status, owner, sprint, priority, module.');
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

        // A3 (ADR 0070/0278) — scope FINO advance vs close. Fechar (status→done/
        // cancelled) é transição terminal → exige jana.mcp.tasks.close; qualquer
        // outra mutação → jana.mcp.tasks.advance. Backward-safe: o umbrella legado
        // jana.mcp.tasks.write autoriza AMBOS (tokens existentes não quebram).
        // Checado AQUI (não no topo) porque advance/close depende do status parseado.
        $terminal = isset($campos['status']) && in_array($campos['status'], ['done', 'cancelled'], true);
        $scope = $terminal ? 'jana.mcp.tasks.close' : 'jana.mcp.tasks.advance';
        $deny = $this->authorizeMcpMutation($request, $scope);
        if ($deny !== null && $this->authorizeMcpMutation($request, 'jana.mcp.tasks.write') !== null) {
            return $deny; // sem o scope fino E sem o umbrella legado → nega
        }

        try {
            $result = app(TaskCrudService::class)->update($taskId, $campos, $author, $principal);
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
        $linhas .= "\n_DB atualizado — mudança é durável (ADR 0144: DB canon, SPEC template)._";

        return Response::text($linhas);
    }
}
