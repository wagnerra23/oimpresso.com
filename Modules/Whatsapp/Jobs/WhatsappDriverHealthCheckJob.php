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
use Modules\Whatsapp\Services\Drivers\DriverFactory;

/**
 * WhatsappDriverHealthCheckJob — pinga driver primário a cada 6h.
 *
 * Decisão mãe: ADR 0096 (mitigação ban Z-API/Baileys via fallback automático).
 *
 * **Lógica:**
 *
 * 1. Pinga driver primário via `DriverFactory::makePrimary($config)::ping()`
 * 2. Resultado healthy → reset `consecutive_failures=0`, `driver_health=healthy`
 * 3. Resultado unhealthy → incrementa `consecutive_failures`
 *    - 5 falhas → marca `driver_health=degraded`, ATIVA fallback (driver
 *      efetivo passa a ser `fallback_driver`)
 *    - 10 falhas → `driver_health=disconnected`
 *    - `banDetected=true` (auth permanent) → `driver_health=banned`
 *      + alerta cross-tenant (3+ businesses banidos em 24h notifica Wagner)
 *
 * Driver primário Meta Cloud não é pingado (não tem janela 24h e Meta
 * oficial não bane). Job só roda pra driver IN (zapi, baileys).
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-014
 * @see memory/requisitos/Whatsapp/ARCHITECTURE.md §4
 */
class WhatsappDriverHealthCheckJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $businessId)
    {
        $this->onQueue(config('whatsapp.queue', 'whatsapp'));
    }

    public function handle(): void
    {
        $config = WhatsappBusinessConfig::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $this->businessId)
            ->firstOrFail();

        // Só checa drivers não-oficiais (Meta Cloud é confiável)
        if (! in_array($config->driver, ['zapi', 'baileys'], true)) {
            return;
        }

        $driver = DriverFactory::makePrimary($config);

        try {
            $health = $driver->ping($config);
        } catch (\Throwable $e) {
            $this->markFailure($config, "Exception: {$e->getMessage()}", false);
            return;
        }

        if ($health->healthy) {
            $this->markHealthy($config, $health->displayPhone);
            return;
        }

        $this->markFailure($config, $health->errorMessage ?? 'unknown', $health->banDetected);
    }

    private function markHealthy(WhatsappBusinessConfig $config, ?string $displayPhone): void
    {
        $config->update([
            'driver_health' => 'healthy',
            'driver_health_consecutive_failures' => 0,
            'last_health_check_at' => now(),
            'last_health_message' => null,
            'display_phone' => $displayPhone ?? $config->display_phone,
        ]);
    }

    private function markFailure(WhatsappBusinessConfig $config, string $errorMessage, bool $banDetected): void
    {
        $cfg = config('whatsapp.health_check', []);
        $degradeThreshold = (int) ($cfg['consecutive_failures_to_degrade'] ?? 5);
        $disconnectThreshold = (int) ($cfg['consecutive_failures_to_disconnect'] ?? 10);

        $newFailures = $config->driver_health_consecutive_failures + 1;
        $previousHealth = $config->driver_health;

        $newHealth = match (true) {
            $banDetected => 'banned',
            $newFailures >= $disconnectThreshold => 'disconnected',
            $newFailures >= $degradeThreshold => 'degraded',
            default => $previousHealth === 'healthy' ? 'healthy' : $previousHealth,
        };

        $config->update([
            'driver_health' => $newHealth,
            'driver_health_consecutive_failures' => $newFailures,
            'last_health_check_at' => now(),
            'last_health_message' => mb_substr($errorMessage, 0, 1000),
        ]);

        // Notifica admin business + Wagner se entrou em estado degradado
        if ($newHealth !== 'healthy' && $previousHealth === 'healthy') {
            \Log::warning('[whatsapp.health_check] driver degradado — fallback ativo', [
                'business_id' => $this->businessId,
                'driver' => $config->driver,
                'fallback_driver' => $config->fallback_driver,
                'state' => $newHealth,
                'error' => $errorMessage,
            ]);
        }

        // Cross-tenant alarme (3+ businesses banidos em 24h notifica Wagner)
        if ($banDetected) {
            $threshold = (int) ($cfg['cross_tenant_ban_alarm_threshold'] ?? 3);
            $banned24h = WhatsappBusinessConfig::query()
                ->withoutGlobalScope(ScopeByBusiness::class)
                ->where('driver_health', 'banned')
                ->where('last_health_check_at', '>=', now()->subDay())
                ->count();

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
        return ["business:{$this->businessId}", 'whatsapp:health_check'];
    }
}
