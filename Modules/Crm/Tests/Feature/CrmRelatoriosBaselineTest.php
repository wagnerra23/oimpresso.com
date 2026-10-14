<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class);

/**
 * Baseline dos dados dos Relatórios do CRM ANTES da migração — thread Crm/13, F2 do MWART.
 *
 * Prende o que os 3 DataTables da Blade calculam e que a F3 tem de repetir. Âncora:
 *   memory/requisitos/Crm/relatorios-parity.md (linhas 2-4, 6-8) + RUNBOOK-relatorios.md §4-5.
 * O portão (is_admin nos endpoints) tem teste próprio: CrmRelatoriosPermissaoTest (achado C1).
 * Aqui todo usuário é admin do negócio — vale antes e depois do conserto do C1.
 *
 * Tenant 98 (fictício, ADR 0358) e 99 como o "outro negócio". NUNCA biz=4.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 *
 * @see Modules/Crm/Http/Controllers/ReportController
 */
const RELB_TAG = '[relb13]';
const RELB_BIZ = 98;
const RELB_OUTRO = 99;
const RELB_AJAX = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: relatórios do CRM requerem schema MySQL UltimatePOS.');
    }
    foreach (['business', 'users', 'contacts', 'crm_schedules', 'crm_schedule_users', 'roles'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente.");
        }
    }
    relbLimpa();
});

afterEach(fn () => relbLimpa());

function relbLimpa(): void
{
    if (Schema::hasTable('crm_schedules')) {
        $ids = DB::table('crm_schedules')->where('title', 'like', '%'.RELB_TAG.'%')->pluck('id');
        DB::table('crm_schedule_users')->whereIn('schedule_id', $ids)->delete();
        DB::table('crm_schedules')->whereIn('id', $ids)->delete();
    }
    if (Schema::hasTable('contacts')) {
        DB::table('contacts')->where('name', 'like', '%'.RELB_TAG.'%')->delete();
    }
}

/** Mesmo conserto do CrmPainelContratoTest::paiNegocio — `id` é guarded. */
function relbNegocio(int $biz): void
{
    if (Business::whereKey($biz)->exists()) {
        return;
    }
    $dono = (int) DB::table('users')->min('id');
    (new Business)->forceFill(['id' => $biz, 'name' => 'Tenant fictício crm '.$biz, 'currency_id' => 1, 'owner_id' => $dono])->save();
}

function relbAdmin(string $username, int $biz = RELB_BIZ): User
{
    relbNegocio($biz);
    $user = User::firstOrCreate(['username' => $username], [
        'email' => $username.'@test.local', 'password' => bcrypt('secret'),
        'business_id' => $biz, 'first_name' => 'Relb', 'last_name' => 'Teste',
        'user_type' => 'user', 'allow_login' => 1, 'is_cmmsn_agnt' => 0,
    ]);
    $role = Role::firstOrCreate(['name' => 'Admin#'.$biz, 'guard_name' => 'web'], ['business_id' => $biz]);
    $user->syncRoles([$role]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user->fresh();
}

function relbContato(int $biz, string $nome, User $criador, array $extra = []): int
{
    relbNegocio($biz);

    return DB::table('contacts')->insertGetId(array_merge([
        'business_id' => $biz, 'type' => 'customer', 'name' => $nome.' '.RELB_TAG, 'mobile' => '0',
        'created_by' => $criador->id, 'created_at' => now(), 'updated_at' => now(),
    ], $extra));
}

function relbAcompanhamento(int $biz, int $contato, User $atribuido, ?string $status, string $inicio = 'now'): void
{
    $id = DB::table('crm_schedules')->insertGetId([
        'business_id' => $biz, 'contact_id' => $contato, 'title' => 'Acompanhamento '.RELB_TAG,
        'status' => $status, 'schedule_type' => 'call', 'is_recursive' => 0,
        'start_datetime' => \Carbon\Carbon::parse($inicio), 'end_datetime' => \Carbon\Carbon::parse($inicio)->addMinutes(30),
        'created_by' => $atribuido->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('crm_schedule_users')->insert(['schedule_id' => $id, 'user_id' => $atribuido->id, 'created_at' => now(), 'updated_at' => now()]);
}

/** Linhas do DataTables; os contadores podem vir com o link "Ver" em HTML depois do número. */
function relbLinhas(User $user, string $rota): array
{
    $resposta = test()->actingAs($user)
        ->withSession(['business' => ['date_format' => 'Y-m-d', 'time_format' => 24]])
        ->get($rota, RELB_AJAX);
    $resposta->assertOk();

    return (array) $resposta->json('data');
}

function relbNumero(mixed $celula): int
{
    return (int) strtok((string) $celula, '<');
}

it('UC-RELB-01 · por usuário conta os acompanhamentos de cada status, os sem status e o total', function () {
    $admin = relbAdmin('relb_admin_test');
    $contato = relbContato(RELB_BIZ, 'Cliente', $admin);
    relbAcompanhamento(RELB_BIZ, $contato, $admin, 'scheduled');
    relbAcompanhamento(RELB_BIZ, $contato, $admin, 'scheduled');
    relbAcompanhamento(RELB_BIZ, $contato, $admin, 'completed');
    relbAcompanhamento(RELB_BIZ, $contato, $admin, null);

    $linha = collect(relbLinhas($admin, '/crm/follow-ups-by-user'))->firstWhere('user_id', $admin->id);
    expect($linha)->not->toBeNull();
    expect(relbNumero($linha['count_scheduled']))->toBe(2);
    expect(relbNumero($linha['count_completed']))->toBe(1);
    expect(relbNumero($linha['count_nulled']))->toBe(1);
    expect(relbNumero($linha['total_follow_ups']))->toBe(4);
});

it('UC-RELB-02 · por usuário não lista usuário de outro negócio [T0]', function () {
    $admin = relbAdmin('relb_admin_test');
    $vizinho = relbAdmin('relb_vizinho_test', RELB_OUTRO);
    relbAcompanhamento(RELB_OUTRO, relbContato(RELB_OUTRO, 'Cliente vizinho', $vizinho), $vizinho, 'open');

    $usuarios = collect(relbLinhas($admin, '/crm/follow-ups-by-user'))->pluck('user_id')->map('intval')->all();
    $this->assertNotContains($vizinho->id, $usuarios, 'vazou usuário de outro business no relatório por usuário');
});

it('UC-RELB-03 · o intervalo de datas deixa de fora o acompanhamento fora dele', function () {
    $admin = relbAdmin('relb_admin_test');
    $contato = relbContato(RELB_BIZ, 'Cliente', $admin);
    relbAcompanhamento(RELB_BIZ, $contato, $admin, 'open', '2026-01-10 10:00:00');
    relbAcompanhamento(RELB_BIZ, $contato, $admin, 'open', '2026-03-10 10:00:00');

    $linha = collect(relbLinhas($admin, '/crm/follow-ups-by-user?start_date=2026-01-01&end_date=2026-01-31'))
        ->firstWhere('user_id', $admin->id);
    expect($linha)->not->toBeNull();
    expect(relbNumero($linha['total_follow_ups']))->toBe(1);
});

it('UC-RELB-04 · por contato soma os acompanhamentos do contato e não lista contato de outro negócio [T0]', function () {
    $admin = relbAdmin('relb_admin_test');
    $vizinho = relbAdmin('relb_vizinho_test', RELB_OUTRO);
    $meu = relbContato(RELB_BIZ, 'Cliente meu', $admin);
    $deFora = relbContato(RELB_OUTRO, 'Cliente vizinho', $vizinho);
    relbAcompanhamento(RELB_BIZ, $meu, $admin, 'open');
    relbAcompanhamento(RELB_BIZ, $meu, $admin, 'cancelled');
    relbAcompanhamento(RELB_OUTRO, $deFora, $vizinho, 'open');

    $linhas = collect(relbLinhas($admin, '/crm/follow-ups-by-contact'));
    $minha = $linhas->first(fn ($r) => ($r['name'] ?? '') === 'Cliente meu '.RELB_TAG);
    expect($minha)->not->toBeNull();
    expect((int) $minha['total_follow_ups'])->toBe(2);
    expect((int) $minha['count_cancelled'])->toBe(1);
    $this->assertNull(
        $linhas->first(fn ($r) => ($r['name'] ?? '') === 'Cliente vizinho '.RELB_TAG),
        'vazou contato de outro business no relatório por contato'
    );
});

it('UC-RELB-05 · conversão conta os leads convertidos por usuário e o detalhe lista os do negócio, mais recente primeiro [T0]', function () {
    $admin = relbAdmin('relb_admin_test');
    $vizinho = relbAdmin('relb_vizinho_test', RELB_OUTRO);
    relbContato(RELB_BIZ, 'Convertido antigo', $admin, ['converted_by' => $admin->id, 'converted_on' => now()->subDays(5)]);
    relbContato(RELB_BIZ, 'Convertido novo', $admin, ['converted_by' => $admin->id, 'converted_on' => now()->subDay()]);
    relbContato(RELB_OUTRO, 'Convertido vizinho', $vizinho, ['converted_by' => $vizinho->id, 'converted_on' => now()]);

    $linhas = collect(relbLinhas($admin, '/crm/lead-to-customer-report'));
    $linha = $linhas->firstWhere('DT_RowId', $admin->id);
    expect($linha)->not->toBeNull();
    expect((int) $linha['total_conversions'])->toBe(2);
    $this->assertNull($linhas->firstWhere('DT_RowId', $vizinho->id), 'vazou usuário de outro business na conversão');

    $detalhe = $this->actingAs($admin)->get('/crm/lead-to-customer-details/'.$admin->id, RELB_AJAX);
    $detalhe->assertOk();
    $detalhe->assertSeeInOrder(['Convertido novo '.RELB_TAG, 'Convertido antigo '.RELB_TAG], false);

    // Pedir o detalhe pelo id do usuário vizinho não traz os contatos do outro negócio.
    $this->actingAs($admin)->get('/crm/lead-to-customer-details/'.$vizinho->id, RELB_AJAX)
        ->assertOk()
        ->assertDontSee('Convertido vizinho '.RELB_TAG, false);
});
