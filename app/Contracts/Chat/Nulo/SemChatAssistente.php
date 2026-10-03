<?php

declare(strict_types=1);

namespace App\Contracts\Chat\Nulo;

use App\Contracts\Chat\ChatAssistente;
use App\User;

/** Padrão quando o módulo Jana não está carregado: nenhuma conversa existe. */
final class SemChatAssistente implements ChatAssistente
{
    public function enviar(User $user, int $businessId, string $texto, ?string $conversaId): ?array
    {
        return null;
    }

    public function historico(User $user, int $businessId, string $conversaId): ?array
    {
        return null;
    }
}
