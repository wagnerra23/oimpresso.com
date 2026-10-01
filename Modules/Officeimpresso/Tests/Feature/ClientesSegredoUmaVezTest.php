<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Thread 05 do playbook Officeimpresso — metade de segurança do painel OAuth.
 *
 * O painel `/officeimpresso/client` imprimia o `secret` de TODOS os clients na lista
 * (`makeVisible('secret')` + coluna com botão de revelar). Agora:
 *   - a lista não seleciona a coluna `secret` (precedente: painel do Connector, #8350);
 *   - o segredo aparece UMA vez, no flash da criação, num bloco copiável;
 *   - a permissão segue a mesma: `superadmin` OU `officeimpresso.clientes.liberar`.
 *
 * Aposentar o painel (D1) ficou decisão [W] — o charter do Connector proíbe delegar
 * emissão de credencial; ver `_saida-05.md`.
 *
 * Tenant 98 (seed) + adversário 99. Nunca biz=4. Sem RefreshDatabase (UltimatePOS).
 */

defined('PERM_OI_LIBERAR_05') || define('PERM_OI_LIBERAR_05', 'officeimpresso.clientes.liberar');

beforeEach(function () {
    // layouts/app.blade.php lê REMOTE_ADDR e HTTP_USER_AGENT do superglobal
    // (mesma razão documentada no LicencasAcessoPermissionTest).
    $_SERVER['REMOTE_ADDR'] ??= '127.0.0.1';
    $_SERVER['HTTP_USER_AGENT'] ??= 'Pest/CI (X11; Linux x86_64) HeadlessChrome';

    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: Passport + schema UltimatePOS exigem MySQL (ADR 0358).');
    }

    Permission::firstOrCreate(['name' => PERM_OI_LIBERAR_05, 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);

    // O tenant de teste (biz=98) faz o papel da empresa OPERADORA: `clientes.liberar`
    // só vale para usuário dela (AcessoOperador). Os casos de cliente usam o biz=99.
    if ($operador = static::resolveSeededTenant()) {
        config(['constants.operator_business_id' => (int) $operador->id]);
    }
});

it('thread 05 · a lista não imprime o secret de client existente', function () {
    $business = $this->seededTenant();
    $admin = makeOiSegredoTestUser($business->id);
    $admin->givePermissionTo('superadmin');
    [, $secret] = makeOiSegredoClient($admin, 'Desktop balcão 05');

    $res = $this->actingAs($admin)->get('/officeimpresso/client');

    $res->assertOk();
    $res->assertSee('Desktop balcão 05');
    $res->assertDontSee($secret);
    $res->assertDontSee('data-secret', false);
});

it('thread 05 · delegado cria e vê o secret uma única vez', function () {
    $business = $this->seededTenant();
    $delegado = makeOiSegredoTestUser($business->id);
    $delegado->givePermissionTo(PERM_OI_LIBERAR_05);
    $nome = 'Delphi 05 '.Str::random(6);

    $this->actingAs($delegado)
        ->from('/officeimpresso/client')
        ->post('/officeimpresso/client', ['name' => $nome])
        ->assertRedirect('/officeimpresso/client');

    // O Passport 13 grava o hash; o texto puro só existe no flash da criação.
    $secret = session('officeimpresso_credencial.secret');
    expect($secret)->toBeString();
    expect(strlen($secret))->toBe(40);
    expect(DB::table('oauth_clients')->where('name', $nome)->exists())->toBeTrue();

    // 1ª abertura depois da criação: o bloco copiável traz o segredo.
    $this->get('/officeimpresso/client')->assertOk()->assertSee($secret);
    // 2ª abertura: o flash foi consumido — o segredo não volta mais.
    $this->get('/officeimpresso/client')->assertOk()->assertSee($nome)->assertDontSee($secret);
});

it('thread 05 · sem superadmin nem a permissão delegável → 403 na lista e na criação', function () {
    $business = $this->seededTenant();
    $nenhuma = makeOiSegredoTestUser($business->id);
    $nome = 'Tentativa 05 '.Str::random(6);

    $this->actingAs($nenhuma)->get('/officeimpresso/client')->assertForbidden();
    $this->actingAs($nenhuma)->post('/officeimpresso/client', ['name' => $nome])->assertForbidden();

    expect(DB::table('oauth_clients')->where('name', $nome)->exists())->toBeFalse();
});

it('thread 05 · a lista é do negócio da sessão (98 não vê o client do 99)', function () {
    $meu = $this->seededTenant();
    $outro = $this->seededSupportClientTenant();

    $delegado = makeOiSegredoTestUser($meu->id);
    $delegado->givePermissionTo(PERM_OI_LIBERAR_05);
    makeOiSegredoClient($delegado, 'Client do 98 · 05');

    $alheio = makeOiSegredoTestUser($outro->id);
    makeOiSegredoClient($alheio, 'Client do 99 · 05');

    $this->actingAs($delegado)->get('/officeimpresso/client')
        ->assertOk()
        ->assertSee('Client do 98 · 05')
        ->assertDontSee('Client do 99 · 05');
});

// ── Trava do negócio operador (2026-10-01) ─────────────────────────────────────
// Criar credencial de password grant contorna o bloqueio de empresa do login desktop
// (User::validateForPassportPasswordGrant só bloqueia os client_id fixos do Delphi).
// `clientes.liberar` passa a valer só para usuário da operadora; superadmin segue valendo.

it('operador · usuário de empresa CLIENTE com clientes.liberar leva 403 e não cria credencial', function () {
    $cliente = $this->seededSupportClientTenant();
    $user = makeOiSegredoTestUser((int) $cliente->id);
    $user->givePermissionTo(PERM_OI_LIBERAR_05);
    // Pré-condição anti-vácuo: o 403 tem de vir da trava, não de faltar a permissão.
    expect($user->can(PERM_OI_LIBERAR_05))->toBeTrue();
    $nome = 'Cliente burla 05 '.Str::random(6);

    $this->actingAs($user)->get('/officeimpresso/client')->assertForbidden();
    $this->actingAs($user)->post('/officeimpresso/client', ['name' => $nome])->assertForbidden();
    $this->actingAs($user)->get('/officeimpresso')->assertForbidden();

    expect(DB::table('oauth_clients')->where('name', $nome)->exists())->toBeFalse();
});

it('operador · Admin de empresa CLIENTE (Gate::before) não cria credencial nem vê o link Clientes', function () {
    $cliente = $this->seededSupportClientTenant();
    $cid = (int) $cliente->id;

    $nomeRole = 'Admin#'.$cid;
    $existia = \Spatie\Permission\Models\Role::where('name', $nomeRole)->exists();
    $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => $nomeRole, 'guard_name' => 'web'], ['business_id' => $cid]);

    $admin = makeOiSegredoTestUser($cid);
    $admin->assignRole($role);
    // Pré-condição: o bypass existe.
    expect($admin->can(PERM_OI_LIBERAR_05))->toBeTrue();
    $nome = 'Admin cliente 05 '.Str::random(6);

    $this->actingAs($admin)->post('/officeimpresso/client', ['name' => $nome])->assertForbidden();
    expect(DB::table('oauth_clients')->where('name', $nome)->exists())->toBeFalse();

    $hrefs = array_column(app(\App\Services\LegacyMenuAdapter::class)->buildTopNavs()['Officeimpresso']['items'] ?? [], 'href');
    expect($hrefs)->not->toContain('/officeimpresso/client');

    $admin->forceDelete();
    if (! $existia) {
        $role->delete();
    }
});

it('operador · a MESMA conta cria credencial quando o config diz que a empresa dela é a operadora', function () {
    $cliente = $this->seededSupportClientTenant();
    $user = makeOiSegredoTestUser((int) $cliente->id);
    $user->givePermissionTo(PERM_OI_LIBERAR_05);
    config(['constants.operator_business_id' => (int) $cliente->id]);
    $nome = 'Operador 99 05 '.Str::random(6);

    $this->actingAs($user)
        ->from('/officeimpresso/client')
        ->post('/officeimpresso/client', ['name' => $nome])
        ->assertRedirect('/officeimpresso/client');

    expect(DB::table('oauth_clients')->where('name', $nome)->exists())->toBeTrue();
});

/**
 * User de teste SEM role (senão o Gate::before faria bypass de tudo).
 */
function makeOiSegredoTestUser(int $businessId): User
{
    return User::create([
        'business_id' => $businessId,
        'first_name'  => 'OI',
        'surname'     => 'Segredo05',
        'username'    => 'oi_segredo05_'.$businessId.'_'.uniqid(),
        'email'       => 'oi_segredo05_'.$businessId.'_'.uniqid().'@test.local',
        'password'    => bcrypt('test12345'),
        'language'    => 'pt_BR',
    ]);
}

/**
 * Fixture por query builder, não pelo model: o Passport 13 gera UUID no `id` e o
 * schema legado é `int` auto-increment — dois clients no mesmo teste colidiam
 * (medido no CI do #8362). O secret entra cru, que é o caso do legado em campo.
 *
 * @return array{0: int, 1: string}
 */
function makeOiSegredoClient(User $owner, string $name): array
{
    $secret = Str::random(40);
    $id = DB::table('oauth_clients')->insertGetId([
        'user_id' => $owner->id,
        'name' => $name,
        'secret' => $secret,
        'redirect' => 'http://localhost',
        'personal_access_client' => 0,
        'password_client' => 1,
        'revoked' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [(int) $id, $secret];
}
