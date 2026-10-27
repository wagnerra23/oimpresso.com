<?php

declare(strict_types=1);

namespace Modules\Jana\Ai\Tools\Acoes;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Modules\Jana\Contracts\DeclaraPermissao;
use Modules\Jana\Services\AcoesDoChat\AcaoPropostaException;
use Modules\Jana\Services\AcoesDoChat\PropostaAcaoService;
use Stringable;

/**
 * Tool de AÇÃO do chat (ADR 0440): PROPÕE criar uma tarefa. Não cria.
 *
 * Devolve ao modelo o id da proposta e o resumo; quem cria é o usuário, ao clicar
 * em Confirmar no cartão. `business_id`, usuário e conversa vêm do constructor
 * (ADR 0141) — o schema não tem campo de empresa, e o modelo não escolhe tenant.
 */
class ProporTarefaTool implements Tool, DeclaraPermissao
{
    public function __construct(
        private readonly int $businessId,
        private readonly Model $user,
        private readonly ?int $conversaId,
    ) {}

    public function description(): Stringable|string
    {
        return 'Propõe criar uma TAREFA para o próprio usuário (ex.: "me lembra de ligar pro fornecedor amanhã"). '
            .'NÃO cria nada: devolve uma proposta que o usuário confirma num cartão. Use só quando ele pedir '
            .'claramente uma tarefa ou um lembrete. Depois de chamar, diga que a tarefa será criada quando ele '
            .'clicar em Confirmar no cartão — nunca diga que já criou.';
    }

    public function handle(Request $request): Stringable|string
    {
        try {
            $p = app(PropostaAcaoService::class)->propor($this->businessId, $this->user, $this->conversaId, 'criar-tarefa', [
                'titulo'     => $request['titulo'] ?? null,
                'data'       => $request['data'] ?? null,
                'prioridade' => $request['prioridade'] ?? null,
                'descricao'  => $request['descricao'] ?? null,
            ]);
        } catch (ValidationException $e) {
            return json_encode(['erro' => 'Campos inválidos: '.implode(' ', $e->validator->errors()->all())], JSON_UNESCAPED_UNICODE);
        } catch (AcaoPropostaException $e) {
            return json_encode(['erro' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }

        return json_encode([
            'proposta_id' => $p->id,
            'resumo'      => $p->previa,
            'status'      => 'aguardando_confirmacao',
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /** A mesma permissão de criar tarefa na tela (`TodoService::canAdd`). */
    public function permission(): string
    {
        return 'essentials.add_todos';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'titulo'     => $schema->string()->description('O que fazer, em uma frase curta.')->required(),
            'data'       => $schema->string()->description('Data no formato AAAA-MM-DD. Hoje se o usuário não disse.'),
            'prioridade' => $schema->string()->enum(['low', 'medium', 'high', 'urgent'])->description('Padrão: medium.'),
            'descricao'  => $schema->string()->description('Detalhe opcional.'),
        ];
    }
}
