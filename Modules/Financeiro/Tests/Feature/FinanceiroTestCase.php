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
            // Gates adicionados em 2026-09-23 (PermissaoRotasContratoTest):
            'financeiro.lancamentos.create',
            'financeiro.extrato.view',
            'financeiro.contas_pagar.pagar',
            'financeiro.contas_receber.create',
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

        // O seed do CI (pest-mysql-setup) NÃO cria o papel Admin#<biz> — o bloco acima
        // vira no-op e o usuário autenticado fica sem permissão nenhuma. Até 2026-09-23
        // isso passava despercebido porque conciliação/extrato/contas não tinham gate
        // (#7766 fechou). Dar direto ao usuário do teste torna o "actAsAdmin" verdadeiro
        // nos dois ambientes; onde ele já é Admin, o Gate::before já liberava tudo.
        if ($this->admin && ! $this->admin->hasRole("Admin#{$businessId}")) {
            foreach ($perms as $name) {
                if (! $this->admin->hasPermissionTo($name)) {
                    $this->admin->givePermissionTo($name);
                }
            }
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
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
