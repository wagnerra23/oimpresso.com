<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Passport 13 × schema legado de `oauth_clients` (id INT auto-increment).
 *
 * O Passport 13 nasce com `Passport::$clientUuids = true` e o model Client gerava UUID
 * no `id`. Com sql_mode não-estrito (prod e CI), o MySQL truncava "01a0f5fe-…" para 1:
 * o 1º client num banco vazio passava, o 2º colidia — e em prod, onde o client 1 existe,
 * todo `store` dos painéis falhava (medido 2026-10-01, só leitura). O conserto desliga o
 * UUID no AuthServiceProvider; estes casos travam o contrato que o desktop Delphi usa:
 * id inteiro, clients existentes intocados, password grant emitindo token.
 *
 * Tenant 98 (seed). Nunca biz=4. Sem RefreshDatabase (UltimatePOS).
 */
beforeEach(function () {
    $_SERVER['REMOTE_ADDR'] ??= '127.0.0.1';
    $_SERVER['HTTP_USER_AGENT'] ??= 'Pest/CI (X11; Linux x86_64) HeadlessChrome';

    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: Passport + schema UltimatePOS exigem MySQL (ADR 0358).');
    }

    Permission::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
});

it('oauth · o app não usa UUID no id do client (schema legado é INT)', function () {
    expect(Passport::$clientUuids)->toBeFalse();
});

it('oauth · 3 clients criados em sequência pelo model ganham id inteiro, distinto e igual ao gravado', function () {
    $owner = makeOauthIdTestUser($this->seededTenant()->id);
    $ids = [];

    foreach (['A', 'B', 'C'] as $sufixo) {
        $nome = 'Delphi id '.$sufixo.' '.Str::random(6);
        $client = Passport::client()->forceFill([
            'user_id' => $owner->id,
            'name' => $nome,
            'secret' => Str::random(40),
            'redirect' => 'http://localhost',
            'personal_access_client' => 0,
            'password_client' => 1,
            'revoked' => false,
        ]);
        $client->save();

        $gravado = DB::table('oauth_clients')->where('name', $nome)->value('id');
        expect($client->id)->toBeInt();
        expect($client->id)->toBe((int) $gravado);
        $ids[] = $client->id;
    }

    expect(array_unique($ids))->toHaveCount(3);
});

it('oauth · o painel /officeimpresso/client cria dois clients seguidos sem erro', function () {
    $admin = makeOauthIdTestUser($this->seededTenant()->id);
    $admin->givePermissionTo('superadmin');

    foreach (['1', '2'] as $n) {
        $nome = 'Painel '.$n.' '.Str::random(6);
        $this->actingAs($admin)
            ->from('/officeimpresso/client')
            ->post('/officeimpresso/client', ['name' => $nome])
            ->assertRedirect('/officeimpresso/client')
            ->assertSessionHas('status.success', true);

        expect(DB::table('oauth_clients')->where('name', $nome)->exists())->toBeTrue();
    }
});

it('oauth · client existente (id int, secret com hash) continua emitindo token no password grant', function () {
    if (! file_exists(storage_path('oauth-private.key')) || ! file_exists(storage_path('oauth-public.key'))) {
        Artisan::call('passport:keys', ['--force' => true]);
    }

    $user = makeOauthIdTestUser($this->seededTenant()->id);
    $secret = Str::random(40);
    // Forma do legado em campo: linha gravada antes do Passport 13, id auto-increment.
    $clientId = (int) DB::table('oauth_clients')->insertGetId([
        'user_id' => $user->id,
        'name' => 'Legado '.Str::random(6),
        'secret' => Hash::make($secret),
        'provider' => 'users',
        'redirect' => 'http://localhost',
        'personal_access_client' => 0,
        'password_client' => 1,
        'revoked' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $r = $this->postJson('/oauth/token', [
        'grant_type' => 'password',
        'client_id' => $clientId,
        'client_secret' => $secret,
        'username' => $user->username,
        'password' => 'test12345',
        'scope' => '',
    ]);

    $r->assertOk();
    expect($r->json('token_type'))->toBe('Bearer');
    expect($r->json('access_token'))->toBeString()->not->toBeEmpty();
    expect(DB::table('oauth_access_tokens')->where('client_id', $clientId)->where('user_id', $user->id)->exists())->toBeTrue();
});

/**
 * User de teste SEM role (senão o Gate::before faria bypass de tudo).
 */
function makeOauthIdTestUser(int $businessId): User
{
    return User::create([
        'business_id' => $businessId,
        'first_name' => 'OI',
        'surname' => 'OauthId',
        'username' => 'oi_oauthid_'.$businessId.'_'.uniqid(),
        'email' => 'oi_oauthid_'.$businessId.'_'.uniqid().'@test.local',
        'password' => bcrypt('test12345'),
        'language' => 'pt_BR',
    ]);
}
