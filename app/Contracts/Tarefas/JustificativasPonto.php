<?php

declare(strict_types=1);

namespace App\Contracts\Tarefas;

use App\User;

/**
 * Contrato do núcleo para as justificativas do Ponto na aba Tarefas do app das lojas.
 *
 * O núcleo (app/) depende deste contrato; o módulo Ponto o implementa e registra no
 * próprio ServiceProvider. Sem o módulo, vale App\Contracts\Tarefas\Nulo\SemJustificativasPonto
 * (ninguém aprova, lista vazia). A seta de dependência fica módulo → núcleo.
 *
 * Tier 0 (ADR 0093): toda consulta recebe o business_id explícito.
 *
 * @phpstan-import-type ItemTarefa from TarefasEssentials
 */
interface JustificativasPonto
{
    /** Quem aprova justificativas (o mesmo critério da tela de aprovações do Ponto). */
    public function aprova(User $user): bool;

    /**
     * Justificativas pendentes: quem aprova vê as da empresa; o colaborador, só as próprias.
     * Até 100, pela data.
     *
     * @return list<ItemTarefa>
     */
    public function pendentes(User $user, int $businessId): array;
}
