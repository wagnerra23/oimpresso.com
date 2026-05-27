<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Observers;

use Illuminate\Support\Facades\Log;
use Modules\Whatsapp\Entities\ClientFeedback;
use Modules\Whatsapp\Services\FeedbackRelevanceService;

/**
 * ClientFeedbackObserver — auto-compute signature + relevance_score em todo write.
 *
 * Hook creating: signature + last_seen_at + score inicial.
 * Hook updating: rescore se severity ou recorrente_count mudou.
 *
 * NÃO faz dedup aqui — dedup vive em ClientFeedbackController::capture()
 * antes do create(), pra retornar feedback existente (idempotência cliente HTTP).
 */
class ClientFeedbackObserver
{
    public function __construct(protected FeedbackRelevanceService $relevance)
    {
    }

    public function creating(ClientFeedback $fb): void
    {
        // Signature determinística
        if (! $fb->signature) {
            $fb->signature = $this->relevance->computeSignature($fb);
        }

        // last_seen_at = agora se não setado
        if (! $fb->last_seen_at) {
            $fb->last_seen_at = now();
        }

        // Score inicial — pode ser refinado depois pelo job de reindex
        if ($fb->relevance_score === null || $fb->relevance_score == 0.0) {
            $fb->relevance_score = $this->relevance->computeScore($fb);
            $fb->relevance_score_at = now();
        }
    }

    public function updating(ClientFeedback $fb): void
    {
        // Se severity OU recorrente_count mudou, recompute score
        $dirty = $fb->getDirty();
        $needsRescore = isset($dirty['severity_nng']) || isset($dirty['recorrente_count']) || isset($dirty['last_seen_at']);

        if ($needsRescore) {
            $fb->relevance_score = $this->relevance->computeScore($fb);
            $fb->relevance_score_at = now();

            Log::debug('[feedback.observer] rescore on update', [
                'feedback_id' => $fb->id,
                'business_id' => $fb->business_id,
                'score' => $fb->relevance_score,
                'reason' => array_keys($dirty),
            ]);
        }
    }
}
