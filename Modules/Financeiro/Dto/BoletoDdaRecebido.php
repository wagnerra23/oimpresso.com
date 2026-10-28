<?php

namespace Modules\Financeiro\Dto;

use Carbon\CarbonInterface;

/**
 * Boleto DDA como a fonte entrega, já normalizado (US-FIN-078).
 * `valor` em reais com até 2 casas — nunca float de locale (regra mestre de valor).
 */
final class BoletoDdaRecebido
{
    public function __construct(
        public readonly string $codigoBarras,
        public readonly string $beneficiarioDocumento,
        public readonly ?string $beneficiarioNome,
        public readonly string $valor,
        public readonly CarbonInterface $vencimento,
        public readonly array $raw = [],
    ) {}
}
