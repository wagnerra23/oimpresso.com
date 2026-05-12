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

    /**
     * Refund Asaas (estorno de cobrança paga) — só faz sentido pro driver Asaas.
     *
     * Inter PJ não tem endpoint nativo de reembolso; nesse caso o caller é
     * RefundCobrancaInterJob que cuida do path manual (TED/PIX humano).
     *
     * @param  int  $businessId  Tenant
     * @param  string  $chargeId  pay_xxx Asaas
     * @param  string  $descricao  Motivo (gravado no Asaas)
     * @param  float|null  $valor  Parcial; null = total
     * @return array  Response Asaas (id, status, value, refundedDate)
     *
     * @throws \InvalidArgumentException se driver do tenant não for Asaas
     */
    public function refundAsaas(int $businessId, string $chargeId, string $descricao, ?float $valor = null): array
    {
        $driver = $this->driver($businessId);

        if (! $driver instanceof AsaasDriver) {
            throw new \InvalidArgumentException(
                'refundAsaas() exige driver Asaas — tenant business_id=' . $businessId
                . ' está configurado com driver diferente.'
            );
        }

        return $driver->refund($chargeId, $descricao, $valor);
    }

    /**
     * Fetch payment Asaas — usado pra checar status atual antes de decidir
     * cancelar vs refund (PENDING => cancelar; RECEIVED|CONFIRMED => refund).
     */
    public function fetchPaymentAsaas(int $businessId, string $chargeId): array
    {
        $driver = $this->driver($businessId);

        if (! $driver instanceof AsaasDriver) {
            throw new \InvalidArgumentException(
                'fetchPaymentAsaas() exige driver Asaas — tenant business_id=' . $businessId
                . ' está configurado com driver diferente.'
            );
        }

        return $driver->fetchPayment($chargeId);
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
