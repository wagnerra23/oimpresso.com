<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Dto;

/**
 * Input pra emitir cobrança (boleto/PIX/cartão).
 *
 * Imutável — segura pra passar entre listeners + workers sem corrida.
 * Idempotency: $idempotencyKey é o vínculo lógico entre tentativas.
 *
 * ADR 0170 — CONTRACTS.md §2.
 */
final class EmitirCobrancaInput
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $contactId,
        public readonly int $valorCentavos,
        public readonly \DateTimeImmutable $vencimento,
        public readonly string $descricao,
        public readonly string $idempotencyKey,
        public readonly ?string $origemType = null,
        public readonly ?int $origemId = null,
        public readonly array $multa = [],
        public readonly array $juros = [],
        public readonly array $desconto = [],
        public readonly ?string $instrucoesPagador = null,
        public readonly ?string $callbackUrl = null,
        public readonly array $meta = [],
    ) {
    }
}
