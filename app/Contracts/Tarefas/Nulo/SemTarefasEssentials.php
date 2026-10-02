<?php

declare(strict_types=1);

namespace App\Contracts\Tarefas\Nulo;

use App\Contracts\Tarefas\TarefasEssentials;
use App\User;

/** Padrão quando o módulo Essentials não está carregado: nenhuma ToDo. */
final class SemTarefasEssentials implements TarefasEssentials
{
    public function pendentes(User $user, int $businessId): array
    {
        return [];
    }

    public function concluir(User $user, int $businessId, int $id): bool
    {
        return false;
    }

    public function detalhe(User $user, int $businessId, int $id): ?array
    {
        return null;
    }
}
