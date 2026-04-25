<?php

namespace Modules\Copiloto\Contracts;

use Modules\Copiloto\Entities\Conversa;
use Modules\Copiloto\Support\ContextoNegocio;

/**
 * Adapter de IA — ver adr/tech/0002-adapter-ia-laravelai-ou-openai.md.
 *
 * Implementações:
 * - Modules\Copiloto\Services\Ai\LaravelAiDriver (quando módulo LaravelAI ativo)
 * - Modules\Copiloto\Services\Ai\OpenAiDirectDriver (fallback via openai-php)
 */
interface AiAdapter
{
    public function gerarBriefing(ContextoNegocio $ctx): string;

    /**
     * @return array Propostas estruturadas — ver SPEC.md seção Chat/US-COPI-003.
     */
    public function sugerirMetas(ContextoNegocio $ctx, string $prompt): array;

    public function responderChat(Conversa $conv, string $mensagem): string;
}
