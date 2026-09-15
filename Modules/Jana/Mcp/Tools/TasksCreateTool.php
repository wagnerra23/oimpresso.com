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
 * TaskRegistry Fase 1 (US-TR-005) — Tool tasks-create.
 *
 * Gera o BLOCO markdown de uma US-* nova e registra o evento. NÃO escreve em
 * arquivo: quem materializa a US é o chamador, commitando no git (US-COPI-149).
 *
 * O próximo webhook (após git push) sincroniza a nova task pro DB.
 */
class TasksCreateTool extends Tool
{
    use AuthorizesMcpMutation;

    protected string $name = 'tasks-create';

    protected string $title = 'Criar nova task (US-*) no SPEC.md';

    protected string $description = 'Gera o bloco markdown de uma US-* nova e reserva o ID (US-{MODULE}-{NNN}). NÃO escreve no SPEC: você cola o bloco no seu repo, commita e pusha — o webhook sincroniza pro DB. A US só existe depois do commit.';

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
        if ($deny = $this->authorizeMcpMutation($request, 'jana.mcp.tasks.write')) {
            return $deny;
        }

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
        $specPath = $result['spec_path'];
        $markdown = $result['markdown'];

        // US-COPI-149: um ramo só, e ele NÃO afirma durabilidade. A US existe
        // quando o chamador commita — não quando esta tool responde. O ramo
        // antigo dizia "criada e adicionada" e mandava rodar `git add` num path
        // do SERVIDOR, que o deploy apaga e onde ninguém pode commitar.
        $out = "📋 **{$taskId}** gerada — **ela ainda NÃO existe** até você commitar.\n\n";
        if ($markdown !== null && $specPath !== null) {
            $out .= "Cole o bloco abaixo em `{$specPath}` **no seu repo**, commite e pushe — o webhook sincroniza pro DB.\n\n";
            $out .= "```markdown{$markdown}```\n\n";
            $out .= "_O ID já está reservado contra colisão (max(DB, SPEC)). Nada foi escrito no servidor: o SPEC é a fonte de US nova (ADR 0144), e escrita no checkout de lá é apagada pelo próximo deploy (US-COPI-149)._";
        } else {
            $out .= "_Task ad-hoc (projeto sem módulo canônico): não há bloco de SPEC — ela vive no DB._";
        }
        return Response::text($out);
    }
}
