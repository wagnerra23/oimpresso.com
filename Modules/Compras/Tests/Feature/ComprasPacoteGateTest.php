<?php

declare(strict_types=1);

namespace Modules\Compras\Tests\Feature;

use App\Facades\Menu;
use App\Services\LegacyMenuAdapter;
use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Modules\Compras\Http\Controllers\DataController;
use Modules\Compras\Http\Middleware\ComprasLiberado;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Gate do pacote `compras_module` nas rotas de /compras (lote 2.1 da auditoria de Compras, D1).
 *
 * Em 2026-11-06, na biz=1, `/compras` respondeu 200 com a Page `Compras/Index` sem o pacote:
 * só o menu (DataController::modifyAdminMenu) conferia. O pacote é a Camada 1 de
 * `memory/reference/feedback-habilitar-modulo-por-business.md` — sem hardcode de business_id.
 *
 * O pacote é fixado por mock do ModuleUtil (mesmo padrão de MetodoCustoNoCadastroDoProdutoTest):
 * no CI a tabela `system` não tem `superadmin_version`, então sem o mock o ModuleUtil real libera
 * qualquer pacote e o teste não provaria nada. Cada caso negativo tem o par positivo com o MESMO
 * usuário e a MESMA requisição — o 403 vem do pacote, não de outra camada.
 *
 * Tier 0 ADR 0358 — tenant fictício 98.
 */
class ComprasPacoteGateTest extends TestCase
{
    use DatabaseTransactions;

    private const BIZ = 98;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->markTestSkipped('SQLite-incompatível: Spatie permissions exige schema MySQL UPos (ADR 0101)');
        }
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('users')) {
            $this->markTestSkipped('Tabelas Spatie/users ausentes — rodar php artisan migrate primeiro');
        }

        $perms = collect(['compras.view', 'purchase.update', 'product.update'])
            ->map(fn (string $p) => Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']));
        $role = Role::firstOrCreate(['name' => 'compras_gate#'.self::BIZ, 'guard_name' => 'web', 'business_id' => self::BIZ]);
        $role->givePermissionTo($perms->all());

        $this->admin = User::factory()->create([
            'business_id' => self::BIZ,
            'user_type' => 'user',
            'allow_login' => 1,
        ]);
        $this->admin->assignRole($role);
    }

    /** Fixa só o pacote Compras; qualquer outra consulta de pacote segue liberada. */
    private function pacoteCompras(bool $tem): void
    {
        $this->partialMock(ModuleUtil::class)
            ->shouldReceive('hasThePermissionInSubscription')
            ->andReturnUsing(fn ($biz, $permissao = null, ...$resto) => $permissao === ComprasLiberado::PACOTE ? $tem : true);
    }

    private function comoAdmin(): static
    {
        return $this->actingAs($this->admin)
            ->withSession(['user' => ['business_id' => self::BIZ, 'id' => $this->admin->id]]);
    }

    public function test_empresa_sem_o_pacote_recebe_403_no_cockpit(): void
    {
        $this->pacoteCompras(false);

        $this->comoAdmin()->get('/compras')->assertStatus(403);
    }

    public function test_empresa_com_o_pacote_recebe_200_e_a_page_compras_index(): void
    {
        $this->pacoteCompras(true);

        $this->comoAdmin()->get('/compras')
            ->assertStatus(200)
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Compras/Index'));
    }

    public function test_sem_o_pacote_as_rotas_de_acao_tambem_recebem_403(): void
    {
        $this->pacoteCompras(false);

        $this->comoAdmin()->getJson('/compras/999999999/detalhe')->assertStatus(403);
        $this->comoAdmin()->postJson('/compras/propostas-preco/999999999/aprovar')->assertStatus(403);
        $this->comoAdmin()->postJson('/compras/propostas-preco/999999999/recusar')->assertStatus(403);
        $this->comoAdmin()->putJson('/compras/insumos/999999999/metodo-custo', ['metodo' => 'media_ponderada'])->assertStatus(403);
    }

    public function test_com_o_pacote_o_detalhe_inexistente_volta_a_ser_404(): void
    {
        // Par do caso acima: com o pacote, a mesma requisição chega ao controller (404 = não achou).
        $this->pacoteCompras(true);

        $this->comoAdmin()->getJson('/compras/999999999/detalhe')->assertStatus(404);
    }

    public function test_com_o_pacote_e_sem_compras_view_o_cockpit_segue_403(): void
    {
        $this->pacoteCompras(true);
        $semPerm = User::factory()->create(['business_id' => self::BIZ, 'user_type' => 'user', 'allow_login' => 1]);

        $this->actingAs($semPerm)
            ->withSession(['user' => ['business_id' => self::BIZ, 'id' => $semPerm->id]])
            ->get('/compras')
            ->assertStatus(403);
    }

    /** Labels da sidebar pelo caminho real (DataController -> LegacyMenuAdapter). */
    private function labelsDoMenu(User $user): array
    {
        $this->actingAs($user);
        session(['user' => ['business_id' => self::BIZ, 'id' => $user->id]]);
        Menu::create('admin-sidebar-menu', fn ($menu) => $menu);
        (new DataController())->modifyAdminMenu();

        return array_column((new LegacyMenuAdapter())->build(), 'label');
    }

    public function test_menu_compras_segue_a_mesma_regra_das_rotas(): void
    {
        $this->pacoteCompras(true);
        $this->assertContains('Compras', $this->labelsDoMenu($this->admin));

        // Mesmo usuário, empresa sem o pacote: some do menu (a rota daria 403).
        $this->pacoteCompras(false);
        $this->assertNotContains('Compras', $this->labelsDoMenu($this->admin));

        // Com o pacote e sem compras.view: some também.
        $this->pacoteCompras(true);
        $semPerm = User::factory()->create(['business_id' => self::BIZ, 'user_type' => 'user', 'allow_login' => 1]);
        $this->assertNotContains('Compras', $this->labelsDoMenu($semPerm));
    }

    public function test_toda_rota_operacional_de_compras_carrega_o_gate_de_pacote(): void
    {
        $operacionais = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => str_starts_with((string) $r->getName(), 'compras.'));

        $this->assertGreaterThanOrEqual(5, $operacionais->count(), 'rotas compras.* não encontradas');

        foreach ($operacionais as $rota) {
            $this->assertContains(ComprasLiberado::class, $rota->gatherMiddleware(), "rota {$rota->getName()} sem o gate de pacote");
        }

        $this->assertContains(ComprasLiberado::class.':compras.view', Route::getRoutes()->getByName('compras.index')->gatherMiddleware());
        $this->assertContains(ComprasLiberado::class.':compras.view', Route::getRoutes()->getByName('compras.show')->gatherMiddleware());
    }
}
