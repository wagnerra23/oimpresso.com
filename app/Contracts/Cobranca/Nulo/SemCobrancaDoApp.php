<?php

declare(strict_types=1);

namespace App\Contracts\Cobranca\Nulo;

use App\Contracts\Cobranca\CobrancaDoApp;
use App\Contracts\Cobranca\FalhaCobranca;

/** Padrão quando o módulo PaymentGateway não está carregado: não há gateway configurado. */
final class SemCobrancaDoApp implements CobrancaDoApp
{
    public function emitir(int $businessId, array $dados): int
    {
        throw new FalhaCobranca('sem_configuracao');
    }

    public function consultar(int $businessId, int $cobrancaId): void
    {
        throw new FalhaCobranca('sem_configuracao');
    }

    public function cancelar(int $businessId, int $cobrancaId): void
    {
        throw new FalhaCobranca('sem_configuracao');
    }
}
