<?php

declare(strict_types=1);

namespace App\Contracts\Compras;

/**
 * Fila dos preços propostos pelo recebimento da compra (US-COM-028 · UC-FLX-17), vista pelo núcleo.
 *
 * O núcleo não importa de módulo (tests/Feature/Architecture/DependencyDirectionTest). Quem
 * implementa é `Modules\Compras\Services\PropostasDePreco`, ligado pelo ComprasServiceProvider.
 * Sem o módulo a interface não está ligada e o detalhe da compra não mostra a seção.
 */
interface PropostasDePreco
{
    /**
     * Propostas da compra + o aviso da regra (onde se aprova; etapa Conferido sem efeito).
     *
     * @return array{aprovacao: ?string, aviso: ?string, itens: list<array<string, mixed>>}
     */
    public function daCompra(int $businessId, int $transactionId): array;
}
