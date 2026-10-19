<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Conversão de orçamento em venda recusada: a margem pelo custo guardado está abaixo do mínimo da
 * empresa e quem converte não tem `sell.aprovar_margem_baixa` (UC-DRAFT-05 · UC-FLX-35).
 *
 * A mensagem é para o vendedor: os controllers que convertem a mostram como está, como fazem com
 * PurchaseSellMismatch.
 */
class MargemAbaixoDoMinimo extends Exception
{
    public function __construct(public readonly float $margem, public readonly float $minima)
    {
        parent::__construct(sprintf(
            'Margem de %s%% abaixo do mínimo de %s%% da empresa. Peça a quem pode aprovar margem baixa para converter este orçamento.',
            number_format($margem, 1, ',', '.'),
            number_format($minima, 1, ',', '.'),
        ));
    }

    /**
     * A mensagem para o vendedor quando a exceção pega é esta; nula para qualquer outra.
     * Existe porque um catch antigo do SellPosController declara `Exception` sem `\` e o PHPStan
     * conta as chamadas a `getMessage()` nele na baseline.
     */
    public static function mensagemDe(object $e): ?string
    {
        return $e instanceof self ? $e->getMessage() : null;
    }
}
