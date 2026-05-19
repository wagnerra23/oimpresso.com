<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Contracts;

use App\Account;
use Modules\PaymentGateway\Dto\CardToken;
use Modules\PaymentGateway\Dto\CobrancaEmitidaResult;
use Modules\PaymentGateway\Dto\CobrancaStatus;
use Modules\PaymentGateway\Dto\EmitirCobrancaInput;

/**
 * API pública do módulo PaymentGateway — quem precisa cobrar usa só isso.
 *
 * Implementação concreta + binding container chegam em Onda 4.
 * Onda 1 (esta) define apenas a interface — drivers reais ainda em
 * Modules/RecurringBilling.
 *
 * ADR 0170 — CONTRACTS.md §1.
 */
interface PaymentGatewayContract
{
    /**
     * Seleciona a credencial de gateway vinculada à conta.
     *
     * Idempotente — pode ser chamado várias vezes na mesma request.
     *
     * @throws \Modules\PaymentGateway\Exceptions\CredentialMisconfiguredException
     */
    public function for(Account $account): self;

    /**
     * Emite boleto único.
     *
     * Idempotência via $input->idempotencyKey — se já existe Cobranca
     * em status emitida|paga, retorna resultado anterior sem chamar gateway.
     *
     * @throws \Modules\PaymentGateway\Exceptions\GatewayUnavailableException
     * @throws \Modules\PaymentGateway\Exceptions\InvalidPayerException
     * @throws \Modules\PaymentGateway\Exceptions\CredentialMisconfiguredException
     */
    public function emitirBoleto(EmitirCobrancaInput $input): CobrancaEmitidaResult;

    /**
     * Emite PIX cobrança.
     *
     * @param string $tipo 'cob' (imediata) | 'cobv' (com vencimento)
     */
    public function emitirPix(EmitirCobrancaInput $input, string $tipo = 'cob'): CobrancaEmitidaResult;

    /**
     * Emite PIX Automático BCB (mandato recorrente).
     *
     * Suportado apenas por driver bcb_pix hoje.
     *
     * @throws \Modules\PaymentGateway\Exceptions\DriverNotSupportedException
     */
    public function emitirPixAutomatico(EmitirCobrancaInput $input): CobrancaEmitidaResult;

    /**
     * Tokeniza + cobra cartão.
     *
     * @throws \Modules\PaymentGateway\Exceptions\DriverNotSupportedException
     * @throws \Modules\PaymentGateway\Exceptions\CardDeclinedException
     */
    public function cobrarCartao(EmitirCobrancaInput $input, CardToken $token): CobrancaEmitidaResult;

    /**
     * Cancela cobrança ainda não paga.
     *
     * Para estornar cobrança JÁ paga, use refund().
     */
    public function cancelar(object $cobranca, string $motivo): void;

    /**
     * Estorna cobrança paga. Driver-dependente.
     *
     * @throws \Modules\PaymentGateway\Exceptions\DriverNotSupportedException
     */
    public function refund(object $cobranca, ?int $valorCentavos, string $motivo): void;

    /**
     * Consulta status atualizado direto no gateway (bypass cache local).
     */
    public function consultar(object $cobranca): CobrancaStatus;
}
