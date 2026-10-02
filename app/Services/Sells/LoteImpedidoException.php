<?php

declare(strict_types=1);

namespace App\Services\Sells;

/**
 * Lançada dentro da transação do reverter de lote importado quando o `deleteSale()` recusa
 * uma venda: desfaz TUDO o que já foi apagado no lote (tudo-ou-nada, D3 de [W] 2026-10-02).
 */
final class LoteImpedidoException extends \RuntimeException
{
    /** @param  list<array{id: int, invoice_no: string, motivo: string}>  $impedidas */
    public function __construct(public readonly array $impedidas)
    {
        parent::__construct('Lote importado recusado: venda que não pode ser apagada.');
    }
}
