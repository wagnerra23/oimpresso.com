<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

// @covers-us US-SUPER-003

/**
 * Contrato da tela `/superadmin/superadmin-subscription` (lista de assinaturas) — onda SA-O4a.
 *
 * Os UCs vêm do contrato, não do código:
 *   Modules/Superadmin/Resources/js/Pages/superadmin/Assinaturas/Index.casos.md
 *   memory/requisitos/Superadmin/RUNBOOK-assinaturas.md
 *
 * O assunto desta suíte é a TRADUÇÃO enum → rótulo. É onde a tela pode mentir sem quebrar:
 * `approved` significa duas coisas conforme a data, `trial` não é status nenhum, e `declined`
 * não é cancelamento.
 *
 * ⚠️ SKIP em SQLite: a tela precisa do schema UltimatePOS real. Em sqlite estes casos PULAM e
 * o arquivo sai exit 0 sem provar nada — leia *assertions*, não "0 failed" (LC-13).
 *
 * @see Modules/Superadmin/Http/Controllers/SuperadminSubscriptionsController::index()
 * @see Modules/Superadmin/Support/RotuloAssinatura
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md §exceções Superadmin
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: /superadmin/superadmin-subscription requer schema MySQL UltimatePOS.');
    }
    if (! Schema::hasTable('subscriptions') || ! Schema::hasTable('packages') || ! Schema::hasTable('business')) {
        $this->markTestSkipped('Schema UltimatePOS ausente — rode migrations primeiro.');
    }

    // O gate de ROTA é o middleware `Superadmin`, que compara o USERNAME com
    // `config('constants.administrator_usernames')` — não é Spatie nem Bouncer.
    config(['constants.administrator_usernames' => 'ass_superadmin_test']);

    assFixtures();
});

/** Tenant do teste. NUNCA biz=4 (ROTA LIVRE, produção) — ADR 0358. */
const BIZ_ASS = 98;

/** Segundo tenant: sem ele, "cross-tenant" não tem o que provar. */
const BIZ_ASS_2 = 97;

const ROTA_ASS = '/superadmin/superadmin-subscription';

function assSuperadmin(): User
{
    $user = User::firstOrCreate(
        ['username' => 'ass_superadmin_test'],
        [
            'email' => 'ass_superadmin@test.local',
            'password' => bcrypt('secret'),
            'business_id' => BIZ_ASS,
            'first_name' => 'Ass',
            'last_name' => 'Superadmin',
        ]
    );

    $permission = Permission::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);

    if (! $user->hasPermissionTo('superadmin')) {
        $user->givePermissionTo($permission);
    }

    return $user;
}

function assAdminDeNegocio(): User
{
    $user = User::firstOrCreate(
        ['username' => 'ass_admin_test'],
        [
            'email' => 'ass_admin@test.local',
            'password' => bcrypt('secret'),
            'business_id' => BIZ_ASS,
            'first_name' => 'Admin',
            'last_name' => 'Negocio',
        ]
    );

    $user->syncRoles([]);
    $user->syncPermissions([]);

    return $user;
}

/**
 * DQE do F1 §2: uma assinatura em cada estado que a tela precisa distinguir.
 *
 * As datas são RELATIVAS ao dia da execução — data fixa em fixture envelhece e o caso passa a
 * provar outra coisa (uma "vigente" de 2026 vira vencida em 2027 sem ninguém tocar no teste).
 *
 * Os preços são números sintéticos e neutros. Nenhum literal monetário entra aqui — Tier 0.
 */
function assFixtures(): void
{
    Business::firstOrCreate(['id' => BIZ_ASS], ['name' => 'Tenant fictício assinaturas', 'currency_id' => 1]);
    Business::firstOrCreate(['id' => BIZ_ASS_2], ['name' => 'Segundo tenant fictício', 'currency_id' => 1]);

    $pacote = DB::table('packages')->where('name', 'Pacote fictício SA-O4a')->first();

    if ($pacote === null) {
        $pacoteId = DB::table('packages')->insertGetId([
            'name' => 'Pacote fictício SA-O4a',
            'description' => 'Só para o contrato da tela de Assinaturas.',
            'location_count' => 1,
            'user_count' => 3,
            'product_count' => 0,
            'invoice_count' => 0,
            'interval' => 'months',
            'interval_count' => 1,
            'trial_days' => 14,
            'price' => 100,
            'created_by' => 1,
            'sort_order' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    } else {
        $pacoteId = $pacote->id;
    }

    $hoje = now()->startOfDay();

    $casos = [
        // status, business, start, trial_end, end  — o rótulo esperado está no UC, não aqui.
        ['approved', BIZ_ASS, $hoje->copy()->subDays(10), null, $hoje->copy()->addDays(20)],
        ['approved', BIZ_ASS, $hoje->copy()->subDays(60), null, $hoje->copy()->subDays(5)],
        ['approved', BIZ_ASS_2, $hoje->copy()->subDays(3), $hoje->copy()->addDays(7), $hoje->copy()->addDays(30)],
        ['waiting', BIZ_ASS_2, null, null, null],
        ['declined', BIZ_ASS, $hoje->copy()->subDays(40), null, $hoje->copy()->addDays(2)],
        ['cancelled', BIZ_ASS, $hoje->copy()->subDays(80), null, $hoje->copy()->subDays(20)],
        ['expired', BIZ_ASS_2, $hoje->copy()->subDays(90), null, $hoje->copy()->subDays(30)],
    ];

    foreach ($casos as $i => [$status, $biz, $inicio, $trial, $fim]) {
        $marca = 'sa-o4a-'.$i;

        if (DB::table('subscriptions')->where('payment_transaction_id', $marca)->exists()) {
            continue;
        }

        DB::table('subscriptions')->insert([
            'business_id' => $biz,
            'package_id' => $pacoteId,
            'start_date' => $inicio?->toDateString(),
            'trial_end_date' => $trial?->toDateString(),
            'end_date' => $fim?->toDateString(),
            'package_price' => 100,
            'package_details' => json_encode(['interval' => 'months', 'interval_count' => 1]),
            'created_id' => 1,
            'paid_via' => 'fixture',
            'payment_transaction_id' => $marca,
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

/** Pede uma prop deferred — sem isso o payload nem é calculado. */
function assProp(string $prop, string $query = ''): array
{
    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());

    $resposta = test()->actingAs(assSuperadmin())->get(ROTA_ASS.$query, [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) $versao,
        'X-Inertia-Partial-Data' => $prop,
        'X-Inertia-Partial-Component' => 'superadmin/Assinaturas/Index',
    ]);

    $resposta->assertOk();

    return (array) $resposta->json('props.'.$prop);
}

// ── UC-SAASS-01 · responde Inertia, não DataTables ──────────────────────────

it('UC-SAASS-01 · a rota responde Inertia com o componente da tela nova', function () {
    $this->actingAs(assSuperadmin())
        ->get(ROTA_ASS)
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('superadmin/Assinaturas/Index')
            ->has('filtros')
        );
});

// ── UC-SAASS-02 · admin barrado ENQUANTO superadmin passa ───────────────────

it('UC-SAASS-02 · admin de negócio é barrado enquanto o superadmin passa', function () {
    $barrado = $this->actingAs(assAdminDeNegocio())->get(ROTA_ASS);
    expect($barrado->getStatusCode())->toBeIn([302, 403]);

    // A segunda metade importa: um teste que só prova o 403 fica verde se a rota quebrar
    // para todo mundo.
    $this->actingAs(assSuperadmin())->get(ROTA_ASS)->assertOk();
});

// ── UC-SAASS-03 · cross-tenant intencional ──────────────────────────────────

it('UC-SAASS-03 · a lista cobre assinatura de mais de um business', function () {
    $linhas = assProp('assinaturas')['linhas'] ?? [];

    $negocios = collect($linhas)->pluck('negocio_id')->unique();

    // Cross-tenant é INTENCIONAL (ADR 0093 §exceções). Se alguém "consertar" aplicando escopo
    // de tenant, sobra um negócio só e este caso cai.
    expect($negocios->count())->toBeGreaterThanOrEqual(2);
});

// ── UC-SAASS-04 · o enum nunca chega à tela ─────────────────────────────────

it('UC-SAASS-04 · nenhum valor cru do enum aparece no payload da lista', function () {
    $linhas = assProp('assinaturas')['linhas'] ?? [];

    expect($linhas)->not->toBeEmpty();

    $cru = ['approved', 'waiting', 'declined', 'expired', 'cancelled'];
    $rotulos = ['Ativa', 'Pendente', 'Bloqueada', 'Vencida', 'Cancelada'];

    foreach ($linhas as $linha) {
        expect($linha['situacao'])->toBeIn($rotulos);

        // Varre a linha INTEIRA, não só `situacao`: o enum poderia voltar por qualquer campo
        // que alguém adicionasse depois.
        foreach ($linha as $valor) {
            if (is_string($valor)) {
                expect(in_array($valor, $cru, true))->toBeFalse();
            }
        }
    }
});

// ── UC-SAASS-05 · `approved` significa duas coisas, e a data decide ─────────

it('UC-SAASS-05 · approved vigente sai Ativa e approved vencido sai Vencida', function () {
    $linhas = collect(assProp('assinaturas')['linhas'] ?? [])->keyBy('transacao');

    // As duas fixtures são `approved` no banco; só a data as separa.
    expect($linhas->has('sa-o4a-0'))->toBeTrue()
        ->and($linhas->has('sa-o4a-1'))->toBeTrue()
        ->and($linhas['sa-o4a-0']['situacao'])->toBe('Ativa')
        ->and($linhas['sa-o4a-1']['situacao'])->toBe('Vencida');
});

// ── UC-SAASS-06 · "Em trial" é derivado de data, não de status ──────────────

it('UC-SAASS-06 · o KPI de trial conta pelo fim do teste, não por status', function () {
    $kpis = assProp('kpis');

    // A fixture 2 é `approved` com `trial_end_date` no futuro.
    expect($kpis['trial'])->toBeGreaterThanOrEqual(1);

    // Prova do contrapositivo: não existe linha `trial` no enum. Se alguém "criar" o status,
    // este caso avisa antes de a query silenciosamente voltar vazia.
    expect(DB::table('subscriptions')->where('status', 'trial')->count())->toBe(0);
});

// ── UC-SAASS-07 · bloqueada não vira cancelada ──────────────────────────────

it('UC-SAASS-07 · declined sai num contador próprio, fora de vencidas ou canceladas', function () {
    $kpis = assProp('kpis');

    expect($kpis)->toHaveKey('bloqueadas')
        ->and($kpis['bloqueadas'])->toBeGreaterThanOrEqual(1);

    // O que este caso protege: somar `bloqueadas` dentro de `vencidas_canceladas` daria um
    // número redondo e errado. As fixtures têm 1 declined, 1 cancelled e 2 vencidas
    // (`expired` + `approved` com data passada) — se a soma engolir a bloqueada, cai aqui.
    $bloqueadas = DB::table('subscriptions')->where('status', 'declined')->count();
    $canceladas = DB::table('subscriptions')->where('status', 'cancelled')->count();
    $expiradas = DB::table('subscriptions')->where('status', 'expired')->count();
    $vencidasPorData = DB::table('subscriptions')
        ->where('status', 'approved')
        ->whereDate('end_date', '<', now()->toDateString())
        ->count();

    expect($kpis['vencidas_canceladas'])->toBe($canceladas + $expiradas + $vencidasPorData)
        ->and($kpis['bloqueadas'])->toBe($bloqueadas);
});

// ── UC-SAASS-08 · ordenar é whitelist, não request ──────────────────────────

it('UC-SAASS-08 · coluna de ordenação fora da whitelist cai no default', function () {
    $this->actingAs(assSuperadmin())
        ->get(ROTA_ASS.'?ordem=(SELECT+1)&dir=DROP')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filtros.ordem', 'criado')
            ->where('filtros.dir', 'desc')
        );
});

it('UC-SAASS-08 · filtro de status fora da lista é descartado', function () {
    $this->actingAs(assSuperadmin())
        ->get(ROTA_ASS.'?status=approved&periodo=sempre')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            // `approved` é ENUM, não rótulo de tela — a tela só aceita o vocabulário dela.
            ->where('filtros.status', null)
            ->where('filtros.periodo', null)
        );
});

// ── UC-SAASS-09 · filtro combinado sobrevive à paginação ────────────────────

it('UC-SAASS-09 · o filtro de status devolve só o recorte pedido', function () {
    $linhas = assProp('assinaturas', '?status=pendente')['linhas'] ?? [];

    expect($linhas)->not->toBeEmpty();

    foreach ($linhas as $linha) {
        expect($linha['situacao'])->toBe('Pendente');
    }
});

// ── UC-SAASS-10 · a lista não escreve ───────────────────────────────────────

it('UC-SAASS-10 · abrir a lista não muda status nenhum no banco', function () {
    $antes = DB::table('subscriptions')
        ->orderBy('id')
        ->pluck('status', 'id')
        ->all();

    assProp('assinaturas');
    assProp('kpis');

    $depois = DB::table('subscriptions')
        ->orderBy('id')
        ->pluck('status', 'id')
        ->all();

    // "Vencida" é rótulo derivado. Se alguém "arrumar" isso gravando `expired` na leitura,
    // abrir uma tela vira escrita em massa sem trilha — e cai aqui.
    expect($depois)->toBe($antes);
});
