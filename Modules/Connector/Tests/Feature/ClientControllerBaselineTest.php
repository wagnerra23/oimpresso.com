<?php

declare(strict_types=1);

// @covers-us US-CONN-015

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * BASELINE F2 do MWART para a tela Conector (API) — US-CONN-015.
 *
 * Por que existe: a F3 vai trocar `view('connector::clients.index')` por
 * um render Inertia da page `Connector/Index`. Sem baseline, a troca pode perder
 * comportamento em silêncio — que é a pior dimensão da régua de migração
 * (proibicoes.md §"Migração Blade→React sem parity").
 *
 * O que este arquivo trava, pela PORTA REAL (rota HTTP), nunca replicando a
 * query do controller — teste derivado da implementação é tautológico e está
 * catalogado no §5 (2026-06-05).
 *
 * Tenant: `seededTenant()` = 98 (fictício, canônico) e 99 como adversário
 * cross-tenant — doutrina da ADR 0358, que supersede a 0101 nas cláusulas de
 * default. biz=4 é PROIBIDO sem exceção; biz=1 é empresa real.
 *
 * @see memory/requisitos/Connector/RUNBOOK-connector-index.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md
 */

defined('BIZ_ADVERSARIO') || define('BIZ_ADVERSARIO', 99);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: Passport + schema UltimatePOS exigem MySQL (ADR 0358)');
    }
    foreach (['oauth_clients', 'business', 'users'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente; rode migrate + seed mínimo");
        }
    }
});

/** Cria um user do business dado. Devolve o model. */
function connectorUser(int $businessId, bool $superadmin): User
{
    $user = User::factory()->create([
        "business_id" => $businessId,
        "username" => "conn_baseline_" . uniqid(),
    ]);

    if ($superadmin) {
        $perm = Permission::firstOrCreate(["name" => "superadmin", "guard_name" => "web"]);
        $user->givePermissionTo($perm);
    }

    return $user;
}

/** Autentica e popula a session que o global scope de business lê. */
function connectorActAs($test, User $user)
{
    session()->put("user.business_id", $user->business_id);

    return $test->actingAs($user);
}

/** Remove o user pela tabela — previsível, sem depender de SoftDeletes. */
function connectorDropUser(User $user): void
{
    DB::table("users")->where("id", $user->id)->delete();
}

/** Insere um oauth_client de senha pertencente ao user dado. Devolve o id. */
function connectorOauthClient(int $userId, string $nome): string
{
    $id = DB::table('oauth_clients')->insertGetId([
        'user_id' => $userId,
        'name' => $nome,
        'secret' => str_repeat('s', 40),
        'redirect' => 'http://localhost',
        'personal_access_client' => 0,
        'password_client' => 1,
        'revoked' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return (string) $id;
}

it('US-CONN-015: nega 403 a quem não é superadmin', function () {
    $user = connectorUser($this->seededTenant(), superadmin: false);

    try {
        connectorActAs($this, $user)->get('/connector/client')->assertForbidden();
    } finally {
        connectorDropUser($user);
    }
});

it('US-CONN-015: superadmin vê a lista e o nome do próprio client', function () {
    $user = connectorUser($this->seededTenant(), superadmin: true);
    $nome = 'CLIENT-PROPRIO-'.uniqid();
    $clientId = connectorOauthClient($user->id, $nome);

    try {
        connectorActAs($this, $user)->get('/connector/client')
            ->assertOk()
            ->assertSee($nome);
    } finally {
        DB::table('oauth_clients')->where('id', $clientId)->delete();
        connectorDropUser($user);
    }
});

it('US-CONN-015 TIER 0: client de OUTRO business NÃO vaza na lista', function () {
    $dono = connectorUser($this->seededTenant(), superadmin: true);
    $alheio = connectorUser(BIZ_ADVERSARIO, superadmin: false);

    $nomeAlheio = 'CLIENT-ALHEIO-'.uniqid();
    $idAlheio = connectorOauthClient($alheio->id, $nomeAlheio);

    try {
        // O filtro do controller é LEFT JOIN em `users` — `oauth_clients` NÃO
        // tem business_id próprio. Se alguém remover o JOIN, este teste quebra.
        connectorActAs($this, $dono)->get('/connector/client')
            ->assertOk()
            ->assertDontSee($nomeAlheio);
    } finally {
        DB::table('oauth_clients')->where('id', $idAlheio)->delete();
        connectorDropUser($alheio);
        connectorDropUser($dono);
    }
});

it('US-CONN-015: store cria client de senha com secret de 40 caracteres', function () {
    $user = connectorUser($this->seededTenant(), superadmin: true);
    $nome = 'CLIENT-NOVO-'.uniqid();

    try {
        connectorActAs($this, $user)->post('/connector/client', ['name' => $nome]);

        $criado = DB::table('oauth_clients')->where('name', $nome)->first();

        expect($criado)->not->toBeNull('store não persistiu o client');
        expect((int) $criado->password_client)->toBe(1, 'client precisa ser password_client');
        expect((int) $criado->personal_access_client)->toBe(0);
        expect(strlen((string) $criado->secret))->toBe(40, 'secret precisa ter 40 caracteres');
        expect((int) $criado->revoked)->toBe(0);
    } finally {
        DB::table('oauth_clients')->where('name', $nome)->delete();
        connectorDropUser($user);
    }
});

it('US-CONN-015: a lista expõe o secret em texto puro (comportamento ATUAL, não endosso)', function () {
    // Documenta o que existe hoje: `makeVisible('secret')` imprime o segredo na
    // tabela. A F3 deve preservar por paridade; MASCARAR é decisão [W]
    // (RUNBOOK §10.2). Este teste é o que torna a mudança visível se acontecer.
    $user = connectorUser($this->seededTenant(), superadmin: true);
    $segredo = str_repeat('z', 40);

    $clientId = DB::table('oauth_clients')->insertGetId([
        'user_id' => $user->id,
        'name' => 'CLIENT-SECRET-'.uniqid(),
        'secret' => $segredo,
        'redirect' => 'http://localhost',
        'personal_access_client' => 0,
        'password_client' => 1,
        'revoked' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    try {
        connectorActAs($this, $user)->get('/connector/client')
            ->assertOk()
            ->assertSee($segredo);
    } finally {
        DB::table('oauth_clients')->where('id', $clientId)->delete();
        connectorDropUser($user);
    }
});
