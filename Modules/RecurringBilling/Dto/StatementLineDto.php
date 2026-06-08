<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Dto;

use Carbon\Carbon;

/**
 * Lançamento de extrato bancário normalizado (cross-banco).
 *
 * Cada `BankStatementDriverContract` mapeia o response específico do banco
 * pra esse formato. `idempotencyKey` é o ID estável do banco (Inter:
 * `idTransacao` ou `endToEndId` PIX) usado pra UPSERT em
 * `fin_extrato_lancamentos`.
 *
 * @see US-RB-046
 */
class StatementLineDto
{
    public function __construct(
        public readonly Carbon $data,
        public readonly float $valor,
        public readonly string $tipo,           // 'C' = credito, 'D' = debito
        public readonly string $descricao,
        public readonly ?string $contraparteDocumento,
        public readonly ?string $contraparteNome,
        public readonly string $idempotencyKey,
        public readonly array $raw,
    ) {}

    public function toArray(): array
    {
        return [
            'data'                  => $this->data->toDateString(),
            'valor'                 => $this->valor,
            'tipo'                  => $this->tipo,
            'descricao'             => $this->descricao,
            'contraparte_documento' => $this->contraparteDocumento,
            'contraparte_nome'      => $this->contraparteNome,
            'idempotency_key'       => $this->idempotencyKey,
            'raw_payload'           => $this->raw,
        ];
    }
}
