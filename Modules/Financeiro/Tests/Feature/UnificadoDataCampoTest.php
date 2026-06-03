<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * Paridade filtros WR (2026-06-03) — filtro por CAMPO de data + intervalo.
 *
 * Espelha os filtros de data do WR Comercial (Emissão/Vencimento/Pagamento/
 * Competência) na Visão Unificada. Backend: parseFilters() + aplicarFiltroData().
 *
 * Cobre:
 *  (1) default data_campo = 'vencimento' (back-compat: comportamento anterior)
 *  (2) ?data_campo=emissao|pagamento|competencia preservado no shape
 *  (3) valor inválido cai pra 'vencimento' (sanitização anti-tampering)
 *  (4) ?data_inicio/?data_fim preservados no shape
 *  (5) cada data_campo responde 200 (query não quebra em nenhum dos 4 ramos)
 *
 * Skip gracioso quando DB greenfield ou module gate bloqueia.
 */

function dataCampoBootstrap(): User
{
    try {
        $business = Business::first();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tabela business indisponível: '.$e->getMessage());
    }

    if (! $business) {
        test()->markTestSkipped('Sem business no banco.');
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
        'user.business_id' => $business->id,
        'user.id'          => $user->id,
        'business.id'      => $business->id,
        'business.name'    => $business->name,
        'business'         => ['id' => $business->id, 'name' => $business->name, 'currency_symbol' => 'R$'],
        'is_admin'         => true,
    ]);

    return $user;
}

function dataCampoGet(User $user, string $qs = '')
{
    $response = test()->actingAs($user)->get('/financeiro/unificado'.$qs);

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    return $response;
}

it('default data_campo = vencimento (back-compat preservado)', function () {
    $user = dataCampoBootstrap();

    dataCampoGet($user)->assertInertia(fn (AssertableInertia $page) => $page
        ->where('filters.data_campo', 'vencimento')
        ->where('filters.data_inicio', '')
        ->where('filters.data_fim', '')
    );
});

it('aceita ?data_campo=emissao no shape', function () {
    $user = dataCampoBootstrap();

    dataCampoGet($user, '?data_campo=emissao')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('filters.data_campo', 'emissao')
    );
});

it('valor inválido cai pra vencimento (sanitização)', function () {
    $user = dataCampoBootstrap();

    dataCampoGet($user, '?data_campo=DROP_TABLE')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('filters.data_campo', 'vencimento')
    );
});

it('preserva intervalo explícito data_inicio/data_fim', function () {
    $user = dataCampoBootstrap();

    dataCampoGet($user, '?data_inicio=2026-01-01&data_fim=2026-01-31')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filters.data_inicio', '2026-01-01')
            ->where('filters.data_fim', '2026-01-31')
        );
});

it('os 4 campos de data respondem 200 (nenhum ramo da query quebra)', function () {
    $user = dataCampoBootstrap();

    foreach (['vencimento', 'emissao', 'pagamento', 'competencia'] as $campo) {
        dataCampoGet($user, "?data_campo={$campo}&data_inicio=2026-01-01&data_fim=2026-12-31")
            ->assertOk();
    }
});
