<?php

declare(strict_types=1);

namespace Modules\Compras\Tests\Feature;

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Smoke test canônico de rotas Modules/Compras (padrão D2.b — ADR 0155/0157).
 *
 * Complementa ComprasIndexTest (que cobre GET /compras + props C1) — aqui:
 *  1. Catraca do throttle:60,1 (audit sênior 2026-05-25 Gap #3 anti-DOS) —
 *     remover o middleware da rota quebra este teste, não passa silencioso.
 *  2. GET /compras/{id}/detalhe de id inexistente → 404 (defense-in-depth
 *     §3.1.4 do audit sênior: não revelar existência cross-business).
 *  3. GET /compras/{id}/detalhe sem permission compras.view → 403.
 *
 * Tier 0 ADR 0093/0101 — fixtures SEMPRE biz=1 (NUNCA biz=4 cliente).
 */
class ComprasSmokeRoutesTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // SQLite skip — Spatie permissions exige schema MySQL UPos completo
        // (mesmo guard do sibling ComprasIndexTest; CI/CT100 roda MySQL).
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->markTestSkipped('SQLite-incompatível: Spatie permissions exige schema MySQL UPos (ADR 0101)');
        }
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('users')) {
            $this->markTestSkipped('Tabelas Spatie/users ausentes — rodar php artisan migrate primeiro');
        }

        $permView = Permission::firstOrCreate(['name' => 'compras.view', 'guard_name' => 'web']);
        // Tier 0 UltimatePOS: roles.business_id NOT NULL com FK (proibicoes.md §FSM).
        $role = Role::firstOrCreate(['name' => 'admin#1', 'guard_name' => 'web', 'business_id' => 1]);
        $role->givePermissionTo([$permView]);

        // user_type/allow_login obrigatórios pro middleware CheckUserLogin
        // (espelha ComprasIndexTest + PurchaseCalculoValorEstoqueE2ETest).
        $this->admin = User::factory()->create([
            'business_id' => 1,
            'user_type' => 'user',
            'allow_login' => 1,
        ]);
        $this->admin->assignRole($role);
    }

    /**
     * Catraca anti-DOS: a rota compras.index DEVE carregar throttle:60,1
     * (audit sênior 2026-05-25 Gap #3). Se alguém remover o middleware do
     * array em Routes/web.php, este teste quebra — decisão consciente, nunca
     * regressão silenciosa.
     */
    public function test_rota_compras_index_mantem_throttle_60_1(): void
    {
        $route = Route::getRoutes()->getByName('compras.index');

        $this->assertNotNull($route, 'Rota compras.index deve existir');
        $this->assertContains(
            'throttle:60,1',
            $route->gatherMiddleware(),
            'Rota compras.index perdeu o throttle:60,1 (defesa anti-DOS audit sênior Gap #3)'
        );
    }

    public function test_show_detalhe_tambem_mantem_throttle(): void
    {
        $route = Route::getRoutes()->getByName('compras.show');

        $this->assertNotNull($route, 'Rota compras.show deve existir');
        $this->assertContains('throttle:60,1', $route->gatherMiddleware());
    }

    /**
     * Defense-in-depth §3.1.4 audit sênior: detalhe de compra inexistente (ou
     * de OUTRO business) responde 404 — nunca 200 vazio, nunca 403 que revele
     * existência.
     */
    public function test_show_detalhe_id_inexistente_retorna_404(): void
    {
        $response = $this->actingAs($this->admin)
            ->withSession(['user' => ['business_id' => 1, 'id' => $this->admin->id]])
            ->getJson('/compras/999999999/detalhe');

        $response->assertStatus(404);
    }

    public function test_show_detalhe_sem_permission_compras_view_retorna_403(): void
    {
        $userSemPerm = User::factory()->create([
            'business_id' => 1,
            'user_type' => 'user',
            'allow_login' => 1,
        ]);

        $response = $this->actingAs($userSemPerm)
            ->withSession(['user' => ['business_id' => 1, 'id' => $userSemPerm->id]])
            ->getJson('/compras/1/detalhe');

        $response->assertStatus(403);
    }
}
