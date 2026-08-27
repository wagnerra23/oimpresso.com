<?php

namespace Modules\Jana\Services;

use App\Util\OtelHelper;
use Modules\Jana\Contracts\AiAdapter;
use Modules\Jana\Entities\Conversa;
use Modules\Jana\Support\ContextoNegocio;

/**
 * SuggestionEngine — orquestra: contexto → prompt → IA → propostas estruturadas.
 *
 * ⚠️ Este docblock declarava "STUB spec-ready: o prompt e o parse final do JSON
 * estruturado ainda estão pendentes". O `SPEC.md` já tinha medido e registrado que
 * isso é FALSO (§US-COPI-003, "o agente gera as propostas e o driver valida o shape
 * campo-a-campo") — o docblock só não tinha sido corrigido. Removido em 2026-08-27.
 *
 * O fato que o SPEC registra e que continua valendo é OUTRO, e mais incômodo: o
 * `ChatController` injeta esta classe no construtor e **nunca a chama**. O dono desse
 * fato é o SPEC — não o repito aqui em número nem em estado (§5 2026-07-17).
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
