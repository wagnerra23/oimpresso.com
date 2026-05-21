<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Repair\Events\RepairStatusChanged;
use Modules\Whatsapp\Console\Commands\AutoLinkConversationContactsCommand;
use Modules\Whatsapp\Console\Commands\BackfillChannelAccessCommand;
use Modules\Whatsapp\Console\Commands\CustomerMemoryBackfillCommand;
use Modules\Whatsapp\Console\Commands\CustomerMemoryEnrichFirebirdCommand;
use Modules\Whatsapp\Console\Commands\CustomerMemoryRefreshDailyCommand;
use Modules\Whatsapp\Console\Commands\EmployeePerformanceBackfillCommand;
use Modules\Whatsapp\Console\Commands\EmployeePerformanceRefreshDailyCommand;
use Modules\Whatsapp\Console\Commands\BackfillMediaDownloadCommand;
use Modules\Whatsapp\Console\Commands\ChannelResetCommand;
use Modules\Whatsapp\Console\Commands\CleanupStaleJobsCommand;
use Modules\Whatsapp\Console\Commands\CleanupWebhookNoncesCommand;
use Modules\Whatsapp\Console\Commands\ChannelsReconcilerCommand;
use Modules\Whatsapp\Console\Commands\DaemonSourceDriftCheckCommand;
use Modules\Whatsapp\Console\Commands\WhatsappAuthStateDriftCheckCommand;
use Modules\Whatsapp\Console\Commands\WhatsappObservabilityHealthCommand;
use Modules\Whatsapp\Console\Commands\DriverHealthCheckAllCommand;
use Modules\Whatsapp\Console\Commands\ImportHistoryCommand;
use Modules\Whatsapp\Console\Commands\LidBackfillCommand;
use Modules\Whatsapp\Console\Commands\MetricsAggregateCommand;
use Modules\Whatsapp\Console\Commands\RegisterWhatsappPermissionsCommand;
use Modules\Whatsapp\Console\Commands\ReparseMediaFromPayloadCommand;
use Modules\Whatsapp\Console\Commands\RetryRecentMediaDownloadsCommand;
use Modules\Whatsapp\Console\Commands\ScanMediaDriftCommand;
use Modules\Whatsapp\Console\Commands\SlaScanCommand;
use Modules\Whatsapp\Entities\Message;
use Modules\Whatsapp\Entities\WhatsappMessage;
use Modules\Whatsapp\Events\OmnichannelMessageReceived;
use Modules\Whatsapp\Events\OmnichannelMessageSent;
use Modules\Whatsapp\Events\WhatsappMessageReceived;
use Modules\Whatsapp\Events\WhatsappMessageSent;
use Modules\Whatsapp\Http\Middleware\EnforceWebhookBackpressure;
use Modules\Whatsapp\Http\Middleware\PropagateTraceparent;
use Modules\Whatsapp\Http\Middleware\VerifyBaileysSignature;
use Modules\Whatsapp\Http\Middleware\VerifyBaileysWebhookHmac;
use Modules\Whatsapp\Http\Middleware\VerifyMetaSignature;
use Modules\Whatsapp\Http\Middleware\VerifyZapiSignature;
use Modules\Whatsapp\Listeners\DispatchToJanaBot;
use Modules\Whatsapp\Listeners\NotifyRepairCustomer;
use Modules\Whatsapp\Listeners\TouchCustomerMemoryOnMessage;
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
use Modules\Whatsapp\Services\Notes\CorrigirHandler;
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
                BackfillChannelAccessCommand::class,
                RegisterWhatsappPermissionsCommand::class,
                // Guardião 6 camadas anti-mídia-perdida
                ScanMediaDriftCommand::class,           // Camada 5 — scan drift daily
                BackfillMediaDownloadCommand::class,    // Bonus — backfill one-shot
                ReparseMediaFromPayloadCommand::class,  // Bonus — extrai meta de payload pré-PR #664
                RetryRecentMediaDownloadsCommand::class, // PR #882 — cron horário retry mídia recente (cron em app/Console/Kernel.php:657)
                AutoLinkConversationContactsCommand::class, // US-WA-078 — backfill auto-link Contact CRM
                ImportHistoryCommand::class,            // US-WA-080 — import histórico Baileys 90d
                LidBackfillCommand::class,              // US-WA-093 P1 — backfill LID→phone de messages.payload histórico
                SlaScanCommand::class,                  // CYCLE-07 PR-2 — scan SLA policies + alertas escalation
                MetricsAggregateCommand::class,         // US-WA-021/041 — snapshot diário métricas (CYCLE-07 PR-3)
                ChannelsReconcilerCommand::class,       // 2026-05-13 — auto-fix drift channels↔daemon (cron 5min)
                ChannelResetCommand::class,             // 2026-05-13 — reset 1-comando channel travado
                CleanupWebhookNoncesCommand::class,     // US-WA-082 — purga nonces >24h (replay protection cleanup)
                CleanupStaleJobsCommand::class,         // US-WA-084 — purga jobs presos da fila whatsapp-history (>6h)
                DaemonSourceDriftCheckCommand::class,   // 2026-05-13 — alerta drift main↔daemon CT 100 (cron weekly)
                WhatsappAuthStateDriftCheckCommand::class, // 2026-05-15 — alerta drift auth_state↔channels pós incident Baileys 7.x deploy (cron daily 03h BRT)
                WhatsappObservabilityHealthCommand::class,  // Wave 16 D9 — snapshot observabilidade (phones + msgs 24h + fail-rate)
                // US-WA-VOZ-001 — Customer Memory foundation (2026-05-15)
                CustomerMemoryBackfillCommand::class,
                CustomerMemoryRefreshDailyCommand::class,
                // US-WA-VOZ-002 — Enrichment Firebird OfficeImpresso (2026-05-15)
                CustomerMemoryEnrichFirebirdCommand::class,
                // US-WA-VOZ-003 — Employee Performance scorecard (2026-05-15)
                EmployeePerformanceBackfillCommand::class,
                EmployeePerformanceRefreshDailyCommand::class,
            ]);
        }

        // Append-only enforcement em WhatsappMessage (Tier 0 — ADR 0093 + ADR 0096)
        // Bloqueia UPDATE em IMMUTABLE_COLUMNS + DELETE direto
        WhatsappMessage::observe(WhatsappMessageObserver::class);

        // Observer da entidade Message (schema novo omnichannel — ADR 0135)
        // Dispara OmnichannelMessageReceived/Sent para o listener Centrifugo
        // publicar em `omnichannel:business:{id}` (US-WA-059).
        Message::observe(MessageObserver::class);

        // Observer da entidade Channel (sync Laravel→daemon Baileys)
        // Quando status transita pra banned/disconnected/removed/setup OU
        // channel é deletado, purga instance no daemon CT 100 (fecha Gap A
        // do post-mortem 2026-05-13).
        \Modules\Whatsapp\Entities\Channel::observe(\Modules\Whatsapp\Observers\ChannelObserver::class);

        // Observer LidPhoneMap — wave-protocol-stack PR2 (sessão 2026-05-15).
        // Quando LID resolve pra phone (NULL→valor em phone_e164), dispara
        // BackfillLidConversationsJob que re-linka conversations órfãs
        // criadas ANTES do phone ser descoberto. Fecha gap "1ª msg @lid sem
        // senderPn cria conv órfã pra sempre".
        \Modules\Whatsapp\Entities\LidPhoneMap::observe(\Modules\Whatsapp\Observers\LidPhoneMapObserver::class);

        // Observer ContactObserver — invalida cache `whatsapp.auto_link:*` quando
        // phone fields (mobile/landline/alternate_number) de Contact CRM mudam.
        // Fix cross-contact recorrente catalogado em smoke 2026-05-15: cache 1h
        // TTL preservava mapping Eliana-stale mesmo após operador limpar campos
        // do Contact via UI. Defesa-em-profundidade: invalida cache BOTH old+new
        // phone values. Detalhes em `Modules/Whatsapp/Observers/ContactObserver.php` docblock.
        \App\Contact::observe(\Modules\Whatsapp\Observers\ContactObserver::class);

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

        // US-WA-VOZ-001 — Customer Memory (perfil persistente do cliente final).
        // Listener síncrono: cheap path UPSERT last_interaction_at + dispatch
        // RebuildCustomerMemoryJob assíncrono se memória stale (>6h default).
        Event::listen(OmnichannelMessageReceived::class, TouchCustomerMemoryOnMessage::class);
        Event::listen(OmnichannelMessageSent::class, TouchCustomerMemoryOnMessage::class);

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
        // US-WA-082 — Replay protection HMAC + nonce no webhook receiver Baileys
        $router->aliasMiddleware('whatsapp.baileys.hmac', VerifyBaileysWebhookHmac::class);
        // US-WA-084 — Backpressure protetiva (429 quando queue depth > max)
        $router->aliasMiddleware('whatsapp.baileys.backpressure', EnforceWebhookBackpressure::class);
        // US-WA-083 — Propaga `traceparent` W3C do daemon → Log context
        $router->aliasMiddleware('whatsapp.otel.propagate', PropagateTraceparent::class);
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
        // Família completa US-WA-074/075/076/077 — 4 comandos slash registrados.
        $this->app->singleton(SlashCommandParser::class);
        $this->app->singleton(CorrigirHandler::class);
        $this->app->singleton(LembrarHandler::class);
        $this->app->singleton(LembreteHandler::class);
        $this->app->singleton(ConfigHandler::class);
        $this->app->singleton(SlashCommandRegistry::class, function ($app) {
            $registry = new SlashCommandRegistry();
            // Ordem alfabética dos comandos (estável pra reduzir conflito merge).
            // US-WA-075 (ADR 0142 §3a) — training signal Jana
            $registry->register('corrigir', $app->make(CorrigirHandler::class));
            // US-WA-077 (ADR 0142 §3c) — toggle bot per-contato via /config bot=on|off
            $registry->register('config', $app->make(ConfigHandler::class));
            // US-WA-074 (ADR 0142 §3d) — grava fato em memoria_facts
            $registry->register('lembrar', $app->make(LembrarHandler::class));
            // US-WA-076 (ADR 0142 §3b) — lembrete agendado + cron hourly
            $registry->register('lembrete', $app->make(LembreteHandler::class));
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
