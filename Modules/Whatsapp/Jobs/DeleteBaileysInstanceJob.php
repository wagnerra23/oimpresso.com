<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * DeleteBaileysInstanceJob — purge instance no daemon Node.
 *
 * **Disparado:**
 * - ChannelObserver quando channel.status transita pra `disconnected`, `banned`,
 *   `removed`, `setup` (de qualquer estado conectado anterior)
 * - ChannelObserver quando channel.deleted (hard ou soft delete)
 * - Comando manual `whatsapp:purge-instance {instance_id}` (P2 — futuro)
 *
 * **Faz:**
 * 1. DELETE {daemon_url}/instances/{instance_id} (purga creds + para socket)
 * 2. Loga sucesso ou falha
 * 3. Em caso de falha: retry 3x exponencial — daemon pode estar em restart
 *
 * **NÃO faz:** modifica DB Hostinger. O motivo: este job é o efeito-colateral
 * do sync Laravel→daemon. O DB Hostinger já mudou pra status banned/disconnected
 * (que disparou este job). Tarefa do job é só sincronizar o daemon.
 *
 * **Idempotente:** se daemon retorna 404 (instance não existe), trata como
 * sucesso. Operação naturalmente idempotente — múltiplos DELETEs no mesmo
 * id retornam ok.
 *
 * **Multi-tenant Tier 0 (ADR 0093):** $businessId no constructor por logging
 * + auditoria. Não há query DB aqui (job só faz HTTP outbound), mas mantém
 * o pattern dos outros jobs.
 *
 * **Por que existe (incident 2026-05-13):** Channels id=2 e id=3 (biz=1) foram
 * desativados/banidos no Laravel mas suas instâncias seguiram ativas no daemon
 * CT 100 por dias, consumindo CPU + acelerando ban Meta por sessões fantasmas.
 * Purge manual via curl resolveu — este job automatiza.
 *
 * @see memory/sessions/2026-05-13-whatsapp-incident-zombie-banned-loop.md (Gap A)
 * @see Modules/Whatsapp/Observers/ChannelObserver.php
 * @see Modules/Whatsapp/Jobs/BaileysConnectJob.php (job inverso)
 */
class DeleteBaileysInstanceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;

    /**
     * @return array<int, int>  backoff em segundos
     */
    public function backoff(): array
    {
        return [10, 30, 90];
    }

    public function __construct(
        public readonly int $businessId,
        public readonly string $instanceId,
        public readonly string $reason = 'channel_deactivated',
    ) {
        $this->onQueue(config('whatsapp.queue', 'whatsapp'));
    }

    public function handle(): void
    {
        if ($this->instanceId === '') {
            Log::info('[whatsapp.baileys.delete] instance_id vazio — nada a purgar', [
                'business_id' => $this->businessId,
                'reason' => $this->reason,
            ]);

            return;
        }

        $apiKey = (string) config('whatsapp.baileys.api_key', '');
        if ($apiKey === '') {
            Log::error('[whatsapp.baileys.delete] WHATSAPP_BAILEYS_API_KEY ausente no .env', [
                'business_id' => $this->businessId,
                'instance_id' => $this->instanceId,
            ]);

            return;
        }

        $daemonUrl = (string) config('whatsapp.baileys.daemon_url', 'https://whatsapp-baileys.oimpresso.local');
        $timeout = (int) config('whatsapp.baileys.request_timeout', 15);

        $response = Http::baseUrl(rtrim($daemonUrl, '/'))
            ->timeout($timeout)
            ->withToken($apiKey)
            ->acceptJson()
            ->delete("/instances/{$this->instanceId}");

        if ($response->successful() || $response->status() === 404) {
            Log::info('[whatsapp.baileys.delete] daemon purgou instance', [
                'business_id' => $this->businessId,
                'instance_id' => $this->instanceId,
                'reason' => $this->reason,
                'status' => $response->status(),
            ]);

            return;
        }

        $errorMsg = "daemon delete falhou: HTTP {$response->status()} — "
            . Str::limit($response->body(), 200);

        Log::error('[whatsapp.baileys.delete] ' . $errorMsg, [
            'business_id' => $this->businessId,
            'instance_id' => $this->instanceId,
            'reason' => $this->reason,
        ]);

        if ($this->attempts() < $this->tries) {
            throw new \RuntimeException($errorMsg);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[whatsapp.baileys.delete] todas as tentativas falharam — instance pode estar zumbi no daemon', [
            'business_id' => $this->businessId,
            'instance_id' => $this->instanceId,
            'reason' => $this->reason,
            'error' => $exception->getMessage(),
        ]);
    }
}
