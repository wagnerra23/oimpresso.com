<?php

declare(strict_types=1);

namespace Modules\NfeBrasil\Services\Tributacao;

/**
 * DTO de entrada do MotorTributarioService.
 *
 * Desacopla o motor do model `Product` UPos — qualquer caller (POS,
 * RecurringBilling, integração externa) monta este context com o que tem.
 *
 * `fiscal_rule_override_id` aponta pra `nfe_fiscal_rules.id` que vence
 * a cascade (Nível 1 ADR ARQ-0006).
 */
final readonly class ProdutoFiscalContext
{
    public function __construct(
        public ?string $ncm,
        public float   $valor,
        public ?int    $fiscal_rule_override_id = null,
        public ?string $cest = null,
        public ?string $description = null,
    ) {}
}
