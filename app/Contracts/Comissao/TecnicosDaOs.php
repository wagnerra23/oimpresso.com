<?php

declare(strict_types=1);

namespace App\Contracts\Comissao;

/**
 * Quem trabalhou na OS que gerou uma venda, e quantas horas cada um digitou.
 *
 * Fronteira entre a Oficina (dona das horas, US-AUTO-006) e a comissão por regra (dona do cálculo,
 * US-COMM-015): a comissão do papel técnico pode ser dividida pela fração de horas de cada mecânico.
 * Lista vazia = a venda não veio de OS com mecânicos lançados (a comissão segue o técnico único).
 *
 * Tier 0: sempre pelo `$businessId` recebido — nunca pela sessão (roda também em fila).
 */
interface TecnicosDaOs
{
    /** @return list<array{user_id:int, horas:float}> */
    public function daVenda(int $businessId, int $transactionId): array;
}
