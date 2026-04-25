<?php

namespace Modules\Copiloto\Support;

/**
 * DTO imutável do contexto de negócio que alimenta a IA.
 * Ver adr/tech/0002 — seção "Contrato mínimo de ContextoNegocio".
 *
 * Sanitização: CPF/CNPJ mascarados ANTES de popular esse DTO.
 */
final class ContextoNegocio
{
    public function __construct(
        public readonly ?int $businessId,
        public readonly string $businessName,
        /** @var array<array{mes: string, valor: float}> */
        public readonly array $faturamento90d,
        public readonly int $clientesAtivos,
        /** @var string[] */
        public readonly array $modulosAtivos,
        /** @var array<array{nome: string, valor_alvo: float, realizado: float}> */
        public readonly array $metasAtivas,
        public readonly ?string $observacoes = null,
    ) {
    }
}
