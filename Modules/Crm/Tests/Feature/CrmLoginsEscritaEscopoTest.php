<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * Escrita de login de contato presa ao escopo certo — achado da thread Crm/16 (Tier 0).
 *
 * Antes: `update()` gravava em QUALQUER usuário do negócio pelo id (inclusive da equipe: e-mail e
 * senha), e `store()`/`update()` aceitavam `crm_contact_id` de outro negócio.
 * Depois: só usuário com `crm_contact_id` é editável, e o contato tem de ser do negócio da sessão.
 *
 * Tenant 98 (fictício, ADR 0358) e 99 como o "outro negócio". NUNCA biz=4.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 *
 * @see Modules/Crm/Http/Controllers/ContactLoginController::store()
 * @see Modules/Crm/Http/Controllers/ContactLoginController::update()
 */
const LOGX_TAG = 'logx16';
const LOGX_BIZ = 98;
const LOGX_OUTRO = 99;

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
    logxLimpa();
});

afterEach(fn () => logxLimpa());

function logxLimpa(): void
{
    DB::table('users')->where('username', 'like', LOGX_TAG.'%')->delete();
    DB::table('contacts')->where('name', 'like', '%'.LOGX_TAG.'%')->delete();
}

/** Mesmo conserto do CrmAcompanhamentosContratoTest::acoNegocio (`id` é guarded; `owner_id` é FK). */
function logxNegocio(int $biz): void
{
    if (Business::whereKey($biz)->exists()) {
        return;
    }
    $dono = (int) DB::table('users')->min('id');
    (new Business)->forceFill(['id' => $biz, 'name' => 'Tenant fictício crm '.$biz, 'currency_id' => 1, 'owner_id' => $dono])->save();
}

function logxEquipe(string $sufixo, array $permissoes = []): User
{
    logxNegocio(LOGX_BIZ);
    $user = User::create([
        'username' => LOGX_TAG.'_'.$sufixo, 'email' => LOGX_TAG.'_'.$sufixo.'@test.local',
        'password' => bcrypt('senha-original'), 'business_id' => LOGX_BIZ, 'first_name' => 'Equipe', 'last_name' => $sufixo,
        'user_type' => 'user', 'allow_login' => 1,
    ]);
    $user->syncPermissions(array_map(fn ($p) => Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']), $permissoes));

    return $user;
}

function logxContato(int $biz, string $nome): int
{
    logxNegocio($biz);

    return DB::table('contacts')->insertGetId([
        'business_id' => $biz, 'type' => 'customer', 'name' => $nome.' '.LOGX_TAG, 'mobile' => '0',
        'created_by' => (int) DB::table('users')->min('id'), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function logxLogin(int $contato, string $sufixo): int
{
    return DB::table('users')->insertGetId([
        'username' => LOGX_TAG.'_'.$sufixo, 'email' => LOGX_TAG.'_'.$sufixo.'@test.local',
        'password' => bcrypt('senha-original'), 'business_id' => LOGX_BIZ, 'crm_contact_id' => $contato,
        'first_name' => 'Login', 'last_name' => $sufixo, 'user_type' => 'user_customer', 'allow_login' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function logxEditar($teste, User $operador, int $id, int $contato, string $email)
{
    return $teste->actingAs($operador)->put('/crm/contact-login/'.$id, [
        'crm_contact_id' => $contato, 'email' => $email, 'password' => 'senha-trocada', 'is_active' => 1,
    ], ['X-Requested-With' => 'XMLHttpRequest']);
}

it('editar login de contato do meu negócio funciona (controle positivo)', function () {
    $op = logxEquipe('op', ['crm.access_contact_login']);
    $contato = logxContato(LOGX_BIZ, 'Meu');
    $login = logxLogin($contato, 'meu');

    logxEditar($this, $op, $login, $contato, LOGX_TAG.'_novo@test.local')->assertOk()->assertJson(['success' => true]);

    expect(DB::table('users')->where('id', $login)->value('email'))->toBe(LOGX_TAG.'_novo@test.local');
});

it('o id de um usuário da equipe não é editável como login de contato [T0]', function () {
    $op = logxEquipe('op', ['crm.access_contact_login']);
    $admin = logxEquipe('admin');
    $contato = logxContato(LOGX_BIZ, 'Meu');
    $antes = DB::table('users')->where('id', $admin->id)->first(['email', 'password', 'crm_contact_id']);

    logxEditar($this, $op, $admin->id, $contato, LOGX_TAG.'_sequestro@test.local')->assertNotFound();

    $depois = DB::table('users')->where('id', $admin->id)->first(['email', 'password', 'crm_contact_id']);
    $this->assertSame($antes->email, $depois->email, 'o e-mail do usuário da equipe mudou');
    $this->assertSame($antes->password, $depois->password, 'a senha do usuário da equipe mudou');
    $this->assertNull($depois->crm_contact_id, 'o usuário da equipe virou login de contato');
});

it('contato de outro negócio é recusado ao editar e ao criar [T0]', function () {
    $op = logxEquipe('op', ['crm.access_contact_login']);
    $meu = logxContato(LOGX_BIZ, 'Meu');
    $alheio = logxContato(LOGX_OUTRO, 'Alheio');
    $login = logxLogin($meu, 'meu');

    logxEditar($this, $op, $login, $alheio, LOGX_TAG.'_x@test.local')->assertNotFound();
    expect((int) DB::table('users')->where('id', $login)->value('crm_contact_id'))->toBe($meu);

    $this->actingAs($op)->post('/crm/contact-login', [
        'crm_contact_id' => $alheio, 'username' => LOGX_TAG.'_criado', 'email' => LOGX_TAG.'_criado@test.local',
        'password' => 'senha-nova', 'first_name' => 'Novo', 'allow_login' => 1, 'is_active' => 1,
    ], ['X-Requested-With' => 'XMLHttpRequest'])->assertNotFound();
    $this->assertFalse(DB::table('users')->where('username', LOGX_TAG.'_criado')->exists(), 'criou login preso a contato de outro negócio');
});
