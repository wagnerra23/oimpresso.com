<?php

declare(strict_types=1);

namespace Modules\Officeimpresso\Services;

use App\Services\Support\SupportAccessService;
use App\User;

/**
 * Quem pode ESCREVER na gestão de licenças desktop (Officeimpresso).
 *
 * As ações de licença (bloquear máquina, bloquear/alterar a empresa inteira, excluir) não
 * conferem o `business_id` do equipamento — por desenho: é o painel interno da operadora (WR),
 * que administra as licenças de TODOS os clientes. Só que permissão Spatie é por papel DENTRO
 * de cada business (roles `Nome#biz`): se um papel de uma empresa CLIENTE ganhasse
 * `officeimpresso.licencas.gerenciar`, aquele usuário bloquearia licença de qualquer empresa —
 * escrita cross-tenant (Tier 0, ADR 0093).
 *
 * Regra: a permissão delegável só vale para usuário DA EMPRESA OPERADORA. O `superadmin`
 * segue valendo como sempre. O id da operadora vem da fonte única
 * `config('constants.operator_business_id')`, via `SupportAccessService` — nunca chumbado.
 *
 * Usa o `business_id` do USUÁRIO, não o da sessão: o que se pergunta é a quem ele pertence,
 * não em que empresa está navegando.
 *
 * Medido em produção em 2026-10-01 (só leitura): as 3 permissões estavam em 1 papel, do
 * negócio operador, com 5 usuários — todos do operador; nenhuma concessão direta. A trava
 * não tira acesso de ninguém hoje; fecha a porta para a próxima concessão errada.
 */
final class AcessoOperador
{
    /** Pode exercer `$permissao` delegável de escrita em licença? */
    public static function pode(?User $user, string $permissao): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->can('superadmin')) {
            return true;
        }

        return self::ehOperador($user) && $user->can($permissao);
    }

    /** O usuário pertence à empresa operadora? */
    public static function ehOperador(User $user): bool
    {
        return (int) $user->business_id === app(SupportAccessService::class)->operatorBusinessId();
    }
}
