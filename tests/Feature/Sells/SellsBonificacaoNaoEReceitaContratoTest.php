<?php

declare(strict_types=1);

use App\Http\Controllers\ContactController;
use App\User;
use App\Utils\TransactionUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * P-154 (Sabor Brasil) — a bonificação não é receita nem dívida.
 *
 * Decisão [W] 2026-10-30: a venda bonificada (`sub_type = 'bonificacao'`) aparece em Vendas, mas
 * fica fora do faturamento, do saldo em aberto do cliente e dos relatórios de venda e lucro do
 * core. O core marca a bonificação como `payment_status = due` (não tem pagamento), então sem
 * filtro ela entrava como valor a receber em todo lugar que soma `due`.
 *
 * Regra mestre de valor: cada caso compara a MESMA medida antes e depois de gravar duas vendas de
 * 100,00 — uma comum e uma bonificada. Caminho 1: a comum move a medida em +100,00 (prova que o
 * instrumento mede). Caminho 2: a bonificada move em 0,00. Sem o filtro, o caminho 2 dá +100,00.
 *
 * Casos (resources/js/Pages/Sells/Index.casos.md): UC-SBON-06 · UC-SBON-07 · UC-SBON-08 · UC-SBON-09.
 * Tenants: 98 (canônico, ADR 0358) × outro business semeado. Nunca biz=1 nem biz=4.
 * Não roda local (proibicoes.md): lane sells-pest.yml (MySQL).
 */
uses(DatabaseTransactions::class);

const SBR_DIA = '2031-05-20';

function sbrUsuario(int $bizId, array $permissoes): User
{
    $id = DB::table('users')->insertGetId([
        'first_name' => 'SBR',
        'username' => 'sbr_'.uniqid(),
        'password' => bcrypt('ci'),
        'business_id' => $bizId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $user = User::findOrFail($id);
    foreach ($permissoes as $p) {
        Permission::findOrCreate($p, 'web');
        $user->givePermissionTo($p);
    }
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

function sbrCliente(int $bizId, int $userId, string $nome): int
{
    $dados = [
        'business_id' => $bizId, 'type' => 'customer', 'name' => $nome, 'contact_status' => 'active',
        'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
    ];
    // ADR 0188 — a lista de clientes filtra pela flag `is_customer` quando ela existe.
    if (\Illuminate\Support\Facades\Schema::hasColumn('contacts', 'is_customer')) {
        $dados['is_customer'] = 1;
    }

    return (int) DB::table('contacts')->insertGetId($dados);
}

/** Venda de 100,00 a receber (sem pagamento), no dia fixo do teste. */
function sbrVenda(object $t, int $bizId, ?int $contactId, string $sufixo, ?string $subType): int
{
    return (int) DB::table('transactions')->insertGetId([
        'business_id' => $bizId,
        'location_id' => EstoqueFixture::locationId($bizId, 'SBR'),
        'contact_id' => $contactId,
        'created_by' => $t->user->id,
        'type' => 'sell',
        'sub_type' => $subType,
        'status' => 'final',
        'payment_status' => 'due',
        'is_direct_sale' => 1,
        'invoice_no' => $t->prefixo.$sufixo,
        'transaction_date' => SBR_DIA.' 15:00:00',
        'due_date' => '2020-01-01 00:00:00',
        'total_before_tax' => 100,
        'final_total' => 100,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function sbrPrivado(object $obj, string $metodo, ...$args)
{
    $m = new ReflectionMethod($obj, $metodo);
    $m->setAccessible(true);

    return $m->invoke($obj, ...$args);
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS MySQL ausente — roda na lane sells-pest.yml / CT 100.');
    }

    $this->bizId = EstoqueFixture::businessId();
    $this->outroBizId = EstoqueFixture::secondBusinessId();
    expect($this->bizId)->toBe(98);

    $this->prefixo = 'SBR'.strtoupper(substr(uniqid(), -6)).'-';
    $this->user = sbrUsuario($this->bizId, ['sell.create', 'direct_sell.view', 'access_all_locations', 'customer.view']);
    $this->actingAs($this->user);
    session(['user.business_id' => $this->bizId, 'user.id' => $this->user->id]);
});

it('UC-SBON-06 o rodapé da lista soma a venda comum e deixa a bonificação fora dos valores', function () {
    sbrVenda($this, $this->bizId, null, 'COMUM', null);
    sbrVenda($this, $this->bizId, null, 'BONIF', 'bonificacao');

    $totals = $this->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
        ->get('/sells-list-json?'.http_build_query(['q' => $this->prefixo]))
        ->assertOk()->json('totals');

    // As duas linhas aparecem (UC-SBON-02); só a comum entra nos valores.
    expect((int) $totals['count'])->toBe(2)
        ->and((float) $totals['sum_final_total'])->toBe(100.0)
        ->and((float) $totals['sum_total_paid'])->toBe(0.0)
        ->and((float) $totals['sum_due'])->toBe(100.0);
});

it('UC-SBON-07 a bonificação fica fora do faturamento do dia no painel de vendas', function () {
    $agg = app(\App\Services\Sells\SellsCockpitAggregator::class);
    $antes = (float) $agg->buildCoworkAggregates($this->bizId)['faturadoHojeTotal'];

    $hoje = now()->format('Y-m-d').' 10:00:00';
    $comum = sbrVenda($this, $this->bizId, null, 'COMUM', null);
    $bonif = sbrVenda($this, $this->bizId, null, 'BONIF', 'bonificacao');
    DB::table('transactions')->whereIn('id', [$comum, $bonif])->update(['transaction_date' => $hoje]);

    $depois = (float) $agg->buildCoworkAggregates($this->bizId)['faturadoHojeTotal'];
    expect(round($depois - $antes, 2))->toBe(100.0);
});

it('UC-SBON-08 relatórios de venda e lucro do core não contam a bonificação como receita', function () {
    $util = app(TransactionUtil::class);
    $vendasAntes = $util->getSellTotals($this->bizId, SBR_DIA, SBR_DIA);
    $lucroAntes = (float) $util->getGrossProfit($this->bizId, SBR_DIA, SBR_DIA, null, null, 'all');

    // Produto sem controle de estoque: o lucro bruto do core conta quantidade × preço.
    $produto = EstoqueFixture::singleProduct($this->bizId, false);
    $local = EstoqueFixture::locationId($this->bizId, 'SBR');
    $comum = EstoqueFixture::saleWithLine($produto, 0, $local, 2, 50.0)['transaction_id'];
    $bonif = EstoqueFixture::saleWithLine($produto, 0, $local, 2, 50.0)['transaction_id'];
    DB::table('transactions')->whereIn('id', [$comum, $bonif])->update(['transaction_date' => SBR_DIA.' 11:00:00', 'payment_status' => 'due']);
    DB::table('transactions')->where('id', $bonif)->update(['sub_type' => 'bonificacao']);

    $vendasDepois = $util->getSellTotals($this->bizId, SBR_DIA, SBR_DIA);
    $lucroDepois = (float) $util->getGrossProfit($this->bizId, SBR_DIA, SBR_DIA, null, null, 'all');

    // Relatório de lucro do core (aba por venda): a bonificação não aparece como venda lucrativa.
    $porVenda = sbrPrivado(app(\App\Http\Controllers\ReportController::class), 'consultaLucro', $this->bizId, 'invoice', ['start_date' => SBR_DIA, 'end_date' => SBR_DIA])
        ->get()->pluck('transaction_id')->map(fn ($i) => (int) $i)->all();
    expect($porVenda)->toContain($comum)
        ->and($porVenda)->not->toContain($bonif);

    expect(round((float) $vendasDepois['total_sell_inc_tax'] - (float) $vendasAntes['total_sell_inc_tax'], 2))->toBe(100.0)
        ->and(round((float) $vendasDepois['invoice_due'] - (float) $vendasAntes['invoice_due'], 2))->toBe(100.0)
        ->and(round($lucroDepois - $lucroAntes, 2))->toBe(100.0);
});

it('UC-SBON-09 [T0] a bonificação não entra no saldo em aberto do cliente', function () {
    $controller = app(ContactController::class);
    $kpis = fn () => sbrPrivado($controller, 'buildClienteIndexKpis', $this->bizId, 'customer');
    $antes = $kpis();

    $nome = $this->prefixo.'Supermercado';
    $cliente = sbrCliente($this->bizId, $this->user->id, $nome);
    sbrVenda($this, $this->bizId, $cliente, 'COMUM', null);
    sbrVenda($this, $this->bizId, $cliente, 'BONIF', 'bonificacao');

    // Só bonificação: o cliente não fica "com venda em aberto" por causa dela.
    $soBonif = sbrCliente($this->bizId, $this->user->id, $this->prefixo.'SoBonif');
    sbrVenda($this, $this->bizId, $soBonif, 'BONIF2', 'bonificacao');

    $depois = $kpis();
    expect(round((float) $depois['valor_total_aberto'] - (float) $antes['valor_total_aberto'], 2))->toBe(100.0)
        ->and((int) $depois['com_os_aberta'] - (int) $antes['com_os_aberta'])->toBe(1)
        ->and((int) $depois['com_atraso'] - (int) $antes['com_atraso'])->toBe(1);

    request()->merge(['q' => $this->prefixo]);
    $linhas = collect(sbrPrivado($controller, 'buildClienteIndexCustomers', $this->bizId, 'customer')['data'])->keyBy('id');
    expect((float) $linhas[$cliente]['valor_aberto'])->toBe(100.0)
        ->and((float) $linhas[$soBonif]['valor_aberto'])->toBe(0.0);

    // Outra empresa: a bonificação de lá não muda o saldo daqui.
    if ($this->outroBizId !== null) {
        $deLa = sbrCliente($this->outroBizId, $this->user->id, $this->prefixo.'OutraEmpresa');
        sbrVenda($this, $this->outroBizId, $deLa, 'OUTRA', null);
        expect(round((float) $kpis()['valor_total_aberto'] - (float) $depois['valor_total_aberto'], 2))->toBe(0.0);
    }
});
