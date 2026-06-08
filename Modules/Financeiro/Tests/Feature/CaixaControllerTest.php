<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * US-FIN-CAIXA — guard da tela /financeiro/caixa (F6 Soft wrapper).
 *
 * Cobre invariantes:
 *  1. Multi-tenant Tier 0 (ADR 0093 IRREVOGÁVEL): business B não vê caixas de A
 *  2. Permission gate `view_cash_register` enforce
 *  3. Inertia component path + shape esperado (caixas[], stats, filters, links)
 *  4. Filtro `?status=open|close` aplica
 *  5. `?limit` clamped em [10, 200]
 *
 * Skip gracioso (Financeiro convention) quando DB greenfield ou subscription gate.
 */
function caixaBootstrap(): User
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

    Permission::firstOrCreate(['name' => 'view_cash_register', 'guard_name' => 'web']);
    if (! $user->hasPermissionTo('view_cash_register')) {
        $user->givePermissionTo('view_cash_register');
    }

    session([
        'user.id' => $user->id,
        'user.business_id' => $business->id,
        'business.id' => $business->id,
    ]);

    return $user;
}

it('renderiza Inertia component Financeiro/Caixa/Index com shape esperado', function () {
    $user = caixaBootstrap();

    $response = $this->actingAs($user)->get('/financeiro/caixa');

    $response->assertStatus(200);
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Financeiro/Caixa/Index')
        ->has('caixas')
        ->has('stats.total_caixas')
        ->has('stats.caixas_abertos')
        ->has('stats.soma_fechamentos')
        ->has('filters.status')
        ->has('filters.limit')
        ->has('links.pos_create')
    );
});

it('bloqueia user sem permission view_cash_register (403)', function () {
    $business = Business::first();
    if (! $business) {
        $this->markTestSkipped('Sem business.');
    }
    $user = User::where('business_id', $business->id)->first();
    if (! $user) {
        $this->markTestSkipped('Sem user.');
    }

    // Garante que NÃO tem a permission
    if ($user->hasPermissionTo('view_cash_register')) {
        $user->revokePermissionTo('view_cash_register');
    }

    session([
        'user.id' => $user->id,
        'user.business_id' => $business->id,
        'business.id' => $business->id,
    ]);

    $response = $this->actingAs($user)->get('/financeiro/caixa');

    expect($response->status())->toBeIn([403, 302]); // 403 forbidden ou redirect login
});

it('aplica filtro ?status=open na query', function () {
    $user = caixaBootstrap();

    $response = $this->actingAs($user)->get('/financeiro/caixa?status=open');

    $response->assertStatus(200);
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('filters.status', 'open')
    );
});

it('clamp ?limit acima de 200 vira 200', function () {
    $user = caixaBootstrap();

    $response = $this->actingAs($user)->get('/financeiro/caixa?limit=9999');

    $response->assertStatus(200);
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('filters.limit', 200)
    );
});

it('clamp ?limit abaixo de 10 vira 10', function () {
    $user = caixaBootstrap();

    $response = $this->actingAs($user)->get('/financeiro/caixa?limit=1');

    $response->assertStatus(200);
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('filters.limit', 10)
    );
});

it('Tier 0 multi-tenant — não vaza caixa de outro business', function () {
    $userA = caixaBootstrap();
    $businessA = Business::find($userA->business_id);
    $businessB = Business::where('id', '!=', $businessA->id)->first();

    if (! $businessB) {
        $this->markTestSkipped('Precisa 2+ businesses no banco.');
    }
    $userBExists = User::where('business_id', $businessB->id)->exists();
    if (! $userBExists) {
        $this->markTestSkipped('Sem user no businessB.');
    }

    // Inserir cash_register no businessB e confirmar que userA NÃO vê
    $crBId = \DB::table('cash_registers')->insertGetId([
        'business_id' => $businessB->id,
        'user_id' => User::where('business_id', $businessB->id)->value('id'),
        'status' => 'open',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($userA)->get('/financeiro/caixa');

    $response->assertStatus(200);
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('caixas', function ($caixas) use ($crBId) {
            foreach ($caixas as $c) {
                if ((int) $c['id'] === $crBId) {
                    return false; // VAZAMENTO!
                }
            }
            return true;
        })
    );

    // Cleanup
    \DB::table('cash_registers')->where('id', $crBId)->delete();
});
