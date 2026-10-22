<?php

declare(strict_types=1);

namespace App\Contracts\Compras;

use App\Transaction;

/**
 * Regra de custo e preço no recebimento da compra (US-COM-026 · UC-FLX-17), vista pelo núcleo.
 *
 * O núcleo não importa de módulo (tests/Feature/Architecture/DependencyDirectionTest). Quem
 * implementa é `Modules\Compras\Services\AplicarCustoDoRecebimento`, ligado pelo
 * ComprasServiceProvider. Sem o módulo a interface não está ligada e o PurchaseController segue
 * como antes (`app()->bound(CustoDoRecebimento::class)`).
 */
interface CustoDoRecebimento
{
    /** A empresa gravou a regra de custo, ligada e disparando em Recebido. */
    public function assume(int $businessId): bool;

    /**
     * Aplica a regra se a compra está recebida e a empresa assume o custo; senão não faz nada.
     *
     * @return list<array<string, mixed>> o que mudou, por variação
     */
    public function aoReceber(Transaction $compra): array;
}
