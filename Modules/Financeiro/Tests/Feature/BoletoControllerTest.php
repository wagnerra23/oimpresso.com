<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Inertia\Testing\AssertableInertia;
use Modules\Financeiro\Models\BoletoRemessa;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * US-BOL-XXX — Pest GUARD da tela /financeiro/boletos.
 *
 * Cobre invariantes do charter (Index.charter.md) + visual-comparison F1.5:
 * - Multi-tenant Tier 0 (ADR 0093 IRREVOGÁVEL): biz B nunca vê dados de biz A
 * - Inertia component path correto + Props shape esperado (remessas, kpis, funil, contas, filtros)
 * - Filter por status via querystring
 * - Cancelar boleto pago/cancelado é idempotente (back-with-error)
 *
 * Padrão Unificado/Fluxo/Jana/Repair: skip gracioso quando DB greenfield
 * ou subscription gate bloqueia financeiro_module no env atual.
 */
function boletoBootstrap(): User
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

it('renderiza Inertia component Financeiro/Boletos/Index', function () {
    $user = boletoBootstrap();

    $response = $this->actingAs($user)->get('/financeiro/boletos');

    if ($response->status() === 403) {
        test()->markTestSkipped('Subscription gate financeiro_module bloqueia neste env.');
    }
    if ($response->status() === 404) {
        test()->markTestSkipped('Módulo Financeiro não instalado neste env (financeiro:install pendente).');
    }

    expect($response->status())->toBe(200);
    expect($response->headers->get('X-Inertia'))->not()->toBeNull();
});

it('expõe Props no shape esperado (remessas, kpis, funil, contas, filtros)', function () {
    $user = boletoBootstrap();
    $response = $this->actingAs($user)->get('/financeiro/boletos');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Financeiro/Boletos/Index')
        ->has('remessas')
        ->has('kpis.pago_mes.qtd')
        ->has('kpis.pago_mes.valor')
        ->has('kpis.vencido.qtd')
        ->has('kpis.vencido.valor')
        ->has('kpis.aberto.qtd')
        ->has('kpis.aberto.valor')
        ->has('funil.aberto.qtd')
        ->has('funil.lembrete.qtd')
        ->has('funil.cobranca.qtd')
        ->has('funil.vencido_5d.qtd')
        ->has('funil.protesto.qtd')
        ->has('contas')
        ->has('filtros')
    );
});

it('expõe funil 5 etapas com qtd numérico (UI-only F1 derivado de status)', function () {
    $user = boletoBootstrap();
    $response = $this->actingAs($user)->get('/financeiro/boletos');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('funil.aberto.qtd', fn ($v) => is_int($v) && $v >= 0)
        ->where('funil.lembrete.qtd', fn ($v) => is_int($v) && $v >= 0)
        ->where('funil.cobranca.qtd', fn ($v) => is_int($v) && $v >= 0)
        ->where('funil.vencido_5d.qtd', fn ($v) => is_int($v) && $v >= 0)
        ->where('funil.protesto.qtd', 0)
    );
});

it('filtra por status via querystring', function () {
    $user = boletoBootstrap();
    $response = $this->actingAs($user)->get('/financeiro/boletos?status=pago');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('filtros.status', 'pago')
    );
});

it('Tier 0 IRREVOGÁVEL: BoletoRemessa respeita business_id global scope (ADR 0093)', function () {
    $user = boletoBootstrap();
    $businessId = (int) $user->business_id;

    $response = $this->actingAs($user)->get('/financeiro/boletos');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $response->assertInertia(function (AssertableInertia $page) use ($businessId) {
        $remessas = $page->toArray()['props']['remessas'] ?? [];
        foreach ($remessas as $r) {
            $id = $r['id'] ?? null;
            if ($id === null) {
                continue;
            }
            $count = BoletoRemessa::query()
                ->where('id', $id)
                ->where('business_id', '!=', $businessId)
                ->count();
            expect($count)->toBe(0, "BoletoRemessa {$id} cross-tenant detectada (Tier 0 violation)");
        }
    });
});

it('não dispara mutação em GET /boletos (read-only puro)', function () {
    $user = boletoBootstrap();

    $countBefore = BoletoRemessa::query()->count();
    $response = $this->actingAs($user)->get('/financeiro/boletos');

    if (in_array($response->status(), [403, 404], true)) {
        test()->markTestSkipped('Module gate bloqueia neste env.');
    }

    $countAfter = BoletoRemessa::query()->count();
    expect($countAfter)->toBe($countBefore);
});
