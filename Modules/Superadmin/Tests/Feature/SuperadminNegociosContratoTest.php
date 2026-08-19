<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

// @covers-us US-SUPER-001

/**
 * Contrato da tela `/superadmin/business` (lista de negócios) — onda SA-O2.
 *
 * Os UCs vêm do contrato, não do código:
 *   Modules/Superadmin/Resources/js/Pages/superadmin/Negocios/Index.casos.md
 *   memory/requisitos/Superadmin/RUNBOOK-negocios.md
 *
 * ⚠️ SKIP em SQLite: a tela precisa do schema UltimatePOS real. Em sqlite estes casos PULAM e
 * o arquivo sai exit 0 sem provar nada — leia *assertions*, não "0 failed" (LC-13).
 *
 * @see Modules/Superadmin/Http/Controllers/BusinessController::index()
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md §exceções Superadmin
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: /superadmin/business requer schema MySQL UltimatePOS.');
    }
    if (! Schema::hasTable('users') || ! Schema::hasTable('business') || ! Schema::hasTable('permissions')) {
        $this->markTestSkipped('Schema UltimatePOS ausente — rode migrations primeiro.');
    }

    // O gate de ROTA é o middleware `Superadmin`, que compara o USERNAME com
    // `config('constants.administrator_usernames')` — não é Spatie nem Bouncer.
    config(['constants.administrator_usernames' => 'neg_superadmin_test']);
});

/** Tenant do teste. NUNCA biz=4 (ROTA LIVRE, produção) — ADR 0358. */
const BIZ_NEG = 98;

function negSuperadmin(): User
{
    Business::firstOrCreate(['id' => BIZ_NEG], ['name' => 'Tenant fictício negócios', 'currency_id' => 1]);

    $user = User::firstOrCreate(
        ['username' => 'neg_superadmin_test'],
        [
            'email' => 'neg_superadmin@test.local',
            'password' => bcrypt('secret'),
            'business_id' => BIZ_NEG,
            'first_name' => 'Neg',
            'last_name' => 'Superadmin',
        ]
    );

    $permission = Permission::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);

    if (! $user->hasPermissionTo('superadmin')) {
        $user->givePermissionTo($permission);
    }

    return $user;
}

function negAdminDeNegocio(): User
{
    Business::firstOrCreate(['id' => BIZ_NEG], ['name' => 'Tenant fictício negócios', 'currency_id' => 1]);

    $user = User::firstOrCreate(
        ['username' => 'neg_admin_test'],
        [
            'email' => 'neg_admin@test.local',
            'password' => bcrypt('secret'),
            'business_id' => BIZ_NEG,
            'first_name' => 'Admin',
            'last_name' => 'Negocio',
        ]
    );

    $user->syncRoles([]);
    $user->syncPermissions([]);

    return $user;
}

// ── UC-SANEG-01 · responde Inertia, não DataTables ──────────────────────────

it('UC-SANEG-01 · /superadmin/business responde Inertia com o componente da tela nova', function () {
    $this->actingAs(negSuperadmin())
        ->get('/superadmin/business')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('superadmin/Negocios/Index')
            ->has('filtros')
        );
});

// ── UC-SANEG-02 · 1 negócio = 1 linha, e o total não mente ──────────────────

it('UC-SANEG-02 · o total da lista bate com a contagem real de negócios', function () {
    // O payload é privado; o contrato observável é a prop. Pede a página deferred.
    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());

    $resposta = $this->actingAs(negSuperadmin())->get('/superadmin/business', [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) $versao,
        'X-Inertia-Partial-Data' => 'negocios',
        'X-Inertia-Partial-Component' => 'superadmin/Negocios/Index',
    ]);

    $resposta->assertOk();

    $total = $resposta->json('props.negocios.total');
    $linhas = $resposta->json('props.negocios.linhas');

    // Sem filtro, o total tem que ser a contagem CRUA de negócios. Se alguém reintroduzir um
    // join 1-para-N sem subquery, o número infla e este caso cai.
    expect($total)->toBe(DB::table('business')->count());

    // E a página não repete negócio — a assinatura entra pela mais recente, não por todas.
    $ids = collect($linhas)->pluck('id');
    expect($ids->unique()->count())->toBe($ids->count());
});

// ── UC-SANEG-03 · admin barrado ENQUANTO superadmin passa ───────────────────

it('UC-SANEG-03 · admin de negócio é barrado enquanto o superadmin passa', function () {
    $barrado = $this->actingAs(negAdminDeNegocio())->get('/superadmin/business');
    expect($barrado->getStatusCode())->toBeIn([302, 403]);

    $this->actingAs(negSuperadmin())->get('/superadmin/business')->assertOk();
});

// ── UC-SANEG-04 · cross-tenant intencional ──────────────────────────────────

it('UC-SANEG-04 · a lista cobre negócio de todos os business, não só o do usuário', function () {
    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());

    $resposta = $this->actingAs(negSuperadmin())->get('/superadmin/business', [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) $versao,
        'X-Inertia-Partial-Data' => 'negocios',
        'X-Inertia-Partial-Component' => 'superadmin/Negocios/Index',
    ]);

    $resposta->assertOk();

    // Cross-tenant é INTENCIONAL aqui (ADR 0093 §exceções). Este caso impede que alguém
    // "conserte" a tela aplicando escopo de tenant: com escopo, o total cairia pra 1.
    expect($resposta->json('props.negocios.total'))->toBe(DB::table('business')->count())
        ->and(DB::table('business')->count())->toBeGreaterThanOrEqual(2);
});

// ── UC-SANEG-05 · filtro fora da lista não chega na query ───────────────────

it('UC-SANEG-05 · valor de filtro fora da lista é descartado', function () {
    $this->actingAs(negSuperadmin())
        ->get('/superadmin/business?assinatura=DROP&status=qualquer')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filtros.assinatura', null)
            ->where('filtros.status', null)
        );
});

// ── UC-SANEG-06 · busca por número só com dígito puro ───────────────────────

it('UC-SANEG-06 · a busca preserva o termo digitado nos filtros', function () {
    $this->actingAs(negSuperadmin())
        ->get('/superadmin/business?q=12+anos')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('filtros.q', '12 anos'));

    // Dígito puro é o único caso em que o id entra na comparação (ctype_digit no controller).
    $this->actingAs(negSuperadmin())
        ->get('/superadmin/business?q=12')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('filtros.q', '12'));
});
