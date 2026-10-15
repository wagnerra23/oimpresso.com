<?php

declare(strict_types=1);

use App\Http\Controllers\Api\App\RelatoriosController;
use App\User;
use App\Utils\TransactionUtil;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Relatórios do app (tela 13) — abas lucratividade e comissões (P6, só leitura).
 *
 * Contrato: memory/requisitos/AppMobile/api/tela-13-lucratividade.md (UC-APPLC-01..06). NÃO derivado
 * do controller. Dupla prova dos números: (a) delta contra a conta feita à mão aqui; (b) contra as
 * funções do relatório web — getProfitLossDetails (Lucro/Prejuízo) e getTotalSellCommission /
 * getTotalPaymentWithCommission (Comissão por vendedor).
 *
 * Tier 0 (ADR 0093 · ADR 0358): tenant 98 × 2 — venda e vendedor do business 2 nunca aparecem no 98.
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! file_exists(storage_path('oauth-public.key'))) {
        \Illuminate\Support\Facades\Artisan::call('passport:keys', ['--force' => true]);
    }
    if (DB::connection()->getDriverName() === 'sqlite' || ! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Requer schema MySQL UltimatePOS (ADR 0358).');
    }
    foreach (['transaction_payments', 'transaction_sell_lines_purchase_lines', 'purchase_lines'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema ausente ({$t}).");
        }
    }

    $this->biz = EstoqueFixture::businessId();
    $this->outro = EstoqueFixture::secondBusinessId();
    if (! $this->outro) {
        $this->markTestSkipped('Lane sem 2º business — contrato cross-tenant não exercitável.');
    }
    $this->loc = EstoqueFixture::locationId($this->biz);
    foreach (['dashboard.data', 'commission_agent.view', 'commission_agent.manage', 'access_all_locations'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
});

/** @param list<string> $perms */
function appLcUsuario(int $biz, array $perms, float $pct = 0): User
{
    $id = DB::table('users')->insertGetId([
        'first_name' => 'APP LC ' . uniqid(), 'username' => 'app_lc_' . uniqid(), 'password' => 'x',
        'business_id' => $biz, 'cmmsn_percent' => $pct, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $u = User::findOrFail($id);
    foreach ($perms as $p) {
        $u->givePermissionTo($p);
    }

    return $u;
}

/** Ajustes do pos_settings do business (a transação do teste desfaz). */
function appLcPos(int $biz, array $valores): void
{
    $pos = json_decode((string) DB::table('business')->where('id', $biz)->value('pos_settings'), true) ?: [];
    DB::table('business')->where('id', $biz)->update(['pos_settings' => json_encode(array_merge($pos, $valores))]);
}

/** Venda final de hoje com 1 linha; devolve o id da venda. */
function appLcVenda(int $biz, int $loc, float $qtd, float $preco, ?int $vendedor = null, bool $comEstoque = false): array
{
    $p = EstoqueFixture::singleProduct($biz, $comEstoque);
    $v = EstoqueFixture::saleWithLine($p, 0, $loc, $qtd, $preco);
    DB::table('transactions')->where('id', $v['transaction_id'])->update(['commission_agent' => $vendedor, 'invoice_no' => 'LC-' . uniqid()]);

    return $v + ['produto' => $p];
}

function appLcPagamento(int $venda, float $valor): void
{
    DB::table('transaction_payments')->insert([
        'transaction_id' => $venda, 'amount' => $valor, 'method' => 'cash', 'is_return' => 0,
        'paid_on' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('UC-APPLC-01 lucratividade: delta da venda e da despesa bate com a conta à mão e com o Lucro/Prejuízo web', function () {
    $eu = appLcUsuario($this->biz, ['dashboard.data', 'access_all_locations']);
    Passport::actingAs($eu, [], 'api');
    $antes = $this->getJson('/api/app/relatorios?periodo=mes&aba=lucratividade')->assertOk()->json('lucratividade');

    appLcVenda($this->biz, $this->loc, 2, 100.00);                       // sem estoque: lucro = 200
    DB::table('transactions')->insert([
        'business_id' => $this->biz, 'location_id' => $this->loc, 'type' => 'expense', 'status' => 'final',
        'payment_status' => 'due', 'transaction_date' => now(), 'final_total' => 50.00, 'total_before_tax' => 50.00,
        'created_by' => $eu->id, 'essentials_duration' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    appLcVenda((int) $this->outro, EstoqueFixture::locationId((int) $this->outro), 1, 9999.00);   // outro business: fora

    $r = $this->getJson('/api/app/relatorios?periodo=mes&aba=lucratividade')->assertOk()->json();
    $l = $r['lucratividade'];
    $d = fn (string $k) => round((float) $l[$k] - (float) $antes[$k], 2);

    expect(array_keys($l))->toBe(['receita', 'custo_variavel', 'custo_fixo', 'margem_contribuicao', 'margem_pct', 'ponto_equilibrio', 'menores_margens']);
    expect($d('receita'))->toBe(200.0);
    expect($d('margem_contribuicao'))->toBe(200.0);
    expect($d('custo_fixo'))->toBe(50.0);
    expect($r['dre'])->toBeNull();
    expect($r['comissoes'])->toBeNull();

    // Prova 2: o Lucro/Prejuízo web no mesmo período e locais.
    $hoje = CarbonImmutable::today();
    $web = app(TransactionUtil::class)->getProfitLossDetails($this->biz, null, $hoje->startOfMonth()->toDateString(), $hoje->endOfMonth()->toDateString(), null, $eu->permitted_locations($this->biz));
    expect((float) $l['receita'])->toBe(round((float) $web['total_sell'], 2));
    expect((float) $l['margem_contribuicao'])->toBe(round((float) $web['gross_profit'], 2));
    expect((float) $l['custo_fixo'])->toBe(round((float) $web['total_expense'], 2));
    expect((float) $l['custo_variavel'])->toBe(round((float) $l['receita'] - (float) $l['margem_contribuicao'], 2));
});

it('UC-APPLC-02 menores margens: a venda abaixo do custo vem primeiro; a soma por venda bate com getGrossProfit', function () {
    $eu = appLcUsuario($this->biz, ['dashboard.data', 'access_all_locations']);
    Passport::actingAs($eu, [], 'api');

    // Venda de 100 com custo de 1000 → margem −900%.
    $v = appLcVenda($this->biz, $this->loc, 1, 100.00, null, true);
    $compra = DB::table('transactions')->insertGetId([
        'business_id' => $this->biz, 'location_id' => $this->loc, 'type' => 'purchase', 'status' => 'received',
        'payment_status' => 'paid', 'transaction_date' => now()->subDays(40), 'final_total' => 1000, 'total_before_tax' => 1000,
        'created_by' => $eu->id, 'essentials_duration' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $pl = DB::table('purchase_lines')->insertGetId([
        'transaction_id' => $compra, 'product_id' => $v['produto']->productId, 'variation_id' => $v['produto']->variations[0]['variation_id'],
        'quantity' => 1, 'purchase_price' => 1000, 'purchase_price_inc_tax' => 1000, 'item_tax' => 0, 'quantity_sold' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('transaction_sell_lines_purchase_lines')->insert([
        'sell_line_id' => $v['sell_line_id'], 'purchase_line_id' => $pl, 'quantity' => 1, 'qty_returned' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $menores = $this->getJson('/api/app/relatorios?aba=lucratividade')->assertOk()->json('lucratividade.menores_margens');

    expect($menores[0]['pedido_id'])->toBe($v['transaction_id']);
    expect((float) $menores[0]['margem_pct'])->toBe(-900.0);
    expect((float) $menores[0]['valor'])->toBe(100.0);
    expect(array_keys($menores[0]))->toBe(['pedido_id', 'numero', 'resumo', 'valor', 'margem_pct']);

    $hoje = CarbonImmutable::today();
    [$de, $ate] = [$hoje->startOfMonth(), $hoje->endOfMonth()->startOfDay()];
    $locais = $eu->permitted_locations($this->biz);
    $porVenda = app(RelatoriosController::class)->lucroPorVenda($this->biz, $de, $ate, $locais)->get()->sum('lucro');
    $web = app(TransactionUtil::class)->getGrossProfit($this->biz, $de->toDateString(), $ate->toDateString(), null, null, $locais);
    expect(round((float) $porVenda, 2))->toBe(round((float) $web, 2));
});

it('UC-APPLC-03 comissões (base recebido): o gerente vê todos; números = conta à mão = relatório web por vendedor', function () {
    appLcPos($this->biz, ['cmmsn_calculation_type' => 'payment_received']);
    $ana = appLcUsuario($this->biz, [], 3.0);
    $bruno = appLcUsuario($this->biz, [], 5.0);
    $va = appLcVenda($this->biz, $this->loc, 10, 100.00, $ana->id);    // vendido 1000
    appLcPagamento($va['transaction_id'], 600.00);                      // recebido 600, aberto 400
    appLcVenda($this->biz, $this->loc, 2, 100.00, $bruno->id);          // vendido 200, nada recebido
    $alheio = appLcUsuario((int) $this->outro, [], 9.0);
    appLcVenda((int) $this->outro, EstoqueFixture::locationId((int) $this->outro), 1, 7777.00, $alheio->id);
    appLcVenda((int) $this->outro, EstoqueFixture::locationId((int) $this->outro), 1, 5555.00, $ana->id);   // id da Ana noutro business: fora

    $gerente = appLcUsuario($this->biz, ['commission_agent.view', 'access_all_locations']);
    Passport::actingAs($gerente, [], 'api');
    $c = $this->getJson('/api/app/relatorios?aba=comissoes')->assertOk()->json('comissoes');

    expect($c['base'])->toBe('payment_received');
    $linhas = collect($c['vendedores'])->keyBy('id');
    expect($linhas[$ana->id])->toMatchArray(['percentual' => 3, 'vendido' => 1000, 'recebido' => 600, 'comissao' => 18, 'a_liberar' => 12]);
    expect($linhas[$bruno->id])->toMatchArray(['percentual' => 5, 'vendido' => 200, 'recebido' => 0, 'comissao' => 0, 'a_liberar' => 10]);
    expect(array_keys($linhas[$ana->id]))->toBe(['id', 'nome', 'percentual', 'vendido', 'recebido', 'comissao', 'a_liberar']);
    expect($linhas->has($alheio->id))->toBeFalse();

    // Prova 2: as funções do relatório Comissão por vendedor do web, por vendedor.
    $hoje = CarbonImmutable::today();
    [$de, $ate] = [$hoje->startOfMonth()->toDateString(), $hoje->endOfMonth()->toDateString()];
    $tu = app(TransactionUtil::class);
    expect((float) $linhas[$ana->id]['vendido'])->toBe(round((float) $tu->getTotalSellCommission($this->biz, $de, $ate, null, $ana->id)['total_sales_with_commission'], 2));
    expect((float) $linhas[$ana->id]['recebido'])->toBe(round((float) $tu->getTotalPaymentWithCommission($this->biz, $de, $ate, null, $ana->id)['total_payment_with_commission'], 2));
});

it('UC-APPLC-04 comissões (base venda): comissão sobre o vendido e nada a liberar', function () {
    appLcPos($this->biz, ['cmmsn_calculation_type' => 'invoice_value']);
    $ana = appLcUsuario($this->biz, [], 3.0);
    $va = appLcVenda($this->biz, $this->loc, 10, 100.00, $ana->id);
    appLcPagamento($va['transaction_id'], 600.00);
    Passport::actingAs(appLcUsuario($this->biz, ['commission_agent.view', 'access_all_locations']), [], 'api');

    $c = $this->getJson('/api/app/relatorios?aba=comissoes')->assertOk()->json('comissoes');

    expect($c['base'])->toBe('invoice_value');
    expect(collect($c['vendedores'])->firstWhere('id', $ana->id))->toMatchArray(['comissao' => 30, 'a_liberar' => 0]);
});

it('UC-APPLC-05 vendedor sem commission_agent.view vê só a própria linha; com o ajuste desligado, nada', function () {
    appLcPos($this->biz, ['cmmsn_calculation_type' => 'payment_received']);
    $ana = appLcUsuario($this->biz, ['access_all_locations'], 3.0);
    $bruno = appLcUsuario($this->biz, [], 5.0);
    appLcVenda($this->biz, $this->loc, 10, 100.00, $ana->id);
    appLcVenda($this->biz, $this->loc, 2, 100.00, $bruno->id);
    Passport::actingAs($ana, [], 'api');

    $c = $this->getJson('/api/app/relatorios?aba=comissoes')->assertOk()->json('comissoes');
    expect(collect($c['vendedores'])->pluck('id')->all())->toBe([$ana->id]);
    expect((float) $c['vendedores'][0]['vendido'])->toBe(1000.0);

    // Ajuste da empresa desligado: o vendedor perde a aba; sem outro bloco, a rota é 403.
    appLcPos($this->biz, ['app_comissao_vendedor_ve_propria' => false]);
    $this->getJson('/api/app/relatorios?aba=comissoes')->assertStatus(403)->assertJsonPath('erro', 'sem_permissao');

    // Quem vê a lista continua vendo com o ajuste desligado.
    Passport::actingAs(appLcUsuario($this->biz, ['commission_agent.view', 'access_all_locations']), [], 'api');
    expect($this->getJson('/api/app/relatorios?aba=comissoes')->assertOk()->json('comissoes.vendedores'))->not->toBeEmpty();
});

it('UC-APPLC-06 sem dashboard.data a lucratividade vem null; sem percentual nem permissão, comissões null', function () {
    Passport::actingAs(appLcUsuario($this->biz, ['commission_agent.view']), [], 'api');
    $r = $this->getJson('/api/app/relatorios?aba=lucratividade')->assertOk()->json();
    expect($r['lucratividade'])->toBeNull();

    Passport::actingAs(appLcUsuario($this->biz, ['dashboard.data']), [], 'api');
    $r = $this->getJson('/api/app/relatorios?aba=comissoes')->assertOk()->json();
    expect($r['comissoes'])->toBeNull();
    expect($r['lucratividade'])->toBeNull();   // só o bloco da aba pedida vem preenchido
});
