<?php

namespace Modules\RecurringBilling\Services\Boleto;

use Illuminate\Support\Facades\Crypt;
use Modules\RecurringBilling\Contracts\BoletoDriverContract;
use Modules\RecurringBilling\Dto\BoletoResult;
use Modules\RecurringBilling\Models\BoletoCredential;
use Modules\RecurringBilling\Services\Boleto\Drivers\AsaasDriver;
use Modules\RecurringBilling\Services\Boleto\Drivers\C6Driver;
use Modules\RecurringBilling\Services\Boleto\Drivers\InterDriver;

/**
 * Orquestra o driver de boleto correto por tenant.
 *
 * Drivers disponíveis:
 *   'inter'  — Banco Inter API OAuth2 mTLS (sem taxa PJ, boleto registrado)
 *   'c6'     — C6 Bank geração local CNAB (sem taxa PJ)
 *   'asaas'  — Asaas API REST (sandbox disponível, PIX + boleto + cartão)
 */
class BoletoService
{
    public function emitir(int $businessId, array $params): BoletoResult
    {
        return $this->driver($businessId)->emitir($params);
    }

    public function cancelar(int $businessId, string $nossoNumero, string $motivo = 'ACERTOS'): bool
    {
        return $this->driver($businessId)->cancelar($nossoNumero, $motivo);
    }

    public function pdf(int $businessId, string $nossoNumero): string
    {
        return $this->driver($businessId)->pdf($nossoNumero);
    }

    private function driver(int $businessId): BoletoDriverContract
    {
        $credential = BoletoCredential::where('business_id', $businessId)
            ->where('ativo', true)
            ->firstOrFail();

        $config = $this->decryptConfig($credential);

        return match ($credential->banco) {
            'inter' => new InterDriver($config),
            'c6'    => new C6Driver($config),
            'asaas' => new AsaasDriver($config),
            default => throw new \InvalidArgumentException("Driver de boleto '{$credential->banco}' não suportado."),
        };
    }

    private function decryptConfig(BoletoCredential $credential): array
    {
        $config = $credential->config_json ?? [];

        // Descriptografa campos sensíveis. Note: certificado_key_b64 é
        // criptografado pra proteger a chave privada — após decifrar, ainda
        // é base64; o driver faz o base64_decode antes de usar o PEM.
        foreach (['client_secret', 'api_key', 'certificado_senha', 'certificado_key_b64'] as $field) {
            if (isset($config[$field])) {
                $config[$field] = Crypt::decryptString($config[$field]);
            }
        }

        return $config;
    }
}
