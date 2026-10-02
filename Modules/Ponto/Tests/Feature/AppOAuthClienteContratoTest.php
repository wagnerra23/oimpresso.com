<?php

declare(strict_types=1);

use App\Auth\AppMobileOAuth;
use App\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Ponto\Tests\Feature\PontoTestCase;

uses(PontoTestCase::class);

/**
 * Login do app das lojas (oimpresso-app) — client OAuth PÚBLICO + password grant.
 *
 * Fonte do contrato: decisão [W] 2026-10-01 (telas próprias no app, API Passport) e as
 * travas do login web em LoginController::authenticated — que o /oauth/token não aplicava.
 * NÃO derivado de App\Auth\AppMobileOAuth.
 *
 * Tier 0: tenant fictício 98 (ADR 0358). Transação revertida por caso.
 */

const AOC_BIZ = 98;
const AOC_SENHA = 'senha-de-teste-aoc';

function aocUsuario(array $extra = []): User
{
    $id = DB::table('users')->insertGetId(array_merge([
        'first_name' => 'AOC teste', 'username' => 'aoc_' . uniqid(), 'password' => Hash::make(AOC_SENHA),
        'business_id' => AOC_BIZ, 'status' => 'active', 'allow_login' => 1, 'user_type' => 'user',
        'created_at' => now(), 'updated_at' => now(),
    ], $extra));

    return User::findOrFail($id);
}

function aocToken($teste, int $clientId, string $username)
{
    return $teste->withHeaders(['Accept' => 'application/json'])->postJson('/oauth/token', [
        'grant_type' => 'password', 'client_id' => $clientId,
        'username' => $username, 'password' => AOC_SENHA, 'scope' => '',
    ]);
}

function aocClienteId(): int
{
    Artisan::call('app-mobile:oauth-client');

    return (int) DB::table('oauth_clients')->where('name', AppMobileOAuth::CLIENT_NAME)->value('id');
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Schema UltimatePOS + oauth_clients legado exigem MySQL (ADR 0358).');
    }
    if (! DB::table('business')->where('id', AOC_BIZ)->exists()) {
        $this->markTestSkipped('Tenant 98 ausente nesta lane.');
    }
    if (! file_exists(storage_path('oauth-private.key'))) {
        Artisan::call('passport:keys', ['--force' => true]);
    }
    DB::beginTransaction();
    DB::table('business')->where('id', AOC_BIZ)->update(['is_active' => 1]);
});

afterEach(function () {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

it('o comando cria UM client público (sem secret, password grant) e é idempotente', function () {
    $antes = DB::table('oauth_clients')->where('name', AppMobileOAuth::CLIENT_NAME)->count();
    $id1 = aocClienteId();
    $id2 = aocClienteId();

    expect($id1)->toBeGreaterThan(0)->and($id2)->toBe($id1);
    expect(DB::table('oauth_clients')->where('name', AppMobileOAuth::CLIENT_NAME)->count())->toBe(max($antes, 1));

    $c = DB::table('oauth_clients')->find($id1);
    expect($c->secret)->toBe('')
        ->and((int) $c->password_client)->toBe(1)
        ->and((int) $c->revoked)->toBe(0);
});

it('usuário ativo entra pelo app SEM client_secret e recebe access_token', function () {
    $u = aocUsuario();

    $r = aocToken($this, aocClienteId(), $u->username);

    $r->assertOk();
    expect($r->json('access_token'))->toBeString()->not->toBeEmpty();
    expect($r->json('token_type'))->toBe('Bearer');
});

it('o token do app autentica na API do ponto como o próprio usuário', function () {
    $u = aocUsuario();
    $token = aocToken($this, aocClienteId(), $u->username)->json('access_token');

    // /api/user devolve o usuário dono do token (guard api).
    $this->withHeaders(['Accept' => 'application/json', 'Authorization' => 'Bearer ' . $token])
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('id', $u->id);
});

it('trava do login web vale no app: allow_login=0, usuário inativo e cliente CRM NÃO recebem token', function () {
    $cliente = aocClienteId();

    foreach ([
        ['allow_login' => 0],
        ['status' => 'inactive'],
        ['user_type' => 'user_customer'],
    ] as $extra) {
        $u = aocUsuario($extra);
        $r = aocToken($this, $cliente, $u->username);
        expect($r->getStatusCode())->toBe(400);
        expect($r->json('error'))->toBe('invalid_grant');
    }
});

it('empresa inativa: nenhum usuário dela entra pelo app', function () {
    $u = aocUsuario();
    DB::table('business')->where('id', AOC_BIZ)->update(['is_active' => 0]);

    $r = aocToken($this, aocClienteId(), $u->username);

    expect($r->getStatusCode())->toBe(400);
    expect($r->json('error'))->toBe('invalid_grant');
});

it('senha errada continua recusada (controle negativo)', function () {
    $u = aocUsuario();

    $r = $this->withHeaders(['Accept' => 'application/json'])->postJson('/oauth/token', [
        'grant_type' => 'password', 'client_id' => aocClienteId(),
        'username' => $u->username, 'password' => 'errada', 'scope' => '',
    ]);

    expect($r->getStatusCode())->toBe(400);
    expect($r->json('error'))->toBe('invalid_grant');
});
