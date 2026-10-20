<?php

declare(strict_types=1);

namespace App\Contracts\Producao;

/**
 * Custo da hora de máquina de um posto do PCP (UC-FLX-33 · US-COMVIS-019), visto pelo núcleo.
 *
 * O núcleo (`app/`) não importa de módulo (tests/Feature/Architecture/DependencyDirectionTest).
 * Quem implementa é `Modules\Producao\Services\CustoHoraDoPostoService`, ligado pelo
 * ProducaoServiceProvider. Sem o módulo, a interface não está ligada: quem consome confere
 * `app()->bound(CustoHoraDoPosto::class)` e segue sem custo de máquina.
 */
interface CustoHoraDoPosto
{
    /**
     * Custo da hora do posto com 2 casas ("42.00"), ou null quando o posto não é da empresa,
     * não custa por hora de máquina (`tipo_custo` = pessoa) ou não tem custo informado (zero).
     */
    public function custoHora(int $businessId, int $postoId): ?string;
}
