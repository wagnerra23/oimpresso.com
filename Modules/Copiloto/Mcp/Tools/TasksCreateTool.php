<?php

declare(strict_types=1);

namespace Modules\Copiloto\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Modules\Copiloto\Services\TaskRegistry\TaskCrudService;

/**
 * TaskRegistry Fase 1 (US-TR-005) — Tool tasks-create.
 *
 * Gera nova US-* no SPEC.md do módulo e registra evento.
 * Se o servidor não tiver permissão de escrita, retorna o markdown
 * pra o usuário colar manualmente.
 *
 * O próximo webhook (após git push) sincroniza a nova task pro DB.
 */
class TasksCreateTool extends Tool
{
    protected string $name = 'tasks-create';

    protected string $title = 'Criar nova task (US-*) no SPEC.md';

    protected string $description = 'Cria uma nova US-* no SPEC.md do módulo especificado. O ID é gerado automaticamente (US-{MODULE}-{NNN}). Se o servidor tiver acesso de escrita, o arquivo é atualizado on-the-spot; senão retorna o markdown pra colar manualmente. O próximo git push + webhook sincroniza pro DB.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'module' => $schema->string()
                ->description('Módulo alvo (ex: NFSe, Copiloto, Financeiro). Deve ter SPEC.md em memory/requisitos/{module}/.')
                ->required(),
            'title' => $schema->string()
                ->description('Título da user story (ex: "Cadastrar fornecedor")')
                ->required(),
            'owner' => $schema->string()
                ->description('Owner da task (ex: eliana, wagner). Omite pra sem owner.'),
            'sprint' => $schema->string()
                ->description('Sprint (ex: A, B, 2026-W20). Omite pra sem sprint.'),
            'priority' => $schema->string()
                ->description('Prioridade: p0|p1|p2|p3. Default: p2.'),
            'estimate_h' => $schema->number()
                ->description('Estimativa em horas (ex: 4, 8, 12).'),
            'blocked_by' => $schema->string()
                ->description('IDs de tasks que bloqueiam esta, separados por vírgula (ex: US-NFSE-001,US-NFSE-002).'),
            'description' => $schema->string()
                ->description('Descrição/acceptance criteria em markdown (bullets, etc).'),
            'author' => $schema->string()
                ->description('Quem está criando (para audit log). Default: wagner.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $module = trim((string) $request->get('module', ''));
        $title  = trim((string) $request->get('title', ''));

        if ($module === '') return Response::text('❌ module é obrigatório.');
        if ($title === '')  return Response::text('❌ title é obrigatório.');

        $blockedByRaw = trim((string) $request->get('blocked_by', ''));
        $blockedBy    = $blockedByRaw !== '' && $blockedByRaw !== '—'
            ? array_map('trim', explode(',', $blockedByRaw))
            : null;

        $data = [
            'module'      => $module,
            'title'       => $title,
            'owner'       => $request->get('owner') ?: null,
            'sprint'      => $request->get('sprint') ?: null,
            'priority'    => $request->get('priority') ?: 'p2',
            'estimate_h'  => $request->get('estimate_h') ?: null,
            'blocked_by'  => $blockedBy,
            'description' => $request->get('description') ?: null,
            'author'      => trim((string) $request->get('author', 'wagner')) ?: 'wagner',
        ];

        try {
            $result = app(TaskCrudService::class)->create($data);
        } catch (\Throwable $e) {
            return Response::text('❌ ' . $e->getMessage());
        }

        $taskId   = $result['task_id'];
        $written  = $result['written'];
        $specPath = $result['spec_path'];
        $markdown = $result['markdown'];

        if ($written) {
            $out  = "✅ **{$taskId}** criada e adicionada em `{$specPath}`.\n\n";
            $out .= "Faça `git add {$specPath} && git commit -m 'feat: {$taskId}'` e o webhook sincronizará pro DB.\n\n";
            $out .= "**Bloco gerado:**\n```markdown{$markdown}```";
        } else {
            $out  = "⚠️ **{$taskId}** gerada, mas não foi possível escrever no arquivo (permissão).\n\n";
            $out .= "Copie o bloco abaixo e adicione manualmente em `{$specPath}`:\n\n";
            $out .= "```markdown{$markdown}```\n\n";
            $out .= "_Após o commit+push, o webhook sincronizará pro DB automaticamente._";
        }

        return Response::text($out);
    }
}
