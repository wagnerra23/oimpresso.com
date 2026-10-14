<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * Baseline da Blade `/crm/all-contacts-login` — thread Crm/16, MWART F2 (ADR 0104).
 *
 * Trava o comportamento de HOJE antes da Page (F3): permissão, negócio da sessão no ajax
 * (`GET /crm/contact-login`), senha fora do JSON e filtro por contato.
 * Plano em memory/requisitos/Crm/RUNBOOK-logins.md.
 *
 * Tenant 98 (fictício, ADR 0358) e 99 como o "outro negócio". NUNCA biz=4.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 *
 * @see Modules/Crm/Http/Controllers/ContactLoginController::index()
 * @see Modules/Crm/Http/Controllers/ContactLoginController::allContactsLoginList()
 */
const LOGB_TAG = 'logb16';
const LOGB_BIZ = 98;
const LOGB_OUTRO = 99;

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
    logbLimpa();
});

afterEach(fn () => logbLimpa());

function logbLimpa(): void
{
    DB::table('users')->where('username', 'like', LOGB_TAG.'%')->delete();
    DB::table('contacts')->where('name', 'like', '%'.LOGB_TAG.'%')->delete();
}

/** Mesmo conserto do CrmAcompanhamentosContratoTest::acoNegocio (`id` é guarded; `owner_id` é FK). */
function logbNegocio(int $biz): void
{
    if (Business::whereKey($biz)->exists()) {
        return;
    }
    $dono = (int) DB::table('users')->min('id');
    (new Business)->forceFill(['id' => $biz, 'name' => 'Tenant fictício crm '.$biz, 'currency_id' => 1, 'owner_id' => $dono])->save();
}

/** Usuário da equipe que opera a tela. `user_type`/`allow_login` explícitos por causa do CheckUserLogin. */
function logbOperador(string $sufixo, array $permissoes, int $biz = LOGB_BIZ): User
{
    logbNegocio($biz);
    $user = User::create([
        'username' => LOGB_TAG.'_op_'.$sufixo, 'email' => LOGB_TAG.'_op_'.$sufixo.'@test.local',
        'password' => bcrypt('secret'), 'business_id' => $biz, 'first_name' => 'Op', 'last_name' => 'Teste',
        'user_type' => 'user', 'allow_login' => 1,
    ]);
    $user->syncPermissions(array_map(fn ($p) => Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']), $permissoes));

    return $user;
}

function logbContato(int $biz, string $nome): int
{
    logbNegocio($biz);

    return DB::table('contacts')->insertGetId([
        'business_id' => $biz, 'type' => 'customer', 'name' => $nome.' '.LOGB_TAG, 'mobile' => '0',
        'created_by' => (int) DB::table('users')->min('id'), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Login de contato como o CrmUtil::creatContactPerson grava (user_type user_customer). */
function logbLogin(int $biz, int $contato, string $sufixo): int
{
    return DB::table('users')->insertGetId([
        'username' => LOGB_TAG.'_'.$sufixo, 'email' => LOGB_TAG.'_'.$sufixo.'@test.local',
        'password' => bcrypt('nao-vaza'), 'business_id' => $biz, 'crm_contact_id' => $contato,
        'first_name' => 'Login', 'last_name' => $sufixo, 'user_type' => 'user_customer', 'allow_login' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** O ajax do DataTables da Blade. */
function logbAjax(User $user, array $query = []): array
{
    $r = test()->actingAs($user)->get('/crm/contact-login?'.http_build_query($query), ['X-Requested-With' => 'XMLHttpRequest']);
    $r->assertOk();

    return (array) $r->json('data');
}

it('a Blade abre com crm.access_contact_login e barra quem não tem', function () {
    $this->actingAs(logbOperador('sem', []))->get('/crm/all-contacts-login')->assertForbidden();

    $this->actingAs(logbOperador('com', ['crm.access_contact_login']))
        ->get('/crm/all-contacts-login')
        ->assertOk()
        ->assertViewIs('crm::contact_login.all_contacts_login');
});

it('o ajax lista só os logins de contato do negócio da sessão, sem a senha [T0]', function () {
    $op = logbOperador('com', ['crm.access_contact_login']);
    logbLogin(LOGB_BIZ, logbContato(LOGB_BIZ, 'Meu'), 'meu');
    logbLogin(LOGB_OUTRO, logbContato(LOGB_OUTRO, 'Vizinho'), 'vizinho');

    $linhas = collect(logbAjax($op))->filter(fn ($l) => str_starts_with((string) ($l['username'] ?? ''), LOGB_TAG));
    $usuarios = $linhas->pluck('username')->all();

    expect($usuarios)->toContain(LOGB_TAG.'_meu');
    $this->assertNotContains(LOGB_TAG.'_vizinho', $usuarios, 'vazou login de contato de outro negócio');
    $this->assertNotContains(LOGB_TAG.'_op_com', $usuarios, 'usuário da equipe (sem crm_contact_id) não é login de contato');
    foreach ($linhas as $linha) {
        $this->assertArrayNotHasKey('password', $linha, 'a senha saiu no JSON do DataTables');
        $this->assertArrayNotHasKey('remember_token', $linha, 'o remember_token saiu no JSON do DataTables');
    }
});

it('o filtro de contato restringe o ajax ao contato escolhido', function () {
    $op = logbOperador('com', ['crm.access_contact_login']);
    $a = logbContato(LOGB_BIZ, 'Alfa');
    $b = logbContato(LOGB_BIZ, 'Beta');
    logbLogin(LOGB_BIZ, $a, 'alfa');
    logbLogin(LOGB_BIZ, $b, 'beta');

    $usuarios = collect(logbAjax($op, ['contact_id' => $a]))->pluck('username')->all();

    expect($usuarios)->toContain(LOGB_TAG.'_alfa');
    $this->assertNotContains(LOGB_TAG.'_beta', $usuarios, 'o filtro de contato não restringiu');
});
