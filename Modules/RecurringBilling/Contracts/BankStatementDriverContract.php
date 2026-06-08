<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Contracts;

use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Driver de extrato bancário — contract genérico cross-banco.
 *
 * Separado de `BoletoDriverContract` (cobrança) — SoC ADR 0094 §5.
 * Implementações: `InterStatementDriver` (Fase 2). Próximos:
 * `SicoobStatementDriver`, `BtgStatementDriver`, `CoraStatementDriver`.
 *
 * @see US-RB-046
 */
interface BankStatementDriverContract
{
    /**
     * Lê extrato do banco entre `$from` e `$to` (datas inclusivas).
     *
     * @return Collection<int, \Modules\RecurringBilling\Dto\StatementLineDto>
     */
    public function fetchStatement(Carbon $from, Carbon $to): Collection;
}
