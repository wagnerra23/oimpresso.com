<?php

namespace Modules\Whatsapp\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Whatsapp\Services\Drivers\DriverInterface;
use Modules\Whatsapp\Services\Drivers\NullDriver;

/**
 * ServiceProvider do módulo Whatsapp.
 *
 * Decisão arquitetural mãe: ADR 0096 (memory/decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md)
 * - Z-API/Baileys = driver default
 * - Meta Cloud = fallback obrigatório (gating duro FormRequest)
 * - Evolution API = PROIBIDO Tier 0
 *
 * @see memory/requisitos/Whatsapp/SPEC.md
 * @see memory/requisitos/Whatsapp/ARCHITECTURE.md
 */
class WhatsappServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerConfig();
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'whatsapp');
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);

        // Driver bind default — NullDriver enquanto Lote 2b (Drivers reais) não merge.
        // Lote 2b registra DriverFactory que resolve por business_id em runtime.
        $this->app->bind(DriverInterface::class, NullDriver::class);
    }

    protected function registerConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../Config/config.php' => config_path('whatsapp.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__ . '/../Config/config.php', 'whatsapp');
    }
}
