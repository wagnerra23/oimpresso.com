<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Services;

use Modules\PaymentGateway\Dto\CobrancaStatus;
use Modules\PaymentGateway\Exceptions\CredentialMisconfiguredException;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Modules\PaymentGateway\Services\PaymentGatewayService;

/**
 * Cancelar, estornar e consultar uma cobrança já emitida, pelos drivers do PaymentGateway.
 *
 * Decisão b1 (2026-11-23, auditoria RecurringBilling 2026-11-19): o dono dos drivers e das
 * credenciais é o PaymentGateway (ADR 0170). A emissão já saía pelo PG; cancelar e estornar ainda
 * usavam a cópia do RB (`Services/Boleto/*`, credencial em `rb_boleto_credentials`). Este é o único
 * ponto do RB que fala com o gateway para isso.
 *
 * Credencial: `payment_gateway_credentials` da empresa, do gateway pedido, ativa. Com produção e
 * sandbox ativas ao mesmo tempo (a UNIQUE do PG permite), vale a de produção. Sem credencial ativa
 * lança `CredentialMisconfiguredException` — o job falha visível e entra no retry, nunca segue sem
 * cancelar.
 *
 * Valor: o estorno passa `null` (total) ou centavos inteiros; nada de float.
 */
class CobrancaNoGateway
{
    public function cancelar(int $businessId, string $gatewayKey, string $ref, string $motivo): void
    {
        $this->servico($businessId, $gatewayKey)->cancelar(self::cobranca($ref), $motivo);
    }

    public function estornar(int $businessId, string $gatewayKey, string $ref, string $motivo, ?int $valorCentavos = null): void
    {
        $this->servico($businessId, $gatewayKey)->refund(self::cobranca($ref), $valorCentavos, $motivo);
    }

    public function consultar(int $businessId, string $gatewayKey, string $ref): CobrancaStatus
    {
        return $this->servico($businessId, $gatewayKey)->consultar(self::cobranca($ref));
    }

    private function servico(int $businessId, string $gatewayKey): PaymentGatewayService
    {
        // SUPERADMIN: roda em job, sem sessão; o business_id vem do construtor do job e filtra a busca.
        $credencial = PaymentGatewayCredential::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('gateway_key', $gatewayKey)
            ->where('ativo', true)
            ->orderByRaw("CASE WHEN ambiente = 'production' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->first();

        if ($credencial === null) {
            throw new CredentialMisconfiguredException(
                "Empresa {$businessId} sem credencial '{$gatewayKey}' ativa em payment_gateway_credentials"
            );
        }

        return (new PaymentGatewayService())->comCredencial($credencial);
    }

    /** O driver do PG lê só `gateway_external_id` (e `tipo` no estorno Pix do Inter). */
    private static function cobranca(string $ref): object
    {
        return (object) ['gateway_external_id' => $ref];
    }
}
