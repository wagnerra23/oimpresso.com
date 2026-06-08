<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Listeners;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Conversation;
use Modules\Whatsapp\Entities\CustomerMemory;
use Modules\Whatsapp\Events\OmnichannelMessageReceived;
use Modules\Whatsapp\Events\OmnichannelMessageSent;
use Modules\Whatsapp\Jobs\RebuildCustomerMemoryJob;
use Modules\Whatsapp\Services\CustomerMemory\CustomerMemoryRebuilder;
use Throwable;

/**
 * US-WA-VOZ-001 — Atualiza `customer_memory.last_interaction_at` em tempo real.
 *
 * 2 caminhos:
 *   1. Cheap path (síncrono): `Rebuilder::touch()` — UPSERT atômico de
 *      `last_interaction_at` + `first_interaction_at` (COALESCE). 1 query.
 *   2. Heavy path (async): dispatcha `RebuildCustomerMemoryJob` SE
 *      `last_rebuilt_at` está velho OU memória nunca foi rebuildada.
 *      Evita queue depth explodir.
 *
 * Threshold "velho" = configurável `whatsapp.customer_memory.rebuild_after_hours`
 * (default 6h). Garante stats agregados refrescam várias vezes por dia.
 *
 * Tier 0 multi-tenant: `business_id` vem do event (Eloquent global scope OK).
 *
 * @see Modules/Whatsapp/Jobs/RebuildCustomerMemoryJob.php
 * @see Modules/Whatsapp/Services/CustomerMemory/CustomerMemoryRebuilder.php
 */
class TouchCustomerMemoryOnMessage
{
    public function __construct(
        protected readonly CustomerMemoryRebuilder $rebuilder,
    ) {
    }

    /**
     * Handler genérico — funciona pra `OmnichannelMessageReceived` (inbound)
     * E `OmnichannelMessageSent` (outbound). Mesma lógica.
     */
    public function handle(OmnichannelMessageReceived|OmnichannelMessageSent $event): void
    {
        if (! (bool) config('whatsapp.customer_memory.enabled', true)) {
            return;
        }

        $message = $event->message;

        try {
            // SUPERADMIN: listener fora de session HTTP — business_id vem do event
            $conversation = Conversation::query()
                ->withoutGlobalScope(ScopeByBusiness::class)
                ->where('business_id', $message->business_id)
                ->where('id', $message->conversation_id)
                ->first(['id', 'customer_external_id']);

            if ($conversation === null || ! $conversation->customer_external_id) {
                return;
            }

            $extId = ltrim((string) $conversation->customer_external_id, '+');
            if ($extId === '') {
                return;
            }

            // Cheap path — touch last_interaction_at (1 query upsert)
            $this->rebuilder->touch($message->business_id, $extId, $message->created_at ?? now());

            // Heavy path? Só dispatcha rebuild se memória velha ou nunca rebuildada.
            $rebuildAfterHours = (int) config('whatsapp.customer_memory.rebuild_after_hours', 6);

            $needsRebuild = DB::table('customer_memory')
                ->where('business_id', $message->business_id)
                ->where('customer_external_id', $extId)
                ->where(function ($q) use ($rebuildAfterHours) {
                    $q->whereNull('last_rebuilt_at')
                      ->orWhere('last_rebuilt_at', '<', now()->subHours($rebuildAfterHours));
                })
                ->exists();

            if ($needsRebuild) {
                RebuildCustomerMemoryJob::dispatch(
                    $message->business_id,
                    $extId,
                    CustomerMemory::REBUILT_VIA_LISTENER,
                );
            }
        } catch (Throwable $e) {
            Log::channel('single')->warning('[customer_memory.touch_failed]', [
                'metric_name' => 'customer_memory_touch_failed',
                'business_id' => $message->business_id,
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
            // Fail-open: listener NÃO rethrow — não trava pipeline message
        }
    }
}
