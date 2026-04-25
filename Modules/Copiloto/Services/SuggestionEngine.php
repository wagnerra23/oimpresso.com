<?php

namespace Modules\Copiloto\Services;

use Modules\Copiloto\Contracts\AiAdapter;
use Modules\Copiloto\Entities\Conversa;
use Modules\Copiloto\Support\ContextoNegocio;

/**
 * SuggestionEngine — orquestra: contexto → prompt → IA → propostas estruturadas.
 *
 * STUB spec-ready: pipeline está montado, mas o prompt e o parse final do
 * JSON estruturado ainda estão pendentes (Fase de implementação real).
 */
class SuggestionEngine
{
    public function __construct(
        protected AiAdapter $ai,
        protected ContextSnapshotService $snapshot,
    ) {
    }

    public function gerarBriefing(?int $businessId): string
    {
        $ctx = $this->snapshot->paraBusiness($businessId);
        return $this->ai->gerarBriefing($ctx);
    }

    /**
     * @return array Propostas — shape ver SPEC.md US-COPI-003.
     */
    public function sugerir(Conversa $conversa, string $prompt): array
    {
        $ctx = $this->snapshot->paraBusiness($conversa->business_id);
        return $this->ai->sugerirMetas($ctx, $prompt);
    }

    public function responder(Conversa $conversa, string $mensagemUser): string
    {
        return $this->ai->responderChat($conversa, $mensagemUser);
    }
}
