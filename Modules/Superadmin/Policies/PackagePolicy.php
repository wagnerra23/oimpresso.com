<?php

declare(strict_types=1);

namespace Modules\Superadmin\Policies;

use App\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Modules\Superadmin\Entities\Package;

/**
 * Policy — autorização para gerenciar pacotes SaaS.
 *
 * D8 Wave 15 Security — Policy formal pra entity cross-tenant Package.
 * SUPERADMIN: Package é cross-tenant intencional (ADR 0093 §exceções).
 *
 * Único gate: permission `superadmin` via Spatie. Não verifica business_id
 * (Package é repo-wide, não tenant-scoped).
 *
 * Registro: AuthServiceProvider::policies array
 *   Package::class => PackagePolicy::class
 *
 * Uso em Controller:
 *   $this->authorize('create', Package::class);
 *   $this->authorize('update', $package);
 *
 * @see Modules/Superadmin/Entities/Package.php
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
class PackagePolicy
{
    use HandlesAuthorization;

    /**
     * Gate único — todas operações exigem permission `superadmin`.
     * Spatie injeta esse before() automaticamente quando user é superadmin.
     */
    public function before(User $user, string $ability): ?bool
    {
        // SUPERADMIN: bypass cross-tenant intencional.
        if ($user->can('superadmin')) {
            return true;
        }

        return null; // Continua avaliação normal — vai cair em deny por método.
    }

    public function viewAny(User $user): bool
    {
        return $user->can('superadmin');
    }

    public function view(User $user, Package $package): bool
    {
        return $user->can('superadmin');
    }

    public function create(User $user): bool
    {
        return $user->can('superadmin');
    }

    public function update(User $user, Package $package): bool
    {
        return $user->can('superadmin');
    }

    public function delete(User $user, Package $package): bool
    {
        // Hard delete pacote pode quebrar subscriptions ativas.
        // SUPERADMIN: ainda permite, mas requer permission explícita.
        return $user->can('superadmin');
    }

    public function restore(User $user, Package $package): bool
    {
        return $user->can('superadmin');
    }

    public function forceDelete(User $user, Package $package): bool
    {
        return $user->can('superadmin');
    }
}
