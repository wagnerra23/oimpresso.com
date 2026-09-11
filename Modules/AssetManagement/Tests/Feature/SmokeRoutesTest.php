<?php

declare(strict_types=1);

use App\Business;
use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\AssetManagement\Entities\Asset;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class);

/**
 * Smoke test das rotas principais do Modules/AssetManagement.
 *
 * Valida que as rotas resource declaradas em Routes/web.php foram registradas
 * pelo nWidart/laravel-modules e estão acessíveis via Route::has().
 *
 * Refs: Routes/web.php (Route::resource assets/allocation/revocation/settings/asset-maintenance)
 * Padrão: skill `criar-modulo` + Modules/Auditoria/Tests/Feature/AuditoriaModuleTest.php
 */

it('cenario 1: rota nomeada assets.index existe', function () {
    expect(\Route::has('assets.index'))->toBeTrue('Rota assets.index deveria existir per Routes/web.php');
});

it('cenario 2: rota nomeada assets.create existe', function () {
    expect(\Route::has('assets.create'))->toBeTrue('Rota assets.create deveria existir');
});

it('cenario 3: rota nomeada allocation.index existe', function () {
    expect(\Route::has('allocation.index'))->toBeTrue('Rota allocation.index deveria existir');
});

it('cenario 4: rota nomeada asset-maintenance.index existe', function () {
    expect(\Route::has('asset-maintenance.index'))->toBeTrue('Rota asset-maintenance.index deveria existir');
});

/**
 * Gate de permissão do ÍNDICE de Bens — `GET asset/assets` exige `asset.view`.
 *
 * O QUE DEFENDE: até 2026-09-08 o `AssetController::index()` checava só a assinatura
 * do módulo (`can('superadmin') || hasThePermissionInSubscription(..., 'assetmanagement_module')`),
 * então QUALQUER usuário da empresa com o módulo assinado listava o patrimônio inteiro.
 * As checagens de `asset.view_all_maintenance` (:145), `asset.update` (:154) e
 * `asset.delete` (:163) que já existiam no método só desenham botão de linha — botão
 * escondido não é autorização. Os métodos `create()`, `edit()` e `destroy()` já traziam
 * o padrão correto (permissão de tela ANTES do gate de assinatura); o `index()` era o
 * único método de leitura de dados do controller sem ele.
 *
 * PERMISSÃO NÃO INVENTADA: `asset.view` já existia registrada em
 * `Modules/AssetManagement/Http/Controllers/DataController.php:31`
 * (`user_permissions()`, que popula a lista de `/roles/{id}/edit`) e já era a permissão
 * que o próprio módulo usava para decidir se mostra o link "Ativos"
 * (`Resources/views/layouts/nav.blade.php:21`, `@can('asset.view')`). A guarda faz a
 * ROTA honrar o que a NAV já declarava.
 *
 * CONTROLE POSITIVO (CN): sem ele, o 403 do primeiro cenário seria indistinguível de um
 * 403 vindo do pipeline (`throttle`/`authh`/`auth`/`SetSessionData`/`AdminSidebarMenu`) —
 * um teste verde que não prova a guarda. O CN asserta `status !== 403` (e não `200`) de
 * propósito: ele responde "o 403 veio da guarda?", NÃO "a listagem renderiza" — o corpo
 * do `index()` fora do ramo ajax é comportamento pré-existente que este PR não toca.
 *
 * TENANT: 98 (fictício, ADR 0358) resolvido por `find`-ou-`forceCreate`. Fixar o id sem
 * o fallback assume um seed que o CT 100 pode não ter (ele já causou FK violation em
 * `ClienteVeiculosModuleGateTest`); os fixtures são removidos no `afterEach` porque a base
 * do CT 100 é clone de prod e NÃO se limpa entre runs.
 *
 * @see Modules/AssetManagement/Http/Controllers/AssetController::index()
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md
 */
function assetViewGateFixture(bool $comPermissao): array
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped('SQLite-incompatível: exige schema MySQL UltimatePOS (FKs business/users/roles).');
    }
    foreach (['business', 'users', 'roles', 'permissions'] as $tabela) {
        if (! Schema::hasTable($tabela)) {
            test()->markTestSkipped("Schema UltimatePOS ausente (tabela {$tabela}) — esta suíte roda em MySQL real semeado.");
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

    // user_type='user' + allow_login=1: sem eles o pipeline UltimatePOS aborta antes
    // de chegar no controller, e o teste passaria pelo motivo errado.
    $user = User::factory()->create([
        'business_id' => $biz->id,
        'username' => 'asset_view_gate_'.uniqid(),
        'user_type' => 'user',
        'allow_login' => 1,
    ]);

    // A Permission é criada nos DOIS cenários, de propósito. Num banco limpo `asset.view`
    // NÃO existe na tabela `permissions`: o seeder do módulo é vazio
    // (`AssetManagementDatabaseSeeder`) e as permissões nascem sob demanda em
    // `RoleController::__createPermissionIfNotExists()`, só quando alguém salva um Role.
    // Se ela não existisse aqui, o `can()` do cenário MORDE devolveria false por AUSÊNCIA
    // da permissão, e o 403 provaria a coisa errada — verde por acidente, que some no dia
    // em que alguém cadastrar um Role. O que deve variar entre os cenários é o usuário
    // TER ou não a permissão, nunca a permissão existir ou não.
    //
    // NÃO É HIPOTÉTICO — aconteceu aqui. Como só o cenário CN criava a permissão e o Pest
    // roda em ordem aleatória, a 1ª execução desta suíte (seed 1788872533) rodou o MORDE
    // ANTES do CN, com a tabela `permissions` ainda sem `asset.view`: aquele verde passou
    // pelo motivo errado. Medido depois: `asset.view id=194 created_at=2026-09-08 10:02:15`,
    // criada pela própria suíte — e `asset.create` sequer existe no catálogo do ambiente,
    // apesar de a guarda de `create()` estar em produção.
    $perm = Permission::firstOrCreate(['name' => 'asset.view', 'guard_name' => 'web']);

    if ($comPermissao) {
        // `roles.business_id` é NOT NULL + FK pra business e o sufixo `#{biz}` é a
        // convenção da casa pra role por tenant (proibicoes.md §FSM).
        $role = Role::firstOrCreate(
            ['name' => 'asset-view-gate#'.$biz->id, 'guard_name' => 'web'],
            ['business_id' => $biz->id]
        );
        $role->givePermissionTo($perm);
        $user->assignRole($role);
    }

    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return [$biz, $user];
}

function assetViewGateChamar(Business $biz, User $user)
{
    return test()
        ->actingAs($user)
        ->withSession([
            'user.business_id' => $biz->id,
            'user' => ['business_id' => $biz->id, 'id' => $user->id],
        ])
        ->get('/asset/assets');
}

function assetViewGateLimpar(): void
{
    app()->forgetInstance(ModuleUtil::class);

    // `withTrashed`: App\User usa SoftDeletes — sem isto um fixture já soft-deletado
    // escaparia da varredura e ficaria na base. Esta é a forma provada no CT 100
    // (apagou os 8 órfãos de 2026-09-08 sem uma exceção).
    $users = User::withTrashed()->where('username', 'like', 'asset_view_gate_%')->get();
    foreach ($users as $u) {
        DB::table('model_has_roles')->where('model_id', $u->id)->delete();
        $u->forceDelete();
    }
    Role::where('name', 'like', 'asset-view-gate#%')->delete();
}

// A limpeza vai em `try/finally` DENTRO do closure, não em `->afterEach()` encadeado:
// medido no CT 100 em 2026-09-08, o `->afterEach()` por teste não executou (4 rodadas
// deixaram 8 usuários e 1 role órfãos na base, que é clone de prod e NÃO se limpa entre
// runs). A mesma função de limpeza, chamada à mão, apagou os 8 sem erro — ou seja, o
// defeito era o gancho, não a lógica. `finally` também limpa quando o assert falha.
it('MORDE: usuário SEM asset.view recebe 403 em GET asset/assets', function () {
    [$biz, $user] = assetViewGateFixture(comPermissao: false);

    try {
        // Canário do próprio cenário: o 403 tem de vir de "o usuário não TEM a permissão",
        // nunca de "a permissão não existe no catálogo". Sem estas duas linhas, remover o
        // `firstOrCreate` do fixture deixaria o teste verde pelo motivo errado e ninguém veria.
        expect(Permission::where('name', 'asset.view')->where('guard_name', 'web')->exists())
            ->toBeTrue('asset.view precisa EXISTIR no catálogo para este cenário significar algo');
        expect($user->can('asset.view'))->toBeFalse();

        assetViewGateChamar($biz, $user)->assertStatus(403);
    } finally {
        assetViewGateLimpar();
    }
});

it('CN: usuário COM asset.view NÃO é barrado pela guarda (o 403 acima veio dela, não do pipeline)', function () {
    [$biz, $user] = assetViewGateFixture(comPermissao: true);

    try {
        // A assinatura do módulo é o gate SEGUINTE do método; sem neutralizá-la, quem tem
        // `asset.view` tomaria 403 na linha de baixo e o controle positivo mediria a coisa errada.
        $moduleUtil = Mockery::mock(ModuleUtil::class)->makePartial();
        $moduleUtil->shouldReceive('hasThePermissionInSubscription')->andReturn(true);
        app()->instance(ModuleUtil::class, $moduleUtil);

        expect(assetViewGateChamar($biz, $user)->status())->not->toBe(403);
    } finally {
        assetViewGateLimpar();
    }
});

/**
 * Isolamento multi-tenant da lista de garantias do `dashboard()` — ADR 0093, Tier 0.
 *
 * O DEFEITO: em `AssetController::dashboard()`, o `->orWhereNull('aw.end_date')` do
 * `$expiring_assets` ficava FORA do `->where(function ($q) {...})`. Como AND liga mais forte
 * que OR, o SQL virava
 *     (assets.business_id = X AND datas…) OR (aw.end_date IS NULL)
 * e o segundo ramo do OR não carregava filtro de tenant nenhum. Como o `select` traz
 * `assets.name` e `asset_code`, TODO bem sem garantia registrada — de QUALQUER empresa —
 * era renderizado no dashboard de todas. Não dependia de dado corrompido: vazava sempre.
 *
 * O CASO monta exatamente o vetor: o adversário (biz=99) tem um bem SEM garantia; o dono
 * (biz=98) abre o próprio dashboard. O bem do adversário não pode aparecer na lista.
 *
 * POR QUE `viewData` E NÃO O HTML: a asserção mira a QUERY (a variável que o controller
 * passa à view), não o template. Um template que deixasse de renderizar a lista faria o
 * teste passar por acidente se ele olhasse só o HTML.
 *
 * ADMIN: o bloco vulnerável só executa sob `if ($is_admin)`, e `Util::is_admin()` resolve
 * por `hasRole('Admin#'.$business_id)` — daí a role no fixture.
 *
 * `created_by` é preenchido de propósito: a FK `assets_created_by_foreign` é justamente o
 * que derruba as suítes vizinhas do módulo (7 falhas pré-existentes no CT 100).
 *
 * @see Modules/AssetManagement/Http/Controllers/AssetController::dashboard()
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
function assetDashboardTenantFixture(): array
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped('SQLite-incompatível: exige schema MySQL UltimatePOS.');
    }
    foreach (['business', 'users', 'roles', 'assets', 'asset_warranties'] as $tabela) {
        if (! Schema::hasTable($tabela)) {
            test()->markTestSkipped("Schema ausente (tabela {$tabela}) — suíte roda em MySQL real semeado.");
        }
    }

    $dono = test()->seededTenant();                    // 98 — tenant canônico (ADR 0358)
    $adversario = test()->seededSupportClientTenant(); // 99 — outra empresa

    $admin = User::factory()->create([
        'business_id' => $dono->id,
        'username' => 'asset_dash_tnt_'.uniqid(),
        'user_type' => 'user',
        'allow_login' => 1,
    ]);
    $roleAdmin = Role::firstOrCreate(
        ['name' => 'Admin#'.$dono->id, 'guard_name' => 'web'],
        ['business_id' => $dono->id]
    );
    $admin->assignRole($roleAdmin);

    // Os bens NÃO nascem aqui — o teste os cria em ETAPAS, porque a asserção virou um
    // delta (ver o `it` abaixo). Devolver o adversário junto é o que permite isso.
    return [$dono, $adversario, $admin];
}

/**
 * Bem SEM nenhuma linha em `asset_warranties` — é o que cai no balde `sem` do painel
 * (`aw.end_date IS NULL` no leftjoin de `painelGarantia`).
 *
 * `created_by` é preenchido de propósito: a FK `assets_created_by_foreign` é justamente o
 * que derruba as suítes vizinhas do módulo quando esquecida.
 */
function assetDashboardBemSemGarantia(int $businessId, string $codigo, string $nome, int $criadoPor): void
{
    Asset::create([
        'business_id' => $businessId,
        'name' => $nome,
        'asset_code' => $codigo,
        'quantity' => 1,
        'unit_price' => 10,
        'is_allocatable' => 0,
        'purchase_type' => 'owned',
        'created_by' => $criadoPor,
    ]);
}

/**
 * Quantos BENS o painel do `$dono` conta no balde `sem` (sem garantia registrada).
 *
 * Vai pela ROTA, não pelo controller. Até 2026-09-08 este teste chamava
 * `app(AssetController::class)->dashboard()` e lia `$view->getData()`, com um comentário
 * explicando que a `dashboard.blade.php` estourava no `@num_format` em ambiente de teste.
 * O PR #7040 migrou o `dashboard()` para `Inertia::render('Patrimonio/Index')` e **não
 * atualizou este teste**: `Inertia\Response` não tem `getData()`, então a catraca passou a
 * morrer com `BadMethodCallException` — verde nenhum, e a proteção Tier 0 do #7018 ficou
 * sem quem a defendesse. Sem Blade no caminho, o motivo do desvio deixou de existir.
 *
 * `flushHeaders()` não é zelo: `withHeaders()` grava em `$defaultHeaders` da INSTÂNCIA do
 * TestCase, então o `X-Inertia: true` do partial reload sobrevive à chamada e faria o GET
 * seguinte devolver JSON em vez da root view ("The response is not a view"). Só morde
 * quando o mesmo teste resolve as props mais de uma vez — que é exatamente o caso aqui.
 */
function assetDashboardBensSemGarantia(Business $dono, User $admin): int
{
    $sessao = [
        'user.business_id' => $dono->id,
        'user' => ['business_id' => $dono->id, 'id' => $admin->id],
    ];

    test()->flushHeaders();

    // O primeiro GET não é desperdício: é a FONTE DA VERSÃO. O XHR do Inertia devolve 409
    // quando o header não bate com a que o middleware calculou, e a versão boa é a que este
    // render acabou de emitir.
    $inicial = test()->actingAs($admin)->withSession($sessao)->get('/asset/dashboard');

    expect($inicial->status())->toBe(200);

    $versao = data_get($inicial->viewData('page'), 'version');

    test()->flushHeaders();

    // `garantia` é `Inertia::defer` — não vem no primeiro render. `is_admin` vai junto
    // porque o partial devolve só o que se pede, e ele é o canário.
    $parcial = test()->actingAs($admin)->withSession($sessao)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) $versao,
            'X-Inertia-Partial-Component' => 'Patrimonio/Index',
            'X-Inertia-Partial-Data' => 'garantia,is_admin',
        ])
        ->get('/asset/dashboard');

    expect($parcial->status())->toBe(200);

    $props = $parcial->json('props');

    // Canário: `painelGarantia()` devolve `null` quando não é admin, e a lista viria vazia
    // — o teste passaria sem exercitar nada.
    expect(data_get($props, 'is_admin'))->toBeTrue('os baldes de garantia só rodam sob is_admin');

    $balde = collect(data_get($props, 'garantia', []))->firstWhere('balde', 'sem');

    expect($balde)->not->toBeNull('o balde `sem` é sempre emitido, mesmo zerado');

    return (int) $balde['bens'];
}

function assetDashboardTenantLimpar(): void
{
    foreach (['AST-DASH-TNT99', 'AST-DASH-TNT98'] as $code) {
        Asset::where('asset_code', $code)->forceDelete();
    }
    foreach (User::withTrashed()->where('username', 'like', 'asset_dash_tnt_%')->get() as $u) {
        DB::table('model_has_roles')->where('model_id', $u->id)->delete();
        $u->forceDelete();
    }
}

it('UC-PAT-01: bem SEM garantia de outra empresa não entra nos baldes de garantia do painel', function () {
    [$dono, $adversario, $admin] = assetDashboardTenantFixture();

    try {
        // POR QUE DELTA, E NÃO CONTAGEM ABSOLUTA: no CT 100 a base é clone de prod e NÃO se
        // limpa entre execuções — o tenant 98 já chega com dezenas de assets, boa parte sem
        // garantia. Um assert de total exato mediria o histórico da base, não a regra, e
        // quebraria no run seguinte.
        $base = assetDashboardBensSemGarantia($dono, $admin);

        // ETAPA 1 — só o ADVERSÁRIO ganha um bem sem garantia.
        assetDashboardBemSemGarantia(
            (int) $adversario->id, 'AST-DASH-TNT99', 'Bem sigiloso do adversario', (int) $admin->id
        );

        // O painel do DONO não pode ter se mexido. É aqui que o vazamento apareceria — e a
        // forma dele mudou: o `painelGarantia()` de hoje agrega em baldes e não devolve mais
        // `name`/`asset_code`, então o vazamento seria NUMÉRICO (um bem a mais na contagem),
        // não um nome à mostra. O predicado Tier 0 é o mesmo; o que se observa é outro.
        expect(assetDashboardBensSemGarantia($dono, $admin))->toBe(
            $base,
            'bem sem garantia do biz 99 entrou na contagem do biz 98 — vazamento cross-tenant'
        );

        // ETAPA 2 — agora o DONO ganha o dele.
        assetDashboardBemSemGarantia(
            (int) $dono->id, 'AST-DASH-TNT98', 'Bem legitimo do dono', (int) $admin->id
        );

        // CANÁRIO, e ele é o que dá sentido à etapa 1: prova que a contagem REAGE a um bem
        // sem garantia do próprio tenant. Sem esta asserção, uma query quebrada (ou um
        // `where` que zerasse tudo) faria a etapa 1 passar por não medir nada.
        expect(assetDashboardBensSemGarantia($dono, $admin))->toBe(
            $base + 1,
            'a contagem não subiu com o bem sem garantia do PRÓPRIO tenant — o instrumento não mede'
        );
    } finally {
        assetDashboardTenantLimpar();
    }
});
