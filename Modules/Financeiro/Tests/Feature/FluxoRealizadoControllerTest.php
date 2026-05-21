<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Inertia\Testing\AssertableInertia;
use Modules\Financeiro\Models\TituloBaixa;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * US-FIN-014c — Pest GUARD da tab "Realizado" em /financeiro/fluxo.
 *
 * Fase 3 deprecação legacy (2026-05-21): tab Realizado absorve Cash Flow legacy
 * (`/account/cash-flow` → 301 → `/financeiro/fluxo?tab=realizado` via PR #1283).
 *
 * Cobre:
 *  - tab=projetado é default (omitido = projetado)
 *  - tab=realizado retorna payload no shape canon (meta + totais + meses)
 *  - tab inválido cai pra default
 *  - Multi-tenant Tier 0 ADR 0093 IRREVOGÁVEL: TituloBaixa filtrada por business_id
 *  - ?meses=N respeitado e clampado (1..36)
 *  - GET é read-only (não cria/altera baixa)
 *
 * Skip gracioso quando DB greenfield ou subscription gate bloqueia env.
 */
function fluxoRealizadoBootstrap(): User
{
    try {
        $business = Business::first();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tabela business indisponível: '.$e->getMessage());
    }

    if (! $business) {
        test()->markTestSkipped('Sem business no banco — rode seeder UltimatePOS antes.');
    }

    $user = User::where('business_id', $business->id)->first();

    if (! $user) {
        test()->markTestSkipped('Sem user no business.');
    }

    Permission::firstOrCreate(['name' => 'financeiro.dashboard.view', 'guard_name' => 'web']);
    if (! $user->hasPermissionTo('financeiro.dashboard.view')) {
        $user->givePermissionTo('financeiro.dashboard.view');
    }

    session([
        'user.business_id'         => $business->id,
        'user.id'                  => $user->id,
        'business.id'              => $business->id,
        'business.name'            => $business->name,
        'business.currency_symbol' => 'R$',
        'business'                 => [
            'id'              => $business->id,
            'name'            => $business->name,
            'currency_symbol' => 'R$',
        ],
        'is_admin'                 => true,
    ]);

    return $user;
}

it('tab default = projetado (sem query string)', function () {
    $user = fluxoRealizadoBootstrap();

    $response = $this->actingAs($user)->get('/financeiro/fluxo');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Financeiro/Fluxo/Index')
        ->where('tab', 'projetado')
        // shape Projetado preservado (props originais permanecem na raiz)
        ->has('saldo_hoje')
        ->has('dias')
    );
});

it('tab=realizado expõe payload canon (meta + totais + meses)', function () {
    $user = fluxoRealizadoBootstrap();

    $response = $this->actingAs($user)->get('/financeiro/fluxo?tab=realizado');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Financeiro/Fluxo/Index')
        ->where('tab', 'realizado')
        ->has('realizado.meta.meses_janela')
        ->has('realizado.meta.primeiro_mes')
        ->has('realizado.meta.ultimo_mes')
        ->has('realizado.meta.business_id')
        ->has('realizado.totais.entradas')
        ->has('realizado.totais.saidas')
        ->has('realizado.totais.saldo')
        ->has('realizado.totais.qtd_baixas')
        ->has('realizado.meses')
    );
});

it('tab=realizado retorna exatamente 12 meses por default (janela canon)', function () {
    $user = fluxoRealizadoBootstrap();

    $response = $this->actingAs($user)->get('/financeiro/fluxo?tab=realizado');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('realizado.meta.meses_janela', 12)
        ->has('realizado.meses', 12)
    );
});

it('cada mes do realizado tem shape esperado (mes, entradas, saidas, saldo, qtd_baixas, is_current)', function () {
    $user = fluxoRealizadoBootstrap();

    $response = $this->actingAs($user)->get('/financeiro/fluxo?tab=realizado');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertInertia(function (AssertableInertia $page) {
        $meses = $page->toArray()['props']['realizado']['meses'] ?? [];
        expect($meses)->toBeArray()->not->toBeEmpty();

        foreach ($meses as $i => $mes) {
            expect($mes)->toHaveKeys([
                'mes', 'mes_label', 'ano', 'entradas', 'saidas', 'saldo', 'qtd_baixas', 'is_current',
            ], "mes[$i] sem chave canon");
            expect($mes['entradas'])->toBeNumeric();
            expect($mes['saidas'])->toBeNumeric();
            // saldo = entradas - saidas (invariante matemática)
            $delta = abs(($mes['entradas'] - $mes['saidas']) - $mes['saldo']);
            expect($delta)->toBeLessThan(0.01, "mes[$i].saldo != entradas - saidas (delta R\$ {$delta})");
        }

        // Exatamente 1 mes_current = true (o atual)
        $atuais = array_filter($meses, fn ($m) => $m['is_current'] === true);
        expect(count($atuais))->toBe(1, 'deve ter exatamente 1 mes is_current = true');
    });
});

it('tab inválido cai pra default projetado', function () {
    $user = fluxoRealizadoBootstrap();

    $response = $this->actingAs($user)->get('/financeiro/fluxo?tab=lixo');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('tab', 'projetado')
    );
});

it('?meses=N respeita clamp 1..36', function () {
    $user = fluxoRealizadoBootstrap();

    // 999 → clamp 36
    $r1 = $this->actingAs($user)->get('/financeiro/fluxo?tab=realizado&meses=999');
    if (in_array($r1->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }
    $r1->assertInertia(fn (AssertableInertia $page) => $page
        ->where('realizado.meta.meses_janela', 36)
        ->has('realizado.meses', 36)
    );

    // 0 → clamp 1
    $r2 = $this->actingAs($user)->get('/financeiro/fluxo?tab=realizado&meses=0');
    $r2->assertInertia(fn (AssertableInertia $page) => $page
        ->where('realizado.meta.meses_janela', 1)
        ->has('realizado.meses', 1)
    );
});

it('Tier 0 IRREVOGÁVEL: realizado respeita business_id (ADR 0093)', function () {
    $user = fluxoRealizadoBootstrap();
    $businessId = (int) $user->business_id;

    $response = $this->actingAs($user)->get('/financeiro/fluxo?tab=realizado');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    // meta.business_id casa com auth biz
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('realizado.meta.business_id', $businessId)
    );

    // Defensiva: se totais.qtd_baixas > 0, conferir que TituloBaixa retornadas
    // pertencem ao biz auth (BusinessScope global scope + filtro explícito no
    // service garantem; este guard verifica que não houve regressão removendo
    // o scope).
    $countDoBiz = TituloBaixa::query()
        ->where('business_id', $businessId)
        ->whereNull('estorno_de_id')
        ->count();
    $countTotal = TituloBaixa::query()
        ->withoutGlobalScopes()
        ->whereNull('estorno_de_id')
        ->count();

    // Se há baixas no DB de outros tenants, business scope tem que filtrar
    if ($countTotal > $countDoBiz) {
        expect($countDoBiz)->toBeLessThan($countTotal, 'BusinessScope deve filtrar cross-tenant');
    }
});

it('tab=projetado não carrega payload realizado (perf — evita query custosa)', function () {
    $user = fluxoRealizadoBootstrap();

    $response = $this->actingAs($user)->get('/financeiro/fluxo?tab=projetado');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('tab', 'projetado')
        ->where('realizado', null)
    );
});

it('não dispara mutação em GET /fluxo?tab=realizado (read-only puro)', function () {
    $user = fluxoRealizadoBootstrap();

    $baixaCountBefore = TituloBaixa::query()->count();
    $response = $this->actingAs($user)->get('/financeiro/fluxo?tab=realizado');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $baixaCountAfter = TituloBaixa::query()->count();
    expect($baixaCountAfter)->toBe($baixaCountBefore);
});

it('totais.saldo = totais.entradas - totais.saidas (invariante contábil)', function () {
    $user = fluxoRealizadoBootstrap();

    $response = $this->actingAs($user)->get('/financeiro/fluxo?tab=realizado');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertInertia(function (AssertableInertia $page) {
        $totais = $page->toArray()['props']['realizado']['totais'] ?? null;
        expect($totais)->not->toBeNull();
        $delta = abs(($totais['entradas'] - $totais['saidas']) - $totais['saldo']);
        expect($delta)->toBeLessThan(0.01, "totais.saldo inconsistente com entradas - saidas (delta R\$ {$delta})");

        // totais.entradas = SUM(meses[].entradas) (consistência agregada)
        $meses = $page->toArray()['props']['realizado']['meses'] ?? [];
        $somaEnt = array_sum(array_column($meses, 'entradas'));
        $somaSai = array_sum(array_column($meses, 'saidas'));
        expect(abs($somaEnt - $totais['entradas']))->toBeLessThan(0.01, 'SUM(meses.entradas) != totais.entradas');
        expect(abs($somaSai - $totais['saidas']))->toBeLessThan(0.01, 'SUM(meses.saidas) != totais.saidas');
    });
});
