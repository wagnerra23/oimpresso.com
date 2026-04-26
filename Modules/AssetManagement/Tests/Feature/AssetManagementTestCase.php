<?php

namespace Modules\AssetManagement\Tests\Feature;

use App\Business;
use App\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Base test case do módulo AssetManagement.
 * Modelado conforme FinanceiroTestCase / EssentialsTestCase.
 *
 * NÃO usa RefreshDatabase — UltimatePOS tem 100+ migrations + triggers que
 * não rodam bem em sqlite. Roda contra DB dev real.
 */
abstract class AssetManagementTestCase extends TestCase
{
    protected ?User $admin = null;
    protected ?Business $business = null;

    protected function actAsAdmin(): User
    {
        if ($this->admin) {
            $this->actingAs($this->admin);

            return $this->admin;
        }

        $this->business = Business::first();
        if (! $this->business) {
            $this->markTestSkipped('Nenhum business — precisa seeder UltimatePOS.');
        }

        $this->admin = User::where('business_id', $this->business->id)->first();
        if (! $this->admin) {
            $this->markTestSkipped('Nenhum user no business.');
        }

        $this->ensureAssetPermissions($this->business->id);

        session([
            'user.business_id' => $this->business->id,
            'user.id' => $this->admin->id,
            'business.id' => $this->business->id,
            'business.name' => $this->business->name,
            'is_admin' => true,
        ]);

        $this->actingAs($this->admin);

        return $this->admin;
    }

    protected function ensureAssetPermissions(int $businessId): void
    {
        // Permissões declaradas em memory/requisitos/AssetManagement.md (R-ASSE-002..007).
        $perms = [
            'superadmin',
            'asset.view',
            'asset.create',
            'asset.update',
            'asset.delete',
            'asset.view_all_maintenance',
            'asset.view_own_maintenance',
        ];

        foreach ($perms as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $role = Role::where('name', "Admin#{$businessId}")->first();
        if ($role) {
            foreach ($perms as $name) {
                $p = Permission::where('name', $name)->first();
                if ($p && ! $role->hasPermissionTo($p)) {
                    $role->givePermissionTo($p);
                }
            }
        }
    }
}
