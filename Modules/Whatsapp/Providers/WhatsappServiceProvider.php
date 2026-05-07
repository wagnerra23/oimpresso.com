<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Repair\Events\RepairStatusChanged;
use Modules\Whatsapp\Entities\WhatsappMessage;
use Modules\Whatsapp\Http\Middleware\VerifyMetaSignature;
use Modules\Whatsapp\Http\Middleware\VerifyZapiSignature;
use Modules\Whatsapp\Listeners\NotifyRepairCustomer;
use Modules\Whatsapp\Observers\WhatsappMessageObserver;
use Modules\Whatsapp\Services\Drivers\DriverInterface;
use Modules\Whatsapp\Services\Drivers\MetaCloudDriver;
use Modules\Whatsapp\Services\Drivers\NullDriver;
use Modules\Whatsapp\Services\Drivers\ZapiDriver;

/**
 * ServiceProvider do módulo Whatsapp.
 *
 * Decisão arquitetural mãe: ADR 0096 (memory/decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md)
 * - Z-API = driver default Sprint 1
 * - Meta Cloud = fallback obrigatório Sprint 1 (gating duro FormRequest)
 * - BaileysDriver custom = autorizado Sprint 3 (estrutura customizada de atendimento)
 * - Evolution API = PROIBIDO permanente
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
        $this->registerMiddleware();

        // Append-only enforcement em WhatsappMessage (Tier 0 — ADR 0093 + ADR 0096)
        // Bloqueia UPDATE em IMMUTABLE_COLUMNS + DELETE direto
        WhatsappMessage::observe(WhatsappMessageObserver::class);

        // Plug Repair: dispara Whatsapp em mudança de status (cumpre ADR Repair tech/0001)
        // Evento Modules\Repair\Events\RepairStatusChanged é declarado em Modules/Repair/Events/
        // — dispatch real depende de PR coordenado com Felipe/Maíra modificando JobSheetController.
        Event::listen(RepairStatusChanged::class, [NotifyRepairCustomer::class, 'handleEvent']);
    }

    protected function registerMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app['router'];
        $router->aliasMiddleware('whatsapp.meta.signature', VerifyMetaSignature::class);
        $router->aliasMiddleware('whatsapp.zapi.signature', VerifyZapiSignature::class);
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);

        // Drivers como singletons (stateless — só lógica HTTP).
        // Resolução por business é feita em runtime via DriverFactory::make($config).
        $this->app->singleton(ZapiDriver::class);
        $this->app->singleton(MetaCloudDriver::class);
        $this->app->singleton(NullDriver::class);

        // Bind default da interface — usado quando algum service injeta
        // DriverInterface diretamente (sem passar business). Aponta pro
        // driver default global (config('whatsapp.default_driver')).
        // Em produção real, sempre prefira DriverFactory::make($config) que
        // aplica fallback runtime.
        $this->app->bind(DriverInterface::class, function () {
            return match (config('whatsapp.default_driver', 'zapi')) {
                'zapi' => $this->app->make(ZapiDriver::class),
                'meta_cloud' => $this->app->make(MetaCloudDriver::class),
                'null' => $this->app->make(NullDriver::class),
                default => $this->app->make(NullDriver::class),
            };
        });
    }

    protected function registerConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../Config/config.php' => config_path('whatsapp.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__ . '/../Config/config.php', 'whatsapp');
    }
}
