<?php

namespace Modules\Compras\Tests\Feature;

use App\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Wave 1 scaffold — smoke test que prova:
 * 1. Rota /compras existe + responde 200 com user autenticado + permission `compras.view`.
 * 2. Renderiza Inertia component `Compras/Index` (NÃO Blade).
 * 3. User sem permission `compras.view` recebe 403.
 *
 * Tier 0 ADR 0093 — multi-tenant scope coberto por business_id=1 (ADR 0101 NUNCA biz=4).
 * Multi-tenant deep test entra na Wave 3 (MultiTenantIsolationTest).
 */
class ComprasIndexTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Suffix #1 (biz_id=1) — convenção UltimatePOS spatie/laravel-permission.
        $perm = Permission::firstOrCreate(['name' => 'compras.view', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'admin#1', 'guard_name' => 'web']);
        $role->givePermissionTo($perm);

        $this->admin = User::factory()->create(['business_id' => 1]);
        $this->admin->assignRole($role);
    }

    public function test_rota_compras_responde_200_com_permission(): void
    {
        $response = $this->actingAs($this->admin)
            ->withSession(['user' => ['business_id' => 1, 'id' => $this->admin->id]])
            ->get('/compras');

        $response->assertStatus(200);
    }

    public function test_index_renderiza_inertia_component_compras_index(): void
    {
        $response = $this->actingAs($this->admin)
            ->withSession(['user' => ['business_id' => 1, 'id' => $this->admin->id]])
            ->get('/compras');

        // Inertia retorna HTML com data-page; component fica no payload JSON.
        $response->assertStatus(200);
        $response->assertSee('Compras/Index', false);
    }

    public function test_sem_permission_compras_view_retorna_403(): void
    {
        $userSemPerm = User::factory()->create(['business_id' => 1]);

        $response = $this->actingAs($userSemPerm)
            ->withSession(['user' => ['business_id' => 1, 'id' => $userSemPerm->id]])
            ->get('/compras');

        $response->assertStatus(403);
    }
}
