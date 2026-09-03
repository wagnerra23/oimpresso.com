<?php

declare(strict_types=1);

use App\Services\Dashboard\GradesDoPainelService;
use App\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

// `uses(TestCase::class)` é global em tests/Pest.php pra toda pasta Feature/.

/**
 * US-DASH-005 — abas de grade + drawer do painel "Visão geral".
 *
 * Invariantes cobertas:
 *  1. Aba SEM permissão não aparece — e a aparição segue as DUAS camadas do Blade
 *     (`dashboard.data` em volta de tudo + o `@can` de cada grade)
 *  2. Setting desligado esconde a aba condicional (`enable_product_expiry`)
 *  3. Aba desconhecida na query string cai na primeira PERMITIDA (nunca estoura,
 *     nunca serve dado que o usuário não pode ver)
 *  4. Tier 0 multi-tenant (ADR 0093) — a grade não devolve linha de outro business
 *  5. PARIDADE com o endpoint legado — trava a duplicação de critério que o
 *     Non-Goal do charter impõe (ver o docblock de GradesDoPainelService)
 *  6. Non-Goal GUARD — os 4 endpoints AJAX seguem respondendo intactos
 *
 * Skip gracioso (convenção oimpresso) quando o DB está greenfield.
 */
function gradesBootstrap(array $permissoes = ['dashboard.data']): User
{
    try {
        $business = test()->seededTenant();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tabela business indisponível: '.$e->getMessage());
    }

    $user = User::where('business_id', $business->id)
        ->where('user_type', '!=', 'user_customer')
        ->first();

    if (! $user) {
        test()->markTestSkipped('Sem user não-customer no business.');
    }

    // Zera o que este teste governa, pra uma execução não herdar a permissão da anterior
    // (a database do CT 100 PERSISTE entre runs — ver proibicoes.md §Ambiente).
    foreach (array_keys(GradesDoPainelService::catalogo()) as $key) {
        foreach (GradesDoPainelService::catalogo()[$key]['perms'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
            if ($user->hasPermissionTo($perm)) {
                $user->revokePermissionTo($perm);
            }
        }
    }

    foreach ($permissoes as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        if (! $user->hasPermissionTo($perm)) {
            $user->givePermissionTo($perm);
        }
    }

    if (! in_array('dashboard.data', $permissoes, true) && $user->hasPermissionTo('dashboard.data')) {
        $user->revokePermissionTo('dashboard.data');
    }

    $user->forgetCachedPermissions();

    session([
        'user.id' => $user->id,
        'user.business_id' => $business->id,
        'user.first_name' => $user->first_name ?? 'Usuário',
        'business.id' => $business->id,
        'business.currency_id' => $business->currency_id ?? 1,
        'business.enable_product_expiry' => 0,
        'business.common_settings' => [],
        'currency' => DB::table('currencies')->find($business->currency_id ?? 1)
            ? (array) DB::table('currencies')->find($business->currency_id ?? 1)
            : ['code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','],
    ]);

    return $user;
}

/** @return string[] */
function chavesDasAbas(array $abas): array
{
    return array_map(static fn ($a) => $a['key'], $abas);
}

it('UC-DASH-07 · aba sem permissão NÃO aparece — cada grade tem o seu próprio gate', function () {
    $user = gradesBootstrap(['dashboard.data', 'sell.view']);

    $response = $this->actingAs($user)->get('/dashboard-legacy');

    $response->assertStatus(200);
    $response->assertInertia(function (AssertableInertia $page) {
        $abas = chavesDasAbas($page->toArray()['props']['abas']);

        expect($abas)->toContain('venc-venda')
            ->and($abas)->not->toContain('venc-compra')
            ->and($abas)->not->toContain('estoque')
            ->and($abas)->not->toContain('expedicao');
    });
});

it('UC-DASH-08 · sem dashboard.data NÃO há aba nenhuma, mesmo com a permissão da grade', function () {
    // No Blade legado TODAS as grades vivem dentro do `@if(can('dashboard.data'))`
    // que abre na linha 369 e fecha na 1013 — medido em 2026-08-27.
    $user = gradesBootstrap(['sell.view', 'purchase.view', 'stock_report.view']);

    $response = $this->actingAs($user)->get('/dashboard-legacy');

    $response->assertStatus(200);
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('can_dashboard_data', false)
        ->where('abas', [])
        ->where('aba', null)
        ->where('grade', null)
    );
});

it('UC-DASH-09 · setting desligado esconde a aba condicional (Lotes a vencer)', function () {
    $user = gradesBootstrap(['dashboard.data', 'stock_report.view']);

    session(['business.enable_product_expiry' => 0]);
    $comSettingDesligado = $this->actingAs($user)->get('/dashboard-legacy');

    $comSettingDesligado->assertInertia(function (AssertableInertia $page) {
        $abas = chavesDasAbas($page->toArray()['props']['abas']);

        expect($abas)->toContain('estoque')          // mesma permissão…
            ->and($abas)->not->toContain('validade'); // …mas o setting está desligado
    });

    session(['business.enable_product_expiry' => 1]);
    $comSettingLigado = $this->actingAs($user)->get('/dashboard-legacy');

    $comSettingLigado->assertInertia(function (AssertableInertia $page) {
        expect(chavesDasAbas($page->toArray()['props']['abas']))->toContain('validade');
    });
});

it('UC-DASH-10 · aba desconhecida na query string cai na primeira PERMITIDA, sem estourar', function () {
    $user = gradesBootstrap(['dashboard.data', 'purchase.view']);

    // `caixa` é a 9ª aba do protótipo — ela não existe no Blade e não existe aqui.
    $response = $this->actingAs($user)->get('/dashboard-legacy?aba=caixa');

    $response->assertStatus(200);
    $response->assertInertia(fn (AssertableInertia $page) => $page->where('aba', 'venc-compra'));
});

it('UC-DASH-10 · aba que o usuário NÃO pode ver não é servida nem quando pedida na URL', function () {
    $user = gradesBootstrap(['dashboard.data', 'purchase.view']);

    $response = $this->actingAs($user)->get('/dashboard-legacy?aba=venc-venda');

    $response->assertStatus(200);
    // Pediu a de venda (sem permissão) e recebeu a de compra (a única permitida).
    $response->assertInertia(fn (AssertableInertia $page) => $page->where('aba', 'venc-compra'));
});

it('UC-DASH-11 · Tier 0 multi-tenant — a grade não devolve linha de outro business', function () {
    $user = gradesBootstrap(['dashboard.data', 'sell.view']);
    $businessId = (int) session('user.business_id');

    $outro = DB::table('transactions')
        ->where('business_id', '!=', $businessId)
        ->where('type', 'sell')
        ->pluck('id');

    if ($outro->isEmpty()) {
        $this->markTestSkipped('Sem transaction de outro business — o teste não teria o que provar.');
    }

    $this->actingAs($user);
    $servico = app(GradesDoPainelService::class);
    $linhas = $servico->linhas('venc-venda', $businessId);

    expect($linhas)->not->toBeNull();

    $idsServidos = collect($linhas->items())->pluck('id')->all();

    expect(array_intersect($idsServidos, $outro->all()))->toBe([]);
});

it('UC-DASH-12 · PARIDADE com o endpoint legado — mesmo total de títulos de venda vencendo', function () {
    // Esta é a trava da duplicação que o Non-Goal do charter impõe: o service tem
    // query PRÓPRIA porque `/home/sales-payment-dues` devolve HTML, e não pode ser
    // tocado. Se um dos dois critérios derivar, este teste cai.
    $user = gradesBootstrap(['dashboard.data', 'sell.view']);
    $businessId = (int) session('user.business_id');

    $legado = $this->actingAs($user)
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->get('/home/sales-payment-dues');

    $legado->assertStatus(200);

    $totalLegado = $legado->json('recordsTotal');

    if ($totalLegado === null) {
        $this->markTestSkipped('Endpoint legado não devolveu recordsTotal — nada a comparar.');
    }

    $this->actingAs($user);
    $totalNovo = app(GradesDoPainelService::class)->linhas('venc-venda', $businessId)->total();

    expect($totalNovo)->toBe((int) $totalLegado);
});

it('UC-DASH-13 · Non-Goal GUARD — os 4 endpoints AJAX do Blade seguem respondendo', function () {
    // O charter proíbe TOCAR esses endpoints. O guard não prova que o corpo é
    // idêntico — prova que eles continuam de pé e servindo o Blade legado.
    $user = gradesBootstrap(['dashboard.data', 'sell.view', 'purchase.view', 'stock_report.view']);

    foreach ([
        '/home/get-totals',
        '/home/product-stock-alert',
        '/home/purchase-payment-dues',
        '/home/sales-payment-dues',
    ] as $rota) {
        $resposta = $this->actingAs($user)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get($rota);

        expect($resposta->status())->toBeIn([200, 302]);
    }
});

it('UC-DASH-14 · catálogo declara as 8 grades do Blade — e nenhuma inventada', function () {
    // O protótipo desenha 9 abas; o Blade tem 8. A 9ª ("Fluxo de caixa") não tem
    // fonte e por isso não pode existir aqui.
    $catalogo = GradesDoPainelService::catalogo();

    expect(array_keys($catalogo))->toBe([
        'venc-venda',
        'venc-compra',
        'estoque',
        'validade',
        'pedidos',
        'compras-abertas',
        'requisicoes',
        'expedicao',
    ]);

    expect($catalogo)->not->toHaveKey('caixa');
});

it('UC-DASH-15 · ordenacao: coluna da allowlist ordena de verdade', function () {
    $user = gradesBootstrap(['dashboard.data', 'sell.view']);
    $businessId = (int) session('user.business_id');
    $this->actingAs($user); // o service le auth()->user() em abasPermitidas()
    $servico = app(GradesDoPainelService::class);

    request()->merge(['sort' => 'documento', 'dir' => 'asc']);
    $asc = collect($servico->linhas('venc-venda', $businessId)->items())->pluck('documento')->all();

    request()->merge(['sort' => 'documento', 'dir' => 'desc']);
    $desc = collect($servico->linhas('venc-venda', $businessId)->items())->pluck('documento')->all();

    if (count($asc) < 2) {
        $this->markTestSkipped('Menos de 2 titulos vencendo — sem par para provar ordem.');
    }

    expect($asc)->toBe(array_reverse($desc));
});

it('UC-DASH-15 · ordenacao: coluna FORA da allowlist nao vira SQL', function () {
    // `sort` vem da query string. Chave desconhecida cai na ordenacao padrao em vez de
    // ser interpolada — senao a allowlist seria decorativa. Payload com aspas e parenteses
    // quebraria a query se chegasse crua no orderBy.
    $user = gradesBootstrap(['dashboard.data', 'sell.view']);
    $businessId = (int) session('user.business_id');
    $this->actingAs($user); // idem — chamada direta ao service exige usuario autenticado

    request()->merge(['sort' => "transactions.id) OR 1=1 --", 'dir' => 'asc']);

    $linhas = app(GradesDoPainelService::class)->linhas('venc-venda', $businessId);

    expect($linhas)->not->toBeNull();
});

it('UC-DASH-16 · ordenaveis() espelha o sortable da ancora — situacao NAO ordena', function () {
    // O prototipo marca `sortable: true` em documento/contato/data/valor, e NAO em situacao.
    expect(GradesDoPainelService::ordenaveis('venc-venda'))->toContain('documento', 'contato', 'vencimento')
        ->and(GradesDoPainelService::ordenaveis('venc-venda'))->not->toContain('situacao')
        ->and(GradesDoPainelService::ordenaveis('pedidos'))->toContain('total')
        ->and(GradesDoPainelService::ordenaveis('aba-inexistente'))->toBe([]);
});
