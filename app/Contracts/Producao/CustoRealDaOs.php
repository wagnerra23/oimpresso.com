<?php

declare(strict_types=1);

namespace App\Contracts\Producao;

/**
 * O que foi gasto numa OS do PCP, visto por quem não é dono de `pcp_*`
 * (US-COMVIS-005: o pós-cálculo da OS da Comunicação Visual).
 *
 * Quem implementa é `Modules\Producao\Services\CustoRealDaOsService`, ligado pelo
 * ProducaoServiceProvider. As horas usam a conta do `CustoRealService`, sem cópia: centavos ×
 * segundos dos apontamentos FECHADOS, custo-hora copiado no início, arredonda UMA vez por tipo,
 * meio para cima. Sem o módulo a interface não está ligada: quem consome confere `app()->bound(...)`.
 */
interface CustoRealDaOs
{
    /**
     * Custo das horas apontadas na OS, com 2 casas, em texto.
     *
     * @return array{maquina: string, pessoa: string}
     */
    public function horas(int $businessId, string $osTipo, int $osId): array;

    /** m² impresso informado ao concluir as etapas da OS (linhas `fim`), 3 casas, em texto. */
    public function m2Impresso(int $businessId, string $osTipo, int $osId): string;
}
