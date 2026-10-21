<?php

declare(strict_types=1);

namespace Modules\Fisio\Exceptions;

use LogicException;

/**
 * Tentativa de alterar ou apagar dado clínico que a lei manda guardar como está.
 *
 * - Registro de prontuário assinado: Res. COFFITO 414/2012 (correção só por adendo).
 * - Guarda de 20 anos: Lei 13.787/2018 ("excluir paciente" = bloquear).
 * - Ledger do jogo: append-only (ADR 0437 §6).
 *
 * O banco recusa o mesmo pelo trigger (MySQL); esta exceção é a camada do model, para o
 * erro aparecer antes do SQL e também em driver sem trigger.
 */
final class DadoClinicoImutavelException extends LogicException
{
    public static function exclusao(string $tabela): self
    {
        return new self("{$tabela}: dado clínico não é apagado (guarda de 20 anos, Lei 13.787/2018). Use o bloqueio.");
    }

    public static function alteracao(string $tabela): self
    {
        return new self("{$tabela} é append-only: correção é lançamento novo (adendo), nunca alteração.");
    }
}