<?php

declare(strict_types=1);

use App\Services\Sells\ImportSalesService;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Reverter lote importado (`GET /revert-sale-import/{batch}`) — tudo ou nada, com retrato.
 *
 * Decisão D3 de [W], 2ª rodada (2026-10-02, textual: "opção 2 no D3"):
 * prototipo-ui/cowork/Wagner/cowork-inbox/venda-menu/playbook/_DECISOES-W-2026-10-02b.md.
 * O reverter CONTINUA APAGANDO, mas grava antes um retrato do lote no log e recusa o lote
 * inteiro se qualquer venda não puder ser apagada (o legado pulava a venda e dizia "sucesso").
 *
 * REGRA MESTRE (valor/estoque) — dupla prova, com a conta feita à mão:
 *   lote importado = venda A (2 × 50 do produto P + 1 × 30 do produto Q = 130,00)
 *                  + venda B (3 × 50 do produto P = 150,00) = 280,00
 *   estoque P: 10 → 5 (importou) → 10 (reverteu) · estoque Q: 10 → 9 → 10
 *   total das vendas finais do business: T → T + 280 → T
 *  (a) UC-IMPREV-01: lote normal volta tudo ao número exato de antes da importação;
 *  (b) UC-IMPREV-02: lote com uma venda impedida (já tem devolução) não muda NADA —
 *      linhas, estoque e total idênticos ao pós-importação — e a resposta diz qual venda.
 *
 * Tenant 98 (ADR 0358) e o 2º business semeado para o cruzamento. Nunca biz=4.
 * Os UC-IMPREV-* entram no casos.md da tela ImportSales quando o PR da tela (#8520) e este
 * estiverem no main — registrado no _saida-05 da thread.
 */
uses(DatabaseTransactions::class);

/** CSV de 3 linhas (2 vendas) em public/uploads/temp, onde a prévia grava. */
function imprevPlanilha(string $skuP, string $skuQ, string $sufixo): string
{
    $nome = 'imprev_'.$sufixo.'_'.bin2hex(random_bytes(3)).'.csv';
    $dir = public_path('uploads/temp');
    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    file_put_contents($dir.'/'.$nome, implode("\n", [
        'Fatura,Cliente,Telefone,SKU,Quantidade,Preco',
        "IMPREV-A-{$sufixo},Cliente A,558888{$sufixo}01,{$skuP},2,50",
        "IMPREV-A-{$sufixo},Cliente A,558888{$sufixo}01,{$skuQ},1,30",
        "IMPREV-B-{$sufixo},Cliente B,558888{$sufixo}02,{$skuP},3,50",
    ])."\n");

    return $nome;
}

/** Produto com 10 em estoque + lastro de compra (o mapPurchaseSell exige purchase_lines). */
function imprevProduto(object $test): object
{
    $produto = EstoqueFixture::singleProduct($test->bizId);
    EstoqueFixture::setStock($produto, 0, $test->locationId, 10);

    $compraId = (int) DB::table('transactions')->insertGetId([
        'business_id' => $test->bizId,
        'type' => 'purchase',
        'status' => 'received',
        'location_id' => $test->locationId,
        'payment_status' => 'paid',
        'transaction_date' => now()->subDay(),
        'total_before_tax' => 0,
        'final_total' => 0,
        'created_by' => $test->user->id,
        'essentials_duration' => 0,
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);
    DB::table('purchase_lines')->insert([
        'transaction_id' => $compraId,
        'product_id' => $produto->productId,
        'variation_id' => $produto->variations[0]['variation_id'],
        'quantity' => 10,
        'quantity_sold' => 0,
        'quantity_adjusted' => 0,
        'quantity_returned' => 0,
        'purchase_price' => 0,
        'purchase_price_inc_tax' => 0,
        'item_tax' => 0,
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    $sku = (string) DB::table('variations')->where('id', $produto->variations[0]['variation_id'])->value('sub_sku');

    return (object) ['produto' => $produto, 'sku' => $sku];
}

/** Importa o lote pelo request (caminho síncrono) e devolve [lote, vendas por invoice_no]. */
function imprevImportar(object $test, object $p, object $q, string $sufixo): array
{
    config(['sells.import.limite_sincrono' => 200]);
    $test->post('/import-sales', [
        'file_name' => imprevPlanilha($p->sku, $q->sku, $sufixo),
        'import_fields' => [0 => 'invoice_no', 1 => 'customer_name', 2 => 'customer_phone_number', 3 => 'sku', 4 => 'quantity', 5 => 'unit_price'],
        'group_by' => 0,
        'location_id' => $test->locationId,
    ])->assertRedirect('import-sales');
    expect(session('status')['success'] ?? null)->toBe(1, json_encode(session('notification')));

    $vendas = imprevVendas($test->bizId, $sufixo);
    expect($vendas)->toHaveCount(2);

    return [(int) $vendas["IMPREV-A-{$sufixo}"]->import_batch, $vendas];
}

/** Vendas das faturas do sufixo, por invoice_no. */
function imprevVendas(int $bizId, string $sufixo): array
{
    return DB::table('transactions')
        ->where('business_id', $bizId)
        ->where('type', 'sell')
        ->where('invoice_no', 'like', "IMPREV-%-{$sufixo}")
        ->get()
        ->keyBy('invoice_no')
        ->all();
}

/** Total das vendas finais do business — o número que o totalizador da lista de vendas soma. */
function imprevTotalVendas(int $bizId): float
{
    return round((float) DB::table('transactions')
        ->where('business_id', $bizId)
        ->where('type', 'sell')
        ->where('status', 'final')
        ->sum('final_total'), 2);
}

/** Linhas de venda das transações dadas. */
function imprevLinhas(array $ids): int
{
    return DB::table('transaction_sell_lines')->whereIn('transaction_id', $ids)->count();
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/seed ausente (sqlite :memory: ou DB vazio) — roda na lane MySQL / CT 100.');
    }

    $this->bizId = EstoqueFixture::businessId(); // tenant 98 (ADR 0358 — nunca biz=4).
    $this->locationId = EstoqueFixture::locationId($this->bizId);
    $this->user = User::where('business_id', $this->bizId)->orderBy('id')->first();
    if (! $this->user) {
        $this->markTestSkipped('Sem user no business seeded.');
    }

    $this->actingAs($this->user);
    session([
        'user.business_id' => $this->bizId,
        'user.id' => $this->user->id,
        'business' => \App\Business::findOrFail($this->bizId),
    ]);

    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('sell.create', 'web');
    Permission::findOrCreate('sell.delete', 'web');
    $this->user->givePermissionTo(['sell.create', 'sell.delete']);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
});

it('UC-IMPREV-01 · lote normal: as 2 vendas e as linhas somem, estoque P 5 → 10 e Q 9 → 10, total volta ao de antes', function () {
    $p = imprevProduto($this);
    $q = imprevProduto($this);
    $sufixo = (string) random_int(1000, 9999);
    $totalAntes = imprevTotalVendas($this->bizId);

    [$lote, $vendas] = imprevImportar($this, $p, $q, $sufixo);
    $ids = array_map(fn ($v) => (int) $v->id, array_values($vendas));

    // Estado pós-importação (conta à mão no docblock).
    expect(imprevTotalVendas($this->bizId))->toBe(round($totalAntes + 280.0, 2));
    expect(EstoqueFixture::currentStock($p->produto, 0, $this->locationId))->toBe(5.0);
    expect(EstoqueFixture::currentStock($q->produto, 0, $this->locationId))->toBe(9.0);
    expect(imprevLinhas($ids))->toBe(3);

    $this->get("/revert-sale-import/{$lote}")->assertRedirect('import-sales');

    expect(session('status')['success'] ?? null)->toBe(1, (string) (session('status')['msg'] ?? ''));
    expect(imprevVendas($this->bizId, $sufixo))->toHaveCount(0);
    expect(imprevLinhas($ids))->toBe(0);
    expect(EstoqueFixture::currentStock($p->produto, 0, $this->locationId))->toBe(10.0);
    expect(EstoqueFixture::currentStock($q->produto, 0, $this->locationId))->toBe(10.0);
    expect(imprevTotalVendas($this->bizId))->toBe($totalAntes);
});

it('UC-IMPREV-02 · lote com uma venda que já tem devolução: NADA é apagado e a resposta diz qual venda impediu', function () {
    $p = imprevProduto($this);
    $q = imprevProduto($this);
    $sufixo = (string) random_int(1000, 9999);

    [$lote, $vendas] = imprevImportar($this, $p, $q, $sufixo);
    $vendaA = $vendas["IMPREV-A-{$sufixo}"];
    $ids = array_map(fn ($v) => (int) $v->id, array_values($vendas));

    // Devolução da venda A — é a regra que faz o deleteSale() recusar (isReturnExist).
    DB::table('transactions')->insert([
        'business_id' => $this->bizId,
        'type' => 'sell_return',
        'status' => 'final',
        'location_id' => $this->locationId,
        'payment_status' => 'due',
        'return_parent_id' => $vendaA->id,
        'transaction_date' => now(),
        'total_before_tax' => 0,
        'final_total' => 0,
        'created_by' => $this->user->id,
        'essentials_duration' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $totalAntes = imprevTotalVendas($this->bizId);
    $linhasAntes = imprevLinhas($ids);

    $this->get("/revert-sale-import/{$lote}")->assertRedirect('import-sales');

    $status = session('status');
    expect($status['success'] ?? null)->toBe(0);
    expect((string) $status['msg'])->toContain("IMPREV-A-{$sufixo}");
    expect((string) $status['msg'])->toContain('nada foi apagado');
    expect($status['impedidas'])->toHaveCount(1);
    expect((int) $status['impedidas'][0]['id'])->toBe((int) $vendaA->id);

    // NADA mudou: as duas vendas, as 3 linhas, o estoque e o total são os do pós-importação.
    // A venda B (sem devolução) também ficou — é isso que o "tudo ou nada" muda no legado.
    expect(imprevVendas($this->bizId, $sufixo))->toHaveCount(2);
    expect($linhasAntes)->toBe(3);
    expect(imprevLinhas($ids))->toBe(3);
    expect(EstoqueFixture::currentStock($p->produto, 0, $this->locationId))->toBe(5.0);
    expect(EstoqueFixture::currentStock($q->produto, 0, $this->locationId))->toBe(9.0);
    expect(imprevTotalVendas($this->bizId))->toBe($totalAntes);
});

it('UC-IMPREV-03 · o retrato do lote vai para o log ANTES de apagar, sem nome nem telefone do cliente', function () {
    $p = imprevProduto($this);
    $q = imprevProduto($this);
    $sufixo = (string) random_int(1000, 9999);

    [$lote, $vendas] = imprevImportar($this, $p, $q, $sufixo);

    $retrato = null;
    $vendasNoBancoNaHoraDoLog = null;
    Event::listen(MessageLogged::class, function (MessageLogged $e) use (&$retrato, &$vendasNoBancoNaHoraDoLog, $sufixo) {
        if ($e->message === ImportSalesService::LOG_RETRATO) {
            $retrato = $e->context;
            $vendasNoBancoNaHoraDoLog = count(imprevVendas($this->bizId, $sufixo));
        }
    });

    $this->get("/revert-sale-import/{$lote}")->assertRedirect('import-sales');

    expect($retrato)->not->toBeNull();
    // As duas vendas ainda estavam no banco quando o retrato foi gravado.
    expect($vendasNoBancoNaHoraDoLog)->toBe(2);
    expect($retrato['business_id'])->toBe($this->bizId);
    expect($retrato['lote'])->toBe($lote);
    expect($retrato['total_do_lote'])->toBe(280.0);
    expect($retrato['vendas'])->toHaveCount(2);

    $porId = collect($retrato['vendas'])->keyBy('id');
    $a = $porId[(int) $vendas["IMPREV-A-{$sufixo}"]->id];
    expect($a['final_total'])->toBe(130.0);
    expect($a['contact_id'])->toBe((int) $vendas["IMPREV-A-{$sufixo}"]->contact_id);
    expect($a['linhas'])->toHaveCount(2);
    expect(collect($a['devolve_ao_estoque'])->sum('quantidade'))->toBe(3.0);
    expect($a)->toHaveKey('pagamentos');

    $json = json_encode($retrato, JSON_UNESCAPED_UNICODE);
    expect(str_contains((string) $json, 'Cliente A'))->toBeFalse();
    expect(str_contains((string) $json, "558888{$sufixo}01"))->toBeFalse();

    // E foi mesmo apagado depois.
    expect(imprevVendas($this->bizId, $sufixo))->toHaveCount(0);
});

it('UC-IMPREV-04 · reverter o lote N do business 98 não toca a venda do lote N de outro business', function () {
    $outro = EstoqueFixture::secondBusinessId();
    if ($outro === null) {
        $this->markTestSkipped('Só um business semeado — sem 2º tenant para o cruzamento.');
    }
    $p = imprevProduto($this);
    $q = imprevProduto($this);
    $sufixo = (string) random_int(1000, 9999);

    [$lote] = imprevImportar($this, $p, $q, $sufixo);

    // Venda do outro negócio com o MESMO número de lote.
    $alheia = (int) DB::table('transactions')->insertGetId([
        'business_id' => $outro,
        'type' => 'sell',
        'status' => 'final',
        'location_id' => EstoqueFixture::locationId($outro),
        'payment_status' => 'due',
        'import_batch' => $lote,
        'invoice_no' => "IMPREV-X-{$sufixo}",
        'transaction_date' => now(),
        'total_before_tax' => 77,
        'final_total' => 77,
        'created_by' => EstoqueFixture::userId($outro),
        'essentials_duration' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->get("/revert-sale-import/{$lote}")->assertRedirect('import-sales');

    expect(session('status')['success'] ?? null)->toBe(1, (string) (session('status')['msg'] ?? ''));
    expect(imprevVendas($this->bizId, $sufixo))->toHaveCount(0);
    expect(DB::table('transactions')->where('id', $alheia)->value('final_total'))->not->toBeNull();
    expect((float) DB::table('transactions')->where('id', $alheia)->value('final_total'))->toBe(77.0);
});
