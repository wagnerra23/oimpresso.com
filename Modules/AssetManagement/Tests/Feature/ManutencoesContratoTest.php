<?php

declare(strict_types=1);

use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\AssetManagement\Entities\Asset;
use Modules\AssetManagement\Entities\AssetMaintenance;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class);

/**
 * Contrato da tela `Patrimonio/Manutencoes` (MWART F3, ADR 0104).
 *
 * Defende os 3 UC de `resources/js/Pages/Patrimonio/Manutencoes.casos.md`.
 *
 * ADR 0358: tenant canônico de teste é o FICTÍCIO 98 (`seededTenant()`); 99 é o adversário
 * (`seededSupportClientTenant()`). Nunca biz=1 nem biz=4.
 *
 * Limpeza à mão em `try/finally`, NÃO por `afterEach` encadeado: medido no CT 100 em
 * 2026-09-08, o gancho por teste não executou e deixou fixtures órfãos numa base que não se
 * limpa entre runs. `finally` também limpa quando o assert falha.
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: Models AssetManagement legacy requerem schema MySQL UltimatePOS');
    }
    foreach (['assets', 'asset_maintenances', 'business', 'users', 'roles', 'permissions'] as $tabela) {
        if (! Schema::hasTable($tabela)) {
            $this->markTestSkipped("Tabela {$tabela} ausente — rode migrate primeiro");
        }
    }
});

/**
 * Usuário com UMA das duas permissões de manutenção.
 *
 * As DUAS `Permission` são criadas SEMPRE, mesmo quando o cenário concede só uma: num banco
 * limpo elas não existem (o seeder do módulo é vazio; nascem sob demanda em
 * `RoleController::__createPermissionIfNotExists()`). Sem isto, um cenário poderia passar por
 * AUSÊNCIA da permissão em vez de pela regra sob teste — foi o defeito medido no #7008.
 *
 * `syncPermissions` (não `givePermissionTo`): a Role é reaproveitada entre cenários pelo
 * `firstOrCreate`, e acumular as duas faria o cenário `view_own` medir um usuário que também
 * tem `view_all` — exatamente a confusão que o UC-MANU-02 apura.
 */
function manutContratoUsuario(int $businessId, string $permissao): User
{
    $user = User::factory()->create([
        'business_id' => $businessId,
        'username' => 'manut_contrato_'.uniqid(),
        'user_type' => 'user',
        'allow_login' => 1,
    ]);

    $todas = [];
    foreach (['asset.view_all_maintenance', 'asset.view_own_maintenance'] as $nome) {
        $todas[$nome] = Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web']);
    }

    // `roles.business_id` é NOT NULL + FK; o sufixo `#{biz}` é a convenção da casa.
    $role = Role::firstOrCreate(
        ['name' => 'manut-contrato-'.$permissao.'#'.$businessId, 'guard_name' => 'web'],
        ['business_id' => $businessId]
    );
    $role->syncPermissions([$todas[$permissao]]);
    $user->assignRole($role);

    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

/** `created_by` é NOT NULL e FK→`users.id`: fixture sem ele estoura antes do assert. */
function manutContratoAsset(int $businessId, int $ownerId, string $codigo, string $nome): Asset
{
    return Asset::create([
        'business_id' => $businessId,
        'name' => $nome,
        'asset_code' => $codigo,
        'quantity' => 1,
        'unit_price' => 900.00,
        'is_allocatable' => 1,
        'purchase_type' => 'owned',
        'created_by' => $ownerId,
    ]);
}

function manutContratoManutencao(
    int $businessId,
    int $assetId,
    int $createdBy,
    string $codigo,
    ?int $assignedTo = null
): AssetMaintenance {
    return AssetMaintenance::create([
        'business_id' => $businessId,
        'asset_id' => $assetId,
        'maitenance_id' => $codigo,   // typo do schema, preservado de propósito
        'status' => 'in_progress',
        'priority' => 'high',
        'created_by' => $createdBy,
        'assigned_to' => $assignedTo,
        'details' => 'Fixture de contrato MANU-CTR',
    ]);
}

/**
 * A assinatura do módulo é o gate SEGUINTE ao de permissão no `index()`. Sem neutralizá-la,
 * todo cenário tomaria 403 nessa linha e mediria a coisa errada.
 */
function manutContratoAssinaturaLiberada(): void
{
    $moduleUtil = Mockery::mock(ModuleUtil::class)->makePartial();
    $moduleUtil->shouldReceive('hasThePermissionInSubscription')->andReturn(true);
    app()->instance(ModuleUtil::class, $moduleUtil);
}

function manutContratoGet(User $user, int $businessId, array $query = [])
{
    $url = '/asset/asset-maintenance'.($query ? '?'.http_build_query($query) : '');

    return test()
        ->actingAs($user)
        ->withSession([
            'user.business_id' => $businessId,
            'user' => ['business_id' => $businessId, 'id' => $user->id],
        ])
        ->get($url);
}

/**
 * Lê a prop DEFERIDA `manutencoes` — exige dois GETs, e o primeiro não é desperdício.
 *
 * O partial reload valida `X-Inertia-Version` contra a versão que o middleware calculou; a
 * versão boa é a que o próprio render acabou de emitir, e ela vem no `page` da root view.
 * Sem isso o teste mediria "409 != 200" em vez do payload. Mesmo padrão do `BensContratoTest`.
 */
function manutContratoPropDeferida(User $user, int $businessId, array $query = []): array
{
    $inicial = manutContratoGet($user, $businessId, $query);

    expect($inicial->status())->toBe(200);

    $versao = data_get($inicial->viewData('page'), 'version');

    $url = '/asset/asset-maintenance'.($query ? '?'.http_build_query($query) : '');

    $parcial = test()
        ->actingAs($user)
        ->withSession([
            'user.business_id' => $businessId,
            'user' => ['business_id' => $businessId, 'id' => $user->id],
        ])
        ->withHeaders([
            // `X-Requested-With` NAO e decoracao: o cliente Inertia o manda
            // INCONDICIONALMENTE junto com `X-Inertia`. Sem ele, este teste montava uma
            // requisicao que o BROWSER NUNCA ENVIA — e ficava verde com a tela quebrada.
            'X-Requested-With' => 'XMLHttpRequest',
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) $versao,
            'X-Inertia-Partial-Component' => 'Patrimonio/Manutencoes',
            'X-Inertia-Partial-Data' => 'manutencoes',
        ])
        ->get($url);

    expect($parcial->status())->toBe(200);

    return data_get($parcial->json(), 'props.manutencoes.data', []);
}

function manutContratoLimpar(): void
{
    app()->forgetInstance(ModuleUtil::class);

    AssetMaintenance::where('maitenance_id', 'like', 'MANU-CTR-%')->forceDelete();
    Asset::where('asset_code', 'like', 'MANU-CTR-%')->forceDelete();

    // `withTrashed`: App\User usa SoftDeletes — sem isto um fixture já soft-deletado escaparia
    // da varredura e ficaria na base do CT 100, que não se limpa entre runs.
    $users = User::withTrashed()->where('username', 'like', 'manut_contrato_%')->get();
    foreach ($users as $u) {
        DB::table('model_has_roles')->where('model_id', $u->id)->delete();
        $u->forceDelete();
    }
    Role::where('name', 'like', 'manut-contrato-%')->delete();
}

it('UC-MANU-01: a listagem traz a manutenção do MEU business e nenhuma do adversário', function () {
    $dono = $this->seededTenant();
    $adversario = $this->seededSupportClientTenant();
    $user = manutContratoUsuario((int) $dono->id, 'asset.view_all_maintenance');

    try {
        manutContratoAssinaturaLiberada();

        $bemDono = manutContratoAsset((int) $dono->id, (int) $user->id, 'MANU-CTR-DONO', 'Torno do dono');
        manutContratoManutencao((int) $dono->id, (int) $bemDono->id, (int) $user->id, 'MANU-CTR-DONO-01');

        $bemAdv = manutContratoAsset((int) $adversario->id, (int) $adversario->owner_id, 'MANU-CTR-ADV', 'Torno do adversario');
        manutContratoManutencao((int) $adversario->id, (int) $bemAdv->id, (int) $adversario->owner_id, 'MANU-CTR-ADV-01');

        // A busca casa o código dos DOIS fixtures de propósito: eles disputam a mesma página,
        // então o isolamento por tenant é a única explicação possível para o segundo sumir.
        $codigos = collect(manutContratoPropDeferida($user, (int) $dono->id, ['q' => 'MANU-CTR']))
            ->pluck('codigo')
            ->all();

        expect($codigos)->toContain('MANU-CTR-DONO-01');
        expect($codigos)->not->toContain('MANU-CTR-ADV-01');
    } finally {
        manutContratoLimpar();
    }
});

it('UC-MANU-01 (espelho): o adversário vê a DELE — o `not->toContain` acima não passou por vazio', function () {
    $dono = $this->seededTenant();
    $adversario = $this->seededSupportClientTenant();
    $userAdv = manutContratoUsuario((int) $adversario->id, 'asset.view_all_maintenance');

    try {
        manutContratoAssinaturaLiberada();

        $bemDono = manutContratoAsset((int) $dono->id, (int) $dono->owner_id, 'MANU-CTR-DONO', 'Torno do dono');
        manutContratoManutencao((int) $dono->id, (int) $bemDono->id, (int) $dono->owner_id, 'MANU-CTR-DONO-01');

        $bemAdv = manutContratoAsset((int) $adversario->id, (int) $userAdv->id, 'MANU-CTR-ADV', 'Torno do adversario');
        manutContratoManutencao((int) $adversario->id, (int) $bemAdv->id, (int) $userAdv->id, 'MANU-CTR-ADV-01');

        $codigos = collect(manutContratoPropDeferida($userAdv, (int) $adversario->id, ['q' => 'MANU-CTR']))
            ->pluck('codigo')
            ->all();

        // O espelho: sem ele, o cenário acima passaria mesmo se a manutenção do adversário
        // nunca tivesse sido criada.
        expect($codigos)->toContain('MANU-CTR-ADV-01');
        expect($codigos)->not->toContain('MANU-CTR-DONO-01');
    } finally {
        manutContratoLimpar();
    }
});

it('UC-MANU-02: quem tem só view_own vê apenas as suas, e o payload avisa (vejo_todas=false)', function () {
    $biz = $this->seededTenant();
    $tecnico = manutContratoUsuario((int) $biz->id, 'asset.view_own_maintenance');

    try {
        manutContratoAssinaturaLiberada();

        // As DUAS manutenções são do MESMO business de propósito: assim o recorte por dono é a
        // única explicação possível para a segunda sumir. Fossem de businesses diferentes, o
        // filtro de tenant já bastaria e o teste mediria outra coisa.
        $bem = manutContratoAsset((int) $biz->id, (int) $tecnico->id, 'MANU-CTR-ESC', 'Prensa');
        manutContratoManutencao((int) $biz->id, (int) $bem->id, (int) $biz->owner_id, 'MANU-CTR-MINHA', (int) $tecnico->id);
        manutContratoManutencao((int) $biz->id, (int) $bem->id, (int) $biz->owner_id, 'MANU-CTR-ALHEIA', null);

        // Canário: o perfil é mesmo o restrito — tem view_own e NÃO tem view_all. Sem isto o
        // cenário poderia passar por outro motivo.
        expect($tecnico->can('asset.view_own_maintenance'))->toBeTrue();
        expect($tecnico->can('asset.view_all_maintenance'))->toBeFalse();

        $inicial = manutContratoGet($tecnico, (int) $biz->id);
        expect($inicial->status())->toBe(200);
        expect(data_get($inicial->viewData('page'), 'props.permissoes.vejo_todas'))->toBeFalse();

        $codigos = collect(manutContratoPropDeferida($tecnico, (int) $biz->id, ['q' => 'MANU-CTR']))
            ->pluck('codigo')
            ->all();

        expect($codigos)->toContain('MANU-CTR-MINHA');
        expect($codigos)->not->toContain('MANU-CTR-ALHEIA');
    } finally {
        manutContratoLimpar();
    }
});

it('UC-MANU-03: o payload NÃO traz campo de valor — a tela não inventa dinheiro que o banco não guarda', function () {
    $biz = $this->seededTenant();
    $user = manutContratoUsuario((int) $biz->id, 'asset.view_all_maintenance');

    try {
        manutContratoAssinaturaLiberada();

        $bem = manutContratoAsset((int) $biz->id, (int) $user->id, 'MANU-CTR-VAL', 'Compressor');
        manutContratoManutencao((int) $biz->id, (int) $bem->id, (int) $user->id, 'MANU-CTR-VAL-01');

        $linhas = manutContratoPropDeferida($user, (int) $biz->id, ['q' => 'MANU-CTR']);

        // Canário: se a lista vier vazia, o assert de ausência abaixo passaria sem medir nada.
        expect($linhas)->not->toBeEmpty();

        // O assert é sobre o PAYLOAD SERVIDO, não sobre o texto do .tsx: grep em fonte mediria
        // a escrita, não o contrato (LC-11 — presença não é comportamento).
        $proibidos = ['custo', 'cost', 'valor', 'amount', 'preco', 'price', 'total'];
        foreach ($linhas as $linha) {
            foreach (array_keys($linha) as $campo) {
                expect(in_array(strtolower((string) $campo), $proibidos, true))
                    ->toBeFalse("O payload de Manutencoes trouxe o campo de valor '{$campo}'. A tabela asset_maintenances NAO tem coluna de custo e o Blade nao mostra nenhuma — decisao [W] 2026-09-08. Ver Non-Goals do charter.");
            }
        }
    } finally {
        manutContratoLimpar();
    }
});
