<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

// @covers-us US-SUPER-010

/**
 * Contrato das telas Usuário 360 (`/superadmin/usuarios` e `/superadmin/usuarios/{id}/360`)
 * — thread Superadmin/01.
 *
 * Os UCs vêm do contrato, não do código:
 *   Modules/Superadmin/Resources/js/Pages/superadmin/Usuario360/Index.casos.md (UC-SAUL-*)
 *   Modules/Superadmin/Resources/js/Pages/superadmin/Usuario360/Show.casos.md  (UC-SAUX-*)
 *
 * ⚠️ SKIP em SQLite: precisa do schema UltimatePOS real. Em sqlite estes casos PULAM e o
 * arquivo sai exit 0 sem provar nada — leia *assertions*, não "0 failed" (LC-13).
 *
 * @see Modules/Superadmin/Http/Controllers/Usuario360Controller.php
 * @see app/Services/UserLockoutService.php
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md §exceções Superadmin
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: Usuário 360 requer schema MySQL UltimatePOS.');
    }
    if (! Schema::hasTable('users') || ! Schema::hasTable('business') || ! Schema::hasTable('user_lockouts')) {
        $this->markTestSkipped('Schema UltimatePOS ausente — rode migrations primeiro.');
    }

    // O gate de ROTA é o middleware `superadmin`, que compara o USERNAME com
    // `config('constants.administrator_usernames')`.
    config(['constants.administrator_usernames' => 'u360_superadmin_test']);
});

/** Tenant do superadmin. NUNCA biz=4 (ROTA LIVRE, produção) — ADR 0358. */
const BIZ_U360 = 98;
/** Segundo tenant, fictício — é onde mora o usuário investigado (cross-tenant). */
const BIZ_U360_OUTRO = 99;

function u360Business(int $id): void
{
    Business::firstOrCreate(['id' => $id], ['name' => "Tenant fictício u360 {$id}", 'currency_id' => 1]);
}

function u360Superadmin(): User
{
    u360Business(BIZ_U360);

    $user = User::firstOrCreate(
        ['username' => 'u360_superadmin_test'],
        [
            'email' => 'u360_superadmin@test.local',
            'password' => bcrypt('secret'),
            'business_id' => BIZ_U360,
            'first_name' => 'U360',
            'last_name' => 'Superadmin',
        ]
    );

    $permission = Permission::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
    if (! $user->hasPermissionTo('superadmin')) {
        $user->givePermissionTo($permission);
    }

    return $user;
}

function u360AdminDeNegocio(): User
{
    u360Business(BIZ_U360);

    $user = User::firstOrCreate(
        ['username' => 'u360_admin_test'],
        [
            'email' => 'u360_admin@test.local',
            'password' => bcrypt('secret'),
            'business_id' => BIZ_U360,
            'first_name' => 'Admin',
            'last_name' => 'Negocio',
        ]
    );
    $user->syncRoles([]);
    $user->syncPermissions([]);

    return $user;
}

/** Usuário investigado, sempre ATIVO e sem trancamento/token ao sair daqui (estado limpo). */
function u360Alvo(string $username, int $businessId): User
{
    u360Business($businessId);

    $user = User::firstOrCreate(
        ['username' => $username],
        [
            'email' => "{$username}@test.local",
            'password' => bcrypt('secret'),
            'business_id' => $businessId,
            'first_name' => 'Alvo',
            'last_name' => 'U360',
        ]
    );

    DB::table('user_lockouts')->where('user_id', $user->id)->delete();
    if (Schema::hasTable('mcp_tokens')) {
        DB::table('mcp_tokens')->where('user_id', $user->id)->delete();
    }
    DB::table('users')->where('id', $user->id)->update(['status' => 'active']);

    return $user->fresh();
}

// ── Index · UC-SAUL-01 · Inertia com o contrato de props ────────────────────

it('UC-SAUL-01 · /superadmin/usuarios responde Inertia com users e filters', function () {
    $this->actingAs(u360Superadmin())
        ->get('/superadmin/usuarios')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('superadmin/Usuario360/Index')
            ->has('users')
            ->has('filters')
        );
});

// ── Index · UC-SAUL-02 · admin barrado ENQUANTO superadmin passa ────────────

it('UC-SAUL-02 · admin de negócio é barrado na lista enquanto o superadmin passa', function () {
    $barrado = $this->actingAs(u360AdminDeNegocio())->get('/superadmin/usuarios');
    expect($barrado->getStatusCode())->toBeIn([302, 403]);

    $this->actingAs(u360Superadmin())->get('/superadmin/usuarios')->assertOk();
});

// ── Index · UC-SAUL-03 · busca casa e devolve o termo ───────────────────────

it('UC-SAUL-03 · a busca acha pelo username, traz os campos do charter e devolve o termo', function () {
    $alvo = u360Alvo('u360busca_alvo98', BIZ_U360);

    $this->actingAs(u360Superadmin())
        ->get('/superadmin/usuarios?q=u360busca_alvo98')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filters.q', 'u360busca_alvo98')
            ->has('users', 1, fn (AssertableInertia $linha) => $linha
                ->where('id', $alvo->id)
                ->where('username', 'u360busca_alvo98')
                ->has('email')
                ->has('nome')
                ->where('business_id', fn ($v) => (int) $v === BIZ_U360)
                ->has('status')
                ->has('user_type')
            )
        );

    // Termo que não casa ninguém: lista vazia, não erro.
    $this->actingAs(u360Superadmin())
        ->get('/superadmin/usuarios?q=u360-ninguem-tem-isto-zz9')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('users', 0));
});

// ── Index · UC-SAUL-04 · cross-tenant intencional ───────────────────────────

it('UC-SAUL-04 · a busca enxerga usuário de outro business, não só o da sessão', function () {
    $doMeu = u360Alvo('u360cross_meu', BIZ_U360);
    $doOutro = u360Alvo('u360cross_outro', BIZ_U360_OUTRO);

    $resposta = $this->actingAs(u360Superadmin())->get('/superadmin/usuarios?q=u360cross_');
    $resposta->assertOk();

    $ids = collect($resposta->viewData('page')['props']['users'])->pluck('business_id', 'id');

    // Se alguém aplicar escopo de business, o do outro tenant some e este caso cai.
    expect((int) $ids->get($doMeu->id))->toBe(BIZ_U360);
    expect((int) $ids->get($doOutro->id))->toBe(BIZ_U360_OUTRO);
});

// ── Show · UC-SAUX-01 · os blocos 360 ───────────────────────────────────────

it('UC-SAUX-01 · o raio-X responde Inertia com os blocos 360 e tabelas_ausentes', function () {
    $alvo = u360Alvo('u360show_alvo', BIZ_U360);

    $this->actingAs(u360Superadmin())
        ->get("/superadmin/usuarios/{$alvo->id}/360")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('superadmin/Usuario360/Show')
            ->where('user.id', $alvo->id)
            ->has('roles')
            ->has('permissions')
            ->has('scopes_ads')
            ->has('tokens_mcp')
            ->has('quotas_copiloto')
            ->has('sessions_ativas')
            ->has('auditoria')
            ->has('lockouts')
            ->has('tabelas_ausentes')
        );
});

// ── Show · UC-SAUX-02 · admin barrado ENQUANTO superadmin passa ─────────────

it('UC-SAUX-02 · admin de negócio é barrado no raio-X enquanto o superadmin passa', function () {
    $alvo = u360Alvo('u360show_alvo', BIZ_U360);

    $barrado = $this->actingAs(u360AdminDeNegocio())->get("/superadmin/usuarios/{$alvo->id}/360");
    expect($barrado->getStatusCode())->toBeIn([302, 403]);

    $this->actingAs(u360Superadmin())->get("/superadmin/usuarios/{$alvo->id}/360")->assertOk();
});

// ── Show · UC-SAUX-03 · cross-tenant intencional ────────────────────────────

it('UC-SAUX-03 · o superadmin abre o raio-X de usuário de outro business', function () {
    $alvo = u360Alvo('u360show_outro', BIZ_U360_OUTRO);

    $this->actingAs(u360Superadmin())
        ->get("/superadmin/usuarios/{$alvo->id}/360")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('user.id', $alvo->id)
            ->where('user.business_id', fn ($v) => (int) $v === BIZ_U360_OUTRO)
        );
});

// ── Show · UC-SAUX-04 · trancar sem motivo não tranca ───────────────────────

it('UC-SAUX-04 · trancar sem motivo devolve erro em reason e não muda nada', function () {
    $alvo = u360Alvo('u360lock_semmotivo', BIZ_U360_OUTRO);

    $this->actingAs(u360Superadmin())
        ->post("/superadmin/usuarios/{$alvo->id}/lock", [])
        ->assertSessionHasErrors('reason');

    // O efeito é o que discrimina: recusa e sucesso devolvem os dois um 302.
    expect(DB::table('users')->where('id', $alvo->id)->value('status'))->toBe('active');
    expect(DB::table('user_lockouts')->where('user_id', $alvo->id)->count())->toBe(0);
});

// ── Show · UC-SAUX-05 · destrancar NÃO devolve token ────────────────────────

it('UC-SAUX-05 · trancar revoga o token MCP e destrancar reativa sem devolvê-lo', function () {
    if (! Schema::hasTable('mcp_tokens')) {
        $this->markTestSkipped('mcp_tokens ausente — o caso depende do token para discriminar.');
    }

    $alvo = u360Alvo('u360lock_ciclo', BIZ_U360_OUTRO);
    $tokenId = DB::table('mcp_tokens')->insertGetId([
        'user_id' => $alvo->id,
        'name' => 'token de teste u360',
        'sha256_token' => hash('sha256', 'u360-token-'.$alvo->id),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $super = u360Superadmin();

    $this->actingAs($super)
        ->post("/superadmin/usuarios/{$alvo->id}/lock", ['reason' => 'Investigação de acesso (teste u360)'])
        ->assertSessionHasNoErrors();

    expect(DB::table('users')->where('id', $alvo->id)->value('status'))->toBe('inactive');
    expect(DB::table('user_lockouts')->where('user_id', $alvo->id)->whereNull('unlocked_at')->count())->toBe(1);
    expect(DB::table('mcp_tokens')->where('id', $tokenId)->value('revoked_at'))->not->toBeNull();

    $this->actingAs($super)
        ->post("/superadmin/usuarios/{$alvo->id}/unlock", ['note' => 'liberado (teste u360)'])
        ->assertSessionHasNoErrors();

    expect(DB::table('users')->where('id', $alvo->id)->value('status'))->toBe('active');
    expect(DB::table('user_lockouts')->where('user_id', $alvo->id)->whereNull('unlocked_at')->count())->toBe(0);
    // O ponto do caso: o token segue revogado depois do unlock.
    expect(DB::table('mcp_tokens')->where('id', $tokenId)->value('revoked_at'))->not->toBeNull();
});

// ── Show · UC-SAUX-06 · cada abertura do raio-X deixa 1 registro de acesso ──

/** Registros de acesso ao raio-X de UM usuário (activity_log, log `superadmin_acesso`). */
function u360Acessos(int $vistoId)
{
    return DB::table('activity_log')
        ->where('log_name', 'superadmin_acesso')
        ->where('subject_type', User::class)
        ->where('subject_id', $vistoId);
}

it('UC-SAUX-06 · abrir o raio-X grava 1 acesso com quem viu, quem foi visto e o business do visto', function () {
    if (! Schema::hasTable('activity_log') || ! Schema::hasColumn('activity_log', 'business_id')) {
        $this->markTestSkipped('activity_log com business_id ausente.');
    }

    $alvo = u360Alvo('u360acesso_alvo', BIZ_U360_OUTRO);
    u360Acessos($alvo->id)->delete();
    $super = u360Superadmin();

    $this->actingAs($super)
        ->withHeader('User-Agent', 'u360-teste-agent')
        ->get("/superadmin/usuarios/{$alvo->id}/360")
        ->assertOk();

    expect(u360Acessos($alvo->id)->count())->toBe(1);

    $registro = u360Acessos($alvo->id)->first();
    expect((int) $registro->causer_id)->toBe($super->id);
    expect($registro->causer_type)->toBe(User::class);
    expect($registro->event)->toBe('usuario360_visualizado');
    // O business é o do USUÁRIO VISTO (99), não o da sessão do superadmin (98).
    expect((int) $registro->business_id)->toBe(BIZ_U360_OUTRO);

    $props = json_decode((string) $registro->properties, true);
    expect($props)->toBeArray();
    expect($props['user_agent'])->toBe('u360-teste-agent');
    expect(array_key_exists('ip', $props))->toBeTrue();
    // Sem PII do titular no payload: o id dele já está no subject.
    expect($props)->not->toHaveKey('email');
    expect((string) $registro->properties)->not->toContain($alvo->email);

    // Append-only: abrir de novo soma, não sobrescreve.
    $this->actingAs($super)->get("/superadmin/usuarios/{$alvo->id}/360")->assertOk();
    expect(u360Acessos($alvo->id)->count())->toBe(2);
});

it('UC-SAUX-06 · listar não grava acesso, e admin de negócio barrado também não', function () {
    if (! Schema::hasTable('activity_log')) {
        $this->markTestSkipped('activity_log ausente.');
    }

    $alvo = u360Alvo('u360acesso_lista', BIZ_U360);
    u360Acessos($alvo->id)->delete();

    $this->actingAs(u360Superadmin())->get('/superadmin/usuarios?q=u360acesso_lista')->assertOk();
    expect(u360Acessos($alvo->id)->count())->toBe(0);

    $barrado = $this->actingAs(u360AdminDeNegocio())->get("/superadmin/usuarios/{$alvo->id}/360");
    expect($barrado->getStatusCode())->toBeIn([302, 403]);
    expect(u360Acessos($alvo->id)->count())->toBe(0);
});
