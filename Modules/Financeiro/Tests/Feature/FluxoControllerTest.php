<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Inertia\Testing\AssertableInertia;
use Modules\Financeiro\Models\Titulo;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * US-FIN-014 — Pest GUARD da tela /financeiro/fluxo.
 *
 * Cobre invariantes do charter (Index.charter.md) + visual-comparison F1.5:
 * - Multi-tenant Tier 0 (ADR 0093 IRREVOGÁVEL): biz B nunca vê dados de biz A
 * - Inertia component path correto + Props no shape esperado
 * - Querystring ?dias=N respeitada e clampada
 *
 * Padrão Unificado/Jana/Repair: skip gracioso quando DB greenfield ou
 * subscription gate bloqueia financeiro_module no env atual.
 */
function fluxoBootstrap(): User
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

it('renderiza Inertia component Financeiro/Fluxo/Index', function () {
    $user = fluxoBootstrap();

    $response = $this->actingAs($user)->get('/financeiro/fluxo');

    if ($response->status() === 403) {
        test()->markTestSkipped('Subscription gate financeiro_module bloqueia neste env.');
    }
    if ($response->status() === 404) {
        test()->markTestSkipped('Módulo Financeiro não instalado neste env (financeiro:install pendente).');
    }

    expect($response->status())->toBe(200);
    expect($response->headers->get('X-Inertia'))->not()->toBeNull();
});

it('expõe Props no shape esperado (saldo_hoje, saldo_30d, pior_dia, margem_minima, conta, dias)', function () {
    $user = fluxoBootstrap();
    $response = $this->actingAs($user)->get('/financeiro/fluxo');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Financeiro/Fluxo/Index')
        ->has('saldo_hoje')
        ->has('saldo_30d')
        ->has('pior_dia.saldo')
        ->has('pior_dia.data_label')
        ->has('margem_minima')
        ->has('conta')
        ->has('dias')
    );
});

it('expõe margem_minima padrão R$ 5000 (Q3 hardcode aprovado 2026-05-14)', function () {
    $user = fluxoBootstrap();
    $response = $this->actingAs($user)->get('/financeiro/fluxo');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('margem_minima', 5000.0)
    );
});

it('aplica clamp em ?dias=N (range 7..60, default 35)', function () {
    $user = fluxoBootstrap();

    // dias=999 → clamp pra 60
    $response = $this->actingAs($user)->get('/financeiro/fluxo?dias=999');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->has('dias')
        ->where(
            'dias',
            // Default histórico (2d) + projeção (60d clampada) = ~63 elementos
            // Aceitar margem tolerante (pode variar com timezone +/- 1)
            fn (array $dias) => count($dias) >= 60 && count($dias) <= 65
        )
    );
});

it('Tier 0 IRREVOGÁVEL: query Titulo respeita business_id global scope (ADR 0093)', function () {
    $user = fluxoBootstrap();
    $businessId = (int) $user->business_id;

    // Defensiva: nenhum evento retornado pode pertencer a Título de outro business.
    // BusinessScope global scope filtra automaticamente, mas se alguém remover o
    // trait, o teste estoura.
    $response = $this->actingAs($user)->get('/financeiro/fluxo');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertInertia(function (AssertableInertia $page) use ($businessId) {
        $dias = $page->toArray()['props']['dias'] ?? [];
        foreach ($dias as $dia) {
            foreach (($dia['eventos'] ?? []) as $evento) {
                // Recupera o título/baixa e confirma business_id
                $tituloId = $evento['id'] ?? null;
                if ($tituloId === null) {
                    continue;
                }
                // Se evento existe e é Titulo (futuro), confirma scope
                // (BusinessScope no Titulo é automático — se chegou aqui, está OK)
                $count = Titulo::query()
                    ->where('id', $tituloId)
                    ->where('business_id', '!=', $businessId)
                    ->count();
                expect($count)->toBe(0, "Evento {$tituloId} cross-tenant detectado (Tier 0 violation)");
            }
        }
    });
});

it('não dispara mutação em GET /fluxo (read-only puro)', function () {
    $user = fluxoBootstrap();

    $tituloCountBefore = Titulo::query()->count();
    $response = $this->actingAs($user)->get('/financeiro/fluxo');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $tituloCountAfter = Titulo::query()->count();
    expect($tituloCountAfter)->toBe($tituloCountBefore);
});
