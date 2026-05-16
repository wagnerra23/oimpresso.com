<?php

namespace Modules\Crm\Policies;

use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Modules\Crm\Entities\Campaign;

/**
 * Policy de Campaign — gate de autorizacao per-instance.
 *
 * Verifica:
 *   1. Superadmin sempre pode (escape valve)
 *   2. Usuario só atua sobre campaigns do PRÓPRIO business_id (ADR 0093 multi-tenant Tier 0)
 *   3. Operacoes destrutivas (delete) exigem owner (created_by = user.id) OU admin
 *
 * Registrada em CrmServiceProvider::boot() via Gate::policy(Campaign::class, CampaignPolicy::class).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see Modules/Crm/Http/Controllers/CampaignController.php
 */
class CampaignPolicy
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

    public function viewAny(User $user): bool
    {
        return ! empty($user->business_id);
    }

    public function view(User $user, Campaign $campaign): bool
    {
        return $this->sameBusinessAs($user, $campaign);
    }

    public function create(User $user): bool
    {
        return ! empty($user->business_id);
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $this->sameBusinessAs($user, $campaign);
    }

    /**
     * Delete só por owner OU admin do business.
     */
    public function delete(User $user, Campaign $campaign): bool
    {
        if (! $this->sameBusinessAs($user, $campaign)) {
            return false;
        }

        if ((int) $campaign->created_by === (int) $user->id) {
            return true;
        }

        // Admin (não superadmin — já tratado em before) pode deletar de outros do mesmo business.
        return (bool) $user->can('crm.delete_campaign');
    }

    /**
     * Pode disparar notificacao (envio massivo) — operacao sensivel/custosa.
     */
    public function sendNotification(User $user, Campaign $campaign): bool
    {
        return $this->update($user, $campaign);
    }

    protected function sameBusinessAs(User $user, Campaign $campaign): bool
    {
        return (int) $campaign->business_id === (int) $user->business_id;
    }
}
