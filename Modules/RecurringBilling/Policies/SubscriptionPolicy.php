<?php

declare(strict_types=1);

namespace Modules\RecurringBilling\Policies;

use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Modules\RecurringBilling\Models\Subscription;

/**
 * Policy — autorização granular pra Subscription.
 *
 * Permissions Spatie (criadas em DataController::user_permissions futuras):
 *   recurringbilling.access           — view list/show
 *   recurringbilling.subscriptions.manage  — create/update/pause/resume
 *   recurringbilling.subscriptions.cancel  — cancelar
 *
 * Por enquanto (v9,75 Ondas 3+): SUPERADMIN-only enquanto canary G1 Martinho
 * não rodou. Pós-canary, troca pra permission granular.
 *
 * Multi-tenant Tier 0 (ADR 0093): toda verificação cross-tenant via
 * $sub->business_id === $user->business_id.
 */
class SubscriptionPolicy
{
    use HandlesAuthorization;

    /**
     * Bypass total pra superadmin.
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
        return $user->can('recurringbilling.access');
    }

    public function view(User $user, Subscription $sub): bool
    {
        return $user->can('recurringbilling.access')
            && $this->sameTenant($user, $sub);
    }

    public function create(User $user): bool
    {
        return $user->can('recurringbilling.subscriptions.manage')
            || $user->can('recurringbilling.access');
    }

    public function update(User $user, Subscription $sub): bool
    {
        return ($user->can('recurringbilling.subscriptions.manage') || $user->can('recurringbilling.access'))
            && $this->sameTenant($user, $sub);
    }

    public function pause(User $user, Subscription $sub): bool
    {
        return $this->update($user, $sub)
            && in_array($sub->status, ['active', 'trialing', 'past_due'], true);
    }

    public function resume(User $user, Subscription $sub): bool
    {
        return $this->update($user, $sub) && $sub->status === 'paused';
    }

    public function cancel(User $user, Subscription $sub): bool
    {
        return ($user->can('recurringbilling.subscriptions.cancel') || $user->can('recurringbilling.access'))
            && $this->sameTenant($user, $sub)
            && $sub->status !== 'canceled';
    }

    public function delete(User $user, Subscription $sub): bool
    {
        return false; // hard delete proibido — usa cancel
    }

    /**
     * Multi-tenant Tier 0 IRREVOGÁVEL — defesa em profundidade alem de HasBusinessScope.
     */
    private function sameTenant(User $user, Subscription $sub): bool
    {
        $userBizId = (int) ($user->business_id ?? 0);

        return $userBizId > 0 && (int) $sub->business_id === $userBizId;
    }
}
