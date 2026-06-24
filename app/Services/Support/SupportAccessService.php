<?php

declare(strict_types=1);

namespace App\Services\Support;

use App\Business;
use App\SupportAgent;
use App\User;
use Illuminate\Support\Collection;

/**
 * Modo Suporte (ADR 0305) — resolução de tenants acessíveis ao agente de suporte.
 *
 * É O ÚNICO lugar onde a empresa operadora (biz do config `OPERATOR_BUSINESS_ID`, default 1)
 * é excluída. Regra: `suporte ⊂ (todas as empresas \ operador)`. NUNCA espalhar
 * `if ($business_id == 1)` pelo código — toda decisão de alcance passa por aqui.
 *
 * Distinto de superadmin: o agente de suporte NÃO entra em ADMINISTRATOR_USERNAMES, logo
 * não passa o Gate::before do superadmin — sem escalonamento (App\Providers\AuthServiceProvider).
 *
 * @see memory/decisions/0305-modo-suporte-cross-tenant-exceto-operador.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class SupportAccessService
{
    /** Business da empresa operadora — fonte única (config), nunca chumbado. */
    public function operatorBusinessId(): int
    {
        return (int) config('constants.operator_business_id', 1);
    }

    /** O usuário tem capability de suporte ativa (concessão não revogada)? */
    public function isSupportAgent(User|int $user): bool
    {
        $userId = $user instanceof User ? (int) $user->id : $user;

        return SupportAgent::query()->active()->where('user_id', $userId)->exists();
    }

    /**
     * Empresas que um agente de suporte PODE acessar: todas, EXCETO a operadora.
     *
     * CROSS-TENANT intencional (ADR 0305): opera sobre todos os business — a empresa é o
     * próprio tenant raiz (sem global scope de business). A exclusão da operadora vive só aqui.
     *
     * @return Collection<int, int>
     */
    public function accessibleBusinessIds(): Collection
    {
        return Business::query()
            ->where('id', '!=', $this->operatorBusinessId())
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values();
    }

    /**
     * O agente PODE acessar este business?
     * Verdadeiro só se: é agente de suporte ativo · não é a operadora · a empresa existe.
     */
    public function canAccessBusiness(User|int $user, int $businessId): bool
    {
        if ($businessId === $this->operatorBusinessId()) {
            return false; // operadora nunca é alcançável pelo suporte (protege o operador)
        }

        if (! $this->isSupportAgent($user)) {
            return false;
        }

        // CROSS-TENANT intencional (ADR 0305): existência da empresa-cliente alvo.
        return Business::query()->whereKey($businessId)->exists();
    }

    /**
     * Fase A (ADR 0308) — o agente PODE "Acessar como" (login-as) este usuário-alvo?
     *
     * Trava Tier 0 (ponto único, antes do loginUsingId):
     *   a) a empresa do alvo é acessível ao agente — reusa canAccessBusiness, que já exige
     *      agente-de-suporte ativo E exclui a operadora (biz=1);
     *   b) o alvo NÃO é superadmin/admin-username — sem escalonamento pra god;
     *   c) o alvo está apto a logar (status active + allow_login).
     */
    public function canImpersonate(User|int $agent, User $target): bool
    {
        if (! $this->canAccessBusiness($agent, (int) $target->business_id)) {
            return false;
        }

        if ($this->isSuperadmin($target)) {
            return false; // nunca virar um operador/superadmin (escalonamento)
        }

        return $target->status === 'active' && (bool) $target->allow_login;
    }

    /**
     * Espelha o Gate::before (App\Providers\AuthServiceProvider): é superadmin quem tem o
     * username em `administrator_usernames`. Fonte única — não reimplementar a regra fora daqui.
     */
    public function isSuperadmin(User $user): bool
    {
        $list = (string) config('constants.administrator_usernames');

        return in_array(strtolower((string) $user->username), explode(',', strtolower($list)), true);
    }
}
