<?php
// @covers-us US-OFICINA-001

declare(strict_types=1);

// casos (G-2 rastreabilidade · ADR 0264): defende
//   UC-OED-03 (OficinaAuto/ServiceOrders/Edit) — peça baixa estoque AO CONCLUIR a OS (peça×qty)
//     ⚠️ correção 2026-07-27 (chip SDD): este comentário dizia "adicionar peça baixa estoque",
//     contradizendo o próprio contrato descrito logo abaixo (§"Contrato implementado" item 1) e o
//     que os casos de teste deste arquivo provam. Corrigido o TEXTO, não o comportamento.
//   UC-OED-06 — concluir 2× não baixa em dobro (idempotência)
//   UC-OED-07 — mão-de-obra / serviço de terceiro não mexem em estoque
//   UC-OED-08 — peça de catálogo de outro business é recusada (Tier 0)

use App\Contact;
use App\VariationLocationDetails;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\ServiceOrderItem;
use Modules\OficinaAuto\Entities\Vehicle;
use Modules\OficinaAuto\Services\ServiceOrderItemService;
use Tests\Support\WithSeededTenant;

uses(Tests\TestCase::class);
uses(DatabaseTransactions::class, WithSeededTenant::class);

/**
 * P0-2 — Catálogo de peças + baixa de estoque no item da OS.
 *
 * Origem: análise tela-venda × oficina 2026-06-04
 * (memory/sessions/2026-06-04-analise-tela-venda-vs-oficina.md §4 P0-2).
 *
 * Contrato implementado:
 *   1. Ao CONCLUIR a OS (`status → concluida`), cada item `tipo=peca` com `product_id`
 *      de produto stock-managed decrementa o estoque pela `quantidade`
 *      (caminho auditável — VariationLocationDetails::save() dispara LogsActivity).
 *   2. `addItem` rejeita `product_id` de produto de OUTRO business (não persiste).
 *   3. Itens sem `product_id` (mão-de-obra / serviço terceiro) NÃO mexem estoque.
 *   4. Concluir a mesma OS 2× não baixa estoque em dobro (idempotência via
 *      guard transaction_id do ServiceOrderObserver + dirty-check do Eloquent).
 *
 * Fixture própria (2026-11-05, lote 1.1 OficinaAuto). Antes o teste PROCURAVA um produto
 * stock-managed em biz=1 e, sem achar, chamava markTestSkipped: no run 40862660055 da
 * oficinaauto-pest os 4 casos saíram skip com 0 assertions, inclusive o UC-OED-08 (Tier 0).
 * Agora cada caso cria no tenant fictício 98 (ADR 0358) um produto com saldo conhecido
 * (10 un num local do 98) e, no UC-OED-08, um produto do 99. Tudo dentro da transação do
 * teste (DatabaseTransactions): nada sobra no banco.
 *
 * Ambiente: schema UltimatePOS real (MySQL). Lane: .github/workflows/oficinaauto-pest.yml.
 *
 * @see Modules/OficinaAuto/Services/ServiceOrderItemService.php (addItem + baixarEstoqueConclusao)
 * @see Modules/OficinaAuto/Observers/ServiceOrderObserver.php (gancho 'concluida')
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md
 */

const PLATE_STOCK_PREFIX = 'STKBX';
const SALDO_INICIAL_STOCK_BAIXA = 10.0;

/**
 * Cria no business $biz um produto stock-managed com UM saldo ($qty) num local do próprio $biz.
 * Mesmo molde da fixture de Modules/Compras/Tests/Feature/CustoMedioMovelTest.php.
 */
function stockBaixa_criaProduto(int $biz, int $userId, float $qty): int
{
    $agora = now();
    $sku = 'STKBX-' . uniqid();

    $unitId = (int) (DB::table('units')->where('business_id', $biz)->value('id')
        ?? DB::table('units')->insertGetId([
            'business_id' => $biz, 'actual_name' => 'Unidade ' . $sku, 'short_name' => 'un',
            'allow_decimal' => 0, 'created_by' => $userId, 'created_at' => $agora, 'updated_at' => $agora,
        ]));
    $productId = DB::table('products')->insertGetId([
        'business_id' => $biz, 'name' => 'Peca ' . $sku, 'type' => 'single', 'unit_id' => $unitId,
        'sku' => $sku, 'enable_stock' => 1, 'alert_quantity' => 0, 'tax_type' => 'exclusive',
        'barcode_type' => 'C128', 'created_by' => $userId, 'created_at' => $agora, 'updated_at' => $agora,
    ]);
    $grupo = DB::table('product_variations')->insertGetId([
        'product_id' => $productId, 'name' => 'DUMMY', 'is_dummy' => 1, 'created_at' => $agora, 'updated_at' => $agora,
    ]);
    $variationId = DB::table('variations')->insertGetId([
        'product_id' => $productId, 'product_variation_id' => $grupo, 'name' => 'DUMMY', 'sub_sku' => $sku . '-1',
        'default_purchase_price' => 20, 'dpp_inc_tax' => 20, 'profit_percent' => 0,
        'default_sell_price' => 30, 'sell_price_inc_tax' => 30, 'created_at' => $agora, 'updated_at' => $agora,
    ]);

    $scheme = DB::table('invoice_schemes')->where('business_id', $biz)->value('id')
        ?? DB::table('invoice_schemes')->insertGetId([
            'business_id' => $biz, 'name' => 'CI ' . $sku, 'scheme_type' => 'blank', 'created_at' => $agora, 'updated_at' => $agora,
        ]);
    $layout = DB::table('invoice_layouts')->where('business_id', $biz)->value('id')
        ?? DB::table('invoice_layouts')->insertGetId([
            'business_id' => $biz, 'name' => 'CI ' . $sku, 'created_at' => $agora, 'updated_at' => $agora,
        ]);
    $localId = DB::table('business_locations')->insertGetId([
        'business_id' => $biz, 'name' => 'Deposito ' . $sku, 'location_id' => 'SB' . substr(uniqid(), -6),
        'country' => 'BR', 'state' => 'SC', 'city' => 'Teste', 'zip_code' => '0000000',
        'invoice_scheme_id' => $scheme, 'invoice_layout_id' => $layout, 'is_active' => 1,
        'created_at' => $agora, 'updated_at' => $agora,
    ]);
    DB::table('variation_location_details')->insert([
        'product_id' => $productId, 'product_variation_id' => $grupo, 'variation_id' => $variationId,
        'location_id' => $localId, 'qty_available' => $qty, 'created_at' => $agora, 'updated_at' => $agora,
    ]);

    return (int) $productId;
}

function stockBaixa_saldo(int $productId): float
{
    return (float) VariationLocationDetails::query()->where('product_id', $productId)->sum('qty_available');
}

function stockBaixa_criaOs(int $biz, string $suffix): ServiceOrder
{
    // contact_id ancorado no VEÍCULO de propósito: este teste exercita o FALLBACK
    // do Observer `$so->vehicle?->contact_id` (OS sem contact_id próprio). Desde o
    // sweep ADR 0265 (2026-06-09) `ServiceOrder.$fillable` JÁ inclui contact_id —
    // mas aqui mantemos a âncora no veículo pra cobrir o caminho de resolução indireta.
    // SUPERADMIN: fixture de teste — lê/cria com business_id explícito do tenant 98.
    $contactId = Contact::withoutGlobalScopes()->where('business_id', $biz)->value('id');
    expect($contactId)->not->toBeNull('o seed da lane (pest-mysql-setup) cria 1 contato no tenant 98');

    // SUPERADMIN: fixture de teste com business_id explícito.
    $vehicle = Vehicle::withoutGlobalScopes()->create([
        'business_id'  => $biz,
        'contact_id'   => $contactId,
        'plate'        => PLATE_STOCK_PREFIX . $suffix . substr(uniqid(), -2),
        'vehicle_type' => 'caminhao',
    ]);

    // SUPERADMIN: fixture de teste com business_id explícito.
    return ServiceOrder::withoutGlobalScopes()->create([
        'business_id' => $biz,
        'vehicle_id'  => $vehicle->id,
        'order_type'  => 'manutencao',
        'status'      => 'aberta',
    ]);
}

beforeEach(function () {
    if (! Schema::hasTable('variation_location_details') || ! Schema::hasTable('service_orders')) {
        $this->markTestSkipped('Schema UltimatePOS/OficinaAuto ausente — rode no MySQL (lane oficinaauto-pest).');
    }

    $this->biz = (int) $this->seededTenant()->id;
    $this->outra = (int) $this->seededSupportClientTenant()->id;
    expect($this->biz)->not->toBe($this->outra);

    $this->user = \App\User::factory()->create(['business_id' => $this->biz]);
    session(['user.business_id' => $this->biz]);
});

// ---------------------------------------------------------------------------
// 1. Baixa de estoque ao concluir OS (núcleo do P0-2)
// ---------------------------------------------------------------------------

it('UC-OED-03 · peça da OS com product_id stock-managed → baixa estoque pela quantidade ao concluir OS', function () {
    $productId = stockBaixa_criaProduto($this->biz, $this->user->id, SALDO_INICIAL_STOCK_BAIXA);
    expect(stockBaixa_saldo($productId))->toBe(SALDO_INICIAL_STOCK_BAIXA);

    $os = stockBaixa_criaOs($this->biz, 'A');

    app(ServiceOrderItemService::class)->addItem($this->biz, (int) $os->id, [
        'tipo'           => ServiceOrderItem::TIPO_PECA,
        'descricao'      => 'Peça catálogo',
        'quantidade'     => 2,
        'valor_unitario' => 100.00,
        'product_id'     => $productId,
    ]);

    // Conclui a OS → dispara baixa de estoque.
    $os->status = 'concluida';
    $os->save();

    // 10 − 2 = 8
    expect(stockBaixa_saldo($productId))->toBe(8.0);
});

// ---------------------------------------------------------------------------
// 2. Mão-de-obra (sem product_id) NÃO mexe estoque — guard anti-over-decrement
// ---------------------------------------------------------------------------

it('UC-OED-07 · item mão-de-obra (product_id null) NÃO altera estoque ao concluir OS', function () {
    $productId = stockBaixa_criaProduto($this->biz, $this->user->id, SALDO_INICIAL_STOCK_BAIXA);

    $os = stockBaixa_criaOs($this->biz, 'B');

    app(ServiceOrderItemService::class)->addItem($this->biz, (int) $os->id, [
        'tipo'           => ServiceOrderItem::TIPO_MAO_OBRA,
        'descricao'      => 'Troca cilindro + sangria',
        'quantidade'     => 3,
        'valor_unitario' => 120.00,
    ]);

    $os->status = 'concluida';
    $os->save();

    // A OS concluiu de fato (faturou): sem isso o "0 baixado" seria vácuo.
    expect($os->fresh()->transaction_id)->not->toBeNull();
    expect(stockBaixa_saldo($productId))->toBe(SALDO_INICIAL_STOCK_BAIXA);
});

// ---------------------------------------------------------------------------
// 3. Tier 0 (ADR 0093) — product_id de OUTRO business é rejeitado
// ---------------------------------------------------------------------------

it('UC-OED-08 · addItem rejeita product_id de produto de outro business (Tier 0)', function () {
    $alheio = stockBaixa_criaProduto($this->outra, $this->user->id, SALDO_INICIAL_STOCK_BAIXA);
    $proprio = stockBaixa_criaProduto($this->biz, $this->user->id, SALDO_INICIAL_STOCK_BAIXA);
    expect((int) DB::table('products')->where('id', $alheio)->value('business_id'))->toBe($this->outra);

    $os = stockBaixa_criaOs($this->biz, 'C');
    $service = app(ServiceOrderItemService::class);

    expect(fn () => $service->addItem($this->biz, (int) $os->id, [
        'tipo'           => ServiceOrderItem::TIPO_PECA,
        'descricao'      => 'Tentativa cross-tenant',
        'quantidade'     => 1,
        'valor_unitario' => 10.00,
        'product_id'     => $alheio,
    ]))->toThrow(InvalidArgumentException::class);

    // Controle positivo: o MESMO caminho aceita o produto do próprio tenant — prova que a
    // recusa acima é pelo business do produto, não por outra falha qualquer.
    $service->addItem($this->biz, (int) $os->id, [
        'tipo'           => ServiceOrderItem::TIPO_PECA,
        'descricao'      => 'Peça do próprio tenant',
        'quantidade'     => 1,
        'valor_unitario' => 10.00,
        'product_id'     => $proprio,
    ]);

    $itens = ServiceOrderItem::withoutGlobalScopes() // SUPERADMIN: leitura de verificação do teste
        ->where('service_order_id', $os->id)
        ->pluck('product_id')
        ->map(fn ($id) => (int) $id)
        ->all();

    expect($itens)->toBe([$proprio]);

    // E concluir a OS não mexe no estoque do 99.
    $os->status = 'concluida';
    $os->save();
    expect(stockBaixa_saldo($alheio))->toBe(SALDO_INICIAL_STOCK_BAIXA);
});

// ---------------------------------------------------------------------------
// 4. Idempotência — concluir 2× não baixa estoque em dobro
// ---------------------------------------------------------------------------

it('UC-OED-06 · concluir a OS duas vezes não baixa estoque em dobro (idempotência)', function () {
    $productId = stockBaixa_criaProduto($this->biz, $this->user->id, SALDO_INICIAL_STOCK_BAIXA);

    $os = stockBaixa_criaOs($this->biz, 'D');

    app(ServiceOrderItemService::class)->addItem($this->biz, (int) $os->id, [
        'tipo'           => ServiceOrderItem::TIPO_PECA,
        'descricao'      => 'Peça catálogo',
        'quantidade'     => 1,
        'valor_unitario' => 100.00,
        'product_id'     => $productId,
    ]);

    $os->status = 'concluida';
    $os->save();
    expect(stockBaixa_saldo($productId))->toBe(9.0);

    // Segunda "conclusão" — re-save terminal não deve re-disparar baixa.
    $os->refresh();
    $os->status = 'concluida';
    $os->save();

    // 10 − 1 = 9, não 8
    expect(stockBaixa_saldo($productId))->toBe(9.0);
});
