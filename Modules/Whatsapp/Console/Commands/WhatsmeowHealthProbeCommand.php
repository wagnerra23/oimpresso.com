<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Entities\Conversation;
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
 *   - LOGGED_OUT / NOT_EXISTS  → `markDisconnectedInDb` (sessão morreu → re-parear)
 *   - BANNED                   → `markDisconnectedInDb(banDetected: true)`
 *   - PAIRED                   → `markPairedInDb` (só re-marca se estava unhealthy)
 *   - DAEMON_UNREACHABLE / QR_PENDING / PROVISION_PENDING / ERROR → NÃO toca o
 *     health (queda transitória do daemon ou pareamento em curso não é logout —
 *     evita falso-positivo; `whatsapp:daemon-source-drift-check` cobre daemon down).
 *
 * **Saúde por mensagem real (Camada 2 · incidente 2026-06-18 inverso).** O flag
 * `loggedIn` do WuzAPI é não-confiável: ele reportou caído num canal que recebia
 * ~48 msg/h (sessão viva, webhook 200), e o banner mostrou "fora do ar" falso.
 * Inbound recente (`whatsapp_conversations.last_inbound_at` < N min, default 10 ·
 * config `whatsapp.whatsmeow.health_fresh_inbound_minutes`) é PROVA autoritativa de
 * "no ar": no ramo caído o probe SUPRIME o falso `disconnected` e AUTO-CURA pra
 * `healthy`. Ausência de inbound ≠ queda (canal pode estar quieto) → sem inbound
 * recente, mantém o sinal do daemon (não cria falso-negativo). BANNED não é
 * suprimível (mensagem pré-ban não invalida o ban). Decisão pura em `decideAction()`.
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
    /**
     * Janela (min) de "inbound recente" que prova que o canal está no ar — martelo
     * [W] 2026-06-18. Override: config `whatsapp.whatsmeow.health_fresh_inbound_minutes`.
     */
    private const FRESH_INBOUND_MINUTES = 10;

    /** Ação que o probe toma sobre channel_health (resultado puro de decideAction). */
    public const ACTION_NONE = 'none';
    public const ACTION_DISCONNECTED = 'disconnected';
    public const ACTION_BANNED = 'banned';
    public const ACTION_PAIRED = 'paired';

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
        $suppressed = 0;
        $freshMinutes = (int) config('whatsapp.whatsmeow.health_fresh_inbound_minutes', self::FRESH_INBOUND_MINUTES);

        foreach ($channels as $channel) {
            $state = $reconciler->reconcile($channel);
            $healthBefore = (string) $channel->channel_health;

            // Só corrobora com mensagem real no ramo "caído" — é onde o loggedIn do WuzAPI mente.
            $isDownState = in_array($state, [WhatsmeowState::LOGGED_OUT, WhatsmeowState::NOT_EXISTS], true);
            $freshInbound = $isDownState && $this->hasRecentInbound($channel, $freshMinutes);

            $action = self::decideAction($state, $healthBefore, $freshInbound);

            $jid = is_array($channel->config_json) ? ($channel->config_json['whatsmeow_jid'] ?? null) : null;
            $jid = is_string($jid) ? $jid : null;

            match ($action) {
                self::ACTION_DISCONNECTED => $reconciler->markDisconnectedInDb($channel, "health-probe: {$state->value}"),
                self::ACTION_BANNED => $reconciler->markDisconnectedInDb($channel, 'health-probe: banned', banDetected: true),
                self::ACTION_PAIRED => $reconciler->markPairedInDb($channel, $jid),
                default => null,
            };

            if ($action !== self::ACTION_NONE) {
                $flipped++;
            }

            if ($freshInbound) {
                $suppressed++;
                Log::warning('whatsmeow.health_probe.false_disconnect_suppressed', [
                    'event' => 'whatsmeow.health_probe.false_disconnect_suppressed',
                    'channel_id' => $channel->id,
                    'business_id' => $channel->business_id,
                    'daemon_state' => $state->value,
                    'health_before' => $healthBefore,
                    'fresh_inbound_minutes' => $freshMinutes,
                    'auto_recovered' => $action === self::ACTION_PAIRED,
                ]);
            }

            if ($this->option('detail')) {
                $this->line(sprintf(
                    '  ch#%d %-22s biz=%d · %s → health=%s%s',
                    $channel->id,
                    "'{$channel->label}'",
                    $channel->business_id,
                    $state->value,
                    $channel->channel_health,
                    $freshInbound ? ' (inbound recente — suprimido)' : '',
                ));
            }
        }

        Log::info('whatsmeow.health_probe.done', [
            'event' => 'whatsmeow.health_probe.done',
            'probed' => $channels->count(),
            'flipped' => $flipped,
            'false_disconnect_suppressed' => $suppressed,
        ]);

        $this->info("Sondados {$channels->count()} canal(is) whatsmeow · {$flipped} mudou de estado · {$suppressed} falso-positivo suprimido.");

        return self::SUCCESS;
    }

    /**
     * Decisão PURA (sem DB/daemon) de como o probe converge channel_health.
     *
     * Isolada pra teste determinístico — a query de inbound fica no handle().
     * `$freshInbound` só vem true no ramo caído: inbound recente prova "no ar" e
     * troca o markDisconnected por auto-cura (markPaired). BANNED nunca é suprimido
     * (mensagem pré-ban não invalida o ban). Idempotente: ACTION_NONE quando o DB
     * já está no alvo.
     *
     * @param  bool  $freshInbound  Houve inbound recente (corrobora "no ar").
     * @return self::ACTION_*
     */
    public static function decideAction(WhatsmeowState $state, string $healthBefore, bool $freshInbound): string
    {
        if (in_array($state, [WhatsmeowState::LOGGED_OUT, WhatsmeowState::NOT_EXISTS], true)) {
            if ($freshInbound) {
                // Falso "fora do ar": daemon diz caído, mas mensagens chegando.
                return $healthBefore === 'healthy' ? self::ACTION_NONE : self::ACTION_PAIRED;
            }

            return $healthBefore === 'disconnected' ? self::ACTION_NONE : self::ACTION_DISCONNECTED;
        }

        if ($state === WhatsmeowState::BANNED) {
            return $healthBefore === 'banned' ? self::ACTION_NONE : self::ACTION_BANNED;
        }

        if ($state === WhatsmeowState::PAIRED) {
            return $healthBefore === 'healthy' ? self::ACTION_NONE : self::ACTION_PAIRED;
        }

        // DAEMON_UNREACHABLE / QR_PENDING / PROVISION_PENDING / ERROR → não muta.
        return self::ACTION_NONE;
    }

    /**
     * Houve mensagem recebida (inbound) neste canal nos últimos $minutes min?
     *
     * Sinal autoritativo-positivo de "no ar". Tier 0 (ADR 0093): escopado por
     * channel_id (PK única por business → nunca cross-tenant); withoutGlobalScopes
     * porque o probe roda em cron sem session de tenant.
     */
    private function hasRecentInbound(Channel $channel, int $minutes): bool
    {
        return Conversation::query()
            ->withoutGlobalScopes()
            ->where('channel_id', $channel->id)
            ->where('last_inbound_at', '>=', now()->subMinutes($minutes))
            ->exists();
    }
}
