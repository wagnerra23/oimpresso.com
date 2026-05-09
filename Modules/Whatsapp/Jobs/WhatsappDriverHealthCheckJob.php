<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\WhatsappBusinessConfig;
use Modules\Whatsapp\Entities\WhatsappBusinessPhone;
use Modules\Whatsapp\Services\Drivers\DriverFactory;

/**
 * WhatsappDriverHealthCheckJob — pinga driver primário a cada 6h.
 *
 * Decisão mãe: ADR 0096 (mitigação ban Z-API/Baileys via fallback automático).
 *
 * **Multi-números (ADR 0115 — US-WA-040):**
 * Aceita `?int $whatsappBusinessPhoneId` opcional. Quando set, ping per phone
 * (cada número tem driver_health próprio); quando NULL, fallback config legacy.
 *
 * Scheduler dispatch (Lote 2d → futuro): 1 job por phone com driver IN
 * (zapi, baileys), a cada 6h. Durante coexistência (PR 1 → PR 5), também
 * dispatcha 1 job por config legacy sem phone equivalente.
 *
 * **Lógica:**
 *
 * 1. Pinga driver primário via `DriverFactory::makePrimary($target)::ping()`
 *    onde `$target` é phone (preferred) ou config (fallback)
 * 2. Resultado healthy → reset `consecutive_failures=0`, `driver_health=healthy`
 * 3. Resultado unhealthy → incrementa `consecutive_failures`
 *    - 5 falhas → marca `driver_health=degraded`, ATIVA fallback
 *    - 10 falhas → `driver_health=disconnected`
 *    - `banDetected=true` → `driver_health=banned`
 *      + alerta cross-tenant (3+ phones banidos em 24h notifica Wagner)
 *
 * Driver primário Meta Cloud não é pingado (não tem janela 24h e Meta
 * oficial não bane). Job só roda pra driver IN (zapi, baileys).
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-014, US-WA-040
 * @see memory/requisitos/Whatsapp/ARCHITECTURE.md §4
 */
class WhatsappDriverHealthCheckJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $businessId,
        public readonly ?int $whatsappBusinessPhoneId = null,
    ) {
        $this->onQueue(config('whatsapp.queue', 'whatsapp'));
    }

    public function handle(): void
    {
        $target = $this->resolveTarget();
        if ($target === null) {
            return;
        }

        // Só checa drivers não-oficiais (Meta Cloud é confiável)
        if (! in_array($target->driver, ['zapi', 'baileys'], true)) {
            return;
        }

        $driver = DriverFactory::makePrimary($target);

        try {
            $health = $driver->ping($target);
        } catch (\Throwable $e) {
            $this->markFailure($target, "Exception: {$e->getMessage()}", false);
            return;
        }

        if ($health->healthy) {
            $this->markHealthy($target, $health->displayPhone);
            return;
        }

        $this->markFailure($target, $health->errorMessage ?? 'unknown', $health->banDetected);
    }

    /**
     * @return WhatsappBusinessConfig|WhatsappBusinessPhone|null
     */
    private function resolveTarget()
    {
        if ($this->whatsappBusinessPhoneId !== null) {
            return WhatsappBusinessPhone::query()
                ->withoutGlobalScope(ScopeByBusiness::class)
                ->where('business_id', $this->businessId)
                ->where('id', $this->whatsappBusinessPhoneId)
                ->first();
        }

        return WhatsappBusinessConfig::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $this->businessId)
            ->first();
    }

    /**
     * @param  WhatsappBusinessConfig|WhatsappBusinessPhone  $target
     */
    private function markHealthy($target, ?string $displayPhone): void
    {
        $target->update([
            'driver_health' => 'healthy',
            'driver_health_consecutive_failures' => 0,
            'last_health_check_at' => now(),
            'last_health_message' => null,
            'display_phone' => $displayPhone ?? $target->display_phone,
        ]);
    }

    /**
     * @param  WhatsappBusinessConfig|WhatsappBusinessPhone  $target
     */
    private function markFailure($target, string $errorMessage, bool $banDetected): void
    {
        $cfg = config('whatsapp.health_check', []);
        $degradeThreshold = (int) ($cfg['consecutive_failures_to_degrade'] ?? 5);
        $disconnectThreshold = (int) ($cfg['consecutive_failures_to_disconnect'] ?? 10);

        $newFailures = $target->driver_health_consecutive_failures + 1;
        $previousHealth = $target->driver_health;

        $newHealth = match (true) {
            $banDetected => 'banned',
            $newFailures >= $disconnectThreshold => 'disconnected',
            $newFailures >= $degradeThreshold => 'degraded',
            default => $previousHealth === 'healthy' ? 'healthy' : $previousHealth,
        };

        $target->update([
            'driver_health' => $newHealth,
            'driver_health_consecutive_failures' => $newFailures,
            'last_health_check_at' => now(),
            'last_health_message' => mb_substr($errorMessage, 0, 1000),
        ]);

        if ($newHealth !== 'healthy' && $previousHealth === 'healthy') {
            \Log::warning('[whatsapp.health_check] driver degradado — fallback ativo', [
                'business_id' => $this->businessId,
                'phone_id' => $this->whatsappBusinessPhoneId,
                'driver' => $target->driver,
                'fallback_driver' => $target->fallback_driver,
                'state' => $newHealth,
                'error' => $errorMessage,
            ]);
        }

        // Cross-tenant alarme (3+ phones banidos em 24h notifica Wagner)
        if ($banDetected) {
            $threshold = (int) ($cfg['cross_tenant_ban_alarm_threshold'] ?? 3);

            // Conta bans em phones (preferido) + configs (legacy) somados
            $bannedPhones24h = WhatsappBusinessPhone::query()
                ->withoutGlobalScope(ScopeByBusiness::class)
                ->where('driver_health', 'banned')
                ->where('last_health_check_at', '>=', now()->subDay())
                ->count();

            $bannedConfigs24h = WhatsappBusinessConfig::query()
                ->withoutGlobalScope(ScopeByBusiness::class)
                ->where('driver_health', 'banned')
                ->where('last_health_check_at', '>=', now()->subDay())
                ->count();

            $banned24h = $bannedPhones24h + $bannedConfigs24h;

            if ($banned24h >= $threshold) {
                \Log::critical('[whatsapp.health_check.cross_tenant_ban_alarm] meta_detection_change_suspect', [
                    'banned_24h' => $banned24h,
                    'threshold' => $threshold,
                    'action_required' => 'Investigar mudança Meta TOS; planejar migração geral pra Meta Cloud',
                ]);
            }
        }
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        $tags = ["business:{$this->businessId}", 'whatsapp:health_check'];
        if ($this->whatsappBusinessPhoneId !== null) {
            $tags[] = "phone:{$this->whatsappBusinessPhoneId}";
        }
        return $tags;
    }
}
