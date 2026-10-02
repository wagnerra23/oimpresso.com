<?php

declare(strict_types=1);

namespace App\Contracts\Tarefas\Nulo;

use App\Contracts\Tarefas\JustificativasPonto;
use App\User;

/** Padrão quando o módulo Ponto não está carregado: ninguém aprova, nenhuma justificativa. */
final class SemJustificativasPonto implements JustificativasPonto
{
    public function aprova(User $user): bool
    {
        return false;
    }

    public function pendentes(User $user, int $businessId): array
    {
        return [];
    }
}
