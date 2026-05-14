<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Observers;

use Illuminate\Support\Facades\Log;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Jobs\DeleteBaileysInstanceJob;

/**
 * ChannelObserver — sync Laravel→daemon Baileys quando channel desativa/some.
 *
 * **Por que existe (incident 2026-05-13):** Channels desativados/banidos no
 * Laravel ficaram com instâncias zumbis no daemon CT 100 consumindo CPU +
 * acelerando ban Meta. Não havia mecanismo de cleanup automático — só
 * purge manual via curl. Este observer fecha o gap A do post-mortem.
 *
 * **Eventos cobertos:**
 *
 * 1. `updating` — antes do save, compara status anterior vs novo.
 *    Se transitar de qualquer estado pra `banned`/`disconnected`/`removed`/`setup`
 *    E houver `display_identifier` (Baileys), dispatch DeleteBaileysInstanceJob.
 *
 * 2. `deleted` — após delete (hard ou soft), dispatch DeleteBaileysInstanceJob
 *    pra purgar credenciais do daemon antes que o registro suma.
 *
 * **NÃO dispatch quando:**
 * - Channel type ≠ whatsapp_baileys (Z-API e Meta Cloud não têm daemon próprio)
 * - display_identifier vazio (nunca foi pareado — daemon não tem instance)
 * - Já está no estado-alvo (transição idempotente)
 *
 * **Multi-tenant Tier 0 (ADR 0093):** observer roda síncrono no contexto do
 * request que mudou o Channel. business_id é propriedade do model e vai no
 * job constructor.
 *
 * @see Modules/Whatsapp/Jobs/DeleteBaileysInstanceJob.php
 * @see memory/sessions/2026-05-13-whatsapp-incident-zombie-banned-loop.md (Gap A)
 */
class ChannelObserver
{
    /**
     * Estados que indicam "instance no daemon deve ser purgada".
     *
     * `setup` é incluído porque um channel volta pra setup quando reseta
     * config — daemon deve esquecer creds anteriores.
     */
    private const DEACTIVATION_STATES = ['banned', 'disconnected', 'removed', 'setup'];

    public function updating(Channel $channel): void
    {
        if (! $this->isBaileysChannel($channel)) {
            return;
        }

        $oldStatus = $channel->getOriginal('status');
        $newStatus = $channel->status;

        if ($oldStatus === $newStatus) {
            return;
        }

        // Transição: estava ativo → deactivation_state
        if (! in_array($oldStatus, self::DEACTIVATION_STATES, true)
            && in_array($newStatus, self::DEACTIVATION_STATES, true)
        ) {
            $this->dispatchPurge(
                channel: $channel,
                reason: "status_transition_{$oldStatus}_to_{$newStatus}",
            );
        }
    }

    public function deleted(Channel $channel): void
    {
        if (! $this->isBaileysChannel($channel)) {
            return;
        }

        $this->dispatchPurge(
            channel: $channel,
            reason: 'channel_deleted',
        );
    }

    private function isBaileysChannel(Channel $channel): bool
    {
        return $channel->type === Channel::TYPE_WHATSAPP_BAILEYS;
    }

    private function dispatchPurge(Channel $channel, string $reason): void
    {
        $instanceId = $this->resolveInstanceId($channel);

        if ($instanceId === null || $instanceId === '') {
            Log::info('[whatsapp.channel-observer] sem instance_id pra purgar — skip', [
                'channel_id' => $channel->id,
                'business_id' => $channel->business_id,
                'reason' => $reason,
            ]);

            return;
        }

        Log::info('[whatsapp.channel-observer] dispatching DeleteBaileysInstanceJob', [
            'channel_id' => $channel->id,
            'business_id' => $channel->business_id,
            'instance_id' => $instanceId,
            'reason' => $reason,
        ]);

        DeleteBaileysInstanceJob::dispatch(
            businessId: $channel->business_id,
            instanceId: $instanceId,
            reason: $reason,
        );
    }

    /**
     * Delega pro helper canônico `Channel::baileysInstanceId()` — fonte única
     * da convenção `ch-{uuid sem hífens}`. Evita drift entre Observer + Job +
     * runbook do agent `whatsapp-doctor`.
     */
    private function resolveInstanceId(Channel $channel): ?string
    {
        return $channel->baileysInstanceId();
    }
}
