<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class);

/**
 * Contrato da lista `/crm/call-log` em React, atrás da flag `useV2CrmChamadas` — thread Crm/12, F3.
 *
 * Os UCs vêm do contrato, não do código:
 *   Modules/Crm/Resources/js/Pages/Crm/Chamadas/Index.casos.md
 *   memory/requisitos/Crm/RUNBOOK-chamadas.md §2-6 + chamadas-parity.md
 *
 * A tela segue desligada por config fixa (achado B1): o teste liga `enable_crm_call_log` por
 * `config()` e prova em UC-CRMCHA-06 que a flag não fura esse portão.
 * A flag é forçada por stub do FeatureFlagService (mesmo desenho do CrmCampanhasContratoTest).
 *
 * Tenant 98 (fictício, ADR 0358) e 99 como o "outro negócio". NUNCA biz=4.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 *
 * @see Modules/Crm/Http/Controllers/CallLogController::index()
 */
const CRMCHA_TAG = '[crmcha12]';
const CRMCHA_BIZ = 98;
const CRMCHA_OUTRO = 99;
const CRMCHA_ROTA = '/crm/call-log';
const CRMCHA_COMPONENTE = 'Crm/Chamadas/Index';
const CRMCHA_MOEDA = ['currency' => ['code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ',']];

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: crm_call_logs requer schema MySQL UltimatePOS.');
    }
    foreach (['business', 'users', 'contacts', 'crm_call_logs'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente.");
        }
    }
    config(['constants.enable_crm_call_log' => true]);
    crmchaFlag(true);
    crmchaLimpa();
});

afterEach(function () {
    crmchaLimpa();
    app()->forgetInstance(\App\Services\FeatureFlagService::class);
});

function crmchaFlag(bool $ligada): void
{
    app()->instance(\App\Services\FeatureFlagService::class, new class($ligada) extends \App\Services\FeatureFlagService
    {
        public function __construct(private bool $ligada) {}

        public function isOn(string $flag, array $attrs = []): bool
        {
            return $flag === 'useV2CrmChamadas' && $this->ligada;
        }
    });
}

function crmchaLimpa(): void
{
    if (Schema::hasTable('crm_call_logs')) {
        DB::table('crm_call_logs')->where('mobile_name', CRMCHA_TAG)->delete();
    }
    if (Schema::hasTable('contacts')) {
        DB::table('contacts')->where('name', 'like', '%'.CRMCHA_TAG.'%')->delete();
    }
}

/** Mesmo conserto do CrmAcompanhamentosContratoTest::acoNegocio — `id` é guarded. */
function crmchaNegocio(int $biz): void
{
    if (Business::whereKey($biz)->exists()) {
        return;
    }
    $dono = (int) DB::table('users')->min('id');
    (new Business)->forceFill(['id' => $biz, 'name' => 'Tenant fictício crm '.$biz, 'currency_id' => 1, 'owner_id' => $dono])->save();
}

function crmchaUsuario(string $username, array $permissoes, bool $admin = false, int $biz = CRMCHA_BIZ): User
{
    crmchaNegocio($biz);
    $user = User::firstOrCreate(['username' => $username], [
        'email' => $username.'@test.local', 'password' => bcrypt('secret'),
        'business_id' => $biz, 'first_name' => 'Crmcha', 'last_name' => 'Teste',
        'user_type' => 'user', 'allow_login' => 1,
    ]);
    $user->syncPermissions(array_map(fn ($p) => Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']), $permissoes));
    $user->syncRoles($admin ? [Role::firstOrCreate(['name' => 'Admin#'.$biz, 'guard_name' => 'web'], ['business_id' => $biz])] : []);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user->fresh();
}

function crmchaContato(int $biz, string $nome, User $criador): int
{
    crmchaNegocio($biz);

    return DB::table('contacts')->insertGetId([
        'business_id' => $biz, 'type' => 'customer', 'name' => $nome.' '.CRMCHA_TAG, 'mobile' => '0',
        'created_by' => $criador->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function crmchaRegistro(int $biz, User $criador, ?int $contato = null, string $numero = '0'): int
{
    crmchaNegocio($biz);

    return DB::table('crm_call_logs')->insertGetId([
        'business_id' => $biz, 'user_id' => $criador->id, 'call_type' => 'outgoing',
        'mobile_number' => $numero, 'mobile_name' => CRMCHA_TAG, 'contact_id' => $contato,
        'start_time' => now(), 'end_time' => now(), 'duration' => 372,
        'created_by' => $criador->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Pede uma prop da tela como o browser pede (X-Inertia + X-Requested-With, partial reload). */
function crmchaProp(User $user, string $prop, string $query = ''): mixed
{
    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $resposta = test()->actingAs($user)->get(CRMCHA_ROTA.$query, [
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => (string) $versao,
        'X-Inertia-Partial-Data' => $prop,
        'X-Inertia-Partial-Component' => CRMCHA_COMPONENTE,
    ]);
    $resposta->assertOk();

    return $resposta->json('props.'.$prop);
}

function crmchaIds(User $user, string $query = ''): array
{
    return collect((array) (crmchaProp($user, 'chamadas', $query)['data'] ?? []))
        ->filter(fn ($r) => ($r['nome_no_celular'] ?? null) === CRMCHA_TAG)
        ->map(fn ($r) => (int) $r['id'])
        ->values()->all();
}

it('UC-CRMCHA-01 · flag desligada serve a Blade; ligada serve a tela React', function () {
    $user = crmchaUsuario('crmcha_todos_test', ['crm.view_all_call_log']);

    crmchaFlag(false);
    $this->actingAs($user)->withSession(CRMCHA_MOEDA)->get(CRMCHA_ROTA)->assertOk()->assertViewIs('crm::call_logs.index');

    crmchaFlag(true);
    $this->actingAs($user)->get(CRMCHA_ROTA)
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p->component(CRMCHA_COMPONENTE, false)->has('filtros')->has('pode_excluir'));
});

it('UC-CRMCHA-02 · visita Inertia não cai no DataTables, e o ajax da tela clássica segue recebendo JSON', function () {
    $user = crmchaUsuario('crmcha_todos_test', ['crm.view_all_call_log']);
    $id = crmchaRegistro(CRMCHA_BIZ, $user);

    expect(crmchaIds($user))->toContain($id);
    $linha = collect(crmchaProp($user, 'chamadas')['data'])->firstWhere('id', $id);
    expect($linha['duracao'])->toBe('00:06:12');

    $json = $this->actingAs($user)->get(CRMCHA_ROTA, ['X-Requested-With' => 'XMLHttpRequest']);
    $json->assertOk();
    $this->assertArrayHasKey('data', (array) $json->json(), 'o DataTables da tela clássica deve continuar recebendo JSON');
    $this->assertNull($json->headers->get('X-Inertia'), 'ajax sem X-Inertia não pode virar resposta Inertia');
});

it('UC-CRMCHA-03 · registro de outro negócio não aparece [T0]', function () {
    $user = crmchaUsuario('crmcha_todos_test', ['crm.view_all_call_log']);
    $vizinho = crmchaUsuario('crmcha_vizinho_test', ['crm.view_all_call_log'], false, CRMCHA_OUTRO);
    $meu = crmchaRegistro(CRMCHA_BIZ, $user);
    $deFora = crmchaRegistro(CRMCHA_OUTRO, $vizinho);

    $ids = crmchaIds($user);
    expect($ids)->toContain($meu);
    $this->assertNotContains($deFora, $ids, 'vazou registro de chamada de outro business');
});

it('UC-CRMCHA-04 · quem só vê os próprios vê só os registros que criou', function () {
    $proprio = crmchaUsuario('crmcha_proprio_test', ['crm.view_own_call_log']);
    $colega = crmchaUsuario('crmcha_colega_test', ['crm.view_all_call_log']);
    $meu = crmchaRegistro(CRMCHA_BIZ, $proprio);
    $doColega = crmchaRegistro(CRMCHA_BIZ, $colega);

    $ids = crmchaIds($proprio);
    expect($ids)->toContain($meu);
    $this->assertNotContains($doColega, $ids, 'quem só vê os próprios viu o registro do colega');
});

it('UC-CRMCHA-05 · contato, autor e busca recortam a lista', function () {
    $user = crmchaUsuario('crmcha_todos_test', ['crm.view_all_call_log']);
    $colega = crmchaUsuario('crmcha_colega_test', ['crm.view_own_call_log']);
    $contato = crmchaContato(CRMCHA_BIZ, 'Padaria', $user);
    $comContato = crmchaRegistro(CRMCHA_BIZ, $user, $contato, '14998124410');
    $doColega = crmchaRegistro(CRMCHA_BIZ, $colega);

    expect(crmchaIds($user, '?contact_id='.$contato))->toBe([$comContato]);
    expect(crmchaIds($user, '?user_id='.$colega->id))->toBe([$doColega]);
    expect(crmchaIds($user, '?q=14998124410'))->toBe([$comContato]);
});

it('UC-CRMCHA-06 · só o admin vê "Excluir selecionados"; a flag não fura a config desligada', function () {
    $this->actingAs(crmchaUsuario('crmcha_admin_test', ['crm.view_all_call_log'], true))->get(CRMCHA_ROTA)
        ->assertInertia(fn (AssertableInertia $p) => $p->where('pode_excluir', true));
    $this->actingAs(crmchaUsuario('crmcha_todos_test', ['crm.view_all_call_log']))->get(CRMCHA_ROTA)
        ->assertInertia(fn (AssertableInertia $p) => $p->where('pode_excluir', false));

    config(['constants.enable_crm_call_log' => false]);
    $this->actingAs(crmchaUsuario('crmcha_todos_test', ['crm.view_all_call_log']))->get(CRMCHA_ROTA)->assertForbidden();
});

it('UC-CRMCHA-07 · com a flag ligada, ?classico=1 devolve a Blade', function () {
    $this->actingAs(crmchaUsuario('crmcha_todos_test', ['crm.view_all_call_log']))
        ->withSession(CRMCHA_MOEDA)
        ->get(CRMCHA_ROTA.'?classico=1')
        ->assertOk()
        ->assertViewIs('crm::call_logs.index');
});
