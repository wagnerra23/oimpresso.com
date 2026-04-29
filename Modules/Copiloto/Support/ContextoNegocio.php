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
        /**
         * Faturamento últimos 90 dias agregado por mês.
         *
         * MEM-FAT-1 (29-abr): expõe 3 números distintos pra LLM saber qual usar:
         *  - bruto    = SUM(sell.final.final_total)
         *  - liquido  = bruto - SUM(sell_return.final.final_total)
         *  - caixa    = SUM(transaction_payments.amount no mês, descontando estornos)
         *
         * Compatibilidade legado: `valor` mantido como alias do bruto pra
         * snapshots antigos não quebrarem (ContextoNegocio é DTO imutável,
         * mas BriefingAgent ainda lê `$m['valor']`).
         *
         * @var array<array{mes: string, valor: float, bruto: float, liquido: float, caixa: float}>
         */
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
