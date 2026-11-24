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

    /**
     * Credencial ativa da empresa no PaymentGateway, com o `config_json` já decifrado pelo cast.
     *
     * Sem `$ambiente`, vale a de produção quando há as duas. Com `$ambiente`, só aquele — é o caso
     * de quem ainda aponta para uma linha de `rb_boleto_credentials` e precisa do mesmo ambiente.
     * Também usada pelo reajuste de assinatura no Asaas e pelo sync de saldo (onda 1 da saída de
     * `rb_boleto_credentials`, 2026-11-24).
     */
    public function credencial(int $businessId, string $gatewayKey, ?string $ambiente = null): PaymentGatewayCredential
    {
        // SUPERADMIN: roda em job e em service sem sessão garantida; o business_id vem de quem chama e filtra a busca.
        $credencial = PaymentGatewayCredential::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('gateway_key', $gatewayKey)
            ->where('ativo', true)
            ->when($ambiente !== null, fn ($q) => $q->where('ambiente', $ambiente))
            ->orderByRaw("CASE WHEN ambiente = 'production' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->first();

        if ($credencial === null) {
            $qual = $ambiente !== null ? " ({$ambiente})" : '';
            throw new CredentialMisconfiguredException(
                "Empresa {$businessId} sem credencial '{$gatewayKey}'{$qual} ativa em payment_gateway_credentials"
            );
        }

        return $credencial;
    }

    private function servico(int $businessId, string $gatewayKey): PaymentGatewayService
    {
        return (new PaymentGatewayService())->comCredencial($this->credencial($businessId, $gatewayKey));
    }

    /** O driver do PG lê só `gateway_external_id` (e `tipo` no estorno Pix do Inter). */
    private static function cobranca(string $ref): object
    {
        return (object) ['gateway_external_id' => $ref];
    }
}
