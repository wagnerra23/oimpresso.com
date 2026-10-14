<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class);

/**
 * Baseline da tela `/crm/call-log` (Registro de chamadas) ANTES da migração — thread Crm/12, F2.
 *
 * Prende o comportamento da Blade/DataTables que a F3 tem de manter. Âncora:
 *   memory/requisitos/Crm/chamadas-parity.md (linhas 1-6) + RUNBOOK-chamadas.md §5-6.
 * A exclusão em massa tem teste próprio (CrmChamadasExclusaoEscopoTest, achado B2).
 *
 * A tela fica desligada por config fixa (`enable_crm_call_log => false`, achado B1): o teste
 * liga por `config()` para medir o resto, e prova o portão desligado em UC-CHAB-01.
 *
 * Tenant 98 (fictício, ADR 0358) e 99 como o "outro negócio". NUNCA biz=4.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 *
 * @see Modules/Crm/Http/Controllers/CallLogController::index()
 */
const CHAB_TAG = '[chab12]';
const CHAB_BIZ = 98;
const CHAB_OUTRO = 99;
const CHAB_ROTA = '/crm/call-log';
const CHAB_AJAX = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];

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
    chabLimpa();
});

afterEach(fn () => chabLimpa());

function chabLimpa(): void
{
    if (Schema::hasTable('crm_call_logs')) {
        DB::table('crm_call_logs')->where('mobile_name', CHAB_TAG)->delete();
    }
    if (Schema::hasTable('contacts')) {
        DB::table('contacts')->where('name', 'like', '%'.CHAB_TAG.'%')->delete();
    }
}

/** Mesmo conserto do CrmAcompanhamentosContratoTest::acoNegocio — `id` é guarded. */
function chabNegocio(int $biz): void
{
    if (Business::whereKey($biz)->exists()) {
        return;
    }
    $dono = (int) DB::table('users')->min('id');
    (new Business)->forceFill(['id' => $biz, 'name' => 'Tenant fictício crm '.$biz, 'currency_id' => 1, 'owner_id' => $dono])->save();
}

function chabUsuario(string $username, array $permissoes, int $biz = CHAB_BIZ): User
{
    chabNegocio($biz);
    $user = User::firstOrCreate(['username' => $username], [
        'email' => $username.'@test.local', 'password' => bcrypt('secret'),
        'business_id' => $biz, 'first_name' => 'Chab', 'last_name' => 'Teste',
        'user_type' => 'user', 'allow_login' => 1,
    ]);
    $user->syncPermissions(array_map(fn ($p) => Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']), $permissoes));
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user->fresh();
}

function chabContato(int $biz, User $criador): int
{
    chabNegocio($biz);

    return DB::table('contacts')->insertGetId([
        'business_id' => $biz, 'type' => 'customer', 'name' => 'Contato '.CHAB_TAG, 'mobile' => '0',
        'created_by' => $criador->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function chabRegistro(int $biz, User $criador, ?int $contato = null): int
{
    chabNegocio($biz);

    return DB::table('crm_call_logs')->insertGetId([
        'business_id' => $biz, 'user_id' => $criador->id, 'call_type' => 'outgoing',
        'mobile_number' => '0', 'mobile_name' => CHAB_TAG, 'contact_id' => $contato,
        'start_time' => now(), 'end_time' => now(), 'duration' => 60,
        'created_by' => $criador->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Ids (só os marcados por este arquivo) que o DataTables da Blade devolve. */
function chabLista(User $user, string $query = ''): array
{
    $resposta = test()->actingAs($user)->get(CHAB_ROTA.$query, CHAB_AJAX);
    $resposta->assertOk();

    return collect((array) $resposta->json('data'))
        ->filter(fn ($r) => ($r['mobile_name'] ?? null) === CHAB_TAG)
        ->map(fn ($r) => (int) $r['id'])
        ->values()->all();
}

it('UC-CHAB-01 · desligada por config é 403 até para quem vê todos; ligada, exige permissão de chamada', function () {
    $todos = chabUsuario('chab_todos_test', ['crm.view_all_call_log']);

    config(['constants.enable_crm_call_log' => false]);
    $this->actingAs($todos)->get(CHAB_ROTA)->assertForbidden();

    config(['constants.enable_crm_call_log' => true]);
    $this->actingAs(chabUsuario('chab_sem_test', []))->get(CHAB_ROTA)->assertForbidden();
    $this->actingAs($todos)
        ->withSession(['currency' => ['code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ',']])
        ->get(CHAB_ROTA)
        ->assertOk()
        ->assertViewIs('crm::call_logs.index');
});

it('UC-CHAB-02 · registro de outro negócio não aparece na lista [T0]', function () {
    $todos = chabUsuario('chab_todos_test', ['crm.view_all_call_log']);
    $vizinho = chabUsuario('chab_vizinho_test', ['crm.view_all_call_log'], CHAB_OUTRO);
    $meu = chabRegistro(CHAB_BIZ, $todos);
    $deFora = chabRegistro(CHAB_OUTRO, $vizinho);

    $ids = chabLista($todos);
    expect($ids)->toContain($meu);
    $this->assertNotContains($deFora, $ids, 'vazou registro de chamada de outro business');
});

it('UC-CHAB-03 · quem só vê os próprios vê só os registros que criou', function () {
    $proprio = chabUsuario('chab_proprio_test', ['crm.view_own_call_log']);
    $colega = chabUsuario('chab_colega_test', ['crm.view_all_call_log']);
    $meu = chabRegistro(CHAB_BIZ, $proprio);
    $doColega = chabRegistro(CHAB_BIZ, $colega);

    $ids = chabLista($proprio);
    expect($ids)->toContain($meu);
    $this->assertNotContains($doColega, $ids, 'quem só vê os próprios viu o registro do colega');

    expect(chabLista($colega))->toContain($meu);
});

it('UC-CHAB-04 · os filtros de contato e de "registro criado por" recortam a lista', function () {
    $todos = chabUsuario('chab_todos_test', ['crm.view_all_call_log']);
    $colega = chabUsuario('chab_colega_test', ['crm.view_own_call_log']);
    $contato = chabContato(CHAB_BIZ, $todos);
    $comContato = chabRegistro(CHAB_BIZ, $todos, $contato);
    $doColega = chabRegistro(CHAB_BIZ, $colega);

    $porContato = chabLista($todos, '?contact_id='.$contato);
    expect($porContato)->toContain($comContato);
    $this->assertNotContains($doColega, $porContato, 'o filtro de contato trouxe registro de outro contato');

    $porAutor = chabLista($todos, '?user_id='.$colega->id);
    expect($porAutor)->toContain($doColega);
    $this->assertNotContains($comContato, $porAutor, 'o filtro "criado por" trouxe registro de outro autor');
});
