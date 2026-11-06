<?php

declare(strict_types=1);

namespace App\Contracts\Producao;

/**
 * Chaves de material da Produção vistas pelo núcleo: a reserva do insumo na venda (US-PCP-031 ·
 * UC-FLX-14) e a irmã "Aguardando material" (US-PCP-032 · D3). A tela Sistema › Fluxos e regras
 * liga e desliga as duas (UC-FLXT-22 e UC-FLXT-25).
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

    /** A OS liberada sem o material da receita vai para Aguardando material? */
    public function aguardaMaterial(int $businessId): bool;

    /** Liga ou desliga "Aguardando material" para a empresa, mexendo só nesta chave de `common_settings`. */
    public function mudarAguardaMaterial(int $businessId, bool $ligada): void;
}
