<?php

namespace Modules\Crm\Policies;

use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Modules\Crm\Entities\Proposal;

/**
 * Policy de Proposal — gate de autorizacao per-instance.
 *
 * Verifica:
 *   1. Superadmin sempre pode (escape valve)
 *   2. Usuario só atua sobre proposals do PRÓPRIO business_id (ADR 0093 multi-tenant Tier 0)
 *   3. Usuario nao-admin so atua sobre proposals que ele MESMO enviou (sent_by = user.id)
 *
 * Registrada em CrmServiceProvider::boot() via Gate::policy(Proposal::class, ProposalPolicy::class).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see Modules/Crm/Http/Controllers/ProposalController.php
 */
class ProposalPolicy
{
    use HandlesAuthorization;

    /**
     * Bypass global — superadmin tem acesso a tudo (Tier 0).
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->can('superadmin')) {
            return true;
        }

        return null;
    }

    /**
     * Pode listar proposals do business.
     */
    public function viewAny(User $user): bool
    {
        return $this->userInSameBusinessAsContextual($user);
    }

    /**
     * Pode ver uma proposal especifica.
     */
    public function view(User $user, Proposal $proposal): bool
    {
        return $this->sameBusinessAs($user, $proposal);
    }

    /**
     * Pode criar proposal.
     */
    public function create(User $user): bool
    {
        return $this->userInSameBusinessAsContextual($user);
    }

    /**
     * Pode atualizar proposal — sempre verifica isolamento + opcionalmente owner.
     */
    public function update(User $user, Proposal $proposal): bool
    {
        if (! $this->sameBusinessAs($user, $proposal)) {
            return false;
        }

        // Nao-admin so atua sobre o que ele mesmo enviou (consistente com ProposalController@index).
        if ((int) $proposal->sent_by !== (int) $user->id) {
            return false;
        }

        return true;
    }

    /**
     * Pode deletar proposal.
     */
    public function delete(User $user, Proposal $proposal): bool
    {
        return $this->update($user, $proposal);
    }

    /**
     * Helper — proposal pertence ao mesmo business_id do usuario (multi-tenant Tier 0).
     */
    protected function sameBusinessAs(User $user, Proposal $proposal): bool
    {
        return (int) $proposal->business_id === (int) $user->business_id;
    }

    /**
     * Helper — usuario esta vinculado a um business valido (contexto sessao).
     */
    protected function userInSameBusinessAsContextual(User $user): bool
    {
        return ! empty($user->business_id);
    }
}
