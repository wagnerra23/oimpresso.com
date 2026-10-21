<?php

declare(strict_types=1);

namespace App\Contracts\Producao;

/**
 * Horas apontadas por posto do PCP num mês e troca do custo-hora do posto, vistas por quem
 * não é dono de `pcp_*` (US-FIN-070 · UC-FLX-20: o custeio RKW do Financeiro).
 *
 * O Financeiro não lê `pcp_*` direto (catálogo módulo→módulo). Quem implementa é
 * `Modules\Producao\Services\HorasDosPostosService`, ligado pelo ProducaoServiceProvider.
 * Sem o módulo a interface não está ligada: quem consome confere `app()->bound(...)`.
 */
interface HorasDosPostos
{
    /**
     * Postos da empresa com segundos trabalhados no mês, contando só trechos FECHADOS
     * (linha `inicio` + a `pausa`/`fim` que a fecha) cujo início cai no mês.
     *
     * `setor` é o texto livre do cadastro do posto (`pcp_postos.setor`); null = posto sem setor.
     * `custo_hora` é o gravado no posto (o que o apontamento copia); `custo_hora_direto` é o
     * custo sem a parcela fixa do custeio RKW: igual ao gravado enquanto nada foi aplicado.
     *
     * @return list<array{posto_id: int, codigo: string, nome: string, setor: ?string, custo_hora: string, custo_hora_direto: string, segundos: int}>
     */
    public function noMes(int $businessId, string $mes): array;

    /**
     * Troca `custo_hora` do posto pelo custo com a parcela fixa. Na primeira troca o custo
     * gravado vira o `custo_hora_direto`; nas seguintes o direto não muda. Apontamentos já
     * gravados guardam a cópia do custo no início e NÃO mudam. Null quando o posto não é da empresa.
     *
     * @return array{antes: string, depois: string}|null
     */
    public function trocarCustoHora(int $businessId, int $postoId, string $novo): ?array;
}
