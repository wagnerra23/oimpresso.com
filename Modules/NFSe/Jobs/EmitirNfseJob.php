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
use Modules\NFSe\Exceptions\ProviderTimeoutException;
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

        try {
            // Da 2ª tentativa em diante a linha `processando` deixada pelo timeout é reenviada
            // com o mesmo nº de DPS, em vez de virar nota nova.
            $service->emitir($this->payload, retomarPendente: $this->attempts() > 1);
        } catch (ProviderTimeoutException $e) {
            throw $e; // a nota pode ter sido aceita: o job retenta com o mesmo nº de DPS
        } catch (NfseException $e) {
            // Erro terminal (certificado, ISS, tomador...): retentar não muda a resposta.
            $this->fail($e);
        }
    }

    public function failed(\Throwable $exception): void
    {
        // Timeout esgotado: a nota pode ter sido aceita pela Sefin. A linha fica `processando`
        // com o aviso do serviço — virar `erro` deixaria um reenvio do usuário criar outra
        // linha com outro nº de DPS (nota em dobro).
        if ($exception instanceof ProviderTimeoutException) {
            return;
        }

        // SUPERADMIN: job failed handler sem session — business_id no payload do constructor
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
