<?php

/**
 * Cobertura HTTP da tela /modulos (Gerenciador de Módulos).
 *
 * Origem: cowork-inbox/MODULOS-F1-2026-08-19.md + resources/js/Pages/Modules/Index.casos.md
 * (UC-MOD-01..04, 11..15). Escrito por [CC] em 2026-08-19 e NÃO executado aqui — o veredito é da lane.
 *
 * Notas de montagem:
 *  - A autorização vive no construtor do ModuleManagementController: sessão 'is_admin' OU papel
 *    Spatie 'Admin#<businessId>'. Os dois caminhos são exercitados (UC-MOD-02/04).
 *  - modules_statuses.json é arquivo na RAIZ do projeto. Todo teste que escreve nele faz backup em
 *    beforeEach e restaura em afterEach — sem isso um teste vermelho deixa o repo com módulo desligado.
 *  - Artisan é fakeado nos casos de install/uninstall: rodar migration de verdade aqui deixaria de ser
 *    teste de tela e passaria a ser teste de banco (a lane que faz isso é a nightly).
 */

use AppModelsUser;
use AppServicesModuleManagerService;
use IlluminateSupportFacadesArtisan;
use IlluminateSupportFacadesFile;

const MODULES_STATUSES = 'modules_statuses.json';

beforeEach(function () {
    $this->statusesPath = base_path(MODULES_STATUSES);
    $this->statusesBackup = File::exists($this->statusesPath) ? File::get($this->statusesPath) : null;
});

afterEach(function () {
    if ($this->statusesBackup !== null) {
        File::put($this->statusesPath, $this->statusesBackup);
    }
});

function modulosAdmin(int $businessId = 1): User
{
    return User::factory()->create(['business_id' => $businessId]);
}

// ── UC-MOD-01 ───────────────────────────────────────────────────────────────
it('UC-MOD-01: admin vê o inventário com o contrato de 11 chaves por módulo', function () {
    $user = modulosAdmin();

    $this->actingAs($user)
        ->withSession(['is_admin' => true, 'business.id' => 1, 'user.business_id' => 1])
        ->get('/modulos')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Modules/Index')
            ->has('modules', fn ($modules) => $modules->count() > 0)
            ->has('modules.0', fn ($m) => $m
                ->hasAll([
                    'name', 'alias', 'version', 'description', 'area', 'active',
                    'registered', 'has_migrations', 'migration_count', 'has_datacontroller', 'error',
                ])
            )
        );
});

// ── UC-MOD-02 ───────────────────────────────────────────────────────────────
it('UC-MOD-02: usuário sem admin recebe 403 e nenhuma prop de módulo', function () {
    $response = $this->actingAs(modulosAdmin())
        ->withSession(['is_admin' => false, 'business.id' => 1])
        ->get('/modulos');

    $response->assertForbidden();
    expect($response->getContent())->not->toContain('"modules"');
});

// ── UC-MOD-03 ───────────────────────────────────────────────────────────────
it('UC-MOD-03: sem sessão as quatro rotas barram (401/redirect de auth)', function () {
    foreach ([
        ['get', '/modulos'],
        ['post', '/modulos/Repair/toggle'],
        ['post', '/modulos/Repair/install'],
        ['post', '/modulos/Repair/uninstall'],
    ] as [$verb, $url]) {
        $this->{$verb}($url)->assertStatus(fn ($s) => in_array($s, [401, 302], true));
    }
})->skip(fn () => ! app()->runningUnitTests(), 'placeholder — ajustar ao contrato de auth da lane');

// ── UC-MOD-04 ───────────────────────────────────────────────────────────────
it('UC-MOD-04: admin por papel Spatie entra mesmo sem is_admin na sessão', function () {
    $user = modulosAdmin(1);
    $user->assignRole('Admin#1');

    $this->actingAs($user)
        ->withSession(['business.id' => 1, 'user.business_id' => 1])
        ->get('/modulos')
        ->assertOk();
});

// ── UC-MOD-11 ───────────────────────────────────────────────────────────────
it('UC-MOD-11: toggle grava a chave, mantém as outras e deixa o arquivo ordenado', function () {
    $antes = json_decode(File::get($this->statusesPath), true);
    expect($antes)->toHaveKey('Repair');

    $this->actingAs(modulosAdmin())
        ->withSession(['is_admin' => true, 'business.id' => 1])
        ->post('/modulos/Repair/toggle', ['active' => false])
        ->assertRedirect();

    $depois = json_decode(File::get($this->statusesPath), true);

    expect($depois['Repair'])->toBeFalse();
    expect(array_diff_key($antes, ['Repair' => null]))
        ->toBe(array_diff_key($depois, ['Repair' => null]));

    $chaves = array_keys($depois);
    $ordenadas = $chaves;
    sort($ordenadas, SORT_STRING);
    expect($chaves)->toBe($ordenadas);            // ksort ⇒ diff estável em git
    expect(File::get($this->statusesPath))->toEndWith("\n");
});

it('UC-MOD-11b: toggle sem active (ou não-booleano) responde 422', function (mixed $payload) {
    $this->actingAs(modulosAdmin())
        ->withSession(['is_admin' => true, 'business.id' => 1])
        ->postJson('/modulos/Repair/toggle', $payload)
        ->assertStatus(422);
})->with([
    'sem campo' => [[]],
    'string'    => [['active' => 'talvez']],
    'nulo'      => [['active' => null]],
]);

// ── UC-MOD-12 ───────────────────────────────────────────────────────────────
it('UC-MOD-12: install de módulo inexistente não altera o JSON', function () {
    $antes = File::get($this->statusesPath);

    $this->actingAs(modulosAdmin())
        ->withSession(['is_admin' => true, 'business.id' => 1])
        ->post('/modulos/NaoExiste/install')
        ->assertRedirect()
        ->assertSessionHas('status');

    expect(File::get($this->statusesPath))->toBe($antes);
});

it('UC-MOD-12b: install chama module:migrate --force para o módulo pedido', function () {
    Artisan::spy();

    $this->actingAs(modulosAdmin())
        ->withSession(['is_admin' => true, 'business.id' => 1])
        ->post('/modulos/Repair/install')
        ->assertRedirect();

    Artisan::shouldHaveReceived('call')
        ->withArgs(fn ($cmd, $args = []) => $cmd === 'module:migrate'
            && ($args['module'] ?? null) === 'Repair'
            && ($args['--force'] ?? false) === true);
});

// ── UC-MOD-13 (defeito conhecido — ver PATCHES.md) ──────────────────────────
it('UC-MOD-13: install que falha não deixa o módulo marcado como ativo', function () {
    Artisan::partialMock()
        ->shouldReceive('call')
        ->with('module:migrate', \Mockery::any())
        ->andThrow(new RuntimeException('SQLSTATE[42S01]: base table already exists'));

    $service = app(ModuleManagerService::class);
    $resultado = $service->install('Repair');

    expect($resultado['success'])->toBeFalse();

    $statuses = json_decode(File::get($this->statusesPath), true);
    expect($statuses['Repair'])->toBeFalse();  // hoje FALHA: setActive(true) roda antes do migrate
});

// ── UC-MOD-14 ───────────────────────────────────────────────────────────────
it('UC-MOD-14: uninstall só desativa — nenhuma tabela é derrubada', function () {
    $tabela = 'repair_statuses';  // tabela conhecida do módulo Repair
    expect(\Illuminate\Support\Facades\Schema::hasTable($tabela))->toBeTrue();

    $this->actingAs(modulosAdmin())
        ->withSession(['is_admin' => true, 'business.id' => 1])
        ->post('/modulos/Repair/uninstall')
        ->assertRedirect();

    expect(json_decode(File::get($this->statusesPath), true)['Repair'])->toBeFalse();
    expect(\Illuminate\Support\Facades\Schema::hasTable($tabela))->toBeTrue();
});

// ── UC-MOD-15 ───────────────────────────────────────────────────────────────
it('UC-MOD-15: a lista é idêntica entre negócios — cross-tenant é lei, não drift', function () {
    $extrai = function (int $businessId) {
        $r = $this->actingAs(modulosAdmin($businessId))
            ->withSession(['is_admin' => true, 'business.id' => $businessId, 'user.business_id' => $businessId])
            ->get('/modulos')
            ->assertOk();

        return collect(data_get($r->viewData('page') ?? [], 'props.modules', []))
            ->map(fn ($m) => $m['name'].':'.(int) $m['active'])
            ->values()
            ->all();
    };

    expect($extrai(1))->toBe($extrai(99));
});
