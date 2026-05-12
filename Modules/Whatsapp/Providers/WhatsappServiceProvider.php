<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Repair\Events\RepairStatusChanged;
use Modules\Whatsapp\Console\Commands\DriverHealthCheckAllCommand;
use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Entities\WhatsappMessage;
use Modules\Whatsapp\Events\OmnichannelMessageReceived;
use Modules\Whatsapp\Events\OmnichannelMessageSent;
use Modules\Whatsapp\Events\WhatsappMessageReceived;
use Modules\Whatsapp\Events\WhatsappMessageSent;
use Modules\Whatsapp\Http\Middleware\VerifyBaileysSignature;
use Modules\Whatsapp\Http\Middleware\VerifyMetaSignature;
use Modules\Whatsapp\Http\Middleware\VerifyZapiSignature;
use Modules\Whatsapp\Listeners\DispatchToJanaBot;
use Modules\Whatsapp\Listeners\NotifyRepairCustomer;
use Modules\Whatsapp\Listeners\PublishMessageReceivedToCentrifugo;
use Modules\Whatsapp\Listeners\PublishMessageSentToCentrifugo;
use Modules\Whatsapp\Listeners\PublishOmnichannelToCentrifugo;
use Modules\Whatsapp\Observers\MessageObserver;
use Modules\Whatsapp\Observers\WhatsappMessageObserver;
use Modules\Whatsapp\Services\Audio\Contracts\AudioTranscriber;
use Modules\Whatsapp\Services\Audio\WhisperTranscriber;
use Modules\Whatsapp\Services\Centrifugo\CentrifugoPublisher;
use Modules\Whatsapp\Services\Drivers\BaileysDriver;
use Modules\Whatsapp\Services\Drivers\DriverInterface;
use Modules\Whatsapp\Services\Drivers\MetaCloudDriver;
use Modules\Whatsapp\Services\Drivers\NullDriver;
use Modules\Whatsapp\Services\Drivers\ZapiDriver;
use Modules\Whatsapp\Services\Notes\ConfigHandler;
use Modules\Whatsapp\Services\Notes\LembrarHandler;
use Modules\Whatsapp\Services\Notes\LembreteHandler;
use Modules\Whatsapp\Services\Notes\SlashCommandParser;
use Modules\Whatsapp\Services\Notes\SlashCommandRegistry;

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

        if ($this->app->runningInConsole()) {
            $this->commands([
                DriverHealthCheckAllCommand::class,
            ]);
        }

        // Append-only enforcement em WhatsappMessage (Tier 0 — ADR 0093 + ADR 0096)
        // Bloqueia UPDATE em IMMUTABLE_COLUMNS + DELETE direto
        WhatsappMessage::observe(WhatsappMessageObserver::class);

        // Observer da entidade Message (schema novo omnichannel — ADR 0135)
        // Dispara OmnichannelMessageReceived/Sent para o listener Centrifugo
        // publicar em `omnichannel:business:{id}` (US-WA-059).
        Message::observe(MessageObserver::class);

        // Plug Repair: dispara Whatsapp em mudança de status (cumpre ADR Repair tech/0001)
        // Evento Modules\Repair\Events\RepairStatusChanged é declarado em Modules/Repair/Events/
        // — dispatch real depende de PR coordenado com Felipe/Maiara modificando JobSheetController.
        Event::listen(RepairStatusChanged::class, [NotifyRepairCustomer::class, 'handleEvent']);

        // Centrifugo real-time UI (ADR 0058) — publica em whatsapp:business:{id} channel
        Event::listen(WhatsappMessageReceived::class, PublishMessageReceivedToCentrifugo::class);
        Event::listen(WhatsappMessageSent::class, PublishMessageSentToCentrifugo::class);

        // Centrifugo real-time UI omnichannel (US-WA-059 + ADR 0135) — channel
        // `omnichannel:business:{id}` separado do canal legacy acima.
        Event::listen(OmnichannelMessageReceived::class, PublishOmnichannelToCentrifugo::class);
        Event::listen(OmnichannelMessageSent::class, PublishOmnichannelToCentrifugo::class);

        // Bot Jana — Sprint 3 prep (default disabled via config('whatsapp.bot.enabled'))
        Event::listen(WhatsappMessageReceived::class, DispatchToJanaBot::class);
    }

    protected function registerMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app['router'];
        $router->aliasMiddleware('whatsapp.meta.signature', VerifyMetaSignature::class);
        $router->aliasMiddleware('whatsapp.zapi.signature', VerifyZapiSignature::class);
        $router->aliasMiddleware('whatsapp.baileys.signature', VerifyBaileysSignature::class);
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);

        // Drivers como singletons (stateless — só lógica HTTP).
        // Resolução por business é feita em runtime via DriverFactory::make($config).
        $this->app->singleton(ZapiDriver::class);
        $this->app->singleton(MetaCloudDriver::class);
        $this->app->singleton(NullDriver::class);
        $this->app->singleton(BaileysDriver::class);

        // Centrifugo publisher singleton (stateless HTTP wrapper)
        $this->app->singleton(CentrifugoPublisher::class);

        // US-WA-072 — Whisper transcription contract. WhisperTranscriber é
        // o único impl nesta fase; Ollama whisper-local vai em US separada.
        // Mock fácil em test via $this->app->bind(AudioTranscriber::class, ...).
        $this->app->bind(AudioTranscriber::class, WhisperTranscriber::class);

        // Bind default da interface — usado quando algum service injeta
        // DriverInterface diretamente (sem passar business). Aponta pro
        // driver default global (config('whatsapp.default_driver')).
        // Em produção real, sempre prefira DriverFactory::make($config) que
        // aplica fallback runtime.
        $this->app->bind(DriverInterface::class, function () {
            return match (config('whatsapp.default_driver', 'zapi')) {
                'zapi' => $this->app->make(ZapiDriver::class),
                'meta_cloud' => $this->app->make(MetaCloudDriver::class),
                'baileys' => $this->app->make(BaileysDriver::class),
                'null' => $this->app->make(NullDriver::class),
                default => $this->app->make(NullDriver::class),
            };
        });

        // US-WA-074 (ADR 0142) — Slash commands em notas internas.
        // Parser é stateless. Registry carrega handlers via injeção (singleton).
        // Slots vazios (`corrigir`/`lembrete`/`config`) serão preenchidos
        // por US-WA-075..077 — apenas adicionar `register('xxx', $handler)`.
        $this->app->singleton(SlashCommandParser::class);
        $this->app->singleton(LembrarHandler::class);
        $this->app->singleton(LembreteHandler::class);
        $this->app->singleton(ConfigHandler::class);
        $this->app->singleton(SlashCommandRegistry::class, function ($app) {
            $registry = new SlashCommandRegistry();
            $registry->register('lembrar', $app->make(LembrarHandler::class));
            // US-WA-076 (ADR 0142 §3b) — lembrete agendado + cron hourly
            $registry->register('lembrete', $app->make(LembreteHandler::class));
            // US-WA-077 (ADR 0142 §3c) — toggle bot per-contato via /config bot=on|off
            $registry->register('config', $app->make(ConfigHandler::class));
            // Reserved slot — handler preenchido em US-WA-075:
            //   $registry->register('corrigir', $app->make(CorrigirHandler::class));
            return $registry;
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
