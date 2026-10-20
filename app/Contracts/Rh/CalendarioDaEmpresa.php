<?php

declare(strict_types=1);

namespace App\Contracts\Rh;

use Carbon\CarbonInterface;

/**
 * Período de funcionamento da empresa cadastrado no RH (Essentials), visto pelo núcleo.
 *
 * Decisão [W] 2026-10-20: o prazo em dias úteis (UC-FLX-06 · US-PCP-028) vem do que a empresa já
 * cadastrou — feriados em RH › Feriados (`essentials_holidays`) e expediente em RH › Turnos
 * (`essentials_shifts`) —, não de feriados fixos no código.
 *
 * O núcleo (`app/`) não importa de módulo (tests/Feature/Architecture/DependencyDirectionTest).
 * Quem implementa é `Modules\Essentials\Services\CalendarioDaEmpresaService`, ligado pelo
 * EssentialsServiceProvider. Sem o módulo, a interface não está ligada: quem consome confere
 * `app()->bound(CalendarioDaEmpresa::class)` e segue com sábado e domingo e sem feriado.
 *
 * Feriado vale com a MESMA regra do Ponto (`Modules\Ponto\Services\ApuracaoService::aplicarFeriado`):
 * o da empresa (`location_id` nulo) ou o da loja, com `start_date ≤ dia ≤ end_date` — para a
 * produção e o ponto pararem nos mesmos dias.
 *
 * Tier 0 (ADR 0093): toda consulta recebe o `business_id` explícito.
 */
interface CalendarioDaEmpresa
{
    /**
     * Dias de feriado da empresa ou da loja `$locationId` entre `$de` e `$ate` (inclusive).
     * `$locationId` nulo = só os feriados da empresa toda.
     *
     * @return list<string> datas Y-m-d, sem repetição, em ordem
     */
    public function diasNaoUteis(int $businessId, ?int $locationId, CarbonInterface $de, CarbonInterface $ate): array;

    /**
     * Expediente da empresa: o turno fixo do RH. Com mais de um, o que tem mais funcionários
     * vinculados (empate: o de menor id). Turno flexível não tem horário e não conta.
     *
     * @return array{nome: string, inicio: string, fim: string, folgas: list<int>}|null
     *         `inicio`/`fim` em HH:MM:SS; `folgas` = dias da semana sem expediente (0 = domingo … 6 = sábado);
     *         null quando a empresa não tem turno fixo com horário
     */
    public function expediente(int $businessId): ?array;
}
