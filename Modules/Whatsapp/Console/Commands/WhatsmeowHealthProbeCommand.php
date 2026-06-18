<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Services\Drivers\WhatsmeowState;
use Modules\Whatsapp\Services\WhatsmeowReconciler;

/**
 * whatsmeow:health-probe — detecta sessão whatsmeow caída e converge channel_health.
 *
 * **Lacuna fechada (incidente 2026-06-18, US-WA-308).** O reconciler de 5min
 * (`whatsapp:channels-reconcile`) é Baileys-only; canais `whatsapp_whatsmeow` só
 * sabiam de queda via webhook `Disconnected`/`LoggedOut`. Mas o WuzAPI NÃO assina
 * `LoggedOut` por default ("Skipping webhook. Not subscribed for this type=LoggedOut"),
 * então um "logged out from another device" nunca chega ao app → `channel_health`
 * fica `healthy` eternamente e a Caixa Unificada não avisa. Foi exatamente o que
 * deixou o canal 11 (biz=1) caído ~3h sem ninguém ver.
 *
 * Este probe consulta o estado REAL do daemon (`Reconciler.reconcile()` →
 * `/session/status`) pra cada canal whatsmeow ATIVO e converge o DB:
 *   - LOGGED_OUT / NOT_EXISTS / PROVISION_PENDING → `markDisconnectedInDb` (canal
 *     ATIVO com sessão não conectada = caiu → re-parear). PROVISION_PENDING incluído
 *     no fix 2026-06-18: o daemon retorna Connected=false (não LOGGED_OUT) quando a
 *     sessão morre — era a razão do canal 11 caído não ser detectado.
 *   - BANNED                   → `markDisconnectedInDb(banDetected: true)`
 *   - PAIRED                   → `markPairedInDb` (só re-marca se estava unhealthy)
 *   - DAEMON_UNREACHABLE / QR_PENDING / ERROR → NÃO toca o health (daemon down ou
 *     pareamento em curso não é queda do canal — `daemon-source-drift-check` cobre).
 *
 * Idempotente (convergente, não acumulativo). Multi-tenant Tier 0 (ADR 0093):
 * varre cross-business (cron sem session), cada row carrega seu `business_id` e o
 * Reconciler nunca atravessa fronteira de tenant.
 *
 * @see Modules\Whatsapp\Services\WhatsmeowReconciler
 * @see Modules\Whatsapp\Http\Controllers\Api\WhatsmeowWebhookController (caminho webhook)
 * @see memory/requisitos/Whatsapp/SPEC.md (US-WA-308)
 */
class WhatsmeowHealthProbeCommand extends Command
{
    protected $signature = 'whatsmeow:health-probe {--detail : Loga o estado observado de cada canal}';

    protected $description = 'Sonda o daemon e marca channel_health quando a sessão whatsmeow cai (incidente 2026-06-18).';

    public function handle(WhatsmeowReconciler $reconciler): int
    {
        // SUPERADMIN: cron sem session — Tier 0 garantido pelo business_id de cada row
        $channels = Channel::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('type', Channel::TYPE_WHATSAPP_WHATSMEOW)
            ->where('status', 'active')
            ->get();

        if ($channels->isEmpty()) {
            $this->info('Nenhum canal whatsmeow ativo pra sondar.');

            return self::SUCCESS;
        }

        $flipped = 0;

        foreach ($channels as $channel) {
            $state = $reconciler->reconcile($channel);
            $healthBefore = (string) $channel->channel_health;

            // PROVISION_PENDING incluído (fix 2026-06-18): pra um canal ATIVO, o
            // daemon WuzAPI retorna Connected=false → reconcile() devolve
            // PROVISION_PENDING (não LOGGED_OUT). Era a razão do canal 11 caído não
            // ser detectado. Active + sessão não conectada = caiu → disconnected.
            if (in_array($state, [WhatsmeowState::LOGGED_OUT, WhatsmeowState::NOT_EXISTS, WhatsmeowState::PROVISION_PENDING], true)) {
                if ($healthBefore !== 'disconnected') {
                    $reconciler->markDisconnectedInDb($channel, "health-probe: {$state->value}");
                    $flipped++;
                }
            } elseif ($state === WhatsmeowState::BANNED) {
                if ($healthBefore !== 'banned') {
                    $reconciler->markDisconnectedInDb($channel, 'health-probe: banned', banDetected: true);
                    $flipped++;
                }
            } elseif ($state === WhatsmeowState::PAIRED) {
                if ($healthBefore !== 'healthy') {
                    $jid = is_array($channel->config_json) ? ($channel->config_json['whatsmeow_jid'] ?? null) : null;
                    $reconciler->markPairedInDb($channel, is_string($jid) ? $jid : null);
                    $flipped++;
                }
            }
            // DAEMON_UNREACHABLE / QR_PENDING / ERROR → não muta (daemon down ou
            // pareamento em curso, não é queda do canal — evita falso-positivo)

            if ($this->option('detail')) {
                $this->line(sprintf(
                    '  ch#%d %-22s biz=%d · %s → health=%s',
                    $channel->id,
                    "'{$channel->label}'",
                    $channel->business_id,
                    $state->value,
                    $channel->channel_health,
                ));
            }
        }

        Log::info('whatsmeow.health_probe.done', [
            'event' => 'whatsmeow.health_probe.done',
            'probed' => $channels->count(),
            'flipped' => $flipped,
        ]);

        $this->info("Sondados {$channels->count()} canal(is) whatsmeow · {$flipped} mudou de estado.");

        return self::SUCCESS;
    }
}
