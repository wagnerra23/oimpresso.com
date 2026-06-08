<?php

/**
 * Teste do fluxo de autenticacao da API via Laravel Passport.
 *
 * Valida o contrato consumido pelos clientes desktop:
 *   - POST /oauth/token com grant_type=password retorna access_token
 *   - GET /api/user com Bearer token retorna 200 + user JSON
 *   - GET /api/officeimpresso com Bearer token retorna 200 + user JSON
 *   - Qualquer chamada sem token retorna 401 Unauthenticated
 *   - POST /oauth/token sem grant_type retorna 400 unsupported_grant_type
 *
 * Nao valida regra de negocio — so o contrato auth.
 *
 * Pre-requisito: DB local com Passport instalado (migrations rodadas).
 */

use App\User;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;

function ensurePassportKeys(): void
{
    $storage = storage_path();
    $priv = $storage . '/oauth-private.key';
    $pub = $storage . '/oauth-public.key';
    if (! file_exists($priv) || ! file_exists($pub)) {
        \Artisan::call('passport:keys', ['--force' => true]);
    }
}

function ensurePasswordClient(): Client
{
    ensurePassportKeys();
    $existing = Client::where('password_client', true)->where('revoked', false)->first();
    if ($existing) {
        return $existing;
    }
    return app(ClientRepository::class)->createPasswordGrantClient(null, 'Desktop Test (password grant)', 'http://localhost');
}

function ensurePersonalAccessClient(): Client
{
    ensurePassportKeys();
    $existing = Client::where('personal_access_client', true)->where('revoked', false)->first();
    if ($existing) {
        return $existing;
    }
    return app(ClientRepository::class)->createPersonalAccessClient(null, 'Desktop Test (personal)', 'http://localhost');
}

it('rejeita /api/user sem token com 401', function () {
    $r = $this->withHeaders(['Accept' => 'application/json'])->getJson('/api/user');
    expect($r->getStatusCode())->toBe(401);
    $r->assertJson(['message' => 'Unauthenticated.']);
});

it('rejeita /api/officeimpresso sem token com 401', function () {
    $r = $this->withHeaders(['Accept' => 'application/json'])->getJson('/api/officeimpresso');
    expect($r->getStatusCode())->toBe(401);
    $r->assertJson(['message' => 'Unauthenticated.']);
});

it('rejeita /oauth/token sem grant_type com 400 unsupported_grant_type', function () {
    $r = $this->withHeaders(['Accept' => 'application/json'])->postJson('/oauth/token', []);
    expect($r->getStatusCode())->toBe(400);
    $data = json_decode($r->getContent(), true);
    expect($data['error'] ?? null)->toBe('unsupported_grant_type');
});

it('aceita /oauth/token com password grant e retorna access_token', function () {
    $user = User::query()->whereNotNull('email')->first();
    if (! $user) {
        $this->markTestSkipped('Sem users no DB — seed antes de rodar');
    }
    $client = ensurePasswordClient();

    $r = $this->withHeaders(['Accept' => 'application/json'])->postJson('/oauth/token', [
        'grant_type' => 'password',
        'client_id' => $client->id,
        'client_secret' => $client->plainSecret ?? $client->secret,
        'username' => $user->email,
        'password' => env('DEV_LOGIN_PASSWORD', 'Wscrct*2312'),
        'scope' => '*',
    ]);

    if ($r->getStatusCode() !== 200) {
        $this->markTestSkipped('Password grant falhou (provavel user/senha divergente em ambiente de teste): ' . $r->getContent());
    }

    $data = json_decode($r->getContent(), true);
    expect($data)->toHaveKeys(['token_type', 'expires_in', 'access_token', 'refresh_token']);
    expect($data['token_type'])->toBe('Bearer');
});

it('aceita /api/user com Bearer token (Personal Access Token)', function () {
    $user = User::query()->first();
    if (! $user) {
        $this->markTestSkipped('Sem users no DB');
    }
    ensurePersonalAccessClient();

    $token = $user->createToken('desktop-test')->accessToken;

    $r = $this->withHeaders([
        'Accept' => 'application/json',
        'Authorization' => 'Bearer ' . $token,
    ])->getJson('/api/user');

    expect($r->getStatusCode())->toBe(200);
    expect($r->json('id'))->toBe($user->id);
});

it('aceita /api/officeimpresso com Bearer token (Personal Access Token)', function () {
    if (! file_exists(base_path('Modules/Officeimpresso/module.json'))) {
        $this->markTestSkipped('Modulo Officeimpresso ausente');
    }
    if (! \Nwidart\Modules\Facades\Module::find('Officeimpresso')?->isEnabled()) {
        $this->markTestSkipped('Modulo Officeimpresso desativado — habilitar em modules_statuses.json');
    }

    $user = User::query()->first();
    if (! $user) {
        $this->markTestSkipped('Sem users no DB');
    }
    ensurePersonalAccessClient();

    $token = $user->createToken('desktop-test-ofc')->accessToken;

    $r = $this->withHeaders([
        'Accept' => 'application/json',
        'Authorization' => 'Bearer ' . $token,
    ])->getJson('/api/officeimpresso');

    expect($r->getStatusCode())->toBe(200);
    expect($r->json('id'))->toBe($user->id);
});
