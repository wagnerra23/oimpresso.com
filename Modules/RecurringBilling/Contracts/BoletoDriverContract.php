<?php

namespace Modules\RecurringBilling\Contracts;

use Modules\RecurringBilling\Dto\BoletoResult;

interface BoletoDriverContract
{
    public function emitir(array $params): BoletoResult;

    public function cancelar(string $nossoNumero, string $motivo = 'ACERTOS'): bool;

    public function pdf(string $nossoNumero): string;
}
