<?php

namespace Modules\Compras\Tests\Feature;

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

        // SQLite skip — Spatie permissions table + users table com Roles+Permissions
        // exigem schema MySQL UPos completo (ADR 0101 — tests biz=1, mas precisam
        // migrate canônico). CI roda em MySQL; smoke local em SQLite skipa.
        // Pattern catalogado em CockpitMultiTenantTest + outros do projeto.
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->markTestSkipped('SQLite-incompatível: Spatie permissions exige schema MySQL UPos (ADR 0101)');
        }
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('users')) {
            $this->markTestSkipped('Tabelas Spatie/users ausentes — rodar php artisan migrate primeiro');
        }

        // Suffix #1 (biz_id=1) — convenção UltimatePOS spatie/laravel-permission.
        $permView = Permission::firstOrCreate(['name' => 'compras.view', 'guard_name' => 'web']);
        // C1 convergência (ADR compras-purchase-convergencia-c1) — botão "+ Nova
        // compra" + AcoesDropdown Editar/Excluir gateiam por purchase.* (trilho
        // A MWART), não compras.*. Admin#1 recebe todas pras smoke tests.
        $permPurchaseCreate = Permission::firstOrCreate(['name' => 'purchase.create', 'guard_name' => 'web']);
        $permPurchaseUpdate = Permission::firstOrCreate(['name' => 'purchase.update', 'guard_name' => 'web']);
        $permPurchaseDelete = Permission::firstOrCreate(['name' => 'purchase.delete', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'admin#1', 'guard_name' => 'web']);
        $role->givePermissionTo([$permView, $permPurchaseCreate, $permPurchaseUpdate, $permPurchaseDelete]);

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

    /**
     * C1 convergência — prop `permissions.create` vem de `purchase.create` (trilho A
     * MWART), não `compras.create`. Frontend usa o gate pra mostrar/ocultar botão
     * "+ Nova compra" que delega `/purchases/create` via `router.visit`.
     *
     * ADR `compras-purchase-convergencia-c1` review trigger #4 ativa SE gap reportado:
     * user com `compras.view` mas SEM `purchase.create` vê cockpit sem o botão.
     */
    public function test_c1_prop_permissions_create_resolve_via_purchase_create(): void
    {
        $response = $this->actingAs($this->admin)
            ->withSession(['user' => ['business_id' => 1, 'id' => $this->admin->id]])
            ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => '1'])
            ->get('/compras');

        $response->assertStatus(200);

        // Payload Inertia JSON: 'permissions' deve estar presente com create=true
        // (admin#1 tem purchase.create no setUp).
        $payload = $response->json();
        $this->assertArrayHasKey('props', $payload);
        $this->assertArrayHasKey('permissions', $payload['props']);
        $this->assertTrue(
            $payload['props']['permissions']['create'],
            'admin#1 com purchase.create deve ver permissions.create=true'
        );
        $this->assertTrue($payload['props']['permissions']['update']);
        $this->assertTrue($payload['props']['permissions']['delete']);
    }

    /**
     * C1 convergência — user com `compras.view` SEM `purchase.create` vê cockpit
     * mas prop `permissions.create=false` (botão "+ Nova compra" oculto no frontend).
     */
    public function test_c1_user_sem_purchase_create_recebe_permissions_create_false(): void
    {
        $permView = Permission::firstOrCreate(['name' => 'compras.view', 'guard_name' => 'web']);
        $roleViewer = Role::firstOrCreate(['name' => 'viewer-only#1', 'guard_name' => 'web']);
        $roleViewer->givePermissionTo($permView);

        $viewer = User::factory()->create(['business_id' => 1]);
        $viewer->assignRole($roleViewer);

        $response = $this->actingAs($viewer)
            ->withSession(['user' => ['business_id' => 1, 'id' => $viewer->id]])
            ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => '1'])
            ->get('/compras');

        $response->assertStatus(200);

        $payload = $response->json();
        $this->assertFalse(
            $payload['props']['permissions']['create'],
            'viewer sem purchase.create deve ver permissions.create=false (esconde botão)'
        );
    }
}
