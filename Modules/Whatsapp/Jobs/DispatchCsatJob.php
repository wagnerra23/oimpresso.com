<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Services\Csat\CsatDispatcher;

/**
 * DispatchCsatJob — wrapper async pra `CsatDispatcher::dispatchOnResolve`.
 *
 * Disparado por `InboxController::updateStatus` quando atendente muda status
 * pra `resolved`. Roda em fila pra não bloquear o request (daemon HTTP pode
 * demorar 1-3s pra responder).
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093):
 *   - Construtor recebe `$businessId` + `$conversationId` + `$resolvedBy` —
 *     fila não tem session(), passamos contexto explícito.
 *   - `withoutGlobalScopes` no SELECT da Conversation (sem session user).
 *
 * Idempotência: `CsatDispatcher::dispatchOnResolve` checa CsatResponse pending
 * nas últimas 24h e retorna null. Re-dispatch (atendente clica resolve 2×)
 * não duplica.
 *
 * @see CsatDispatcher
 * @see memory/requisitos/Whatsapp/COMPARATIVO-MERCADO-2026-05-12.md gap #5 P1
 */
class DispatchCsatJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        public int $businessId,
        public int $conversationId,
        public int $resolvedBy,
    ) {
        $this->onQueue(config('whatsapp.queue', 'whatsapp'));
    }

    public function handle(CsatDispatcher $dispatcher): void
    {
        // SUPERADMIN: job assíncrono sem session() — bypass global scope pra
        // carregar a Conversation; business_id explícito no constructor é
        // a fonte de verdade (passa filter manual).
        $conv = Conversation::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $this->businessId)
            ->where('id', $this->conversationId)
            ->first();

        if (! $conv) {
            Log::warning('[csat.job.conversation_not_found]', [
                'business_id' => $this->businessId,
                'conversation_id' => $this->conversationId,
            ]);
            return;
        }

        $dispatcher->dispatchOnResolve($conv, $this->resolvedBy);
    }
}
