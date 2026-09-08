<?php

declare(strict_types=1);

use App\Business;
use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class);

/**
 * Gate de permissao da area de MANUTENCOES — `GET asset/asset-maintenance`.
 *
 * O QUE DEFENDE (dois defeitos no mesmo `if`, corrigidos em 2026-09-08). Os 6 metodos
 * guardados de AssetMaitenanceController usavam:
 *
 *     if (! ((can('asset.view_all_maintenance') && can('asset.view_own_maintenance'))
 *            || hasThePermissionInSubscription($business_id, 'assetmanagement_module')))
 *
 * (a) o `&&` exigia AS DUAS permissoes, que DataController::user_permissions() declara
 *     `is_radio` com o mesmo `radio_input_name` = `view_maintenance` (:51 e :58) — ou seja,
 *     mutuamente exclusivas na UI de papeis. O gate era insatisfazivel para nao-admin, e
 *     barrava justamente o perfil que o filtro de escopo do index() (:73, `(!view_all) &&
 *     view_own`) foi escrito para atender;
 * (b) o `|| subscription` anulava o gate: como o 2o operando vale para todo usuario do
 *     business que assina o modulo, o `if` colapsava em "o modulo esta assinado" — e e por
 *     isso que (a) nunca apareceu em producao. Consertar so o `&&` seria inerte em runtime.
 *
 * O CENARIO QUE PROVA (a) e o `CN view_own`: antes do conserto ele passava (200) pelo motivo
 * ERRADO — pelo `|| subscription`, nao pela permissao. Depois do conserto ele passa pela
 * permissao, e o MORDE (usuario sem nenhuma das duas) e quem toma 403. Por isso os cenarios
 * mockam `hasThePermissionInSubscription`: sem neutralizar a assinatura, o teste mediria o
 * gate seguinte e nao este.
 *
 * PERMISSOES NAO INVENTADAS: as duas ja estao registradas em
 * Modules/AssetManagement/Http/Controllers/DataController.php (:51 e :58), que popula
 * /roles/{id}/edit. Nenhuma permissao de ESCRITA foi criada — o modulo nao declara uma, e
 * inventa-la seria decisao de produto.
 *
 * AS DUAS Permission SAO CRIADAS NOS TRES CENARIOS, de proposito. Num banco limpo elas NAO
 * existem: o seeder do modulo e vazio e as permissoes nascem sob demanda em
 * RoleController::__createPermissionIfNotExists(), so quando alguem salva um Role. Se
 * faltassem aqui, o 403 do MORDE viria de AUSENCIA da permissao e provaria a coisa errada —
 * verde por acidente, e nao-deterministico (o Pest roda em ordem aleatoria). Foi exatamente
 * o defeito medido no PR #7008 com `asset.view`.
 *
 * O DONO DO NEGOCIO NAO E AFETADO: o `Gate::before` de App/Providers/AuthServiceProvider
 * (:34-46) devolve `true` para quem tem o role `Admin#{business_id}` em qualquer ability
 * fora de backup/superadmin/manage_modules. Quem perde acesso e o usuario NAO-admin sem
 * nenhuma das duas permissoes — que e o defeito sendo fechado, nao regressao.
 *
 * TENANT: 98 (ficticio, ADR 0358) via `find`-ou-`forceCreate`. Limpeza em `try/finally`
 * DENTRO do closure, nao em `afterEach` encadeado: medido no CT 100 em 2026-09-08, o
 * `afterEach` por teste nao executou e deixou fixtures orfaos numa base que nao se limpa
 * entre runs.
 *
 * @see Modules/AssetManagement/Http/Controllers/AssetMaitenanceController::index()
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md
 */
function manutencaoAuthGateFixture(?string $permissao): array
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped('SQLite-incompativel: exige schema MySQL UltimatePOS (FKs business/users/roles).');
    }
    foreach (['business', 'users', 'roles', 'permissions'] as $tabela) {
        if (! Schema::hasTable($tabela)) {
            test()->markTestSkipped("Schema UltimatePOS ausente (tabela {$tabela}) — esta suite roda em MySQL real semeado.");
        }
    }

    $biz = Business::find(98) ?: Business::forceCreate([
        'id' => 98,
        'name' => 'Tenant ficticio 98 (ADR 0358)',
        'currency_id' => 1,
        'start_date' => now()->toDateString(),
        'default_profit_percent' => 0,
        'owner_id' => 1,
        'stop_selling_before' => 0,
        'weighing_scale_setting' => '',
        'certificado' => '',
        'officeimpresso_numerodemaquinas' => 0,
    ]);

    // user_type='user' + allow_login=1: sem eles o pipeline UltimatePOS aborta antes de
    // chegar no controller, e o teste passaria pelo motivo errado.
    $user = User::factory()->create([
        'business_id' => $biz->id,
        'username' => 'manut_auth_gate_'.uniqid(),
        'user_type' => 'user',
        'allow_login' => 1,
    ]);

    // As DUAS existem sempre; o que varia entre cenarios e o usuario TER uma delas.
    $todas = [];
    foreach (['asset.view_all_maintenance', 'asset.view_own_maintenance'] as $nome) {
        $todas[$nome] = Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web']);
    }

    if ($permissao !== null) {
        // `roles.business_id` e NOT NULL + FK pra business; o sufixo `#{biz}` e a convencao
        // da casa pra role por tenant (proibicoes.md §FSM).
        $role = Role::firstOrCreate(
            ['name' => 'manut-auth-gate#'.$biz->id, 'guard_name' => 'web'],
            ['business_id' => $biz->id]
        );
        // syncPermissions, nao givePermissionTo: a Role e reaproveitada entre cenarios
        // (firstOrCreate), e acumular as duas permissoes faria o cenario `view_own` medir
        // um usuario que tambem tem `view_all` — exatamente a confusao que este teste apura.
        $role->syncPermissions([$todas[$permissao]]);
        $user->assignRole($role);
    }

    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return [$biz, $user];
}

function manutencaoAuthGateChamar(Business $biz, User $user)
{
    return test()
        ->actingAs($user)
        ->withSession([
            'user.business_id' => $biz->id,
            'user' => ['business_id' => $biz->id, 'id' => $user->id],
        ])
        ->get('/asset/asset-maintenance');
}

function manutencaoAuthGateNeutralizaAssinatura(): void
{
    $moduleUtil = Mockery::mock(ModuleUtil::class)->makePartial();
    $moduleUtil->shouldReceive('hasThePermissionInSubscription')->andReturn(true);
    app()->instance(ModuleUtil::class, $moduleUtil);
}

function manutencaoAuthGateLimpar(): void
{
    app()->forgetInstance(ModuleUtil::class);

    // `withTrashed`: App/User usa SoftDeletes — sem isto um fixture ja soft-deletado
    // escaparia da varredura e ficaria na base do CT 100, que nao se limpa entre runs.
    $users = User::withTrashed()->where('username', 'like', 'manut_auth_gate_%')->get();
    foreach ($users as $u) {
        DB::table('model_has_roles')->where('model_id', $u->id)->delete();
        $u->forceDelete();
    }
    Role::where('name', 'like', 'manut-auth-gate#%')->delete();
}

it('MORDE: usuario sem nenhuma das duas permissoes recebe 403 em GET asset/asset-maintenance', function () {
    [$biz, $user] = manutencaoAuthGateFixture(permissao: null);

    try {
        // Canario: o 403 tem de vir de "o usuario nao TEM a permissao", nunca de "a permissao
        // nao existe no catalogo". Sem estas linhas, remover o firstOrCreate do fixture
        // deixaria o teste verde pelo motivo errado e ninguem veria.
        expect(Permission::where('name', 'asset.view_all_maintenance')->where('guard_name', 'web')->exists())
            ->toBeTrue('asset.view_all_maintenance precisa EXISTIR no catalogo para este cenario significar algo');
        expect(Permission::where('name', 'asset.view_own_maintenance')->where('guard_name', 'web')->exists())
            ->toBeTrue('asset.view_own_maintenance precisa EXISTIR no catalogo para este cenario significar algo');
        expect($user->can('asset.view_all_maintenance'))->toBeFalse();
        expect($user->can('asset.view_own_maintenance'))->toBeFalse();

        // A assinatura e neutralizada A FAVOR do usuario: assim o 403 so pode vir da
        // permissao. E este o defeito (b) — antes do conserto, a assinatura verdadeira
        // ANULAVA o gate e esta chamada devolvia 200.
        manutencaoAuthGateNeutralizaAssinatura();

        manutencaoAuthGateChamar($biz, $user)->assertStatus(403);
    } finally {
        manutencaoAuthGateLimpar();
    }
});

it('CN view_own: usuario com APENAS asset.view_own_maintenance nao e barrado (o && barrava este perfil)', function () {
    [$biz, $user] = manutencaoAuthGateFixture(permissao: 'asset.view_own_maintenance');

    try {
        // Canario do proprio cenario: e o perfil "vejo so as minhas" — tem view_own e NAO tem
        // view_all. E exatamente quem o `&&` barrava, e para quem o filtro do index() (:73)
        // foi escrito.
        expect($user->can('asset.view_own_maintenance'))->toBeTrue();
        expect($user->can('asset.view_all_maintenance'))->toBeFalse();

        manutencaoAuthGateNeutralizaAssinatura();

        expect(manutencaoAuthGateChamar($biz, $user)->status())->not->toBe(403);
    } finally {
        manutencaoAuthGateLimpar();
    }
});

it('CN view_all: usuario com APENAS asset.view_all_maintenance nao e barrado', function () {
    [$biz, $user] = manutencaoAuthGateFixture(permissao: 'asset.view_all_maintenance');

    try {
        expect($user->can('asset.view_all_maintenance'))->toBeTrue();
        expect($user->can('asset.view_own_maintenance'))->toBeFalse();

        manutencaoAuthGateNeutralizaAssinatura();

        expect(manutencaoAuthGateChamar($biz, $user)->status())->not->toBe(403);
    } finally {
        manutencaoAuthGateLimpar();
    }
});
