<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

// TestCase já vem do tests/Pest.php (uses(TestCase::class)->in('Feature')); repetir derruba a suíte.
uses(DatabaseTransactions::class);

/**
 * Contrato de autenticação que o desktop Delphi (Office Impresso) consome via Laravel Passport:
 *   - POST /oauth/token com grant_type=password devolve access_token (Bearer);
 *   - senha errada devolve 400 invalid_grant e não emite token;
 *   - GET /api/user e GET /api/officeimpresso com o token devolvem o usuário;
 *   - sem token, 401; sem grant_type, 400 unsupported_grant_type.
 *
 * Reescrito em 2026-11-18 (auditoria Officeimpresso 2026-11-05, eixo 1, achado 3): a versão
 * anterior logava com o primeiro usuário do banco e a senha de DEV_LOGIN_PASSWORD do .env, e
 * transformava falha do password grant em markTestSkipped — o login do desktop podia quebrar
 * com a suíte verde. Agora o usuário e o client são semeados aqui, no tenant 98 (ADR 0358), e
 * a falha falha. A senha é valor de teste, não credencial de ninguém.
 *
 * Roda na lane officeimpresso-pest (MySQL). Sem RefreshDatabase (UltimatePOS).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: Passport + schema UltimatePOS exigem MySQL (ADR 0358).');
    }
    if (! file_exists(storage_path('oauth-private.key')) || ! file_exists(storage_path('oauth-public.key'))) {
        Artisan::call('passport:keys', ['--force' => true]);
    }
});

it('rejeita /api/user sem token com 401', function () {
    $this->getJson('/api/user')->assertUnauthorized()->assertJson(['message' => 'Unauthenticated.']);
});

it('rejeita /api/officeimpresso sem token com 401', function () {
    $this->getJson('/api/officeimpresso')->assertUnauthorized()->assertJson(['message' => 'Unauthenticated.']);
});

it('rejeita /oauth/token sem grant_type com 400 unsupported_grant_type', function () {
    $r = $this->postJson('/oauth/token', []);
    $r->assertStatus(400);
    expect($r->json('error'))->toBe('unsupported_grant_type');
});

it('password grant com usuário e senha certos devolve access_token Bearer', function () {
    [$user, $clientId, $secret] = desktopAuthFixture($this->seededTenant()->id);

    $r = $this->postJson('/oauth/token', desktopAuthGrant($user, $clientId, $secret, 'test12345'));

    $r->assertOk();
    expect($r->json())->toHaveKeys(['token_type', 'expires_in', 'access_token', 'refresh_token']);
    expect($r->json('token_type'))->toBe('Bearer');
    expect(DB::table('oauth_access_tokens')->where('client_id', $clientId)->where('user_id', $user->id)->count())->toBe(1);
});

it('password grant com senha errada devolve 400 invalid_grant e não emite token', function () {
    [$user, $clientId, $secret] = desktopAuthFixture($this->seededTenant()->id);

    $r = $this->postJson('/oauth/token', desktopAuthGrant($user, $clientId, $secret, 'senha-errada'));

    $r->assertStatus(400);
    expect($r->json('error'))->toBe('invalid_grant');
    expect($r->json('access_token'))->toBeNull();
    expect(DB::table('oauth_access_tokens')->where('client_id', $clientId)->count())->toBe(0);
});

it('o token do password grant abre /api/user e /api/officeimpresso com o próprio usuário', function () {
    [$user, $clientId, $secret] = desktopAuthFixture($this->seededTenant()->id);
    $token = $this->postJson('/oauth/token', desktopAuthGrant($user, $clientId, $secret, 'test12345'))
        ->assertOk()
        ->json('access_token');

    foreach (['/api/user', '/api/officeimpresso'] as $rota) {
        $r = $this->withHeaders(['Authorization' => 'Bearer '.$token])->getJson($rota);
        $r->assertOk();
        expect($r->json('id'))->toBe($user->id);
        expect((int) $r->json('business_id'))->toBe((int) $user->business_id);
    }
});

/**
 * Usuário SEM role + password client no formato do legado (id INT, secret com hash).
 * Query builder no client: o Passport 13 gera UUID no id e o schema é int (#8362).
 *
 * @return array{0: User, 1: int, 2: string}
 */
function desktopAuthFixture(int $businessId): array
{
    $user = User::create([
        'business_id' => $businessId,
        'first_name'  => 'OI',
        'surname'     => 'DesktopAuth',
        'username'    => 'oi_desktopauth_'.$businessId.'_'.uniqid(),
        'email'       => 'oi_desktopauth_'.$businessId.'_'.uniqid().'@test.local',
        'password'    => bcrypt('test12345'),
        'language'    => 'pt_BR',
    ]);
    $secret = Str::random(40);
    $clientId = (int) DB::table('oauth_clients')->insertGetId([
        'user_id'                => $user->id,
        'name'                   => 'Desktop Auth '.Str::random(6),
        'secret'                 => Hash::make($secret),
        'provider'               => 'users',
        'redirect'               => 'http://localhost',
        'personal_access_client' => 0,
        'password_client'        => 1,
        'revoked'                => 0,
        'created_at'             => now(),
        'updated_at'             => now(),
    ]);

    return [$user, $clientId, $secret];
}

/** Corpo do POST /oauth/token como o desktop manda. */
function desktopAuthGrant(User $user, int $clientId, string $secret, string $senha): array
{
    return [
        'grant_type'    => 'password',
        'client_id'     => $clientId,
        'client_secret' => $secret,
        'username'      => $user->username,
        'password'      => $senha,
        'scope'         => '',
    ];
}
