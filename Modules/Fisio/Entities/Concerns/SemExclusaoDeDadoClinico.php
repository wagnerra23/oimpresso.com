<?php

declare(strict_types=1);

namespace Modules\Fisio\Entities\Concerns;

use Modules\Fisio\Exceptions\DadoClinicoImutavelException;

/**
 * Dado clínico do Fisio não é apagado pelo Eloquent (Lei 13.787/2018, guarda de 20 anos;
 * ADR 0437 §4). "Excluir paciente" é `blocked_at`; programa sai de uso por `status`.
 *
 * Sem SoftDeletes de propósito: SoftDeletes não basta se algum job limpar a tabela, e abre
 * a porta da exclusão física. Aqui não há caminho de exclusão pelo model.
 */
trait SemExclusaoDeDadoClinico
{
    protected static function bootSemExclusaoDeDadoClinico(): void
    {
        static::deleting(function ($model): void {
            throw DadoClinicoImutavelException::exclusao($model->getTable());
        });
    }
}