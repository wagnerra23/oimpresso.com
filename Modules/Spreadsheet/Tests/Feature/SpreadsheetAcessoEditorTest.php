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
 * Quem abre (show) e quem grava (update) uma planilha no editor — F2 da migração do editor.
 *
 * Contrato: SPEC US-SHEET-002 (acesso = quem criou ou recebeu por usuário, função ou tarefa) e a
 * regra que a lista já segue em planilhasVisiveis(). Até 2026-10-27 show() e update() faziam
 * `->where(created_by)->orWhere(...)` sem agrupar: o OR escapava do id. No update() isso deixava um
 * colega com qualquer planilha recebida gravar por cima de planilha que não era dele.
 *
 * Assinatura do módulo forçada por mock parcial do ModuleUtil. Tenant 98 × 99 (ADR 0358).
 * MySQL-only. Tudo o que é criado aqui é apagado no afterEach.
 *
 * @covers-us US-SHEET-002
 * @see Modules\Spreadsheet\Http\Controllers\SpreadsheetController::show
 * @see Modules\Spreadsheet\Http\Controllers\SpreadsheetController::update
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

    $this->edBiz = (int) $this->seededTenant()->id;
    $this->edAlheio = (int) $this->seededSupportClientTenant()->id;
    $this->edSheets = [];
    $this->edUsers = [];
    $this->edRoles = [];
});

afterEach(function () {
    app()->forgetInstance(ModuleUtil::class);
    if ($this->edSheets) {
        DB::table('sheet_spreadsheet_shares')->whereIn('sheet_spreadsheet_id', $this->edSheets)->delete();
        DB::table('sheet_spreadsheets')->whereIn('id', $this->edSheets)->delete();
    }
    foreach ($this->edUsers as $u) {
        $u->forceDelete();
    }
    foreach ($this->edRoles as $r) {
        $r->delete();
    }
});

function edUser($test, int $biz): User
{
    $user = User::create([
        'business_id' => $biz,
        'first_name'  => 'ED',
        'surname'     => 'Acesso',
        'username'    => 'ed_acesso_'.$biz.'_'.uniqid(),
        'email'       => 'ed_acesso_'.$biz.'_'.uniqid().'@test.local',
        'password'    => bcrypt('test12345'),
        'language'    => 'pt_BR',
    ]);
    $role = Role::create(['name' => 'ed-acesso-'.uniqid().'#'.$biz, 'guard_name' => 'web', 'business_id' => $biz]);
    foreach (['access.spreadsheet', 'create.spreadsheet'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    $role->givePermissionTo(['access.spreadsheet', 'create.spreadsheet']);
    $user->assignRole($role);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    $test->edUsers[] = $user;
    $test->edRoles[] = $role;

    return $user;
}

function edPlanilha($test, int $biz, int $criador, string $nome): int
{
    $id = (int) Spreadsheet::create([
        'business_id' => $biz,
        'name'        => $nome,
        'sheet_data'  => ['celula' => 'original'],
        'created_by'  => $criador,
    ])->id;
    $test->edSheets[] = $id;

    return $id;
}

function edCompartilha(int $sheet, string $com, int $id): void
{
    DB::table('sheet_spreadsheet_shares')->insert([
        'sheet_spreadsheet_id' => $sheet, 'shared_with' => $com, 'shared_id' => $id,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function edNome(int $id): string
{
    return (string) DB::table('sheet_spreadsheets')->where('id', $id)->value('name');
}

it('UC-PLED-01 · abro a minha e a recebida; a de colega sem compartilhamento dá 404', function () {
    $eu = edUser($this, $this->edBiz);
    $colega = edUser($this, $this->edBiz);
    $minha = edPlanilha($this, $this->edBiz, (int) $eu->id, 'ED minha');
    $recebida = edPlanilha($this, $this->edBiz, (int) $colega->id, 'ED recebida');
    edCompartilha($recebida, 'user', (int) $eu->id);
    $doColega = edPlanilha($this, $this->edBiz, (int) $colega->id, 'ED do colega');
    $this->actingAs($eu);

    $this->get('/spreadsheet/sheets/'.$minha)->assertOk();
    $this->get('/spreadsheet/sheets/'.$recebida)->assertOk();
    $this->get('/spreadsheet/sheets/'.$doColega)->assertNotFound();
});

it('UC-PLED-02 · [T0] planilha de outro negócio dá 404, mesmo compartilhada com meu usuário', function () {
    $eu = edUser($this, $this->edBiz);
    $dono = edUser($this, $this->edAlheio);
    $alheia = edPlanilha($this, $this->edAlheio, (int) $dono->id, 'ED alheia');
    edCompartilha($alheia, 'user', (int) $eu->id);
    $this->actingAs($eu);

    $r = $this->get('/spreadsheet/sheets/'.$alheia);

    $r->assertNotFound();
    expect($r->getContent())->not->toContain('ED alheia');
});

it('UC-PLED-03 · ter uma planilha recebida não deixa gravar por cima da de colega', function () {
    $eu = edUser($this, $this->edBiz);
    $colega = edUser($this, $this->edBiz);
    $recebida = edPlanilha($this, $this->edBiz, (int) $colega->id, 'ED recebida');
    edCompartilha($recebida, 'user', (int) $eu->id);
    $doColega = edPlanilha($this, $this->edBiz, (int) $colega->id, 'ED do colega');
    $this->actingAs($eu);

    $this->put('/spreadsheet/sheets/'.$doColega, ['name' => 'ED sobrescrita', 'sheet_data' => ['x' => 1]]);

    // Antes do conserto o firstOrFail achava a planilha recebida pelo OR e o UPDATE gravava aqui.
    expect(edNome($doColega))->toBe('ED do colega');

    // Controle positivo: na que recebi, a gravação passa.
    $this->put('/spreadsheet/sheets/'.$recebida, ['name' => 'ED recebida editada', 'sheet_data' => ['x' => 1]]);
    expect(edNome($recebida))->toBe('ED recebida editada');
});

// ── Editor React (thread telas-soltas/04b) — UC-PLED-04 a 07 ─────────────────────────────

it('UC-PLED-04 · abrir planilha devolve o editor React sem dado cru', function () {
    $eu = edUser($this, $this->edBiz);
    $minha = edPlanilha($this, $this->edBiz, (int) $eu->id, 'ED minha');
    $this->actingAs($eu);

    $this->get('/spreadsheet/sheets/'.$minha)->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
        ->component('Spreadsheet/Editor')
        ->where('planilha.id', $minha)
        ->where('planilha.nome', 'ED minha')
        ->where('planilha.minha', true)
        ->where('pode_gravar', true)
        ->missing('planilha.business_id')
        ->missing('planilha.created_by'));
});

it('UC-PLED-05 · planilha nova abre o editor na pasta pedida', function () {
    $this->actingAs(edUser($this, $this->edBiz));

    $this->get('/spreadsheet/sheets/create?folder_id=77')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
        ->component('Spreadsheet/Editor')
        ->where('planilha', null)
        ->where('pasta_id', 77));
});

it('UC-PLED-06 · planilha recebida abre como não-minha', function () {
    $eu = edUser($this, $this->edBiz);
    $colega = edUser($this, $this->edBiz);
    $recebida = edPlanilha($this, $this->edBiz, (int) $colega->id, 'ED recebida');
    edCompartilha($recebida, 'user', (int) $eu->id);
    $this->actingAs($eu);

    $props = $this->get('/spreadsheet/sheets/'.$recebida)->assertOk()->viewData('page')['props'];

    expect($props['planilha']['minha'])->toBeFalse();
    $tarefas = rescue(fn () => action([\Modules\Essentials\Http\Controllers\ToDoController::class, 'index']), null, false);
    if ($tarefas !== null) {
        // Mesmo destino da Blade: quem recebeu volta às tarefas, não à lista de planilhas.
        expect($props['voltar'])->toBe($tarefas);
    }
});

it('UC-PLED-07 · ?legacy=1 abre as Blades até o F5', function () {
    $eu = edUser($this, $this->edBiz);
    $minha = edPlanilha($this, $this->edBiz, (int) $eu->id, 'ED minha');
    $this->actingAs($eu);

    $this->get('/spreadsheet/sheets/'.$minha.'?legacy=1')->assertOk()->assertViewIs('spreadsheet::sheet.show');
    $this->get('/spreadsheet/sheets/create?legacy=1')->assertOk()->assertViewIs('spreadsheet::sheet.create');
});

// ── Compartilhar (thread telas-soltas/04, ações da lista) — UC-PLAN-06 a 08 ───────────────

function edXhr($test): mixed
{
    return $test->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json']);
}

function edShares(int $sheet): array
{
    return DB::table('sheet_spreadsheet_shares')->where('sheet_spreadsheet_id', $sheet)
        ->orderBy('shared_with')->orderBy('shared_id')->get(['shared_with', 'shared_id'])
        ->map(fn ($r) => $r->shared_with.':'.$r->shared_id)->all();
}

it('UC-PLAN-06 · [T0] não mexo no compartilhamento de planilha de outro negócio', function () {
    \Illuminate\Support\Facades\Notification::fake();
    $eu = edUser($this, $this->edBiz);
    $dono = edUser($this, $this->edAlheio);
    $alheia = edPlanilha($this, $this->edAlheio, (int) $dono->id, 'ED alheia');
    edCompartilha($alheia, 'user', (int) $dono->id);
    $antes = edShares($alheia);
    $this->actingAs($eu);

    edXhr($this)->post('/spreadsheet/post-share-sheet', ['sheet_id' => $alheia, 'share' => ['user' => [(int) $eu->id]]])->assertNotFound();
    edXhr($this)->get('/spreadsheet/get-sheet/'.$alheia.'/share')->assertNotFound();

    expect(edShares($alheia))->toBe($antes);
});

it('UC-PLAN-07 · só quem criou compartilha; colega que recebeu não redistribui', function () {
    \Illuminate\Support\Facades\Notification::fake();
    $dono = edUser($this, $this->edBiz);
    $colega = edUser($this, $this->edBiz);
    $terceiro = edUser($this, $this->edBiz);
    $sheet = edPlanilha($this, $this->edBiz, (int) $dono->id, 'ED do dono');
    edCompartilha($sheet, 'user', (int) $colega->id);
    $this->actingAs($colega);

    edXhr($this)->post('/spreadsheet/post-share-sheet', ['sheet_id' => $sheet, 'share' => ['user' => [(int) $terceiro->id]]])->assertNotFound();

    expect(edShares($sheet))->toBe(['user:'.$colega->id]);
});

it('UC-PLAN-08 · [T0] o dono compartilha com gente do negócio; id de outro negócio é ignorado', function () {
    \Illuminate\Support\Facades\Notification::fake();
    $dono = edUser($this, $this->edBiz);
    $colega = edUser($this, $this->edBiz);
    $alheio = edUser($this, $this->edAlheio);
    $sheet = edPlanilha($this, $this->edBiz, (int) $dono->id, 'ED do dono');
    $this->actingAs($dono);

    $r = edXhr($this)->post('/spreadsheet/post-share-sheet', ['sheet_id' => $sheet, 'share' => ['user' => [(int) $colega->id, (int) $alheio->id]]]);

    $r->assertOk();
    expect((bool) $r->json('success'))->toBeTrue();
    expect(edShares($sheet))->toBe(['user:'.$colega->id]);
});

it('UC-PLAN-09 · a tela React recebe o compartilhamento em JSON, só com gente do negócio', function () {
    $dono = edUser($this, $this->edBiz);
    $colega = edUser($this, $this->edBiz);
    $alheio = edUser($this, $this->edAlheio);
    $sheet = edPlanilha($this, $this->edBiz, (int) $dono->id, 'ED do dono');
    edCompartilha($sheet, 'user', (int) $colega->id);
    $this->actingAs($dono);

    $r = edXhr($this)->get('/spreadsheet/get-sheet/'.$sheet.'/share');

    $r->assertOk();
    $usuarios = collect($r->json('usuarios'))->pluck('id')->all();
    expect($usuarios)->toContain((int) $colega->id);
    expect($usuarios)->not->toContain((int) $alheio->id);
    expect($r->json('selecionados.user'))->toBe([(int) $colega->id]);
});

it('UC-PLAN-10 · excluir pela tela React só apaga planilha que eu criei', function () {
    $eu = edUser($this, $this->edBiz);
    $colega = edUser($this, $this->edBiz);
    $minha = edPlanilha($this, $this->edBiz, (int) $eu->id, 'ED minha');
    $doColega = edPlanilha($this, $this->edBiz, (int) $colega->id, 'ED do colega');
    edCompartilha($doColega, 'user', (int) $eu->id);
    $this->actingAs($eu);

    edXhr($this)->delete('/spreadsheet/sheets/'.$doColega)->assertOk();
    edXhr($this)->delete('/spreadsheet/sheets/'.$minha)->assertOk();

    expect(DB::table('sheet_spreadsheets')->where('id', $doColega)->exists())->toBeTrue();
    expect(DB::table('sheet_spreadsheets')->where('id', $minha)->exists())->toBeFalse();
});
