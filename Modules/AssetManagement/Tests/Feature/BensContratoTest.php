<?php

declare(strict_types=1);

use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\AssetManagement\Entities\Asset;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class);

/**
 * Contrato da tela Patrimonio/Bens — a listagem de bens migrada de Blade pra Inertia.
 *
 * Defende os UCs declarados em `resources/js/Pages/Patrimonio/Bens.casos.md` (G-2 do
 * casos-gate, ADR 0264): cada `it()` cita o id do UC no título, que é como o gate amarra
 * caso ↔ teste.
 *
 * ADR 0358: tenant canônico de teste é o FICTÍCIO 98 (`seededTenant()`); 99 é o adversário
 * cross-tenant (`seededSupportClientTenant()`). biz=1 é a WR2 Sistemas, empresa REAL — no
 * CT 100 a base é clone de prod e não se limpa entre execuções. biz=4 (ROTA LIVRE) é
 * proibido sem exceção.
 *
 * @see resources/js/Pages/Patrimonio/Bens.casos.md
 * @see memory/requisitos/AssetManagement/RUNBOOK-bens.md
 * @see memory/decisions/0394-endereco-de-ui-do-patrimonio-pages-patrimonio.md
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: Models AssetManagement legacy requerem schema MySQL UltimatePOS');
    }
    if (! Schema::hasTable('assets') || ! Schema::hasTable('business')) {
        $this->markTestSkipped('Tabelas assets/business ausentes — rode migrate primeiro');
    }
});

/**
 * Usuário com `asset.view` no business dado.
 *
 * A `Permission` é criada SEMPRE, mesmo quando o cenário não a concede: num banco limpo
 * `asset.view` não existe na tabela `permissions` (o seeder do módulo é vazio; elas nascem
 * sob demanda em `RoleController::__createPermissionIfNotExists()`). Sem isto, um cenário
 * poderia passar por AUSÊNCIA da permissão em vez de pela regra sob teste.
 *
 * `roles.business_id` é NOT NULL + FK, e o sufixo `#{biz}` é a convenção da casa.
 */
function bensContratoUsuario(int $businessId, bool $comPermissao = true): User
{
    $user = User::factory()->create([
        'business_id' => $businessId,
        'username' => 'bens_contrato_'.uniqid(),
        'user_type' => 'user',
        'allow_login' => 1,
    ]);

    $perm = Permission::firstOrCreate(['name' => 'asset.view', 'guard_name' => 'web']);

    // `access_all_locations` NAO e enfeite: sem ela `User::permitted_locations()` devolve
    // LISTA VAZIA (app/User.php:156-172) e o `whereIn('assets.location_id', [])` do
    // `applyAssetFilters` zera a listagem inteira. MEDIDO no CT 100 em 2026-09-08 por sonda:
    // com o fixture sem esta permissao, o partial reload devolvia `bens.total = 0` com o bem
    // criado e visivel no banco — e o teste teria "provado" isolamento por acidente, medindo
    // ausencia de permissao de LOCAL em vez da regra de TENANT. E a permissao que um admin de
    // business real tem; e restricao de local nao se afrouxa por query (por isso ela vem do
    // role, nao do filtro).
    $permLocais = Permission::firstOrCreate(['name' => 'access_all_locations', 'guard_name' => 'web']);

    if ($comPermissao) {
        $role = Role::firstOrCreate(
            ['name' => 'bens-contrato#'.$businessId, 'guard_name' => 'web'],
            ['business_id' => $businessId]
        );
        $role->givePermissionTo($perm);
        $role->givePermissionTo($permLocais);
        $user->assignRole($role);
    }

    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

/**
 * `created_by` é NOT NULL nas três tabelas do módulo e FK→`users.id` em duas — fixture sem
 * ele estoura na FK antes de chegar no assert.
 */
function bensContratoAsset(int $businessId, int $ownerId, string $codigo, string $nome): Asset
{
    return Asset::create([
        'business_id' => $businessId,
        'name' => $nome,
        'asset_code' => $codigo,
        'quantity' => 2,
        'unit_price' => 1500.00,
        'is_allocatable' => 1,
        'purchase_type' => 'owned',
        'created_by' => $ownerId,
    ]);
}

/**
 * A assinatura do módulo é o gate SEGUINTE ao de permissão dentro do `index()`. Sem
 * neutralizá-la, todo cenário tomaria 403 nessa linha e mediria a coisa errada.
 */
function bensContratoAssinaturaLiberada(): void
{
    $moduleUtil = Mockery::mock(ModuleUtil::class)->makePartial();
    $moduleUtil->shouldReceive('hasThePermissionInSubscription')->andReturn(true);
    app()->instance(ModuleUtil::class, $moduleUtil);
}

function bensContratoGet(User $user, int $businessId, array $query = [])
{
    $url = '/asset/assets'.($query ? '?'.http_build_query($query) : '');

    return test()
        ->actingAs($user)
        ->withSession([
            'user.business_id' => $businessId,
            'user' => ['business_id' => $businessId, 'id' => $user->id],
        ])
        ->get($url);
}

/**
 * Limpeza à mão em `try/finally`, NÃO por `afterEach` encadeado: medido no CT 100 em
 * 2026-09-08 (thread 03), o gancho por teste não executou e deixou fixtures órfãos numa
 * base que é clone de prod e não se limpa entre runs. `finally` também limpa quando o
 * assert falha.
 */
function bensContratoLimpar(): void
{
    app()->forgetInstance(ModuleUtil::class);

    Asset::where('asset_code', 'like', 'BENS-CTR-%')->forceDelete();

    // `withTrashed`: App\User usa SoftDeletes — sem isto um fixture já soft-deletado
    // escaparia da varredura e ficaria na base.
    $users = User::withTrashed()->where('username', 'like', 'bens_contrato_%')->get();
    foreach ($users as $u) {
        DB::table('model_has_roles')->where('model_id', $u->id)->delete();
        $u->forceDelete();
    }
    Role::where('name', 'like', 'bens-contrato#%')->delete();
}


/**
 * Resolve a prop DEFERIDA `bens` — o que a tela recebe no partial reload.
 *
 * Duas coisas foram MEDIDAS no CT 100 aqui, não supostas:
 *
 * 1. A prop `bens` e `Inertia::defer`, entao ela NAO vem no primeiro render. Assertar sobre
 *    o `data-page` inicial mediria a ausencia dela, que e o comportamento correto do defer.
 * 2. O XHR do Inertia devolve **409** nesta lane quando a versao do header nao bate com a
 *    que o middleware calculou (mesmo passando `Inertia::getVersion()`, que e resolvido
 *    FORA do ciclo da request). A versao boa e a que o proprio render acabou de emitir —
 *    ela vem no `page` da root view. Por isso o primeiro GET nao e desperdicio: ele e a
 *    fonte da versao. Sem isto o teste passava a medir "409 != 200" em vez do payload.
 */
function bensContratoPropDeferida(User $user, int $businessId, array $query = []): array
{
    $inicial = bensContratoGet($user, $businessId, $query);

    expect($inicial->status())->toBe(200);

    $versao = data_get($inicial->viewData('page'), 'version');

    $url = '/asset/assets'.($query ? '?'.http_build_query($query) : '');

    $parcial = test()
        ->actingAs($user)
        ->withSession([
            'user.business_id' => $businessId,
            'user' => ['business_id' => $businessId, 'id' => $user->id],
        ])
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) $versao,
            'X-Inertia-Partial-Component' => 'Patrimonio/Bens',
            'X-Inertia-Partial-Data' => 'bens',
        ])
        ->get($url);

    expect($parcial->status())->toBe(200);

    return collect(data_get($parcial->json(), 'props.bens.data', []))
        ->pluck('asset_code')
        ->all();
}

it('UC-BENS-02: a rota /asset/assets devolve Inertia com o componente Patrimonio/Bens', function () {
    $biz = $this->seededTenant();
    $user = bensContratoUsuario((int) $biz->id);

    try {
        bensContratoAssinaturaLiberada();

        $r = bensContratoGet($user, (int) $biz->id);

        expect($r->status())->toBe(200);

        // O componente é o que distingue "migrou" de "continua Blade": a URL não mudou,
        // então status 200 sozinho não prova nada. `assertInertia` lê o `data-page` da
        // root view — o caminho que de fato exercita o render nesta lane (medido na
        // RepairSettingsContratoTest: variante XHR devolve 409 aqui).
        $r->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('Patrimonio/Bens')
            ->has('filtros')
            ->has('opcoes')
            ->has('permissoes')
        );
    } finally {
        bensContratoLimpar();
    }
});

it('UC-BENS-01: a listagem não devolve bem de outro business (Tier 0)', function () {
    $dono = $this->seededTenant();
    $adversario = $this->seededSupportClientTenant();

    $user = bensContratoUsuario((int) $dono->id);

    try {
        bensContratoAssinaturaLiberada();

        bensContratoAsset((int) $dono->id, (int) $dono->owner_id, 'BENS-CTR-DONO', 'Plotter do dono');
        bensContratoAsset((int) $adversario->id, (int) $adversario->owner_id, 'BENS-CTR-ADV', 'Plotter do adversario');

        // A busca `BENS-CTR` casa o codigo dos DOIS fixtures — o do dono e o do adversario.
        // E isso que torna o cenario decisivo: os dois disputam a MESMA pagina, entao um
        // `not->toContain` so pode passar por isolamento, nunca por paginacao.
        //
        // Sem o recorte, o teste era nao-determinista e falhava por outro motivo: o CT 100
        // e base PERSISTENTE (nao se limpa entre runs) e ja tinha 82 assets no biz=98 em
        // 2026-09-08 — com `paginate(25)` ordenado por nome, o fixture do dono simplesmente
        // caia fora da primeira pagina. MEDIDO, nao suposto.
        $codigos = bensContratoPropDeferida($user, (int) $dono->id, ['q' => 'BENS-CTR']);

        // Controle positivo primeiro: sem ele, uma lista VAZIA satisfaria o `not->toContain`
        // e o teste passaria pelo motivo errado.
        expect($codigos)->toContain('BENS-CTR-DONO');
        expect($codigos)->not->toContain('BENS-CTR-ADV');
    } finally {
        bensContratoLimpar();
    }
});

it('UC-BENS-03: a busca recorta no servidor — o bem fora do termo não volta na página', function () {
    $biz = $this->seededTenant();
    $user = bensContratoUsuario((int) $biz->id);

    try {
        bensContratoAssinaturaLiberada();

        bensContratoAsset((int) $biz->id, (int) $biz->owner_id, 'BENS-CTR-PLOT', 'Plotter Roland');
        bensContratoAsset((int) $biz->id, (int) $biz->owner_id, 'BENS-CTR-FIOR', 'Fiorino de entrega');

        $codigos = bensContratoPropDeferida($user, (int) $biz->id, ['q' => 'Fiorino']);

        // Controle positivo junto: sem ele, uma busca que devolvesse ZERO linha passaria
        // pelo motivo errado (o `not->toContain` sozinho é satisfeito por lista vazia).
        expect($codigos)->toContain('BENS-CTR-FIOR');
        expect($codigos)->not->toContain('BENS-CTR-PLOT');
    } finally {
        bensContratoLimpar();
    }
});

it('UC-BENS-01: o espelho — o adversário vê o bem DELE e não o do dono (controle bidirecional)', function () {
    $dono = $this->seededTenant();
    $adversario = $this->seededSupportClientTenant();

    // Este cenário é o CONTROLE do anterior, e existe por um motivo específico: sozinho, o
    // `not->toContain('BENS-CTR-ADV')` do cenário acima também passaria se o bem do
    // adversário simplesmente NÃO EXISTISSE, ou se a tela fosse incapaz de enxergá-lo por
    // qualquer outro motivo (permissão, local, filtro). Aqui a MESMA tela, com a MESMA
    // busca, é pedida pelo tenant 99 — e o bem do adversário aparece. Isso prova que o
    // isolamento do cenário anterior veio da regra de tenant, e não de ausência de dado.
    //
    // É o substituto do bite-test por mutação: remover o `where('assets.business_id')` do
    // controller provaria o mesmo, mas seria desligar uma proteção Tier 0 pra ver o alarme
    // tocar. Este par mede o mesmo predicado sem tocar na guarda.
    $user = bensContratoUsuario((int) $adversario->id);

    try {
        bensContratoAssinaturaLiberada();

        bensContratoAsset((int) $dono->id, (int) $dono->owner_id, 'BENS-CTR-DONO', 'Plotter do dono');
        bensContratoAsset((int) $adversario->id, (int) $adversario->owner_id, 'BENS-CTR-ADV', 'Plotter do adversario');

        $codigos = bensContratoPropDeferida($user, (int) $adversario->id, ['q' => 'BENS-CTR']);

        expect($codigos)->toContain('BENS-CTR-ADV');
        expect($codigos)->not->toContain('BENS-CTR-DONO');
    } finally {
        bensContratoLimpar();
    }
});
