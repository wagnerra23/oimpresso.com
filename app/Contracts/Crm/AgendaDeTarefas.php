<?php

declare(strict_types=1);

namespace App\Contracts\Crm;

use Carbon\CarbonInterface;

/**
 * Agenda de tarefas (follow-up) de um usuário, vista pelo núcleo e pelos módulos.
 *
 * Primeiro consumidor: a régua de cobrança do Financeiro (US-FIN-071 · UC-FLX-29), que na
 * última etapa cria tarefa para o vendedor. Quem implementa é `Modules\Crm\Services\AgendaDeTarefasDoCrm`,
 * ligado pelo CrmServiceProvider — o módulo Crm continua dono de `crm_schedules`. Sem o módulo, a
 * interface não está ligada: quem consome confere `app()->bound(AgendaDeTarefas::class)` e segue
 * sem tarefa.
 *
 * O núcleo (`app/`) não importa de módulo (tests/Feature/Architecture/DependencyDirectionTest).
 */
interface AgendaDeTarefas
{
    /**
     * Cria uma tarefa da empresa atribuída ao usuário. Devolve o id da tarefa, ou null quando o
     * usuário não é da empresa (multi-tenant Tier 0, ADR 0093) ou o contato é de outra empresa.
     */
    public function criarTarefa(
        int $businessId,
        int $userId,
        string $titulo,
        string $descricao,
        ?int $contactId,
        ?int $transactionId,
        CarbonInterface $quando,
    ): ?int;
}
