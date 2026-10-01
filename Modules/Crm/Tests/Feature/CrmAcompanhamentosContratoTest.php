<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * Contrato da lista `/crm/follow-ups` (Acompanhamentos) — thread Crm/03, Blade → Inertia.
 *
 * Os UCs vêm do contrato, não do código:
 *   Modules/Crm/Resources/js/Pages/Crm/Acompanhamentos/Index.casos.md
 *
 * Tenant 98 (fictício, ADR 0358) e 99 como o "outro negócio". NUNCA biz=4.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 *
 * @see Modules/Crm/Http/Controllers/ScheduleController::index()
 */
const ACO_TAG = '[aco03]';
const ACO_BIZ = 98;
const ACO_OUTRO = 99;
const ACO_ROTA = '/crm/follow-ups';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: crm_schedules requer schema MySQL UltimatePOS.');
    }
    foreach (['business', 'users', 'contacts', 'crm_schedules', 'crm_schedule_users'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente.");
        }
    }
    acoLimpa();
});

afterEach(fn () => acoLimpa());

function acoLimpa(): void
{
    if (Schema::hasTable('crm_schedules')) {
        DB::table('crm_schedules')->where('title', 'like', '%'.ACO_TAG.'%')->delete();
    }
    if (Schema::hasTable('contacts')) {
        DB::table('contacts')->where('name', 'like', '%'.ACO_TAG.'%')->delete();
    }
}

/**
 * Garante o business `$biz`. `id` é guarded no model: `firstOrCreate(['id' => 99])` não cria
 * o 99 — insere outro id SEM `owner_id` e cai no FK `business_owner_id_foreign` (medido na lane
 * verticais-pest, run 36867242229 do #8421: passava só quando outro arquivo já tinha criado o 99
 * antes). Mesmo conserto do CrmPainelContratoTest::paiNegocio e do CrmLeadsContratoTest::leadNegocio.
 */
function acoNegocio(int $biz): void
{
    if (Business::whereKey($biz)->exists()) {
        return;
    }
    $dono = (int) DB::table('users')->min('id');
    (new Business)->forceFill(['id' => $biz, 'name' => 'Tenant fictício crm '.$biz, 'currency_id' => 1, 'owner_id' => $dono])->save();
}

function acoUsuario(string $username, array $permissoes, int $biz = ACO_BIZ): User
{
    acoNegocio($biz);
    // `user_type` e `allow_login` explícitos: o `CheckUserLogin` da rota barra quem não tem os
    // dois, e o model recém-criado NÃO traz os defaults da coluna — medido no CI: 403 só no
    // teste em que o usuário nascia, e 200 nos seguintes, que o liam do banco.
    $user = User::firstOrCreate(['username' => $username], [
        'email' => $username.'@test.local', 'password' => bcrypt('secret'),
        'business_id' => $biz, 'first_name' => 'Aco', 'last_name' => 'Teste',
        'user_type' => 'user', 'allow_login' => 1,
    ]);
    $user->syncPermissions(array_map(fn ($p) => Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']), $permissoes));

    return $user;
}

/** Acompanhamento cru, atribuído a `$atribuido`. */
function acoAcompanhamento(int $biz, string $titulo, User $atribuido, int $recorrente = 0): int
{
    acoNegocio($biz);
    $contato = DB::table('contacts')->insertGetId([
        'business_id' => $biz, 'type' => 'customer', 'name' => 'Contato '.ACO_TAG, 'mobile' => '0',
        'created_by' => $atribuido->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $id = DB::table('crm_schedules')->insertGetId([
        'business_id' => $biz, 'contact_id' => $contato, 'title' => $titulo.' '.ACO_TAG,
        'status' => 'scheduled', 'schedule_type' => 'call', 'is_recursive' => $recorrente,
        'start_datetime' => now(), 'end_datetime' => now()->addMinutes(30),
        'created_by' => $atribuido->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('crm_schedule_users')->insert(['schedule_id' => $id, 'user_id' => $atribuido->id, 'created_at' => now(), 'updated_at' => now()]);

    return $id;
}

/** A prop deferida `acompanhamentos`, pedida como o browser pede (X-Inertia + X-Requested-With). */
function acoLista(User $user, string $query = ''): array
{
    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $resposta = test()->actingAs($user)->get(ACO_ROTA.$query, [
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => (string) $versao,
        'X-Inertia-Partial-Data' => 'acompanhamentos',
        'X-Inertia-Partial-Component' => 'Crm/Acompanhamentos/Index',
    ]);
    $resposta->assertOk();

    return collect((array) $resposta->json('props.acompanhamentos.data'))
        ->filter(fn ($r) => str_contains((string) ($r['titulo'] ?? ''), ACO_TAG))
        ->pluck('titulo')->all();
}

it('UC-CRMACO-01 · a lista responde Inertia com o componente Crm/Acompanhamentos/Index', function () {
    $this->actingAs(acoUsuario('aco_todos_test', ['crm.access_all_schedule']))
        ->get(ACO_ROTA)
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p->component('Crm/Acompanhamentos/Index', false)->has('filtros'));
});

it('UC-CRMACO-02 · visita Inertia não cai no DataTables, e o ajax da tela clássica segue recebendo JSON', function () {
    $user = acoUsuario('aco_todos_test', ['crm.access_all_schedule']);
    acoAcompanhamento(ACO_BIZ, 'Ligar fachada', $user);

    expect(acoLista($user))->toContain('Ligar fachada '.ACO_TAG);

    $json = $this->actingAs($user)->get(ACO_ROTA, ['X-Requested-With' => 'XMLHttpRequest']);
    $json->assertOk();
    $this->assertArrayHasKey('data', (array) $json->json(), 'o DataTables da tela clássica deve continuar recebendo JSON');
    $this->assertNull($json->headers->get('X-Inertia'), 'ajax sem X-Inertia não pode virar resposta Inertia');
});

it('UC-CRMACO-03 · acompanhamento de outro negócio não aparece [T0]', function () {
    $user = acoUsuario('aco_todos_test', ['crm.access_all_schedule']);
    $vizinho = acoUsuario('aco_vizinho_test', ['crm.access_all_schedule'], ACO_OUTRO);
    acoAcompanhamento(ACO_BIZ, 'Meu negocio', $user);
    acoAcompanhamento(ACO_OUTRO, 'Negocio vizinho', $vizinho);

    $titulos = acoLista($user);
    expect($titulos)->toContain('Meu negocio '.ACO_TAG);
    $this->assertNotContains('Negocio vizinho '.ACO_TAG, $titulos, 'vazou acompanhamento de outro business');
});

it('UC-CRMACO-04 · quem só pode ver os próprios vê só os atribuídos a ele', function () {
    $proprio = acoUsuario('aco_proprio_test', ['crm.access_own_schedule']);
    $outro = acoUsuario('aco_outro_test', ['crm.access_all_schedule']);
    acoAcompanhamento(ACO_BIZ, 'Atribuido a mim', $proprio);
    acoAcompanhamento(ACO_BIZ, 'Atribuido ao colega', $outro);

    $titulos = acoLista($proprio);
    expect($titulos)->toContain('Atribuido a mim '.ACO_TAG);
    $this->assertNotContains('Atribuido ao colega '.ACO_TAG, $titulos, 'quem só vê os próprios viu o do colega');

    expect(acoLista($outro))->toContain('Atribuido ao colega '.ACO_TAG);
});

it('UC-CRMACO-05 · sem permissão de acompanhamento é barrado', function () {
    $this->actingAs(acoUsuario('aco_sem_test', []))->get(ACO_ROTA)->assertForbidden();
    $this->actingAs(acoUsuario('aco_todos_test', ['crm.access_all_schedule']))->get(ACO_ROTA)->assertOk();
});

it('UC-CRMACO-06 · a aba recorrente lista só os recorrentes', function () {
    $user = acoUsuario('aco_todos_test', ['crm.access_all_schedule']);
    acoAcompanhamento(ACO_BIZ, 'Avulso', $user);
    acoAcompanhamento(ACO_BIZ, 'Recorrente', $user, 1);

    $recorrentes = acoLista($user, '?is_recursive=1');
    expect($recorrentes)->toContain('Recorrente '.ACO_TAG);
    $this->assertNotContains('Avulso '.ACO_TAG, $recorrentes, 'a aba recorrente trouxe avulso');

    $this->assertNotContains('Recorrente '.ACO_TAG, acoLista($user), 'a aba principal trouxe recorrente');
});

it('UC-CRMACO-07 · ?classico=1 devolve a tela Blade, que hospeda os modais de escrita', function () {
    $resposta = $this->actingAs(acoUsuario('aco_todos_test', ['crm.access_all_schedule']))
        ->withSession(['currency' => ['code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ',']])
        ->get(ACO_ROTA.'?classico=1');

    $resposta->assertOk();
    $resposta->assertViewIs('crm::schedule.index');
});
