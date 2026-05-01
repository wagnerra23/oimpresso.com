<?php

namespace Modules\NFSe\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\NFSe\Contracts\NfseProviderInterface;
use Modules\NFSe\DTO\NfseEmissaoPayload;
use Modules\NFSe\Exceptions\NfseException;
use Modules\NFSe\Models\NfseEmissao;
use Modules\NFSe\Services\NfseEmissaoService;

class EmitirNfseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60; // segundos entre tentativas

    public function __construct(public readonly NfseEmissaoPayload $payload) {}

    public function handle(NfseProviderInterface $provider): void
    {
        $service = new NfseEmissaoService($provider);
        $service->emitir($this->payload);
    }

    public function failed(\Throwable $exception): void
    {
        // Marca nota como erro se o job esgotar todas as tentativas
        NfseEmissao::withoutGlobalScopes()
            ->where('idempotency_key', $this->payload->idempotencyKey())
            ->where('business_id', $this->payload->businessId)
            ->where('status', 'processando')
            ->update([
                'status'        => 'erro',
                'erro_mensagem' => $exception->getMessage(),
            ]);
    }
}
