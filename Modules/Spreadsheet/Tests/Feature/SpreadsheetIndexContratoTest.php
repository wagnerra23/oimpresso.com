<?php

declare(strict_types=1);

use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Modules\Spreadsheet\Entities\Spreadsheet;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class);

/**
 * Contrato da lista de planilhas (`/spreadsheet/sheets`) em React — thread telas-soltas/04.
 * Casos: resources/js/Pages/Spreadsheet/Index.casos.md (UC-PLAN-*), derivados da SPEC
 * US-SHEET-002, da Blade sheet/index e do RUNBOOK-spreadsheet-index — não do .tsx.
 *
 * Flag `useV2SpreadsheetIndex` forçada por stub do FeatureFlagService. Assinatura do módulo
 * forçada por mock parcial do ModuleUtil (só `hasThePermissionInSubscription`). Tenant 98 × 99
 * (ADR 0358). Planilhas, pastas, papéis e usuários criados aqui são apagados no afterEach.
 * MySQL-only.
 *
 * @covers-us US-SHEET-002
 * @see Modules\Spreadsheet\Http\Controllers\SpreadsheetController::index
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS exige MySQL (ADR 0358).');
    }
    if (! Schema::hasTable('sheet_spreadsheets') || ! Schema::hasTable('sheet_spreadsheet_shares')) {
        $this->markTestSkipped('Tabelas do Modules/Spreadsheet ausentes — rode migrate primeiro.');
    }
    $_SERVER['REMOTE_ADDR'] ??= '127.0.0.1';
    $_SERVER['HTTP_USER_AGENT'] ??= 'Pest/CI (X11; Linux x86_64) HeadlessChrome';

    $mu = Mockery::mock(ModuleUtil::class)->makePartial();
    $mu->shouldReceive('hasThePermissionInSubscription')->andReturn(true);
    app()->instance(ModuleUtil::class, $mu);

    $this->shBiz = (int) $this->seededTenant()->id;
    $this->shAlheio = (int) $this->seededSupportClientTenant()->id;
    $this->shSheets = [];
    $this->shPastas = [];
    $this->shUsers = [];
    $this->shRoles = [];
});

afterEach(function () {
    app()->forgetInstance(\App\Services\FeatureFlagService::class);
    app()->forgetInstance(ModuleUtil::class);
    if ($this->shSheets) {
        DB::table('sheet_spreadsheet_shares')->whereIn('sheet_spreadsheet_id', $this->shSheets)->delete();
        DB::table('sheet_spreadsheets')->whereIn('id', $this->shSheets)->delete();
    }
    if ($this->shPastas) {
        DB::table('categories')->whereIn('id', $this->shPastas)->delete();
    }
    foreach ($this->shUsers as $u) {
        $u->forceDelete();
    }
    foreach ($this->shRoles as $r) {
        $r->delete();
    }
});

function shFlag(bool $ligada): void
{
    app()->instance(\App\Services\FeatureFlagService::class, new class($ligada) extends \App\Services\FeatureFlagService
    {
        public function __construct(private bool $ligada) {}

        public function isOn(string $flag, array $attrs = []): bool
        {
            return $flag === 'useV2SpreadsheetIndex' && $this->ligada;
        }
    });
}

/** Usuário com função própria (`#biz`) que concede as permissões pedidas. */
function shUser($test, int $biz, array $perms = ['access.spreadsheet', 'create.spreadsheet']): User
{
    $user = User::create([
        'business_id' => $biz,
        'first_name'  => 'SH',
        'surname'     => 'Idx04',
        'username'    => 'sh_idx04_'.$biz.'_'.uniqid(),
        'email'       => 'sh_idx04_'.$biz.'_'.uniqid().'@test.local',
        'password'    => bcrypt('test12345'),
        'language'    => 'pt_BR',
    ]);
    $role = Role::create(['name' => 'sh-idx04-'.uniqid().'#'.$biz, 'guard_name' => 'web', 'business_id' => $biz]);
    foreach (['access.spreadsheet', 'create.spreadsheet'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    $role->givePermissionTo($perms);
    $user->assignRole($role);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    $test->shUsers[] = $user;
    $test->shRoles[] = $role;

    return $user;
}

function shPlanilha($test, int $biz, int $criador, string $nome, ?int $pasta = null): int
{
    $id = (int) Spreadsheet::create([
        'business_id' => $biz,
        'name'        => $nome,
        'sheet_data'  => ['segredo' => 'conteudo-da-celula'],
        'created_by'  => $criador,
        'folder_id'   => $pasta,
    ])->id;
    $test->shSheets[] = $id;

    return $id;
}

function shCompartilha(int $sheet, string $com, int $id): void
{
    DB::table('sheet_spreadsheet_shares')->insert([
        'sheet_spreadsheet_id' => $sheet, 'shared_with' => $com, 'shared_id' => $id,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function shPasta($test, int $biz, int $criador, string $nome): int
{
    $id = (int) DB::table('categories')->insertGetId([
        'name' => $nome, 'business_id' => $biz, 'parent_id' => 0, 'created_by' => $criador,
        'category_type' => 'spreadsheet', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $test->shPastas[] = $id;

    return $id;
}

/** Partial reload do navegador pedindo as props adiadas (headers que o @inertiajs/core manda). */
function shParcial($test): array
{
    $r = $test->withHeaders([
        'X-Requested-With'            => 'XMLHttpRequest',
        'X-Inertia'                   => 'true',
        'X-Inertia-Version'           => (string) app(App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'Spreadsheet/Index',
        'X-Inertia-Partial-Data'      => 'planilhas,pastas',
    ])->get('/spreadsheet/sheets');
    $r->assertOk();

    return $r->json('props');
}

it('UC-PLAN-01 · flag desligada serve a Blade; ligada, a tela React; ?legacy=1 volta à Blade', function () {
    $this->actingAs(shUser($this, $this->shBiz));

    shFlag(false);
    $this->get('/spreadsheet/sheets')->assertOk()->assertViewIs('spreadsheet::sheet.index');

    shFlag(true);
    $this->get('/spreadsheet/sheets')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
        ->component('Spreadsheet/Index')
        ->where('podeCriar', true)
        ->missing('planilhas')
        ->missing('pastas'));
    $this->get('/spreadsheet/sheets?legacy=1')->assertOk()->assertViewIs('spreadsheet::sheet.index');
});

it('UC-PLAN-02 · [T0] planilha de outro business não aparece, nem compartilhada com meu usuário', function () {
    shFlag(true);
    $eu = shUser($this, $this->shBiz);
    $dono = shUser($this, $this->shAlheio);
    $alheia = shPlanilha($this, $this->shAlheio, (int) $dono->id, 'SH04 alheia');
    shCompartilha($alheia, 'user', (int) $eu->id);
    $minha = shPlanilha($this, $this->shBiz, (int) $eu->id, 'SH04 minha');
    $this->actingAs($eu);

    $ids = array_column(shParcial($this)['planilhas'], 'id');

    expect($ids)->toContain($minha);
    expect($ids)->not->toContain($alheia);
});

it('UC-PLAN-03 · vejo as minhas e as recebidas, não as de colega sem compartilhamento', function () {
    shFlag(true);
    $eu = shUser($this, $this->shBiz);
    $colega = shUser($this, $this->shBiz);
    $minha = shPlanilha($this, $this->shBiz, (int) $eu->id, 'SH04 minha');
    $recebida = shPlanilha($this, $this->shBiz, (int) $colega->id, 'SH04 recebida');
    shCompartilha($recebida, 'user', (int) $eu->id);
    $alheia = shPlanilha($this, $this->shBiz, (int) $colega->id, 'SH04 do colega');
    $this->actingAs($eu);

    $porId = collect(shParcial($this)['planilhas'])->keyBy('id');

    expect($porId->has($minha))->toBeTrue();
    expect($porId->has($recebida))->toBeTrue();
    expect($porId->has($alheia))->toBeFalse();
    expect($porId[$minha]['minha'])->toBeTrue();
    expect($porId[$recebida]['minha'])->toBeFalse();
});

it('UC-PLAN-04 · linha traz contagem de compartilhamento e não traz sheet_data', function () {
    shFlag(true);
    $eu = shUser($this, $this->shBiz);
    $colega = shUser($this, $this->shBiz);
    $sheet = shPlanilha($this, $this->shBiz, (int) $eu->id, 'SH04 compartilhada');
    shCompartilha($sheet, 'user', (int) $colega->id);
    shCompartilha($sheet, 'role', (int) $this->shRoles[1]->id);
    $this->actingAs($eu);

    $linha = collect(shParcial($this)['planilhas'])->firstWhere('id', $sheet);

    expect($linha['compartilhada'])->toBe(['usuarios' => 1, 'funcoes' => 1, 'tarefas' => 0]);
    expect($linha)->not->toHaveKey('sheet_data');
    expect(json_encode($linha))->not->toContain('conteudo-da-celula');
});

it('UC-PLAN-05 · pastas só do business, em ordem; sem pasta é null; sem create.spreadsheet podeCriar=false', function () {
    shFlag(true);
    $eu = shUser($this, $this->shBiz, ['access.spreadsheet']);
    $b = shPasta($this, $this->shBiz, (int) $eu->id, 'SH04 Bravo');
    $a = shPasta($this, $this->shBiz, (int) $eu->id, 'SH04 Alfa');
    $fora = shPasta($this, $this->shAlheio, (int) $eu->id, 'SH04 Alheia');
    $semPasta = shPlanilha($this, $this->shBiz, (int) $eu->id, 'SH04 solta');
    $this->actingAs($eu);

    $this->get('/spreadsheet/sheets')->assertInertia(fn (AssertableInertia $p) => $p->where('podeCriar', false));
    $props = shParcial($this);
    $pastas = array_column($props['pastas'], 'id');

    expect($pastas)->not->toContain($fora);
    expect(array_values(array_intersect($pastas, [$a, $b])))->toBe([$a, $b]);
    expect(collect($props['planilhas'])->firstWhere('id', $semPasta)['pasta_id'])->toBeNull();
});
