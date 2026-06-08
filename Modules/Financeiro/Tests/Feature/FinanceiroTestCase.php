<?php

namespace Modules\Financeiro\Tests\Feature;

use App\Business;
use App\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Base test case do módulo Financeiro.
 * Modelado conforme Modules/PontoWr2/Tests/Feature/PontoTestCase.php.
 *
 * NÃO usa RefreshDatabase — UltimatePOS tem 100+ migrations + triggers que
 * não rodam bem em sqlite. Roda contra DB dev real.
 */
abstract class FinanceiroTestCase extends TestCase
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

        $this->ensureFinanceiroPermissions($this->business->id);

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

    protected function ensureFinanceiroPermissions(int $businessId): void
    {
        $perms = [
            'financeiro.access',
            'financeiro.dashboard.view',
            'financeiro.contas_receber.view',
            'financeiro.contas_pagar.view',
            'financeiro.caixa.view',
            'financeiro.contas_bancarias.manage',
            'financeiro.conciliacao.manage',
            'financeiro.relatorios.view',
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

    protected function inertiaGet(string $url, array $queryParams = [])
    {
        if (! empty($queryParams)) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($queryParams);
        }

        $manifestPath = public_path('build-inertia/manifest.json');
        $version = file_exists($manifestPath) ? md5_file($manifestPath) : '1';

        return $this->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'Accept' => 'text/html',
        ])->get($url);
    }
}
