<?php

declare(strict_types=1);

use App\Jobs\ImportarVendasJob;
use App\Services\Sells\ImportSalesService;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Importação de vendas por planilha (`/import-sales`) — thread 05 do playbook de Vendas.
 * Casos: prototipo-ui/cowork/Wagner/cowork-inbox/venda-menu/Importacao.casos.md (UC-IMPV-*).
 *
 * REGRA MESTRE (valor/estoque) — dupla prova:
 *  (a) a mesma planilha é importada pelo REQUEST (abaixo do limite) e pela FILA
 *      (ImportarVendasJob), e os dois caminhos têm de dar o mesmo total e a mesma baixa;
 *  (b) os dois são comparados com a conta feita à mão abaixo:
 *        venda A = 2 × 50 + 1 × 30 = 130,00 · venda B = 3 × 50 = 150,00 · lote = 280,00
 *        estoque = 10 − 2 − 1 − 3 = 4
 *
 * Tenant 98 (ADR 0358). Nunca biz=4.
 */
uses(DatabaseTransactions::class);

/** CSV com 3 linhas de dados (2 vendas) em public/uploads/temp, onde a prévia grava. */
function impvPlanilha(string $sku, string $sufixo, ?string $skuLinha3 = null): string
{
    $nome = 'impv_'.$sufixo.'_'.bin2hex(random_bytes(3)).'.csv';
    $dir = public_path('uploads/temp');
    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $linhas = [
        'Fatura,Cliente,Telefone,SKU,Quantidade,Preco',
        "IMPV-A-{$sufixo},Cliente A,559999{$sufixo}01,{$sku},2,50",
        "IMPV-A-{$sufixo},Cliente A,559999{$sufixo}01,".($skuLinha3 ?? $sku).',1,30',
        "IMPV-B-{$sufixo},Cliente B,559999{$sufixo}02,{$sku},3,50",
    ];
    file_put_contents($dir.'/'.$nome, implode("\n", $linhas)."\n");

    return $nome;
}

/** Mapeamento coluna → campo, como a prévia envia. */
function impvCampos(): array
{
    return [0 => 'invoice_no', 1 => 'customer_name', 2 => 'customer_phone_number', 3 => 'sku', 4 => 'quantity', 5 => 'unit_price'];
}

/** Produto com 10 em estoque + lastro de compra (o mapPurchaseSell exige purchase_lines). */
function impvProduto(object $test): object
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

/** Vendas gravadas para as faturas do sufixo, indexadas por invoice_no. */
function impvVendas(int $bizId, string $sufixo): array
{
    return DB::table('transactions')
        ->where('business_id', $bizId)
        ->where('type', 'sell')
        ->where('invoice_no', 'like', "IMPV-%-{$sufixo}")
        ->get()
        ->keyBy('invoice_no')
        ->all();
}

function impvPost(object $test, string $arquivo, array $extra = []): \Illuminate\Testing\TestResponse
{
    return $test->post('/import-sales', array_merge([
        'file_name' => $arquivo,
        'import_fields' => impvCampos(),
        'group_by' => 0,
        'location_id' => $test->locationId,
    ], $extra));
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
    // `business` na sessão é o MODELO, como o SetSessionData grava em produção.
    session([
        'user.business_id' => $this->bizId,
        'user.id' => $this->user->id,
        'business' => \App\Business::findOrFail($this->bizId),
    ]);

    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('sell.create', 'web');
    $this->user->givePermissionTo('sell.create');
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
});

it('UC-IMPV-01 · abaixo do limite importa na hora: 2 vendas, 130,00 + 150,00, estoque 10 → 4', function () {
    config(['sells.import.limite_sincrono' => 200]);
    Queue::fake();
    $p = impvProduto($this);
    $sufixo = (string) random_int(1000, 9999);

    $resposta = impvPost($this, impvPlanilha($p->sku, $sufixo));

    $resposta->assertRedirect('import-sales');
    expect(session('status')['success'] ?? null)->toBe(1, json_encode(session('notification')));
    Queue::assertNotPushed(ImportarVendasJob::class);

    $vendas = impvVendas($this->bizId, $sufixo);
    expect($vendas)->toHaveCount(2);
    expect((float) $vendas["IMPV-A-{$sufixo}"]->final_total)->toBe(130.0);
    expect((float) $vendas["IMPV-B-{$sufixo}"]->final_total)->toBe(150.0);
    expect($vendas["IMPV-A-{$sufixo}"]->status)->toBe('final');
    expect((int) $vendas["IMPV-A-{$sufixo}"]->import_batch)->toBe((int) $vendas["IMPV-B-{$sufixo}"]->import_batch);
    expect(EstoqueFixture::currentStock($p->produto, 0, $this->locationId))->toBe(4.0);
});

it('UC-IMPV-02 · acima do limite vai para a fila com o business e o usuário no construtor, sem gravar venda no request', function () {
    config(['sells.import.limite_sincrono' => 2]); // a planilha tem 3 linhas
    Queue::fake();
    $p = impvProduto($this);
    $sufixo = (string) random_int(1000, 9999);

    $resposta = impvPost($this, impvPlanilha($p->sku, $sufixo));

    $resposta->assertRedirect('import-sales');
    Queue::assertPushed(ImportarVendasJob::class, function (ImportarVendasJob $job) {
        return $job->businessId === $this->bizId
            && $job->userId === (int) $this->user->id
            && $job->locationId === $this->locationId
            && $job->queue === ImportarVendasJob::FILA;
    });
    expect(impvVendas($this->bizId, $sufixo))->toHaveCount(0);
    expect(EstoqueFixture::currentStock($p->produto, 0, $this->locationId))->toBe(10.0);
    expect(Cache::get(ImportarVendasJob::chaveDeEstado($this->bizId))['estado'] ?? null)->toBe('na_fila');
});

it('UC-IMPV-03 · a fila grava o mesmo valor e a mesma baixa que o request (REGRA MESTRE, 2º caminho)', function () {
    $p = impvProduto($this);
    $sufixo = (string) random_int(1000, 9999);
    $arquivo = impvPlanilha($p->sku, $sufixo);

    $job = new ImportarVendasJob(
        $this->bizId,
        (int) $this->user->id,
        $this->locationId,
        public_path('uploads/temp/'.$arquivo),
        impvCampos(),
        0,
        $arquivo,
    );
    $job->handle(app(ImportSalesService::class));

    $estado = Cache::get(ImportarVendasJob::chaveDeEstado($this->bizId));
    expect($estado['estado'] ?? null)->toBe('concluido', json_encode($estado, JSON_UNESCAPED_UNICODE));
    expect($estado['feitas'])->toBe(2);

    $vendas = impvVendas($this->bizId, $sufixo);
    expect($vendas)->toHaveCount(2);
    expect((float) $vendas["IMPV-A-{$sufixo}"]->final_total)->toBe(130.0);
    expect((float) $vendas["IMPV-B-{$sufixo}"]->final_total)->toBe(150.0);
    expect(EstoqueFixture::currentStock($p->produto, 0, $this->locationId))->toBe(4.0);
    // O handle devolve a sessão e o usuário como encontrou (worker roda vários negócios).
    expect(auth()->id())->toBe($this->user->id);
    expect(file_exists(public_path('uploads/temp/'.$arquivo)))->toBeFalse();
});

it('UC-IMPV-04 · SKU que só existe noutro negócio não é importado: erro cita a linha e nada é gravado', function () {
    $outro = EstoqueFixture::secondBusinessId();
    if ($outro === null) {
        $this->markTestSkipped('Só um business semeado — sem 2º tenant para o cruzamento.');
    }
    config(['sells.import.limite_sincrono' => 200]);
    $p = impvProduto($this);
    $alheio = EstoqueFixture::singleProduct($outro);
    $skuAlheio = (string) DB::table('variations')->where('id', $alheio->variations[0]['variation_id'])->value('sub_sku');
    $sufixo = (string) random_int(1000, 9999);

    // Linha 3 da planilha aponta para o SKU do outro negócio.
    $resposta = impvPost($this, impvPlanilha($p->sku, $sufixo, $skuAlheio));

    $resposta->assertRedirect('import-sales');
    expect(session('notification')['success'] ?? null)->toBe(0);
    expect((string) (session('notification')['msg'] ?? ''))
        ->toBe(__('lang_v1.import_sale_product_not_found', ['row' => 3, 'product_name' => null, 'sku' => $skuAlheio]));
    expect(impvVendas($this->bizId, $sufixo))->toHaveCount(0);
    expect(EstoqueFixture::currentStock($p->produto, 0, $this->locationId))->toBe(10.0);
});

it('UC-IMPV-05 · local de outro negócio é recusado antes de baixar estoque', function () {
    $outro = EstoqueFixture::secondBusinessId();
    if ($outro === null) {
        $this->markTestSkipped('Só um business semeado — sem 2º tenant para o cruzamento.');
    }
    config(['sells.import.limite_sincrono' => 200]);
    $p = impvProduto($this);
    $sufixo = (string) random_int(1000, 9999);

    $resposta = impvPost($this, impvPlanilha($p->sku, $sufixo), [
        'location_id' => EstoqueFixture::locationId($outro),
    ]);

    $resposta->assertRedirect('import-sales');
    expect(session('notification')['success'] ?? null)->toBe(0);
    expect(impvVendas($this->bizId, $sufixo))->toHaveCount(0);
    expect(EstoqueFixture::currentStock($p->produto, 0, $this->locationId))->toBe(10.0);
});

it('UC-IMPV-06 · a prévia pré-mapeia por semelhança de rótulo (R2)', function () {
    $servico = app(ImportSalesService::class);
    $rotulos = array_map(fn ($c) => $c['label'], $servico->campos($this->bizId));

    $mapa = $servico->preMapear([(string) $rotulos['quantity'], 'zzzz'], $rotulos);

    expect($mapa[0])->toBe('quantity');
    expect($mapa[1])->toBeNull();
});
