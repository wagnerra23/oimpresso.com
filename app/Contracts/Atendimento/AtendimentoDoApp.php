<?php

declare(strict_types=1);

namespace App\Contracts\Atendimento;

use App\User;

/**
 * Contrato do núcleo para o Início do app das lojas saber se mostra a área `atendimento` (tela 41).
 *
 * O núcleo (app/) depende deste contrato; o módulo Whatsapp o implementa
 * (Modules\Whatsapp\Http\Controllers\Api\AppAtendimentoController) e registra no próprio
 * ServiceProvider. Sem o módulo, vale App\Contracts\Atendimento\Nulo\SemAtendimentoDoApp
 * (área escondida). A seta de dependência fica módulo → núcleo (DependencyDirectionTest).
 */
interface AtendimentoDoApp
{
    /** Acesso ao atendimento (permissão + módulo no plano) e um canal WhatsApp ligado e visível. */
    public function areaVisivel(User $user): bool;
}
