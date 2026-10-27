<?php

declare(strict_types=1);

namespace App\Services\Sells;

/** O resgate de pontos pedido na venda não passa na conferência do servidor (US-SELL-079). */
final class ResgatePontosException extends \RuntimeException
{
}
