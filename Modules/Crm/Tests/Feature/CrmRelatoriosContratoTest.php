<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class);

/**
 * Contrato da tela `/crm/reports` em React, atrás da flag `useV2CrmRelatorios` — thread Crm/13, F3.
 *
 * Os UCs vêm do contrato, não do código:
 *   Modules/Crm/Resources/js/Pages/Crm/Relatorios/Index.casos.md
 *   memory/requisitos/Crm/RUNBOOK-relatorios.md §2-5 + relatorios-parity.md
 *
 * A flag é forçada por stub do FeatureFlagService (mesmo desenho do CrmCampanhasContratoTest).
 * Tenant 98 (fictício, ADR 0358) e 99 como o "outro negócio". NUNCA biz=4.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 *
 * @see Modules/Crm/Http/Controllers/ReportController::index()
 */
const CRMREL_TAG = '[crmrel13]';
const CRMREL_BIZ = 98;
const CRMREL_OUTRO = 99;
const CRMREL_ROTA = '/crm/reports';
const CRMREL_COMPONENTE = 'Crm/Relatorios/Index';
const CRMREL_MOEDA = ['currency' => ['code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ',']];

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: relatórios do CRM requerem schema MySQL UltimatePOS.');
    }
    foreach (['business', 'users', 'contacts', 'crm_schedules', 'crm_schedule_users', 'roles'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente.");
        }
    }
    crmrelFlag(true);
    crmrelLimpa();
});

afterEach(function () {
    crmrelLimpa();
    app()->forgetInstance(\App\Services\FeatureFlagService::class);
});

function crmrelFlag(bool $ligada): void
{
    app()->instance(\App\Services\FeatureFlagService::class, new class($ligada) extends \App\Services\FeatureFlagService
    {
        public function __construct(private bool $ligada) {}

        public function isOn(string $flag, array $attrs = []): bool
        {
            return $flag === 'useV2CrmRelatorios' && $this->ligada;
        }
    });
}

function crmrelLimpa(): void
{
    if (Schema::hasTable('crm_schedules')) {
        $ids = DB::table('crm_schedules')->where('title', 'like', '%'.CRMREL_TAG.'%')->pluck('id');
        DB::table('crm_schedule_users')->whereIn('schedule_id', $ids)->delete();
        DB::table('crm_schedules')->whereIn('id', $ids)->delete();
    }
    if (Schema::hasTable('contacts')) {
        DB::table('contacts')->where('name', 'like', '%'.CRMREL_TAG.'%')->delete();
    }
}

/** Mesmo conserto do CrmPainelContratoTest::paiNegocio — `id` é guarded. */
function crmrelNegocio(int $biz): void
{
    if (Business::whereKey($biz)->exists()) {
        return;
    }
    $dono = (int) DB::table('users')->min('id');
    (new Business)->forceFill(['id' => $biz, 'name' => 'Tenant fictício crm '.$biz, 'currency_id' => 1, 'owner_id' => $dono])->save();
}

function crmrelUsuario(string $username, bool $admin = true, int $biz = CRMREL_BIZ): User
{
    crmrelNegocio($biz);
    $user = User::firstOrCreate(['username' => $username], [
        'email' => $username.'@test.local', 'password' => bcrypt('secret'),
        'business_id' => $biz, 'first_name' => 'Crmrel', 'last_name' => 'Teste',
        'user_type' => 'user', 'allow_login' => 1, 'is_cmmsn_agnt' => 0,
    ]);
    $user->syncRoles($admin ? [Role::firstOrCreate(['name' => 'Admin#'.$biz, 'guard_name' => 'web'], ['business_id' => $biz])] : []);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user->fresh();
}

function crmrelContato(int $biz, string $nome, User $criador, array $extra = []): int
{
    crmrelNegocio($biz);

    return DB::table('contacts')->insertGetId(array_merge([
        'business_id' => $biz, 'type' => 'customer', 'name' => $nome.' '.CRMREL_TAG, 'mobile' => '0',
        'created_by' => $criador->id, 'created_at' => now(), 'updated_at' => now(),
    ], $extra));
}

function crmrelAcompanhamento(int $biz, int $contato, User $atribuido, ?string $status, string $inicio = 'now'): void
{
    $id = DB::table('crm_schedules')->insertGetId([
        'business_id' => $biz, 'contact_id' => $contato, 'title' => 'Acompanhamento '.CRMREL_TAG,
        'status' => $status, 'schedule_type' => 'call', 'is_recursive' => 0,
        'start_datetime' => \Carbon\Carbon::parse($inicio), 'end_datetime' => \Carbon\Carbon::parse($inicio)->addMinutes(30),
        'created_by' => $atribuido->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('crm_schedule_users')->insert(['schedule_id' => $id, 'user_id' => $atribuido->id, 'created_at' => now(), 'updated_at' => now()]);
}

/** Pede uma prop da tela como o browser pede (X-Inertia + X-Requested-With, partial reload). */
function crmrelProp(User $user, string $prop, string $query = ''): mixed
{
    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $resposta = test()->actingAs($user)->get(CRMREL_ROTA.$query, [
        'X-Inertia' => 'true',
        'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Version' => (string) $versao,
        'X-Inertia-Partial-Data' => $prop,
        'X-Inertia-Partial-Component' => CRMREL_COMPONENTE,
    ]);
    $resposta->assertOk();

    return $resposta->json('props.'.$prop);
}

it('UC-CRMREL-01 · flag desligada serve a Blade; ligada serve a tela React; não-admin segue barrado', function () {
    $admin = crmrelUsuario('crmrel_admin_test');

    crmrelFlag(false);
    $this->actingAs($admin)->withSession(CRMREL_MOEDA)->get(CRMREL_ROTA)->assertOk()->assertViewIs('crm::reports.index');

    crmrelFlag(true);
    $this->actingAs($admin)->get(CRMREL_ROTA)
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p->component(CRMREL_COMPONENTE, false)->has('filtros')->has('status'));
    $this->actingAs(crmrelUsuario('crmrel_comum_test', false))->get(CRMREL_ROTA)->assertForbidden();
});

it('UC-CRMREL-02 · por usuário conta cada status, os sem status e o total, sem usuário de outro negócio [T0]', function () {
    $admin = crmrelUsuario('crmrel_admin_test');
    $vizinho = crmrelUsuario('crmrel_vizinho_test', true, CRMREL_OUTRO);
    $contato = crmrelContato(CRMREL_BIZ, 'Cliente', $admin);
    crmrelAcompanhamento(CRMREL_BIZ, $contato, $admin, 'scheduled');
    crmrelAcompanhamento(CRMREL_BIZ, $contato, $admin, 'completed');
    crmrelAcompanhamento(CRMREL_BIZ, $contato, $admin, null);
    crmrelAcompanhamento(CRMREL_OUTRO, crmrelContato(CRMREL_OUTRO, 'Vizinho', $vizinho), $vizinho, 'open');

    $linhas = collect(crmrelProp($admin, 'por_usuario'));
    $minha = $linhas->firstWhere('id', $admin->id);
    expect($minha)->not->toBeNull();
    expect([$minha['scheduled'], $minha['completed'], $minha['outros'], $minha['total']])->toBe([1, 1, 1, 3]);
    $this->assertNull($linhas->firstWhere('id', $vizinho->id), 'vazou usuário de outro business');
});

it('UC-CRMREL-03 · o intervalo de datas recorta a matriz por usuário', function () {
    $admin = crmrelUsuario('crmrel_admin_test');
    $contato = crmrelContato(CRMREL_BIZ, 'Cliente', $admin);
    crmrelAcompanhamento(CRMREL_BIZ, $contato, $admin, 'open', '2026-01-10 10:00:00');
    crmrelAcompanhamento(CRMREL_BIZ, $contato, $admin, 'open', '2026-03-10 10:00:00');

    $minha = collect(crmrelProp($admin, 'por_usuario', '?start_date=2026-01-01&end_date=2026-01-31'))->firstWhere('id', $admin->id);
    expect($minha['total'])->toBe(1);
});

it('UC-CRMREL-04 · por contato soma o contato do negócio e não lista contato de outro negócio [T0]', function () {
    $admin = crmrelUsuario('crmrel_admin_test');
    $vizinho = crmrelUsuario('crmrel_vizinho_test', true, CRMREL_OUTRO);
    $meu = crmrelContato(CRMREL_BIZ, 'Cliente meu', $admin);
    crmrelAcompanhamento(CRMREL_BIZ, $meu, $admin, 'open');
    crmrelAcompanhamento(CRMREL_BIZ, $meu, $admin, 'cancelled');
    crmrelAcompanhamento(CRMREL_OUTRO, crmrelContato(CRMREL_OUTRO, 'Cliente vizinho', $vizinho), $vizinho, 'open');

    $linhas = collect(crmrelProp($admin, 'por_contato'));
    $minha = $linhas->firstWhere('nome', 'Cliente meu '.CRMREL_TAG);
    expect($minha)->not->toBeNull();
    expect([$minha['cancelled'], $minha['total']])->toBe([1, 2]);
    $this->assertNull($linhas->firstWhere('nome', 'Cliente vizinho '.CRMREL_TAG), 'vazou contato de outro business');
});

it('UC-CRMREL-05 · conversão conta por usuário e o detalhe lista só os contatos do negócio, mais recente primeiro [T0]', function () {
    $admin = crmrelUsuario('crmrel_admin_test');
    $vizinho = crmrelUsuario('crmrel_vizinho_test', true, CRMREL_OUTRO);
    crmrelContato(CRMREL_BIZ, 'Convertido antigo', $admin, ['converted_by' => $admin->id, 'converted_on' => now()->subDays(5)]);
    crmrelContato(CRMREL_BIZ, 'Convertido novo', $admin, ['converted_by' => $admin->id, 'converted_on' => now()->subDay()]);
    crmrelContato(CRMREL_OUTRO, 'Convertido vizinho', $vizinho, ['converted_by' => $vizinho->id, 'converted_on' => now()]);

    expect(collect(crmrelProp($admin, 'conversao'))->firstWhere('id', $admin->id)['total'])->toBe(2);

    $nomes = collect(crmrelProp($admin, 'convertidos', '?convertido_por='.$admin->id))->pluck('nome')
        ->filter(fn ($n) => str_contains($n, CRMREL_TAG))->values()->all();
    expect($nomes)->toBe(['Convertido novo '.CRMREL_TAG, 'Convertido antigo '.CRMREL_TAG]);

    $deFora = collect(crmrelProp($admin, 'convertidos', '?convertido_por='.$vizinho->id))->pluck('nome')->all();
    $this->assertNotContains('Convertido vizinho '.CRMREL_TAG, $deFora, 'o detalhe trouxe contato de outro business');
});

it('UC-CRMREL-06 · com a flag ligada, ?classico=1 devolve a Blade', function () {
    $this->actingAs(crmrelUsuario('crmrel_admin_test'))
        ->withSession(CRMREL_MOEDA)
        ->get(CRMREL_ROTA.'?classico=1')
        ->assertOk()
        ->assertViewIs('crm::reports.index');
});
