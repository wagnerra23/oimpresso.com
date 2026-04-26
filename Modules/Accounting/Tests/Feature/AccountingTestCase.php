<?php

namespace Modules\Accounting\Tests\Feature;

use App\Business;
use App\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Base test case do módulo Accounting.
 * Modelado conforme Modules/Financeiro/Tests/Feature/FinanceiroTestCase.php.
 *
 * NÃO usa RefreshDatabase — UltimatePOS tem 100+ migrations + triggers que
 * não rodam bem em sqlite. Roda contra DB dev real (mesmo pattern do
 * Financeiro/Essentials/PontoWr2 test cases).
 */
abstract class AccountingTestCase extends TestCase
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

        $this->ensureAccountingPermissions($this->business->id);

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

    protected function ensureAccountingPermissions(int $businessId): void
    {
        // Permissões do módulo Accounting — superadmin já cobre tudo, mas
        // garantimos que os names existem pra futuros testes role-based.
        $perms = [
            'superadmin',
            'accounting.access',
            'accounting.manage_chart_of_account',
            'accounting.manage_journal_entry',
            'accounting.view_reports',
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
