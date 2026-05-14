<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Channel;
use Modules\Whatsapp\Http\Controllers\Api\ChannelBaileysWebhookController;

/**
 * Persiste batch de mensagens históricas vindas do daemon Baileys.
 *
 * **Por que existe (incident 2026-05-13 22h):** o handler `handleHistorySync`
 * processava 50-100 msgs SÍNCRONO dentro do request HTTP do webhook. Com
 * `QUEUE_CONNECTION=sync` no Hostinger, cada msg fazia DB lookups +
 * MessagePersister + dispatch eventos = ~10-30s por chunk. PHP-FPM worker
 * pool saturava → próximos webhooks recebiam HTTP 404 ou 429 do Apache.
 * Pareamento de canal com 10k msgs históricas perdeu 100% das msgs.
 *
 * **Solução**: este Job é dispatchado pelo webhook handler que responde
 * 202 imediato. Processa msgs em background (queue=whatsapp). Daemon
 * não vê 404/429, persiste tudo idempotente.
 *
 * **Idempotência**: MessagePersister já é idempotente via
 * `provider_message_id` UNIQUE. Re-run safe — mesma msg vinda 2x = no-op.
 *
 * **Anti-burst defensive**: backoff exponencial 10s/30s/90s se Job falhar
 * (db lock, conexão MySQL transiente). 3 tries antes de failed_permanent.
 *
 * **Multi-tenant Tier 0 (ADR 0093)**: business_id no constructor. Filtros
 * defensive `withoutGlobalScope` justificados (Job sem session() user).
 *
 * @see Modules/Whatsapp/Http/Controllers/Api/ChannelBaileysWebhookController.php
 * @see memory/sessions/2026-05-13-whatsapp-daemon-rebuild-safeguards.md
 */
class PersistHistorySyncBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120; // 2min — batch grande de 100 msgs pode demorar

    /**
     * @return array<int, int> backoff em segundos
     */
    public function backoff(): array
    {
        return [10, 30, 90];
    }

    /**
     * @param  array<int, mixed>  $messages  Array de msgs do daemon (proto raw + key + timestamp)
     */
    public function __construct(
        public readonly int $businessId,
        public readonly int $channelId,
        public readonly int $syncType,
        public readonly int $chunkIndex,
        public readonly int $chunkTotal,
        public readonly array $messages,
    ) {
        $this->onQueue(config('whatsapp.queue', 'whatsapp'));
    }

    public function handle(): void
    {
        if (empty($this->messages)) {
            return;
        }

        $channel = Channel::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $this->businessId)
            ->where('id', $this->channelId)
            ->first();

        if (! $channel) {
            Log::warning('[whatsapp.history-sync-job] channel not found', [
                'channel_id' => $this->channelId,
                'business_id' => $this->businessId,
            ]);
            return;
        }

        $persisted = 0;
        $skipped = 0;
        $errors = 0;

        $controller = app(ChannelBaileysWebhookController::class);

        foreach ($this->messages as $rawMsg) {
            try {
                $msgData = [
                    'key' => $rawMsg['key'] ?? [],
                    'message' => $rawMsg['message'] ?? [],
                    'messageTimestamp' => $rawMsg['messageTimestamp'] ?? null,
                    'pushName' => $rawMsg['pushName'] ?? null,
                    'is_history_sync' => true,
                ];

                // Reusa pipeline existente handleMessage() via reflection (método
                // é protected). Idempotente via provider_message_id UNIQUE.
                $reflection = new \ReflectionClass($controller);
                $method = $reflection->getMethod('handleMessage');
                $method->setAccessible(true);
                $resp = $method->invoke($controller, $channel, $msgData);

                if ($resp->getStatusCode() === 200) {
                    $persisted++;
                } else {
                    $skipped++;
                }
            } catch (\Throwable $e) {
                $errors++;
                Log::warning('[whatsapp.history-sync-job] erro persistindo msg', [
                    'channel_id' => $this->channelId,
                    'sync_type' => $this->syncType,
                    'chunk_index' => $this->chunkIndex,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('[whatsapp.history-sync-job] chunk processado', [
            'channel_id' => $this->channelId,
            'business_id' => $this->businessId,
            'sync_type' => $this->syncType,
            'chunk_index' => $this->chunkIndex,
            'chunk_total' => $this->chunkTotal,
            'messages_count' => count($this->messages),
            'persisted' => $persisted,
            'skipped' => $skipped,
            'errors' => $errors,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[whatsapp.history-sync-job] todas tentativas falharam — chunk perdido', [
            'channel_id' => $this->channelId,
            'business_id' => $this->businessId,
            'sync_type' => $this->syncType,
            'chunk_index' => $this->chunkIndex,
            'messages_count' => count($this->messages),
            'error' => $exception->getMessage(),
        ]);
    }
}
