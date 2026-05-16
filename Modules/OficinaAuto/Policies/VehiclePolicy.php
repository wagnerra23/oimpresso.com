<?php

declare(strict_types=1);

namespace Modules\OficinaAuto\Policies;

use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Modules\OficinaAuto\Entities\Vehicle;

/**
 * D8 Security Wave 15 — Policy multi-tenant pra Vehicle.
 *
 * Triple defense-in-depth:
 *  1. Global scope no Model (ADR 0093) — query nunca enxerga cross-tenant
 *  2. Spatie permission check (oficinaauto.vehicle.*)
 *  3. Policy guard explícito session.business_id === $vehicle->business_id
 *     (proteção contra escalation se global scope for desligado por bug)
 *
 * Convenção UltimatePOS: superadmin sempre passa (bypass via before()).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class VehiclePolicy
{
    use HandlesAuthorization;

    /**
     * Bypass superadmin antes de qualquer ability check.
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
        return $user->can('oficinaauto.vehicle.view');
    }

    public function view(User $user, Vehicle $vehicle): bool
    {
        return $user->can('oficinaauto.vehicle.view') && $this->sameTenant($user, $vehicle);
    }

    public function create(User $user): bool
    {
        return $user->can('oficinaauto.vehicle.create');
    }

    public function update(User $user, Vehicle $vehicle): bool
    {
        return $user->can('oficinaauto.vehicle.update') && $this->sameTenant($user, $vehicle);
    }

    public function delete(User $user, Vehicle $vehicle): bool
    {
        return $user->can('oficinaauto.vehicle.delete') && $this->sameTenant($user, $vehicle);
    }

    /**
     * Multi-tenant Tier 0 guard explícito (ADR 0093).
     *
     * Mesmo com global scope, defense-in-depth: re-checa que vehicle.business_id
     * bate com sessão. Bloqueia cenário hipotético de escalation via withoutGlobalScopes.
     */
    private function sameTenant(User $user, Vehicle $vehicle): bool
    {
        $sessionBiz = session('user.business_id') ?? session('business.id') ?? $user->business_id;

        if ($sessionBiz === null) {
            return false;
        }

        return (int) $vehicle->business_id === (int) $sessionBiz;
    }
}
