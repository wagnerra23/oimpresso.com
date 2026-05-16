<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Policies;

use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Modules\OficinaAuto\Entities\ServiceOrder;

/**
 * D8 Security Wave 15 — Policy multi-tenant pra ServiceOrder.
 *
 * Mesmo padrão de VehiclePolicy:
 *  1. Global scope no Model (ADR 0093)
 *  2. Spatie permission check (oficinaauto.service_order.*)
 *  3. Policy guard explícito sameTenant (defense-in-depth)
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class ServiceOrderPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->can('superadmin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('oficinaauto.service_order.view');
    }

    public function view(User $user, ServiceOrder $order): bool
    {
        return $user->can('oficinaauto.service_order.view') && $this->sameTenant($user, $order);
    }

    public function create(User $user): bool
    {
        return $user->can('oficinaauto.service_order.create');
    }

    public function update(User $user, ServiceOrder $order): bool
    {
        return $user->can('oficinaauto.service_order.update') && $this->sameTenant($user, $order);
    }

    public function delete(User $user, ServiceOrder $order): bool
    {
        return $user->can('oficinaauto.service_order.delete') && $this->sameTenant($user, $order);
    }

    /**
     * Multi-tenant Tier 0 guard explícito (ADR 0093).
     */
    private function sameTenant(User $user, ServiceOrder $order): bool
    {
        $sessionBiz = session('user.business_id') ?? session('business.id') ?? $user->business_id;

        if ($sessionBiz === null) {
            return false;
        }

        return (int) $order->business_id === (int) $sessionBiz;
    }
}
