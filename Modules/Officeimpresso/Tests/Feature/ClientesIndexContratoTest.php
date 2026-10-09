<?php

declare(strict_types=1);

use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * Contrato da lista de Clientes OAuth (`/officeimpresso/client`) em React — thread
 * Officeimpresso/10. Casos: Pages/Officeimpresso/Clientes/Index.casos.md (UC-OICLI-*),
 * derivados da ficha 10, da thread 05 e da decisão D1 — não do .tsx.
 *
 * A flag `useV2OfficeimpressoClientes` é forçada por stub do FeatureFlagService (mesmo
 * desenho do LogsBaselineTest). Asserções só sobre as linhas que ESTE teste cria: no CT 100
 * o banco persiste entre runs. Tenant 98 (operadora) × 99 (cliente) — ADR 0358. MySQL-only.
 *
 * @see Modules\Officeimpresso\Http\Controllers\ClientController::index
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: Passport + schema UltimatePOS exigem MySQL (ADR 0358).');
    }
    $_SERVER['REMOTE_ADDR'] ??= '127.0.0.1';
    $_SERVER['HTTP_USER_AGENT'] ??= 'Pest/CI (X11; Linux x86_64) HeadlessChrome';

    Permission::firstOrCreate(['name' => 'officeimpresso.clientes.liberar', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
    if ($operador = static::resolveSeededTenant()) {
        config(['constants.operator_business_id' => (int) $operador->id]);
    }

    $this->oiCliIds = [];
    $this->oiCliUsers = [];
});

afterEach(function () {
    app()->forgetInstance(\App\Services\FeatureFlagService::class);
    if ($this->oiCliIds) {
        DB::table('oauth_clients')->whereIn('id', $this->oiCliIds)->delete();
    }
    foreach ($this->oiCliUsers as $u) {
        $u->forceDelete();
    }
});

it('UC-OICLI-01 · flag desligada serve a Blade (rota de fuga)', function () {
    oiCliFlag(false);
    $this->actingAs(oiCliUser($this, $this->seededTenant()->id, 'officeimpresso.clientes.liberar'));

    $this->get('/officeimpresso/client')->assertOk()->assertViewIs('officeimpresso::clients.index');
});

it('UC-OICLI-02 · flag ligada serve Officeimpresso/Clientes/Index com a lista adiada', function () {
    oiCliFlag(true);
    $this->actingAs(oiCliUser($this, $this->seededTenant()->id, 'officeimpresso.clientes.liberar'));

    $this->get('/officeimpresso/client')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Officeimpresso/Clientes/Index')
            ->where('credencial', null)
            ->has('permissions')
            ->missing('clientes'));
});

it('UC-OICLI-03 · [T0] a lista traz só credenciais do negócio da sessão', function () {
    oiCliFlag(true);
    $eu = oiCliUser($this, $this->seededTenant()->id, 'officeimpresso.clientes.liberar');
    $outro = oiCliUser($this, $this->seededSupportClientTenant()->id, null);
    [$minha] = oiCliClient($this, $eu, 'OICLI do 98 '.Str::random(5));
    [$alheia] = oiCliClient($this, $outro, 'OICLI do 99 '.Str::random(5));
    $this->actingAs($eu);

    $ids = collect(oiCliParcial($this)->json('props.clientes'))->pluck('id')->all();

    expect($ids)->toContain($minha);
    expect($ids)->not->toContain($alheia);
});

it('UC-OICLI-04 · [T0] o secret nunca vem na lista', function () {
    oiCliFlag(true);
    $eu = oiCliUser($this, $this->seededTenant()->id, 'officeimpresso.clientes.liberar');
    [$id, $secret] = oiCliClient($this, $eu, 'OICLI segredo '.Str::random(5));
    $this->actingAs($eu);

    $r = oiCliParcial($this);
    $linha = collect($r->json('props.clientes'))->firstWhere('id', $id);

    expect($linha)->toBeArray();
    expect(array_keys($linha))->toEqual(['id', 'name', 'tipo']);
    expect($r->getContent())->not->toContain($secret);
});

it('UC-OICLI-05 · o secret da criação aparece uma vez e some na abertura seguinte', function () {
    oiCliFlag(true);
    $eu = oiCliUser($this, $this->seededTenant()->id, 'officeimpresso.clientes.liberar');
    $nome = 'OICLI novo '.Str::random(6);

    $this->actingAs($eu)->from('/officeimpresso/client')
        ->post('/officeimpresso/client', ['name' => $nome])
        ->assertRedirect('/officeimpresso/client');
    $this->oiCliIds[] = (int) DB::table('oauth_clients')->where('name', $nome)->value('id');
    $secret = session('officeimpresso_credencial.secret');
    expect($secret)->toBeString();

    $this->get('/officeimpresso/client')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('credencial.name', $nome)
        ->where('credencial.secret', $secret));
    $this->get('/officeimpresso/client')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('credencial', null));
});

it('UC-OICLI-06 · [T0] sem permissão 403; delegado não recebe excluir nem regenerar', function () {
    oiCliFlag(true);
    $biz = $this->seededTenant()->id;

    $this->actingAs(oiCliUser($this, $biz, null))->get('/officeimpresso/client')->assertForbidden();

    $this->actingAs(oiCliUser($this, $biz, 'officeimpresso.clientes.liberar'))
        ->get('/officeimpresso/client')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('permissions.pode_excluir', false)
            ->where('permissions.pode_regenerar', false));
});

// ── Helpers (prefixo oiCli — outros arquivos do módulo rodam no mesmo processo) ──

function oiCliFlag(bool $ligada): void
{
    app()->instance(\App\Services\FeatureFlagService::class, new class($ligada) extends \App\Services\FeatureFlagService
    {
        public function __construct(private bool $ligada) {}

        public function isOn(string $flag, array $attrs = []): bool
        {
            return $flag === 'useV2OfficeimpressoClientes' && $this->ligada;
        }
    });
}

/** User SEM role (senão o Gate::before faria bypass de tudo). */
function oiCliUser($test, int $businessId, ?string $permissao): User
{
    $user = User::create([
        'business_id' => $businessId,
        'first_name'  => 'OI',
        'surname'     => 'Clientes10',
        'username'    => 'oi_cli10_'.$businessId.'_'.uniqid(),
        'email'       => 'oi_cli10_'.$businessId.'_'.uniqid().'@test.local',
        'password'    => bcrypt('test12345'),
        'language'    => 'pt_BR',
    ]);
    if ($permissao) {
        $user->givePermissionTo($permissao);
    }
    $test->oiCliUsers[] = $user;

    return $user;
}

/** Query builder, não o model: o id do schema legado é int (ver ClientesSegredoUmaVezTest). */
function oiCliClient($test, User $owner, string $name): array
{
    $secret = Str::random(40);
    $id = (int) DB::table('oauth_clients')->insertGetId([
        'user_id' => $owner->id, 'name' => $name, 'secret' => $secret, 'redirect' => 'http://localhost',
        'personal_access_client' => 0, 'password_client' => 1, 'revoked' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $test->oiCliIds[] = $id;

    return [$id, $secret];
}

/** Partial reload do navegador pedindo a prop adiada `clientes`. */
function oiCliParcial($test)
{
    $r = $test->withHeaders([
        'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) app(App\Http\Middleware\HandleInertiaRequests::class)->version(request()),
        'X-Inertia-Partial-Component' => 'Officeimpresso/Clientes/Index',
        'X-Inertia-Partial-Data' => 'clientes',
    ])->get('/officeimpresso/client');
    $r->assertOk();

    return $r;
}
