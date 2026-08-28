<?php

declare(strict_types=1);

use App\Business;
use App\BusinessLocation;
use App\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

// `uses(TestCase::class)` é aplicado globalmente em tests/Pest.php pra toda pasta Feature/.
// Re-declarar aqui (como estava antes) gerava "TestCase can not be used. The folder already uses..." (Pest TestRepository).

/**
 * US-DASH-001 — guard da tela /home (F6 Soft wrapper).
 *
 * Cobre invariantes:
 *  1. Inertia component path + shape esperado (user_name, is_admin, totals, endpoints)
 *  2. Customer redirect preservado (user_type=user_customer → 302 pra Crm dashboard)
 *  3. Sem permission `dashboard.data` → totals null (shell minimal)
 *  4. ?legacy=1 é INERTE — o Blade legado foi removido em 2026-08-28
 *  5. Tier 0 multi-tenant — não vaza locations de outro business
 *
 * Skip gracioso (convention oimpresso) quando DB greenfield ou subscription gate.
 */
function homeBootstrap(): User
{
    try {
        $business = test()->seededTenant(); // biz=1 canônico (ADR 0101) — skip acionável se o seed faltar
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tabela business indisponível: '.$e->getMessage());
    }

    $user = User::where('business_id', $business->id)
        ->where('user_type', '!=', 'user_customer')
        ->first();

    if (! $user) {
        test()->markTestSkipped('Sem user não-customer no business.');
    }

    Permission::firstOrCreate(['name' => 'dashboard.data', 'guard_name' => 'web']);
    if (! $user->hasPermissionTo('dashboard.data')) {
        $user->givePermissionTo('dashboard.data');
    }

    session([
        'user.id' => $user->id,
        'user.business_id' => $business->id,
        'user.first_name' => $user->first_name ?? 'Usuário',
        'business.id' => $business->id,
        'business.currency_id' => $business->currency_id ?? 1,
        'currency' => DB::table('currencies')->find($business->currency_id ?? 1)
            ? (array) DB::table('currencies')->find($business->currency_id ?? 1)
            : ['code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','],
    ]);

    return $user;
}

it('UC-DASH-01 · renderiza Inertia component Home/Index com shape esperado', function () {
    $user = homeBootstrap();

    $response = $this->actingAs($user)->get('/dashboard-legacy');

    $response->assertStatus(200);
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Home/Index')
        ->has('user_name')
        ->has('is_admin')
        ->has('can_dashboard_data')
        ->has('all_locations')
        ->has('endpoints.totals')
        ->has('endpoints.stock_alert')
        ->has('endpoints.purchase_dues')
        ->has('endpoints.sales_dues')
    );
});

it('UC-DASH-02 · customer redirect preservado (user_type=user_customer → 302)', function () {
    $business = $this->seededTenant(); // biz=1 canônico (ADR 0101) — skip acionável se o seed faltar

    $customer = User::where('business_id', $business->id)
        ->where('user_type', 'user_customer')
        ->first();

    if (! $customer) {
        $this->markTestSkipped('Sem user_customer no business pra testar redirect.');
    }

    session([
        'user.id' => $customer->id,
        'user.business_id' => $business->id,
        'business.id' => $business->id,
    ]);

    $response = $this->actingAs($customer)->get('/dashboard-legacy');

    expect($response->status())->toBe(302);
});

it('UC-DASH-03 · sem permission dashboard.data → totals null (shell minimal)', function () {
    $user = homeBootstrap();

    if ($user->hasPermissionTo('dashboard.data')) {
        $user->revokePermissionTo('dashboard.data');
    }

    $response = $this->actingAs($user)->get('/dashboard-legacy');

    $response->assertStatus(200);
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Home/Index')
        ->where('can_dashboard_data', false)
        ->where('totals', null)
    );
});

it('UC-DASH-04 · ?legacy=1 é inerte — não existe mais fallback Blade', function () {
    $user = homeBootstrap();

    // Até 2026-08-28 esta query servia `view('home.index')`. O Blade foi removido junto
    // com o ramo que o chamava; a query sobrevive apenas como link velho em favorito, e
    // tem de cair na MESMA tela React — nunca em 500 nem numa view fantasma.
    $response = $this->actingAs($user)->get('/dashboard-legacy?legacy=1');

    $response->assertStatus(200);
    $response->assertInertia(fn (AssertableInertia $page) => $page->component('Home/Index'));
});

it('UC-DASH-05 · Tier 0 multi-tenant — não vaza locations de outro business', function () {
    $userA = homeBootstrap();
    $businessA = Business::find($userA->business_id);
    $businessB = Business::where('id', '!=', $businessA->id)->first();

    if (! $businessB) {
        $this->markTestSkipped('Precisa 2+ businesses no banco.');
    }

    // `business_locations` tem TRÊS FKs (contadas no schema, não supostas): business_id,
    // invoice_scheme_id e invoice_layout_id. As duas últimas são NOT NULL e não aceitam o
    // default 0 — o insert sem elas quebra com "Cannot add or update a child row"
    // (SQLSTATE 23000). O teste vivia com esse defeito desde que nasceu porque NENHUMA
    // lane executava `tests/Feature/Home/`; só apareceu quando o dashboard-pest.yml passou
    // a rodá-lo, no mesmo PR que este comentário.
    //
    // As duas são resolvidas JUNTAS: satisfazer uma e deixar a irmã só troca a mensagem
    // de erro — foi o que aconteceu entre dois runs seguidos deste mesmo teste.
    $invoiceScheme = \DB::table('invoice_schemes')->where('business_id', $businessB->id)->value('id')
        ?? \DB::table('invoice_schemes')->value('id');
    $invoiceLayout = \DB::table('invoice_layouts')->where('business_id', $businessB->id)->value('id')
        ?? \DB::table('invoice_layouts')->value('id');

    if (! $invoiceScheme || ! $invoiceLayout) {
        $this->markTestSkipped('Sem invoice_scheme/invoice_layout no banco — as FKs de business_locations não teriam alvo.');
    }

    // Inserir location no businessB e confirmar que userA NÃO vê
    $locBId = \DB::table('business_locations')->insertGetId([
        'business_id' => $businessB->id,
        'invoice_scheme_id' => $invoiceScheme,
        'invoice_layout_id' => $invoiceLayout,
        'name' => '__TIER0_LEAK_GUARD__',
        'landmark' => null,
        'country' => 'BR',
        'state' => 'XX',
        'city' => 'TestCity',
        'zip_code' => '00000',
        'is_active' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($userA)->get('/dashboard-legacy');

    $response->assertStatus(200);
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('all_locations', function ($locations) use ($locBId) {
            // all_locations é Record<id, name> — chave é o id
            return ! array_key_exists($locBId, (array) $locations);
        })
    );

    // Cleanup
    \DB::table('business_locations')->where('id', $locBId)->delete();
});

it('UC-DASH-06 · totals expõe 8 campos canônicos (guard charter v2)', function () {
    $user = homeBootstrap();

    $response = $this->actingAs($user)->get('/dashboard-legacy');

    $response->assertStatus(200);
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Home/Index')
        ->where('can_dashboard_data', true)
        ->has('totals.total_sell')
        ->has('totals.net')
        ->has('totals.invoice_due')
        ->has('totals.total_expense')
        ->has('totals.total_purchase')
        ->has('totals.purchase_due')
        ->has('totals.total_sell_return')
        ->has('totals.total_purchase_return')
    );
});
