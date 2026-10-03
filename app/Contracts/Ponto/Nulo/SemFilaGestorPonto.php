<?php

declare(strict_types=1);

namespace App\Contracts\Ponto\Nulo;

use App\Contracts\Ponto\FilaGestorPonto;
use App\User;

/** Padrão quando o módulo Ponto não está carregado: ninguém vê a fila, nada a decidir. */
final class SemFilaGestorPonto implements FilaGestorPonto
{
    public function podeVer(User $user): bool
    {
        return false;
    }

    public function marcacoes(int $businessId): array
    {
        return [];
    }

    public function validar(User $user, int $businessId, string $id): string
    {
        return self::NAO_ENCONTRADA;
    }

    public function recusar(User $user, int $businessId, string $id): array
    {
        return ['resultado' => self::NAO_ENCONTRADA, 'nsr_anulacao' => null];
    }
}
