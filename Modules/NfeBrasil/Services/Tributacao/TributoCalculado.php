<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Services\Tributacao;

/**
 * DTO de saída do MotorTributarioService.
 *
 * `nivel_usado` (1..4) é debug-friendly conforme ADR ARQ-0006:
 *   1 = override por produto
 *   2 = regra exata (ncm + ufOrigem + ufDestino)
 *   3 = regra NCM padrão (ncm + ufOrigem, ufDestino NULL)
 *   4 = defaults business
 *
 * Alíquotas em decimal (0.18 = 18%); valores absolutos calculados sobre o
 * `valor` do ProdutoFiscalContext usando regra simples (`valor * aliquota`).
 *
 * `csosn` e `cst` são mutuamente exclusivos por regime (CRT 1 = csosn,
 * CRT 3 = cst). Engine não escolhe — quem cadastrou a regra/default escolheu.
 */
final readonly class TributoCalculado
{
    public function __construct(
        public string  $cfop,
        public ?string $csosn,
        public ?string $cst,
        public float   $aliquota_icms,
        public float   $aliquota_pis,
        public float   $aliquota_cofins,
        public float   $aliquota_ipi,
        public float   $valor_icms,
        public float   $valor_pis,
        public float   $valor_cofins,
        public float   $valor_ipi,
        public int     $nivel_usado,
        public ?int    $regra_id = null,
        public ?float  $mva = null,
        public ?float  $fcp = null,
    ) {}
}
