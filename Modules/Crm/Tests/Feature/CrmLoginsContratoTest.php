<?php

declare(strict_types=1);
// Cobre UC-CRMLOG-01, UC-CRMLOG-02, UC-CRMLOG-03, UC-CRMLOG-04, UC-CRMLOG-05 (Crm/Logins/Index.casos.md).

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * Contrato de `/crm/all-contacts-login` em Inertia — thread Crm/16, MWART F3, atrás da flag
 * useV2CrmLogins (ligada aqui pelo override `feature-flags.forced_on`, inerte em produção).
 * A Blade de hoje está travada em CrmLoginsBaselineTest.
 *
 * Tenant 98 (fictício, ADR 0358) e 99 como o "outro negócio". NUNCA biz=4.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 *
 * @see Modules/Crm/Http/Controllers/ContactLoginController::allContactsLoginList()
 */
const LOGC_TAG = 'logc16';
const LOGC_BIZ = 98;
const LOGC_OUTRO = 99;
const LOGC_ROTA = '/crm/all-contacts-login';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: users/contacts requerem schema MySQL UltimatePOS.');
    }
    foreach (['business', 'users', 'contacts'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente.");
        }
    }
    if (! Schema::hasColumn('users', 'crm_contact_id')) {
        $this->markTestSkipped('Coluna users.crm_contact_id ausente — migrations do Crm não rodaram.');
    }
    logcLimpa();
    config(['feature-flags.forced_on' => 'useV2CrmLogins']);
});

afterEach(fn () => logcLimpa());

function logcLimpa(): void
{
    DB::table('users')->where('username', 'like', LOGC_TAG.'%')->delete();
    DB::table('contacts')->where('name', 'like', '%'.LOGC_TAG.'%')->delete();
}

/** Mesmo conserto do CrmAcompanhamentosContratoTest::acoNegocio (`id` é guarded; `owner_id` é FK). */
function logcNegocio(int $biz): void
{
    if (Business::whereKey($biz)->exists()) {
        return;
    }
    $dono = (int) DB::table('users')->min('id');
    (new Business)->forceFill(['id' => $biz, 'name' => 'Tenant fictício crm '.$biz, 'currency_id' => 1, 'owner_id' => $dono])->save();
}

/** Usuário da equipe que opera a tela. `user_type`/`allow_login` explícitos por causa do CheckUserLogin. */
function logcOperador(array $permissoes, string $sufixo = 'op'): User
{
    logcNegocio(LOGC_BIZ);
    $user = User::create([
        'username' => LOGC_TAG.'_'.$sufixo, 'email' => LOGC_TAG.'_'.$sufixo.'@test.local',
        'password' => bcrypt('secret'), 'business_id' => LOGC_BIZ, 'first_name' => 'Op', 'last_name' => 'Teste',
        'user_type' => 'user', 'allow_login' => 1,
    ]);
    $user->syncPermissions(array_map(fn ($p) => Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']), $permissoes));

    return $user;
}

function logcContato(int $biz, string $nome): int
{
    logcNegocio($biz);

    return DB::table('contacts')->insertGetId([
        'business_id' => $biz, 'type' => 'customer', 'name' => $nome.' '.LOGC_TAG, 'mobile' => '0',
        'created_by' => (int) DB::table('users')->min('id'), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Login de contato como o CrmUtil::creatContactPerson grava (user_type user_customer). */
function logcLogin(int $biz, int $contato, string $sufixo): void
{
    DB::table('users')->insert([
        'username' => LOGC_TAG.'_'.$sufixo, 'email' => LOGC_TAG.'_'.$sufixo.'@test.local',
        'password' => bcrypt('nao-vaza'), 'remember_token' => 'token-nao-vaza', 'business_id' => $biz,
        'crm_contact_id' => $contato, 'first_name' => 'Login', 'last_name' => $sufixo,
        'user_type' => 'user_customer', 'allow_login' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** A prop deferida `logins`, pedida como o browser pede (X-Inertia + X-Requested-With). */
function logcLinhas(User $user, string $query = ''): array
{
    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $r = test()->actingAs($user)->get(LOGC_ROTA.$query, [
        'X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'X-Inertia-Version' => (string) $versao,
        'X-Inertia-Partial-Data' => 'logins', 'X-Inertia-Partial-Component' => 'Crm/Logins/Index',
    ]);
    $r->assertOk();

    return collect((array) $r->json('props.logins.data'))
        ->filter(fn ($l) => str_starts_with((string) ($l['usuario'] ?? ''), LOGC_TAG))->values()->all();
}

it('UC-CRMLOG-01 · com a flag a lista é Inertia; sem ela, ou com ?classico=1, é a Blade', function () {
    $op = logcOperador(['crm.access_contact_login']);

    $this->actingAs($op)->get(LOGC_ROTA)->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p->component('Crm/Logins/Index', false)->has('filtros'));

    $this->actingAs($op)->get(LOGC_ROTA.'?classico=1')->assertOk()->assertViewIs('crm::contact_login.all_contacts_login');

    config(['feature-flags.forced_on' => '']);
    app()->instance(\App\Services\FeatureFlagService::class, new class extends \App\Services\FeatureFlagService
    {
        public function isOn(string $flag, array $attrs = []): bool
        {
            return $flag === 'useV2CrmLogins' ? false : parent::isOn($flag, $attrs);
        }
    });
    $this->actingAs($op)->get(LOGC_ROTA)->assertOk()->assertViewIs('crm::contact_login.all_contacts_login');
});

it('UC-CRMLOG-02 · só logins de contato do meu negócio [T0]', function () {
    $op = logcOperador(['crm.access_contact_login']);
    logcLogin(LOGC_BIZ, logcContato(LOGC_BIZ, 'Meu'), 'meu');
    logcLogin(LOGC_OUTRO, logcContato(LOGC_OUTRO, 'Vizinho'), 'vizinho');

    $usuarios = array_column(logcLinhas($op), 'usuario');

    expect($usuarios)->toContain(LOGC_TAG.'_meu');
    $this->assertNotContains(LOGC_TAG.'_vizinho', $usuarios, 'vazou login de contato de outro negócio');
    $this->assertNotContains(LOGC_TAG.'_op', $usuarios, 'usuário da equipe não é login de contato');
});

it('UC-CRMLOG-03 · nenhuma linha traz password nem remember_token [T0]', function () {
    $op = logcOperador(['crm.access_contact_login']);
    logcLogin(LOGC_BIZ, logcContato(LOGC_BIZ, 'Cofre'), 'cofre');

    $linhas = logcLinhas($op);

    $this->assertNotEmpty($linhas, 'pré-condição: o login semeado tem de aparecer, senão o teste passa no vazio');
    foreach ($linhas as $linha) {
        $this->assertArrayNotHasKey('password', $linha, 'a senha chegou às props');
        $this->assertArrayNotHasKey('remember_token', $linha, 'o remember_token chegou às props');
        $this->assertStringNotContainsString('nao-vaza', (string) json_encode($linha), 'algo da credencial chegou às props');
    }
});

it('UC-CRMLOG-04 · sem crm.access_contact_login a rota devolve 403', function () {
    $this->actingAs(logcOperador([], 'sem'))->get(LOGC_ROTA)->assertForbidden();
});

it('UC-CRMLOG-05 · o filtro de contato e a busca por usuário restringem a lista', function () {
    $op = logcOperador(['crm.access_contact_login']);
    $alfa = logcContato(LOGC_BIZ, 'Alfa');
    logcLogin(LOGC_BIZ, $alfa, 'alfa');
    logcLogin(LOGC_BIZ, logcContato(LOGC_BIZ, 'Beta'), 'beta');

    $porContato = array_column(logcLinhas($op, '?contact_id='.$alfa), 'usuario');
    expect($porContato)->toContain(LOGC_TAG.'_alfa');
    $this->assertNotContains(LOGC_TAG.'_beta', $porContato, 'o filtro de contato não restringiu');

    $porBusca = array_column(logcLinhas($op, '?q='.LOGC_TAG.'_beta'), 'usuario');
    expect($porBusca)->toContain(LOGC_TAG.'_beta');
    $this->assertNotContains(LOGC_TAG.'_alfa', $porBusca, 'a busca por usuário não restringiu');
});
