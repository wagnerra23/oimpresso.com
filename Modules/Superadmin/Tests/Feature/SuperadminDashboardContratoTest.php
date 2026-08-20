<?php

declare(strict_types=1);

use App\Business;
use App\Http\Middleware\HandleInertiaRequests;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Modules\Superadmin\Services\SuperadminDashboardService;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

// @covers-us US-SUPER-011

/**
 * Contrato da tela `/superadmin` (visão geral) — onda SA-O1 (Blade/AdminLTE → Inertia).
 *
 * Os UCs vêm do contrato, não do código:
 *   Modules/Superadmin/Resources/js/Pages/superadmin/Dashboard/Index.casos.md
 *   memory/requisitos/Superadmin/RUNBOOK-dashboard.md
 *
 * ⚠️ SKIP em SQLite: a tela precisa do schema UltimatePOS real (business + users + Spatie).
 * Em sqlite estes casos PULAM e o arquivo sai exit 0 sem provar nada — leia *assertions*,
 * não "0 failed" (LC-13). O veredito honesto sai na lane MySQL / CT 100.
 *
 * @see Modules/Superadmin/Http/Controllers/SuperadminController::index()
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md §exceções Superadmin
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: /superadmin requer schema MySQL UltimatePOS.');
    }
    if (! Schema::hasTable('users') || ! Schema::hasTable('business') || ! Schema::hasTable('permissions')) {
        $this->markTestSkipped('Schema UltimatePOS ausente — rode migrations primeiro.');
    }

    // O gate de ROTA não é Spatie nem Bouncer: `App\Http\Middleware\Superadmin` compara o
    // USERNAME com `config('constants.administrator_usernames')` (lista separada por vírgula).
    // Medido em 2026-08-19 — o F1 do Cowork descrevia "Bouncer" e estava errado.
    // A permissão Spatie `superadmin` é a SEGUNDA camada, checada dentro do controller.
    config(['constants.administrator_usernames' => 'dash_superadmin_test']);
});

/** Tenant do teste. NUNCA biz=4 (ROTA LIVRE, produção) — ADR 0358. */
const BIZ_DASH = 98;

function dashSuperadmin(): User
{
    Business::firstOrCreate(['id' => BIZ_DASH], ['name' => 'Tenant fictício dashboard', 'currency_id' => 1]);

    $user = User::firstOrCreate(
        ['username' => 'dash_superadmin_test'],
        [
            'email' => 'dash_superadmin@test.local',
            'password' => bcrypt('secret'),
            'business_id' => BIZ_DASH,
            'first_name' => 'Dash',
            'last_name' => 'Superadmin',
        ]
    );

    $permission = Permission::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);

    if (! $user->hasPermissionTo('superadmin')) {
        $user->givePermissionTo($permission);
    }

    return $user;
}

function dashAdminDeNegocio(): User
{
    Business::firstOrCreate(['id' => BIZ_DASH], ['name' => 'Tenant fictício dashboard', 'currency_id' => 1]);

    $user = User::firstOrCreate(
        ['username' => 'dash_admin_negocio_test'],
        [
            'email' => 'dash_admin@test.local',
            'password' => bcrypt('secret'),
            'business_id' => BIZ_DASH,
            'first_name' => 'Admin',
            'last_name' => 'DeNegocio',
        ]
    );

    $user->syncRoles([]);
    $user->syncPermissions([]);

    return $user;
}

// ── UC-SADASH-01 · a tela responde em Inertia, não em Blade ──────────────────

it('UC-SADASH-01 · /superadmin responde Inertia com o componente da tela nova', function () {
    $this->actingAs(dashSuperadmin())
        ->get('/superadmin')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('superadmin/Dashboard/Index')
            ->has('periodo')
            ->has('janela.rotulo')
        );
});

// ── UC-SADASH-02 · os KPIs vêm do service, não de query inline ───────────────

it('UC-SADASH-02 · o controller lê os KPIs do SuperadminDashboardService', function () {
    // Se o controller voltar a fazer a query inline, o mock não é chamado e o teste cai.
    $this->mock(SuperadminDashboardService::class, function ($mock) {
        $mock->shouldReceive('countNotSubscribedBusinesses')->atLeast()->once()->andReturn(7);
        $mock->shouldReceive('statsForPeriod')->andReturn(['new_subscriptions' => 0, 'new_registrations' => 0]);
        $mock->shouldReceive('buildMonthlyRevenueChart')->andReturn([]);
    });

    // A prop é deferred: só vem quando o partial reload a pede explicitamente.
    // `X-Inertia-Version` é obrigatório — sem ele o Inertia responde 409 (version mismatch)
    // pra forçar full reload, e o teste mediria o protocolo em vez do controller.
    // A versão sai do MESMO cálculo que o middleware fará no request (hash do manifest do
    // Vite, HandleInertiaRequests::version) — um valor paralelo daria 409 de novo.
    $versao = app(HandleInertiaRequests::class)->version(request());

    $resposta = $this->actingAs(dashSuperadmin())
        ->get('/superadmin', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) $versao,
            'X-Inertia-Partial-Data' => 'semAssinatura',
            'X-Inertia-Partial-Component' => 'superadmin/Dashboard/Index',
        ]);

    $resposta->assertOk();

    // Lido do JSON do partial reload, não pelo assertInertia: o helper valida o envelope
    // inteiro (component/props/url/version) e falha por motivo diferente do que este UC mede.
    expect($resposta->json('props.semAssinatura'))->toBe(7);
});

// ── UC-SADASH-03 · admin de negócio não entra ────────────────────────────────

it('UC-SADASH-03 · admin de negócio é barrado ENQUANTO o superadmin passa', function () {
    // As duas metades no mesmo caso, de propósito: sozinho, o `403` não discrimina nada —
    // antes de 2026-08-19 este teste passava porque TODO mundo tomava 403, inclusive o
    // superadmin. Verde que não pode ficar vermelho é carimbo, não cobertura.
    $barrado = $this->actingAs(dashAdminDeNegocio())->get('/superadmin');
    expect($barrado->getStatusCode())->toBeIn([302, 403]);

    $this->actingAs(dashSuperadmin())->get('/superadmin')->assertOk();
});

// ── UC-SADASH-04 · as queries enxergam TODOS os negócios (cross-tenant) ──────

it('UC-SADASH-04 · a contagem cobre negócio de outro business, não só o do usuário', function () {
    // NÃO cria tenant de propósito. `business.owner_id` tem FK pra `users.id`, então
    // inserir negócio novo num banco fresco esbarra na constraint — e o que este UC mede
    // é AUSÊNCIA DE ESCOPO, não criação de dado. As duas contagens abaixo já discriminam:
    // se alguém aplicar `business_id` scope no service, elas divergem na hora.
    //
    // (Primeira versão criava os tenants e passava no CT 100 só porque a database de lá
    // PERSISTE entre runs — no CI, fresco, a FK apareceu. Verde no CT 100 não substitui
    // o gate de merge.)
    $doService = app(SuperadminDashboardService::class)->countNotSubscribedBusinesses();

    $semEscopo = DB::table('business')
        ->leftJoin('subscriptions AS s', 'business.id', '=', 's.business_id')
        ->whereNull('s.id')
        ->count();

    // Cross-tenant aqui é INTENCIONAL (ADR 0093 §exceções Superadmin) — este caso existe
    // pra impedir que alguém "conserte" a tela aplicando escopo de tenant.
    expect($doService)->toBe($semEscopo);

    // E o banco precisa ter mais de um negócio, senão o caso não discriminaria nada:
    // com um tenant só, query escopada e não-escopada devolvem o mesmo número.
    expect(DB::table('business')->count())->toBeGreaterThanOrEqual(2);
});

// ── UC-SADASH-05 · nenhum bloco renderizado com número inventado ─────────────

it('UC-SADASH-05 · props sem query no backend não são enviadas à tela', function () {
    // `mrr` saiu desta lista na SA-O1b: ganhou query real (calcularMrr) e agora chega às
    // props legitimamente. O caso encolhe conforme a dívida é paga — não é afrouxamento.
    $this->actingAs(dashSuperadmin())
        ->get('/superadmin')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->missing('funil')
            ->missing('churn')
            ->missing('receitaPorPacote')
        );
});

// ── UC-SADASH-06 · MRR só conta recorrência vigente e paga ──────────────────

it('UC-SADASH-06 · o MRR delega ao dono do calculo, nao soma por conta propria', function () {
    if (! class_exists(\Modules\RecurringBilling\Repositories\SubscriptionRepository::class)) {
        $this->markTestSkipped('RecurringBilling ausente neste ambiente.');
    }

    $svc = app(SuperadminDashboardService::class);
    $resultado = $svc->calcularMrr(BIZ_DASH);

    expect($resultado)->toHaveKeys(['mrr', 'assinaturas', 'canceladas', 'fonte'])
        ->and($resultado['mrr'])->toBeFloat()
        ->and($resultado['mrr'])->toBeGreaterThanOrEqual(0.0);

    // O contrato que importa: o numero e o MESMO que o repositorio do RecurringBilling
    // devolve. Se alguem trocar isto por uma soma local de `rb_plans.valor`, o valor
    // diverge (o `metadata.valor` sobrepoe o do plano) e este caso cai.
    $doDono = app(\Modules\RecurringBilling\Repositories\SubscriptionRepository::class)
        ->mrrBaselineCached(BIZ_DASH);

    expect($resultado['mrr'])->toBe(round((float) $doDono, 2));
});

// ── UC-SADASH-07 · a tendência mensal não tem buraco ────────────────────────

it('UC-SADASH-07 · a série de tendência cobre os meses sem assinatura', function () {
    $serie = app(SuperadminDashboardService::class)->buildMonthlyRevenueChart();

    // 12 meses + o corrente: o intervalo vai de hoje-1ano até hoje, e os dois extremos
    // caem em meses diferentes. O que este caso trava é que NENHUM mês some do meio —
    // antes da SA-O1b o mês sem assinatura simplesmente não virava chave (smoke 19/08:
    // 11 pontos, sem Oct-2025).
    expect(count($serie))->toBeGreaterThanOrEqual(12);

    // E a sequência é contígua: cada chave é o mês seguinte à anterior.
    $chaves = array_keys($serie);
    $cursor = \Carbon\Carbon::createFromFormat('M-Y', $chaves[0])->startOfMonth();

    foreach ($chaves as $chave) {
        expect($chave)->toBe($cursor->format('M-Y'));
        $cursor->addMonth();
    }
});
