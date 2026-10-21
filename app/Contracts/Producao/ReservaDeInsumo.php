<?php

declare(strict_types=1);

namespace App\Contracts\Producao;

/**
 * Chave da reserva do insumo na venda (US-PCP-031 · UC-FLX-14), vista pelo núcleo: a tela
 * Sistema › Fluxos e regras liga e desliga (UC-FLXT-22).
 *
 * O núcleo (`app/`) não importa de módulo (tests/Feature/Architecture/DependencyDirectionTest).
 * Quem implementa é `Modules\Producao\Services\ReservaDeInsumoDaOs`, ligado pelo
 * ProducaoServiceProvider. Sem o módulo a interface não está ligada: quem consome confere
 * `app()->bound(ReservaDeInsumo::class)` e não mostra o interruptor.
 */
interface ReservaDeInsumo
{
    /** A empresa reserva o insumo na venda? */
    public function ligada(int $businessId): bool;

    /** Liga ou desliga para a empresa, mexendo só nesta chave de `common_settings`. */
    public function mudar(int $businessId, bool $ligada): void;
}
