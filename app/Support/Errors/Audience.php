<?php

declare(strict_types=1);

namespace App\Support\Errors;

/**
 * Audience — quem precisa ver o erro (Fase 1 · E-1).
 *
 * Separa o canal do operador (recuperação, NUNCA trace) do construtor (trace/diagnóstico).
 *
 * @see prototipo-ui/handoffs/erros-fase1-classificacao.md
 */
enum Audience: string
{
    /** Usuário do ERP — vê mensagem de recuperação humana, nunca o trace. */
    case OPERADOR = 'operador';

    /** Time técnico — vê o trace/diagnóstico no canal construtor. */
    case CONSTRUTOR = 'construtor';

    /** Ambos os públicos. */
    case AMBOS = 'ambos';
}
