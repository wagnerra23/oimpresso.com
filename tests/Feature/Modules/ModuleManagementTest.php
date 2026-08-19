<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// Tests\TestCase já aplicado globalmente em tests/Pest.php. NÃO redeclarar.

/**
 * ModuleManagementController — porta HTTP do /modulos (US-SUPER-006).
 *
 * A tela é app-wide cross-tenant POR DESENHO (Index.charter.md): não há `business_id` scope
 * aqui. O que precisa estar travado é QUEM entra — e o UC-MOD-15 trava a exceção contra
 * "consertos" bem-intencionados.
 *
 * Nenhum teste daqui ESCREVE no `modules_statuses.json` real: mutação vive no
 * ModuleManagerServiceTest, em sandbox. Aqui só leitura + autorização + validação.
 *
 * Tenant: NUNCA biz=4 (ROTA LIVRE, cliente Larissa) — ADR 0358.
 *
 * UCs: 01, 02, 03, 04, 06, 11 (só o 422), 15.
 *
 * @see app/Http/Controllers/ModuleManagementController.php
 * @see resources/js/Pages/Modules/Index.casos.md
 */

const BIZ_MOD_TESTE = 1;
const BIZ_MOD_OUTRO = 2;

beforeEach(function () {
    config()->set('otel.enabled', false);

    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Requer User do schema UltimatePOS (lane MySQL — CT 100/CI)');
    }
});

function moduloUser(int $businessId = BIZ_MOD_TESTE): \App\User
{
    $user = \App\User::query()->where('business_id', $businessId)->first();

    if (! $user) {
        test()->markTestSkipped("User biz={$businessId} ausente — rode os seeders UltimatePOS primeiro");
    }

    return $user;
}

/**
 * Concede `manage_modules` ao user e devolve um callback de limpeza.
 *
 * Depois da D2 (2026-08-19) a tela autoriza SO por essa permissao. `session('is_admin')`
 * nao autoriza mais nada aqui, e o Gate::before (AuthServiceProvider) trata
 * `manage_modules` como ability de superadmin: o atalho por papel `Admin#<biz>` do `else`
 * NAO se aplica a ela. Entao o unico caminho testavel e a concessao Spatie explicita.
 *
 * A base do CT 100 persiste entre runs — por isso o cleanup remove o que este arquivo criou.
 */
function concedeManageModules(\App\User $user): callable
{
    $perm = \Spatie\Permission\Models\Permission::firstOrCreate([
        'name' => 'manage_modules', 'guard_name' => 'web',
    ]);
    $criouPermissao = $perm->wasRecentlyCreated;
    $jaTinha = $user->hasPermissionTo($perm);

    if (! $jaTinha) {
        $user->givePermissionTo($perm);
    }

    return function () use ($user, $perm, $jaTinha, $criouPermissao) {
        if (! $jaTinha) {
            $user->revokePermissionTo($perm);
        }
        if ($criouPermissao) {
            $perm->delete();
        }
    };
}

/** Extrai as props Inertia de uma resposta de página. */
function modulosProps(\Illuminate\Testing\TestResponse $response): array
{
    return $response->viewData('page')['props'] ?? [];
}

it('UC-MOD-01 · admin abre a tela e recebe o inventário com o contrato de 11 chaves', function () {
    session(['user.business_id' => BIZ_MOD_TESTE, 'business.id' => BIZ_MOD_TESTE]);

    $user = moduloUser();
    $limpa = concedeManageModules($user);

    try {
        $response = $this->actingAs($user)->get('/modulos');
        $response->assertOk();
        $props = modulosProps($response);

    expect($props['component'] ?? 'Modules/Index')->toBeString()
        ->and($props['modules'])->toBeArray()->not->toBeEmpty()
        ->and($props['modules'][0])->toHaveKeys([
            'name', 'alias', 'version', 'description', 'area',
            'active', 'registered', 'has_migrations', 'migration_count',
            'has_datacontroller', 'error',
            ]);
    } finally {
        $limpa();
    }
})->group('modules');

it('UC-MOD-02 · usuário autenticado sem admin recebe 403 e nenhuma prop de módulo', function () {
    session(['user.business_id' => BIZ_MOD_TESTE, 'business.id' => BIZ_MOD_TESTE]);

    $response = $this->actingAs(moduloUser())->get('/modulos');

    $response->assertStatus(403);
    expect($response->getContent())->not->toContain('has_datacontroller');
})->group('modules');

it('UC-MOD-03 · visitante sem sessão é barrado, e as quatro rotas existem', function () {
    expect(Route::has('modules.index'))->toBeTrue();

    $uris = collect(Route::getRoutes()->getRoutes())
        ->map(fn ($r) => $r->uri())
        ->filter(fn (string $u) => str_starts_with($u, 'modulos'))
        ->values()
        ->all();

    expect($uris)->toContain('modulos')
        ->and($uris)->toContain('modulos/{name}/toggle')
        ->and($uris)->toContain('modulos/{name}/install')
        ->and($uris)->toContain('modulos/{name}/uninstall');

    // sem sessão: o stack web manda pro login (302) ou nega (401)
    expect($this->get('/modulos')->getStatusCode())->toBeIn([301, 302, 401]);
})->group('modules');

it('UC-MOD-04 - admin de UM negocio NAO entra numa tela que desliga modulo do app inteiro', function () {
    // Era ACHADO ate 2026-08-19: o construtor aceitava `Admin#<biz>`, que e admin DE UM
    // NEGOCIO, numa tela app-wide. Medido na epoca: entrava com 200. Depois da D2 a tela
    // autoriza so por `manage_modules`, e o Gate::before exclui de proposito o atalho por
    // papel para essa ability -- entao o papel sozinho nao basta mais.
    $user = moduloUser();
    $nomeRole = 'Admin#' . BIZ_MOD_TESTE;

    $roleExistente = \Spatie\Permission\Models\Role::where('name', $nomeRole)
        ->where('business_id', BIZ_MOD_TESTE)
        ->first();

    $role = $roleExistente ?? \Spatie\Permission\Models\Role::create([
        'name' => $nomeRole, 'guard_name' => 'web', 'business_id' => BIZ_MOD_TESTE,
    ]);

    $jaTinhaPapel = $user->hasRole($nomeRole);
    if (! $jaTinhaPapel) {
        $user->assignRole($role);
    }

    try {
        session(['user.business_id' => BIZ_MOD_TESTE, 'business.id' => BIZ_MOD_TESTE]);

        $this->actingAs($user)
            ->get('/modulos')
            ->assertStatus(403);
    } finally {
        if (! $jaTinhaPapel) {
            $user->removeRole($role);
        }
        if (! $roleExistente) {
            $role->delete();
        }
    }
})->group('modules');

it('UC-MOD-04 - quem TEM manage_modules entra: uma lei so, a mesma do menu e do legado', function () {
    // Controle positivo do caso acima. Sem ele, o 403 poderia ser "ninguem entra" em vez de
    // "so entra quem deve" -- e a tela estaria quebrada, nao consertada.
    $user = moduloUser();
    $limpa = concedeManageModules($user);

    try {
        session(['user.business_id' => BIZ_MOD_TESTE, 'business.id' => BIZ_MOD_TESTE]);

        $this->actingAs($user)
            ->get('/modulos')
            ->assertOk();
    } finally {
        $limpa();
    }
})->group('modules');

it('UC-MOD-06 · [ACHADO] chave do statuses sem pasta não vira linha, e a tela é silenciosa', function () {
    session(['user.business_id' => BIZ_MOD_TESTE, 'business.id' => BIZ_MOD_TESTE]);

    $user = moduloUser();
    $limpa = concedeManageModules($user);
    $response = $this->actingAs($user)->get('/modulos');
    $limpa();
    $modules = modulosProps($response)['modules'];

    $nomesNaTela = collect($modules)->pluck('name')->all();
    $chavesNoJson = array_keys(json_decode(file_get_contents(base_path('modules_statuses.json')), true));

    $orfas = array_values(array_diff($chavesNoJson, $nomesNaTela));

    // Nenhuma órfã pode ter pasta (isso seria regressão da R2 — pasta some da lista).
    foreach ($orfas as $orfa) {
        expect(is_dir(base_path("Modules/{$orfa}")))->toBeFalse(
            "'{$orfa}' tem pasta e ficou fora da lista — regressão de R2."
        );
    }

    // Caracterização do achado: hoje existem órfãs e nada na tela as comunica.
    expect($orfas)->not->toBeEmpty('se zerou, o statuses foi limpo (patch P8) — atualize o UC-MOD-06');
})->group('modules');

it('UC-MOD-11 · alternar sem informar o estado desejado é recusado com 422', function () {
    session(['user.business_id' => BIZ_MOD_TESTE, 'business.id' => BIZ_MOD_TESTE]);

    $user = moduloUser();
    $limpa = concedeManageModules($user);

    try {
        $this->actingAs($user)
            ->postJson('/modulos/Jana/toggle', [])
            ->assertStatus(422);
    } finally {
        $limpa();
    }
})->group('modules');

it('UC-MOD-11 · alternar exige admin: usuário comum não consegue nem tentar', function () {
    session(['user.business_id' => BIZ_MOD_TESTE, 'business.id' => BIZ_MOD_TESTE]);

    $this->actingAs(moduloUser())
        ->postJson('/modulos/Jana/toggle', ['active' => false])
        ->assertStatus(403);
})->group('modules');

it('UC-MOD-15 · a lista é idêntica entre negócios — cross-tenant é lei, não drift', function () {
    $extrai = function (int $businessId) {
        session(['user.business_id' => $businessId, 'business.id' => $businessId]);

        $user = moduloUser($businessId);
        $limpa = concedeManageModules($user);

        try {
            $response = $this->actingAs($user)->get('/modulos');
            $response->assertOk();
        } finally {
            $limpa();
        }

        return collect(modulosProps($response)['modules'])
            ->map(fn ($m) => $m['name'] . ':' . (int) $m['active'])
            ->values()
            ->all();
    };

    expect($extrai(BIZ_MOD_TESTE))->toBe($extrai(BIZ_MOD_OUTRO));
})->group('modules');
