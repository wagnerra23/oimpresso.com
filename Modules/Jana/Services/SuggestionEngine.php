<?php

namespace Modules\Jana\Services;

use App\Util\OtelHelper;
use Modules\Jana\Contracts\AiAdapter;
use Modules\Jana\Entities\Conversa;
use Modules\Jana\Support\ContextoNegocio;

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
        // D9.a OTel Wave 17 — chamada LLM externa (latência + custo a rastrear).
        return OtelHelper::spanBiz('jana.suggestion.briefing', function () use ($businessId) {
            $ctx = $this->snapshot->paraBusiness($businessId);
            return $this->ai->gerarBriefing($ctx);
        }, ['biz.scope' => $businessId]);
    }

    /**
     * @return array Propostas — shape ver SPEC.md US-COPI-003.
     */
    public function sugerir(Conversa $conversa, string $prompt): array
    {
        // D9.a OTel Wave 17 — chamada LLM externa (estructured output).
        return OtelHelper::spanBiz('jana.suggestion.sugerir', function () use ($conversa, $prompt) {
            $ctx = $this->snapshot->paraBusiness($conversa->business_id);
            return $this->ai->sugerirMetas($ctx, $prompt);
        }, ['conversa.id' => $conversa->id, 'prompt.length' => strlen($prompt)]);
    }

    public function responder(Conversa $conversa, string $mensagemUser): string
    {
        // D9.a OTel Wave 17 — chat completion (path quente, latência crítica).
        return OtelHelper::spanBiz('jana.suggestion.responder', fn () => $this->ai->responderChat($conversa, $mensagemUser), [
            'conversa.id'  => $conversa->id,
            'msg.length'   => strlen($mensagemUser),
        ]);
    }

}
