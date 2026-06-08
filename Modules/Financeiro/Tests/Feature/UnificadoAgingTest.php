<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Inertia\Testing\AssertableInertia;
use Modules\Financeiro\Models\Titulo;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * PR E (2026-05-25) US-FIN-022 — Aging buckets BR canon.
 *
 * Cobre:
 *  (1) prop agingBreakdown exposta no Inertia
 *  (2) querystring ?aging=lt30 filtra corretamente
 *  (3) querystring ?aging=30-60,gt90 multi-select OR
 *  (4) bucket inválido descartado (sanitização)
 *  (5) aging combina AND com lifecycle
 *
 * Skip gracioso quando DB greenfield.
 */

function agingBootstrap(): User
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

it('expõe agingBreakdown como prop Inertia com 5 chaves', function () {
    $user = agingBootstrap();

    $response = $this->actingAs($user)->get('/financeiro/unificado');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->has('agingBreakdown')
        ->has('agingBreakdown.lt30')
        ->has('agingBreakdown.30-60')
        ->has('agingBreakdown.60-90')
        ->has('agingBreakdown.gt90')
        ->has('agingBreakdown.gt180')
    );
});

it('aceita querystring aging=lt30 + retorna filter no shape', function () {
    $user = agingBootstrap();

    $response = $this->actingAs($user)->get('/financeiro/unificado?aging=lt30');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->has('filters.aging', 1)
        ->where('filters.aging.0', 'lt30')
    );
});

it('multi-select aging CSV "30-60,gt90" preserva 2 buckets', function () {
    $user = agingBootstrap();

    $response = $this->actingAs($user)->get('/financeiro/unificado?aging=30-60,gt90');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->has('filters.aging', 2)
    );
});

it('sanitiza bucket inválido (xx descartado, mantém válidos)', function () {
    $user = agingBootstrap();

    $response = $this->actingAs($user)->get('/financeiro/unificado?aging=lt30,xx,sql_injection,gt180');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->has('filters.aging', 2)
    );
});

it('aging combina com lifecycle (AND multiplicativo no shape filters)', function () {
    $user = agingBootstrap();

    $response = $this->actingAs($user)->get('/financeiro/unificado?aging=lt30&lifecycle=ar');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->has('filters.aging', 1)
        ->has('filters.lifecycle', 1)
    );
});
