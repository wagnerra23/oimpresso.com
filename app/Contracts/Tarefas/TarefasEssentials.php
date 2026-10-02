<?php

declare(strict_types=1);

namespace App\Contracts\Tarefas;

use App\User;

/**
 * Contrato do núcleo para as ToDos do Essentials na aba Tarefas do app das lojas.
 *
 * O núcleo (app/) depende deste contrato; o módulo Essentials o implementa e registra no
 * próprio ServiceProvider. Sem o módulo, vale App\Contracts\Tarefas\Nulo\SemTarefasEssentials
 * (lista vazia). A seta de dependência fica módulo → núcleo (DependencyDirectionTest).
 *
 * Tier 0 (ADR 0093): toda consulta recebe o business_id explícito.
 *
 * @phpstan-type ItemTarefa array{id: int|string, titulo: string, subtitulo: string, prazo: ?string}
 */
interface TarefasEssentials
{
    /**
     * ToDos não concluídas visíveis ao usuário, pelo mesmo escopo da tela web
     * (admin vê as da empresa; os demais, as criadas por eles ou atribuídas a eles).
     * Até 100, pela data.
     *
     * @return list<ItemTarefa>
     */
    public function pendentes(User $user, int $businessId): array;

    /**
     * Conclui a ToDo se ela for visível ao usuário. Devolve false se não achou.
     */
    public function concluir(User $user, int $businessId, int $id): bool;
}
