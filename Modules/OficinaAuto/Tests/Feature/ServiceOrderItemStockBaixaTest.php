<?php

declare(strict_types=1);

use App\Contact;
use App\Product;
use App\VariationLocationDetails;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\ServiceOrderItem;
use Modules\OficinaAuto\Entities\Vehicle;
use Modules\OficinaAuto\Services\ServiceOrderItemService;

uses(Tests\TestCase::class);
uses(DatabaseTransactions::class);

/**
 * P0-2 — Catálogo de peças + baixa de estoque no item da OS.
 *
 * Origem: análise tela-venda × oficina 2026-06-04
 * (memory/sessions/2026-06-04-analise-tela-venda-vs-oficina.md §4 P0-2).
 *
 * ───────────────────────────────────────────────────────────────────────────
 * TESTE RED (test-first) — define o "pronto" da feature. HOJE FALHA de propósito.
 * ───────────────────────────────────────────────────────────────────────────
 *
 * Estado atual (gap):
 *   - `oficina_service_order_items.product_id` EXISTE no schema + relação `product()`,
 *     MAS o `ServiceOrderItemService::addItem` apenas grava o inteiro sem:
 *       (a) validar que o produto pertence ao business (Tier 0 ADR 0093);
 *       (b) baixar estoque (`variation_location_details.qty_available`) quando a OS
 *           é concluída.
 *   - A UI (`ServiceOrderItemFormSheet.tsx`) nem seta `product_id` — é texto livre.
 *
 * Contrato a implementar (o que faz estes testes passarem):
 *   1. Ao CONCLUIR a OS (`status → concluida`), cada item `tipo=peca` com `product_id`
 *      de produto stock-managed decrementa o estoque pela `quantidade`
 *      (via App\Utils\ProductUtil::decreaseProductQuantity, idempotente).
 *   2. `addItem` rejeita `product_id` de produto de OUTRO business (não persiste).
 *   3. Itens sem `product_id` (mão-de-obra / serviço terceiro) NÃO mexem estoque.
 *   4. Concluir a mesma OS 2× não baixa estoque em dobro (idempotência — espelha o
 *      guard do ServiceOrderObserver via transaction_id + os_ref).
 *
 * Ambiente: requer schema UltimatePOS real (MySQL). Em sqlite :memory: (CI default)
 * faz markTestSkipped gracioso — padrão do projeto (ADR 0101). Rodar no CT 100:
 *   php artisan test --filter=ServiceOrderItemStockBaixa
 *
 * @see Modules/OficinaAuto/Services/ServiceOrderItemService.php (addItem — a estender)
 * @see Modules/OficinaAuto/Observers/ServiceOrderObserver.php (gancho 'concluida')
 * @see app/Utils/ProductUtil.php::decreaseProductQuantity
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

const BIZ_STOCK_BAIXA = 1;
const PLATE_STOCK_PREFIX = 'STKBX';

/**
 * Resolve um produto stock-managed do biz piloto que tenha VLD com saldo >= $min.
 * Retorna [Product, variationId, locationId, totalQtyAntes] ou null se não houver
 * dado adequado (teste então faz skip gracioso — não inventa schema do zero).
 *
 * @return array{0:Product,1:int,2:int,3:float}|null
 */
function stockBaixa_resolveProdutoComEstoque(int $biz, float $min = 5.0): ?array
{
    $productIds = Product::withoutGlobalScopes()
        ->where('business_id', $biz)
        ->where('enable_stock', 1)
        ->pluck('id');

    if ($productIds->isEmpty()) {
        return null;
    }

    $vld = VariationLocationDetails::query()
        ->whereIn('product_id', $productIds)
        ->where('qty_available', '>=', $min)
        ->first();

    if ($vld === null) {
        return null;
    }

    $product = Product::withoutGlobalScopes()->find($vld->product_id);
    if ($product === null) {
        return null;
    }

    $total = (float) VariationLocationDetails::query()
        ->where('product_id', $product->id)
        ->sum('qty_available');

    return [$product, (int) $vld->variation_id, (int) $vld->location_id, $total];
}

function stockBaixa_criaOs(string $suffix, int $biz = BIZ_STOCK_BAIXA): ServiceOrder
{
    // contact_id vai no VEÍCULO: `ServiceOrder.$fillable` ainda não inclui contact_id
    // (gap T9 levantamento 2026-05-26 — mass-assign dropa silenciosamente). O Observer
    // resolve via fallback `$so->vehicle?->contact_id`, então ancoramos lá.
    $contactId = Contact::withoutGlobalScopes()
        ->where('business_id', $biz)
        ->value('id') ?? 1;

    $vehicle = Vehicle::withoutGlobalScopes()->create([
        'business_id'  => $biz,
        'contact_id'   => $contactId,
        'plate'        => PLATE_STOCK_PREFIX . $suffix,
        'vehicle_type' => 'caminhao',
    ]);

    return ServiceOrder::withoutGlobalScopes()->create([
        'business_id' => $biz,
        'vehicle_id'  => $vehicle->id,
        'order_type'  => 'manutencao',
        'status'      => 'aberta',
    ]);
}

beforeEach(function () {
    try {
        if (\App\Business::query()->count() === 0) {
            $this->markTestSkipped('Sem business em DB — rode com DB_CONNECTION=mysql (dev/CI integration).');
        }
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente — sqlite :memory: não tem products/variation_location_details.');
    }

    session(['user.business_id' => BIZ_STOCK_BAIXA]);
});

// ---------------------------------------------------------------------------
// 1. Baixa de estoque ao concluir OS (núcleo do P0-2) — RED hoje
// ---------------------------------------------------------------------------

it('peça da OS com product_id stock-managed → baixa estoque pela quantidade ao concluir OS', function () {
    $resolved = stockBaixa_resolveProdutoComEstoque(BIZ_STOCK_BAIXA, 5.0);
    if ($resolved === null) {
        $this->markTestSkipped('Sem produto stock-managed com VLD >= 5 no biz piloto.');
    }
    [$product, , , $totalAntes] = $resolved;

    $os = stockBaixa_criaOs('A');

    app(ServiceOrderItemService::class)->addItem(BIZ_STOCK_BAIXA, (int) $os->id, [
        'tipo'           => ServiceOrderItem::TIPO_PECA,
        'descricao'      => $product->name ?? 'Peça catálogo',
        'quantidade'     => 2,
        'valor_unitario' => 100.00,
        'product_id'     => (int) $product->id,
    ]);

    // Conclui a OS → dispara baixa de estoque (a implementar)
    $os->status = 'concluida';
    $os->save();

    $totalDepois = (float) VariationLocationDetails::query()
        ->where('product_id', $product->id)
        ->sum('qty_available');

    expect(round($totalAntes - $totalDepois, 3))->toBe(2.0);
});

// ---------------------------------------------------------------------------
// 2. Mão-de-obra (sem product_id) NÃO mexe estoque — guard anti-over-decrement
// ---------------------------------------------------------------------------

it('item mão-de-obra (product_id null) NÃO altera estoque ao concluir OS', function () {
    $resolved = stockBaixa_resolveProdutoComEstoque(BIZ_STOCK_BAIXA, 1.0);
    if ($resolved === null) {
        $this->markTestSkipped('Sem produto stock-managed pra medir baseline.');
    }
    [$product, , , $totalAntes] = $resolved;

    $os = stockBaixa_criaOs('B');

    app(ServiceOrderItemService::class)->addItem(BIZ_STOCK_BAIXA, (int) $os->id, [
        'tipo'           => ServiceOrderItem::TIPO_MAO_OBRA,
        'descricao'      => 'Troca cilindro + sangria',
        'quantidade'     => 3,
        'valor_unitario' => 120.00,
    ]);

    $os->status = 'concluida';
    $os->save();

    $totalDepois = (float) VariationLocationDetails::query()
        ->where('product_id', $product->id)
        ->sum('qty_available');

    expect(round($totalAntes - $totalDepois, 3))->toBe(0.0);
});

// ---------------------------------------------------------------------------
// 3. Tier 0 (ADR 0093) — product_id de OUTRO business é rejeitado
// ---------------------------------------------------------------------------

it('addItem rejeita product_id de produto de outro business (Tier 0)', function () {
    $foreign = Product::withoutGlobalScopes()
        ->where('business_id', '!=', BIZ_STOCK_BAIXA)
        ->first();

    if ($foreign === null) {
        $this->markTestSkipped('Sem produto de outro business pra testar cross-tenant.');
    }

    $os = stockBaixa_criaOs('C');

    try {
        app(ServiceOrderItemService::class)->addItem(BIZ_STOCK_BAIXA, (int) $os->id, [
            'tipo'           => ServiceOrderItem::TIPO_PECA,
            'descricao'      => 'Tentativa cross-tenant',
            'quantidade'     => 1,
            'valor_unitario' => 10.00,
            'product_id'     => (int) $foreign->id,
        ]);
    } catch (\InvalidArgumentException $e) {
        // Aceitável: rejeição via exceção.
    }

    // Contrato: NENHUM item desta OS pode acabar referenciando o produto cross-tenant
    // (seja por throw, seja por nullar o product_id).
    $vazou = ServiceOrderItem::withoutGlobalScopes()
        ->where('service_order_id', $os->id)
        ->where('product_id', $foreign->id)
        ->exists();

    expect($vazou)->toBeFalse();
});

// ---------------------------------------------------------------------------
// 4. Idempotência — concluir 2× não baixa estoque em dobro
// ---------------------------------------------------------------------------

it('concluir a OS duas vezes não baixa estoque em dobro (idempotência)', function () {
    $resolved = stockBaixa_resolveProdutoComEstoque(BIZ_STOCK_BAIXA, 5.0);
    if ($resolved === null) {
        $this->markTestSkipped('Sem produto stock-managed com VLD >= 5 no biz piloto.');
    }
    [$product, , , $totalAntes] = $resolved;

    $os = stockBaixa_criaOs('D');

    app(ServiceOrderItemService::class)->addItem(BIZ_STOCK_BAIXA, (int) $os->id, [
        'tipo'           => ServiceOrderItem::TIPO_PECA,
        'descricao'      => $product->name ?? 'Peça catálogo',
        'quantidade'     => 1,
        'valor_unitario' => 100.00,
        'product_id'     => (int) $product->id,
    ]);

    $os->status = 'concluida';
    $os->save();

    // Segunda "conclusão" — re-save terminal não deve re-disparar baixa.
    $os->refresh();
    $os->status = 'concluida';
    $os->save();

    $totalDepois = (float) VariationLocationDetails::query()
        ->where('product_id', $product->id)
        ->sum('qty_available');

    expect(round($totalAntes - $totalDepois, 3))->toBe(1.0);
});
