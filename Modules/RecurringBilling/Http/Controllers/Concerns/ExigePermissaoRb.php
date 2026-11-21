<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Http\Controllers\Concerns;

/**
 * Gate por permissão nas rotas do RecurringBilling que não passam pela SubscriptionPolicy
 * (lista, faturas, configurações, notas, favoritos, timeline, planos).
 *
 * Auditoria RB d1 (2026-11-19): essas rotas só exigiam login, e 6 das 10 permissões declaradas
 * em DataController::user_permissions nunca eram lidas. Passa o superadmin e quem tiver a
 * permissão pedida; o dono do negócio (Admin#{biz}) passa pelo Gate::before do UltimatePOS.
 */
trait ExigePermissaoRb
{
    protected function exigirPermissao(string $permissao): void
    {
        $user = auth()->user();

        abort_unless(
            $user !== null && ($user->can('superadmin') || $user->can($permissao)),
            403,
            'Sem permissão para esta ação da cobrança recorrente.'
        );
    }
}
