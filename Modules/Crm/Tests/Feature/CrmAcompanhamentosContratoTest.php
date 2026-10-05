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
    foreach (['business', 'users', 'contacts', 'crm_schedules', 'crm_schedule_users', 'crm_schedule_logs'] as $t) {
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

// ── Escrita (thread Crm/07, PR-a): o modal da tela grava pelas MESMAS rotas da Blade ──────────

const ACO_AJAX = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];

/** Contato cru no negócio `$biz` (marcado pela tag, limpo no afterEach). */
function acoContato(int $biz, User $criador): int
{
    acoNegocio($biz);

    return DB::table('contacts')->insertGetId([
        'business_id' => $biz, 'type' => 'customer', 'name' => 'Contato '.ACO_TAG, 'mobile' => '0',
        'created_by' => $criador->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** O corpo que o modal manda: datas ISO do `<input type="datetime-local">`. */
function acoCorpo(string $titulo, int $contato, User $atribuido, array $extra = []): array
{
    return array_merge([
        'title' => $titulo.' '.ACO_TAG, 'contact_id' => $contato, 'status' => 'scheduled',
        'start_datetime' => '2026-10-10T09:00', 'end_datetime' => '2026-10-10T09:30',
        'description' => 'medir fachada', 'schedule_type' => 'call', 'followup_category_id' => null,
        'user_id' => [$atribuido->id], 'allow_notification' => 0,
        'notify_via' => ['sms' => 0, 'mail' => 1], 'notify_before' => 30, 'notify_type' => 'minute',
    ], $extra);
}

it('UC-CRMACO-08 · adicionar grava no meu negócio, com as datas ISO do modal', function () {
    $user = acoUsuario('aco_todos_test', ['crm.access_all_schedule']);
    $contato = acoContato(ACO_BIZ, $user);

    $this->actingAs($user)->post(ACO_ROTA, acoCorpo('Novo pelo modal', $contato, $user), ACO_AJAX)
        ->assertOk()->assertJson(['success' => true]);

    $linha = DB::table('crm_schedules')->where('title', 'Novo pelo modal '.ACO_TAG)->first();
    $this->assertNotNull($linha, 'o store não gravou o acompanhamento');
    expect((int) $linha->business_id)->toBe(ACO_BIZ);
    expect((string) $linha->start_datetime)->toBe('2026-10-10 09:00:00');
    expect(DB::table('crm_schedule_users')->where('schedule_id', $linha->id)->pluck('user_id')->map(fn ($i) => (int) $i)->all())->toBe([$user->id]);
});

it('UC-CRMACO-09 · editar altera o acompanhamento e não aceita trocar o negócio', function () {
    $user = acoUsuario('aco_todos_test', ['crm.access_all_schedule']);
    $id = acoAcompanhamento(ACO_BIZ, 'Antes', $user);
    $contato = (int) DB::table('crm_schedules')->where('id', $id)->value('contact_id');

    $this->actingAs($user)->put(ACO_ROTA.'/'.$id, acoCorpo('Depois', $contato, $user, ['business_id' => ACO_OUTRO]), ACO_AJAX)
        ->assertOk()->assertJson(['success' => true]);

    $linha = DB::table('crm_schedules')->where('id', $id)->first();
    expect($linha->title)->toBe('Depois '.ACO_TAG);
    expect((int) $linha->business_id)->toBe(ACO_BIZ);
});

it('UC-CRMACO-10 · editar ou excluir acompanhamento de outro negócio dá 404 e nada muda [T0]', function () {
    $user = acoUsuario('aco_todos_test', ['crm.access_all_schedule']);
    $vizinho = acoUsuario('aco_vizinho_test', ['crm.access_all_schedule'], ACO_OUTRO);
    $alheio = acoAcompanhamento(ACO_OUTRO, 'Do vizinho', $vizinho);
    $meuContato = acoContato(ACO_BIZ, $user);

    $this->actingAs($user)->put(ACO_ROTA.'/'.$alheio, acoCorpo('Sequestrado', $meuContato, $user), ACO_AJAX)->assertNotFound();
    $this->actingAs($user)->delete(ACO_ROTA.'/'.$alheio, [], ACO_AJAX)->assertNotFound();

    $linha = DB::table('crm_schedules')->where('id', $alheio)->first();
    $this->assertNotNull($linha, 'o acompanhamento de outro negócio foi excluído');
    expect($linha->title)->toBe('Do vizinho '.ACO_TAG);
});

it('UC-CRMACO-11 · excluir remove o acompanhamento do meu negócio', function () {
    $user = acoUsuario('aco_todos_test', ['crm.access_all_schedule']);
    $id = acoAcompanhamento(ACO_BIZ, 'Para excluir', $user);

    $this->actingAs($user)->delete(ACO_ROTA.'/'.$id, [], ACO_AJAX)->assertOk()->assertJson(['success' => true]);

    expect(DB::table('crm_schedules')->where('id', $id)->exists())->toBeFalse();
});

it('UC-CRMACO-12 · adicionar com contato de outro negócio é recusado e nada é gravado [T0]', function () {
    $user = acoUsuario('aco_todos_test', ['crm.access_all_schedule']);
    $vizinho = acoUsuario('aco_vizinho_test', ['crm.access_all_schedule'], ACO_OUTRO);
    $contatoAlheio = acoContato(ACO_OUTRO, $vizinho);

    $this->actingAs($user)->post(ACO_ROTA, acoCorpo('Contato alheio', $contatoAlheio, $user), ACO_AJAX)
        ->assertStatus(422)->assertJsonValidationErrors('contact_id');

    // Mesmo vale para atribuir a um usuário de outro negócio (o `sync` aceitaria o id cru).
    $meuContato = acoContato(ACO_BIZ, $user);
    $this->actingAs($user)->post(ACO_ROTA, acoCorpo('Usuario alheio', $meuContato, $vizinho), ACO_AJAX)
        ->assertStatus(422)->assertJsonValidationErrors('user_id.0');

    expect(DB::table('crm_schedules')->where('title', 'Contato alheio '.ACO_TAG)->exists())->toBeFalse();
    expect(DB::table('crm_schedules')->where('title', 'Usuario alheio '.ACO_TAG)->exists())->toBeFalse();
});

it('UC-CRMACO-13 · a linha da lista traz os valores do modal de edição', function () {
    $user = acoUsuario('aco_todos_test', ['crm.access_all_schedule']);
    acoAcompanhamento(ACO_BIZ, 'Com edicao', $user);
    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());

    $linhas = $this->actingAs($user)->get(ACO_ROTA, [
        'X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'X-Inertia-Version' => (string) $versao,
        'X-Inertia-Partial-Data' => 'acompanhamentos', 'X-Inertia-Partial-Component' => 'Crm/Acompanhamentos/Index',
    ])->assertOk()->json('props.acompanhamentos.data');

    $linha = collect($linhas)->firstWhere('titulo', 'Com edicao '.ACO_TAG);
    $this->assertNotNull($linha, 'a linha criada não veio na lista');
    expect($linha['editar']['user_id'])->toBe([(string) $user->id]);
    expect($linha['editar']['start_datetime'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/');
    expect($linha['editar']['schedule_type'])->toBe('call');
});

// ── Escrita (thread Crm/07, PR-b): recorrente e registro, pelas mesmas rotas da Blade ─────────

/** O corpo do modal de recorrente: sem contato e sem datas, como a Blade create_recursive_follow_up. */
function acoCorpoRecorrente(string $titulo, User $atribuido, array $extra = []): array
{
    return array_merge([
        'title' => $titulo.' '.ACO_TAG, 'status' => 'scheduled', 'description' => '', 'schedule_type' => 'call',
        'followup_category_id' => null, 'user_id' => [$atribuido->id], 'follow_up_by' => 'payment_status',
        'follow_up_by_value' => 'overdue', 'recursion_days' => 7, 'allow_notification' => 0,
        'notify_via' => ['sms' => 0, 'mail' => 1], 'notify_before' => 1, 'notify_type' => 'hour',
    ], $extra);
}

it('UC-CRMACO-14 · adicionar recorrente grava no meu negócio, marcado como recorrente e sem datas', function () {
    $user = acoUsuario('aco_todos_test', ['crm.access_all_schedule']);

    $this->actingAs($user)->post(ACO_ROTA, acoCorpoRecorrente('Cobrar vencidos', $user, ['is_recursive' => 1]), ACO_AJAX)
        ->assertOk()->assertJson(['success' => true]);

    $linha = DB::table('crm_schedules')->where('title', 'Cobrar vencidos '.ACO_TAG)->first();
    $this->assertNotNull($linha, 'o store não gravou o recorrente');
    expect((int) $linha->business_id)->toBe(ACO_BIZ);
    expect((int) $linha->is_recursive)->toBe(1);
    expect((int) $linha->recursion_days)->toBe(7);
    expect($linha->follow_up_by_value)->toBe('overdue');
    expect($linha->start_datetime)->toBeNull();
});

it('UC-CRMACO-15 · editar recorrente altera os dias, segue recorrente e não troca o negócio [T0]', function () {
    $user = acoUsuario('aco_todos_test', ['crm.access_all_schedule']);
    $id = acoAcompanhamento(ACO_BIZ, 'Recorrente antes', $user, 1);
    DB::table('crm_schedules')->where('id', $id)->update(['start_datetime' => null, 'end_datetime' => null]);

    $this->actingAs($user)->put(ACO_ROTA.'/'.$id, acoCorpoRecorrente('Recorrente depois', $user, ['recursion_days' => 15, 'business_id' => ACO_OUTRO]), ACO_AJAX)
        ->assertOk()->assertJson(['success' => true]);

    $linha = DB::table('crm_schedules')->where('id', $id)->first();
    expect($linha->title)->toBe('Recorrente depois '.ACO_TAG);
    expect((int) $linha->recursion_days)->toBe(15);
    expect((int) $linha->is_recursive)->toBe(1);
    expect((int) $linha->business_id)->toBe(ACO_BIZ);
});

it('UC-CRMACO-16 · adicionar registro grava o log com as datas ISO e troca o status do acompanhamento', function () {
    $user = acoUsuario('aco_todos_test', ['crm.access_all_schedule']);
    $id = acoAcompanhamento(ACO_BIZ, 'Com registro', $user);

    $this->actingAs($user)->post('/crm/follow-up-log', [
        'schedule_id' => $id, 'subject' => 'Cliente atendeu', 'log_type' => 'call',
        'start_datetime' => '2026-10-10T09:00', 'end_datetime' => '2026-10-10T09:10', 'description' => '', 'status' => 'completed',
    ], ACO_AJAX)->assertOk()->assertJson(['success' => true]);

    $log = DB::table('crm_schedule_logs')->where('schedule_id', $id)->first();
    $this->assertNotNull($log, 'o store do registro não gravou o log');
    expect((string) $log->start_datetime)->toBe('2026-10-10 09:00:00');
    expect(DB::table('crm_schedules')->where('id', $id)->value('status'))->toBe('completed');
});

it('UC-CRMACO-17 · registro em acompanhamento de outro negócio não grava nem muda o status [T0]', function () {
    $user = acoUsuario('aco_todos_test', ['crm.access_all_schedule']);
    $vizinho = acoUsuario('aco_vizinho_test', ['crm.access_all_schedule'], ACO_OUTRO);
    $alheio = acoAcompanhamento(ACO_OUTRO, 'Do vizinho', $vizinho);

    // A rota recusa pelo findOrFail no negócio da sessão: 404, como editar/excluir (UC-10).
    // Medido na lane verticais-pest (run 37319306701): o catch não engole o ModelNotFound.
    $this->actingAs($user)->post('/crm/follow-up-log', [
        'schedule_id' => $alheio, 'subject' => 'Intruso', 'log_type' => 'call',
        'start_datetime' => '2026-10-10T09:00', 'end_datetime' => '2026-10-10T09:10', 'status' => 'completed',
    ], ACO_AJAX)->assertNotFound();

    expect(DB::table('crm_schedule_logs')->where('schedule_id', $alheio)->exists())->toBeFalse();
    expect(DB::table('crm_schedules')->where('id', $alheio)->value('status'))->toBe('scheduled');
});
