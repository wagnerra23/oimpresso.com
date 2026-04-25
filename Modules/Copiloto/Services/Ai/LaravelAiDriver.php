<?php

namespace Modules\Copiloto\Services\Ai;

use Modules\Copiloto\Contracts\AiAdapter;
use Modules\Copiloto\Entities\Conversa;
use Modules\Copiloto\Support\ContextoNegocio;

/**
 * LaravelAiDriver — delega pro módulo LaravelAI quando disponível.
 *
 * STUB spec-ready: Aguardando o módulo LaravelAI existir de fato.
 * Quando existir, este driver vai delegar pro agente central dele.
 * Ver adr/tech/0002-adapter-ia-laravelai-ou-openai.md.
 */
class LaravelAiDriver implements AiAdapter
{
    public function gerarBriefing(ContextoNegocio $ctx): string
    {
        // TODO: app(\Modules\LaravelAI\Contracts\Agent::class)->briefing($ctx)
        throw new \LogicException('LaravelAiDriver ainda não implementado — módulo LaravelAI pendente.');
    }

    public function sugerirMetas(ContextoNegocio $ctx, string $prompt): array
    {
        throw new \LogicException('LaravelAiDriver ainda não implementado — módulo LaravelAI pendente.');
    }

    public function responderChat(Conversa $conv, string $mensagem): string
    {
        throw new \LogicException('LaravelAiDriver ainda não implementado — módulo LaravelAI pendente.');
    }
}
