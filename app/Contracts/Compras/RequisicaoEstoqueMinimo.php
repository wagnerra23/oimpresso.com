<?php

declare(strict_types=1);

namespace App\Contracts\Compras;

/**
 * Liga e desliga a requisição de compra pelo estoque mínimo (US-COM-027 · UC-FLX-19), vista pelo
 * núcleo — a tela Fluxos (aba Fluxos da empresa) mostra o interruptor.
 *
 * O núcleo (`app/`) não importa de módulo (tests/Feature/Architecture/DependencyDirectionTest).
 * Quem implementa é `Modules\Compras\Services\RequisicaoEstoqueMinimoService`, ligado pelo
 * ComprasServiceProvider. Sem o módulo, a interface não está ligada e a tela não mostra o
 * interruptor (`app()->bound(RequisicaoEstoqueMinimo::class)`).
 */
interface RequisicaoEstoqueMinimo
{
    public function ativo(int $businessId): bool;

    public function definir(int $businessId, bool $ativo): void;
}
