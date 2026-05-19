<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Contracts;

use Modules\PaymentGateway\Dto\CardToken;
use Modules\PaymentGateway\Dto\CobrancaEmitidaResult;
use Modules\PaymentGateway\Dto\CobrancaStatus;
use Modules\PaymentGateway\Dto\DriverHealth;
use Modules\PaymentGateway\Dto\EmitirCobrancaInput;

/**
 * Interface dos drivers concretos (Inter/C6/Asaas/BCB Pix/PesaPal).
 *
 * NÃO é API pública — listada aqui só pra documentar contrato interno.
 * Drivers reais chegam em Onda 4.
 *
 * Cobranca + PaymentGatewayCredential são tipados como object pois
 * Entities concretas só nascem em Onda 2.
 *
 * ADR 0170 — CONTRACTS.md §1.
 */
interface PaymentDriverContract
{
    /**
     * Chave única do driver (inter | c6 | asaas | bcb_pix | pesapal).
     */
    public function key(): string;

    /**
     * Driver suporta este tipo de operação?
     *
     * @param string $tipo boleto | pix_cob | pix_cobv | pix_recv | card
     */
    public function supports(string $tipo): bool;

    public function emitirBoleto(EmitirCobrancaInput $input, object $cred): CobrancaEmitidaResult;

    public function emitirPix(EmitirCobrancaInput $input, object $cred, string $tipo): CobrancaEmitidaResult;

    public function emitirPixAutomatico(EmitirCobrancaInput $input, object $cred): CobrancaEmitidaResult;

    public function cobrarCartao(EmitirCobrancaInput $input, object $cred, CardToken $token): CobrancaEmitidaResult;

    public function cancelar(object $cobranca, object $cred, string $motivo): void;

    public function refund(object $cobranca, object $cred, ?int $valorCentavos, string $motivo): void;

    public function consultar(object $cobranca, object $cred): CobrancaStatus;

    public function healthCheck(object $cred): DriverHealth;

    /**
     * Processa payload de webhook do gateway e retorna a Cobranca afetada
     * (ou null se for evento ignorável). Validação HMAC é responsabilidade
     * do driver.
     */
    public function processWebhook(array $payload, object $cred): ?object;
}
