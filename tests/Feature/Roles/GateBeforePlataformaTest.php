<?php

declare(strict_types=1);

// Tests\TestCase já é aplicado globalmente em tests/Pest.php (uses(TestCase::class)->in('Feature')). NÃO redeclarar aqui — Pest 4 lança TestCaseAlreadyInUse.

use App\Providers\AuthServiceProvider;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Database\Seeders\McpScopesSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(DatabaseTransactions::class);

/**
 * D-GATE (ADR 0415) — o papel `Admin#{business_id}` NÃO herda permissão de PLATAFORMA.
 *
 * ── O QUE ESTAVA ERRADO (medido em origin/main 723d2b1e6, 2026-09-24)
 *   `Gate::before` (app/Providers/AuthServiceProvider.php) devolvia `true` pro dono de
 *   qualquer empresa em QUALQUER ability fora de backup/superadmin/manage_modules. Isso
 *   incluía as permissões que abrem dado de TODAS as empresas: `jana.mcp.usage.all`
 *   (hub Forja + /governance/qualidade-ia, que lê ?business_id= da URL),
 *   `jana.mcp.memory.manage`, `jana.cc.read.all`, `jana.superadmin`
 *   (MetasController::store aceita business_id alheio quando `can()` é true).
 *
 * ── A REGRA NOVA
 *   Permissão de plataforma = scopes `admin_only` do catálogo MCP + `jana.superadmin`
 *   (fonte única: AuthServiceProvider::permissoesDePlataforma()). Nelas passa quem está
 *   em `administrator_usernames` ou quem tem a permissão DE VERDADE (Spatie). As demais
 *   ~345 permissões do ERP seguem liberadas pro dono, sem mudança.
 *
 * ── CONTROLE
 *   O caso "CONTROLE" prova que o bypass continua VIVO pro resto do ERP — sem ele, o
 *   `false` dos casos MORDE poderia vir de o Admin#98 não estar sendo reconhecido, e o
 *   teste mediria a coisa errada.
 *
 * TENANT: 98 canônico, 99 adversário (ADR 0358). NUNCA biz=4, NUNCA biz=1.
 * NÃO RODADO LOCAL: Pest é CT 100/CI only (proibicoes.md §Ambiente).
 *
 * @see app/Providers/AuthServiceProvider.php
 * @see memory/decisions/0415-gate-before-permissoes-de-plataforma-fora-do-bypass.md
 */

const GBP_BIZ = 98;

beforeEach(function () {
    if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
        $this->markTestSkipped('Sem tabelas Spatie — lane sem schema MySQL.');
    }

    $user = User::where('business_id', GBP_BIZ)->first();
    if (! $user) {
        $this->markTestSkipped('Sem user em business_id=98 (tenant canônico ADR 0358).');
    }

    $attrs = ['name' => 'Admin#'.GBP_BIZ, 'guard_name' => 'web'];
    if (Schema::hasColumn('roles', 'business_id')) {
        $attrs['business_id'] = GBP_BIZ;
    }
    $roleAdmin = Role::firstOrCreate($attrs);

    foreach (AuthServiceProvider::permissoesDePlataforma() as $slug) {
        Permission::findOrCreate($slug, 'web');
        // O papel Admin#98 do seed não pode carregar a permissão de verdade — senão os
        // casos MORDE mediriam a concessão real, não o bypass. Rollback pela transação.
        $roleAdmin->revokePermissionTo($slug);
        $user->revokePermissionTo($slug);
    }

    $user->assignRole($roleAdmin);
    $user->user_type = 'user';
    $user->save();

    config(['constants.administrator_usernames' => 'ninguem-gbp-'.uniqid()]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->user = $user->fresh();
});

it('fonte única: a lista de plataforma é admin_only do catálogo MCP + jana.superadmin', function () {
    $adminOnly = array_values(array_map(
        fn (array $s) => $s['slug'],
        array_filter(McpScopesSeeder::catalogo(), fn (array $s) => ($s['admin_only'] ?? false) === true)
    ));

    // Controle positivo: se o catálogo parar de marcar admin_only, a lista encolhe em
    // silêncio e o bypass volta — isto quebra antes.
    expect(count($adminOnly))->toBeGreaterThanOrEqual(5);

    $esperado = array_merge($adminOnly, ['jana.superadmin']);
    sort($esperado);
    $real = AuthServiceProvider::permissoesDePlataforma();
    sort($real);

    expect($real)->toBe($esperado);
    expect($real)->toContain('jana.mcp.usage.all');
})->group('tier0');

it('CONTROLE: o dono segue liberado no resto do ERP pelo Gate::before', function () {
    // Ability sem permission cadastrada: só o bypass pode devolver true aqui.
    expect($this->user->hasRole('Admin#'.GBP_BIZ))->toBeTrue();
    expect($this->user->can('gbp-controle-ability-sem-permission'))->toBeTrue();
})->group('tier0');

it('MORDE: o dono de empresa NÃO herda permissão de plataforma pelo papel Admin', function (string $slug) {
    expect($this->user->hasPermissionTo($slug))->toBeFalse();
    expect($this->user->can($slug))->toBeFalse();
})->with(fn () => AuthServiceProvider::permissoesDePlataforma())->group('tier0');

it('não regride: quem tem a permissão de VERDADE segue entrando', function () {
    $this->user->givePermissionTo('jana.mcp.usage.all');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($this->user->fresh()->can('jana.mcp.usage.all'))->toBeTrue();
})->group('tier0');

it('não regride: administrator_usernames segue entrando sem a permissão', function () {
    config(['constants.administrator_usernames' => 'outro,'.strtoupper((string) $this->user->username)]);

    expect($this->user->hasPermissionTo('jana.superadmin'))->toBeFalse();
    expect($this->user->can('jana.superadmin'))->toBeTrue();
})->group('tier0');

it('MORDE por HTTP: /governance/qualidade-ia de outra empresa é 403 pro dono', function () {
    $this->actingAs($this->user);
    session(['user.business_id' => GBP_BIZ]);

    $this->get('/governance/qualidade-ia?business_id=99')->assertStatus(403);
})->group('tier0');

it('CONTROLE por HTTP: com a permissão de verdade, a mesma URL não é 403', function () {
    // Sem este par, o 403 de cima poderia vir de outra trava da pilha de middleware.
    $this->user->givePermissionTo('jana.mcp.usage.all');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($this->user->fresh());
    session(['user.business_id' => GBP_BIZ]);

    $status = $this->get('/governance/qualidade-ia?business_id=99')->status();

    expect($status)->not->toBe(403);
    expect($status)->toBeLessThan(500);   // anti-vácuo: 5xx não passa por "não é 403"
})->group('tier0');
