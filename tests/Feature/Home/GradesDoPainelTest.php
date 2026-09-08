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

/*
 * UC-DASH-19 — painel "Pendências" (o atalho do §Backlog do charter, liberado por [W]
 * em 2026-09-04). Três invariantes, e cada uma pode reprovar de verdade:
 *
 *  1. CONCORDÂNCIA — o conjunto exibido é EXATAMENTE o das 5 abas da âncora com
 *     total > 0, e cada número é o da grade. Igualdade nos dois sentidos, sem skip:
 *     no tenant sem movimento ela prova que o painel não inventa linha nem mostra
 *     zero; com movimento, prova a concordância. É o
 *     motivo de `pendencias()` reusar `linhas()` em vez de ter query própria: um
 *     segundo predicado de "pendente" drifta do primeiro e o painel passa a prometer
 *     um número que a grade não entrega.
 *  2. GATE — pendência de aba sem permissão não aparece (mesma regra da aba: some,
 *     não fica desabilitada).
 *  3. CASCA — sem `dashboard.data` a prop nem é registrada, igual a `charts`/`totals`.
 */

it('UC-DASH-19 · o painel é EXATAMENTE as 5 abas da âncora com total > 0, e o total é o da grade', function () {
    $user = gradesBootstrap([
        'dashboard.data',
        'sell.view', 'purchase.view', 'stock_report.view', 'access_shipping',
        // As 3 de FLUXO entram com permissão de propósito: sem elas permitidas, o
        // assert de que não aparecem passaria por falta de permissão, não por escolha
        // da âncora — e é a escolha que este teste defende.
        'so.view_all', 'purchase_order.view_all', 'purchase_requisition.view_all',
    ]);
    $this->actingAs($user);

    $servico = app(GradesDoPainelService::class);
    $businessId = (int) session('business.id');

    // O esperado é DERIVADO da grade, aba por aba: é a forma executável do contrato
    // "o número do painel é o número da aba". A lista das 5 é repetida aqui de
    // propósito — se alguém puser 'pedidos' na const do serviço, os dois lados
    // divergem e este assert cai.
    $esperado = [];
    foreach (['venc-venda', 'venc-compra', 'estoque', 'validade', 'expedicao'] as $aba) {
        $total = $servico->linhas($aba, $businessId)?->total() ?? 0;
        if ($total > 0) {
            $esperado[$aba] = $total;
        }
    }

    $obtido = array_column($servico->pendencias($businessId), 'total', 'aba');

    // Igualdade nos DOIS sentidos — e ela afirma algo mesmo no tenant sem movimento:
    // ali prova que o painel não inventa linha nem exibe zero. Com dado, prova a
    // concordância. Por isso este teste NÃO faz skip: um `markTestSkipped` aqui
    // deixaria a invariante central sem execução nenhuma no CI (LICOES_CODE LC-13),
    // que é exatamente o que a primeira versão dele fez.
    expect($obtido)->toBe($esperado);

    expect(array_keys($obtido))->not->toContain('pedidos')
        ->and(array_keys($obtido))->not->toContain('compras-abertas')
        ->and(array_keys($obtido))->not->toContain('requisicoes');
});

it('UC-DASH-19 · pendência de aba sem permissão não aparece no painel', function () {
    $user = gradesBootstrap(['dashboard.data', 'sell.view']);
    $this->actingAs($user);

    $abas = array_column(
        app(GradesDoPainelService::class)->pendencias((int) session('business.id')),
        'aba'
    );

    expect($abas)->not->toContain('venc-compra')
        ->and($abas)->not->toContain('estoque')
        ->and($abas)->not->toContain('validade')
        ->and($abas)->not->toContain('expedicao');
});

it('UC-DASH-19 · sem dashboard.data a prop pendencias nem é registrada', function () {
    $user = gradesBootstrap(['sell.view', 'purchase.view']);

    $response = $this->actingAs($user)->get('/dashboard-legacy');

    $response->assertStatus(200);
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('can_dashboard_data', false)
        ->where('pendencias', null)
    );
});
