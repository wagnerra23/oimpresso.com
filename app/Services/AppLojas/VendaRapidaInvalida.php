<?php

declare(strict_types=1);

namespace App\Services\AppLojas;

/**
 * Recusa da venda rápida do app (tela 11) por regra de negócio — vira `422 { erro: "validacao", campos }`.
 * Lançada DENTRO da transação de banco: quem chama desfaz tudo (venda, linhas, pagamento, estoque e a
 * reserva da Idempotency-Key).
 */
final class VendaRapidaInvalida extends \RuntimeException
{
    /** @param array<string,string> $campos */
    public function __construct(public readonly array $campos)
    {
        parent::__construct('Venda rápida recusada: ' . implode(', ', array_keys($campos)));
    }
}
