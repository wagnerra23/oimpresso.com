<?php

declare(strict_types=1);

namespace App\Contracts\Atendimento\Nulo;

use App\Contracts\Atendimento\AtendimentoDoApp;
use App\User;

/** Padrão quando o módulo Whatsapp não está carregado: a área `atendimento` não aparece. */
final class SemAtendimentoDoApp implements AtendimentoDoApp
{
    public function areaVisivel(User $user): bool
    {
        return false;
    }
}
