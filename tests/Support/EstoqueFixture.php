<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fixture canônica de ESTOQUE pros testes de movimentação (DOC-RAIZ-ESTOQUE §1-§3).
 *
 * POR QUE EXISTE
 * ==============
 * Os testes de estoque legados (ServiceOrderItemStockBaixaTest) RESOLVEM produto
 * stock-managed ambiente do biz piloto — e SKIPam quando não acham (o seed CI mínimo
 * de biz=1/biz=2 NÃO cria products/variations/VLD). Resultado: em CI todo teste de
 * estoque skipa → falsa cobertura ("skipa e mente", pedido Wagner 2026-07-02).
 *
 * Esta fixture MONTA o grafo mínimo UltimatePOS (unit + product single/variable +
 * product_variation + variation + business_location + VLD com saldo conhecido) sobre
 * o business SEMEADO (biz=1 dogfood — ADR 0101, NUNCA biz=4 cliente). Com ela os
 * testes ASSERTAM o delta de qty_available de verdade no MySQL, sem skip.
 *
 * ANTI-TAUTOLOGIA (proibicoes §ideias-descartadas 2026-06-05): a fixture só fabrica o
 * ESTADO INICIAL (saldo conhecido, fato independente). O saldo é semeado por INSERT
 * direto em VLD — NÃO pelo mesmo mutador sob teste — pra que a asserção do delta meça
 * o efeito do FLUXO, não a si mesma.
 *
 * SKIP-GRACIOSO (padrão ADR 0101): em sqlite :memory: (sem schema UltimatePOS) ou sem
 * business semeado, `schemaReady()` devolve false e o teste faz markTestSkipped. O gate
 * de verdade é a lane MySQL (estoque-pest.yml) + CT 100 — lá NÃO skipa.
 *
 * @see app/Utils/ProductUtil.php (updateProductQuantity / decreaseProductQuantity / adjustProductStockForInvoice)
 * @see memory/requisitos/Estoque/DOC-RAIZ-ESTOQUE.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
final class EstoqueFixture
{
    /**
     * true quando o schema real UltimatePOS (MySQL) + um business semeado existem.
     * false em sqlite :memory: ou DB vazio → o teste faz markTestSkipped gracioso.
     */
    public static function schemaReady(): bool
    {
        try {
            if (DB::connection()->getDriverName() === 'sqlite') {
                return false;
            }
            foreach (['business', 'products', 'variations', 'product_variations', 'variation_location_details', 'business_locations', 'units'] as $t) {
                if (! Schema::hasTable($t)) {
                    return false;
                }
            }

            return DB::table('business')->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Business piloto (menor id semeado = biz=1 dogfood, ADR 0101). */
    public static function businessId(): int
    {
        return (int) DB::table('business')->orderBy('id')->value('id');
    }

    /** Segundo tenant (pra INV-6 cross-tenant). null se só houver 1 business semeado. */
    public static function secondBusinessId(): ?int
    {
        $id = (int) DB::table('business')
            ->where('id', '!=', self::businessId())
            ->orderBy('id')
            ->value('id');

        return $id > 0 ? $id : null;
    }

    /** Um user_id do business (created_by NOT NULL em products/units). */
    public static function userId(int $businessId): int
    {
        $uid = (int) DB::table('users')->where('business_id', $businessId)->orderBy('id')->value('id');
        if ($uid > 0) {
            return $uid;
        }

        // Fallback: owner do business (o seed CI seta users.business_id depois).
        return (int) (DB::table('business')->where('id', $businessId)->value('owner_id') ?? 1);
    }

    /** invoice_scheme mínimo (FK NOT NULL de business_locations). Idempotente por business. */
    private static function invoiceSchemeId(int $businessId): int
    {
        $existing = DB::table('invoice_schemes')
            ->where('business_id', $businessId)
            ->where('name', 'EST-FIX')
            ->value('id');
        if ($existing) {
            return (int) $existing;
        }

        return (int) DB::table('invoice_schemes')->insertGetId([
            'business_id' => $businessId,
            'name' => 'EST-FIX',
            'scheme_type' => 'blank',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** invoice_layout mínimo (FK NOT NULL de business_locations). Idempotente por business. */
    private static function invoiceLayoutId(int $businessId): int
    {
        $existing = DB::table('invoice_layouts')
            ->where('business_id', $businessId)
            ->where('name', 'EST-FIX')
            ->value('id');
        if ($existing) {
            return (int) $existing;
        }

        return (int) DB::table('invoice_layouts')->insertGetId([
            'business_id' => $businessId,
            'name' => 'EST-FIX',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * business_location de teste (idempotente por business+suffix). Resolve os FKs
     * NOT NULL invoice_scheme_id / invoice_layout_id.
     */
    public static function locationId(int $businessId, string $suffix = ''): int
    {
        $name = 'EST-FIX-LOC' . $suffix;
        $existing = DB::table('business_locations')
            ->where('business_id', $businessId)
            ->where('name', $name)
            ->value('id');
        if ($existing) {
            return (int) $existing;
        }

        return (int) DB::table('business_locations')->insertGetId([
            'business_id' => $businessId,
            'name' => $name,
            'country' => 'BR',
            'state' => 'SC',
            'city' => 'Termas do Gravatal',
            'zip_code' => '8890000',
            'invoice_scheme_id' => self::invoiceSchemeId($businessId),
            'invoice_layout_id' => self::invoiceLayoutId($businessId),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** unit de teste (idempotente por business). */
    public static function unitId(int $businessId): int
    {
        $existing = DB::table('units')
            ->where('business_id', $businessId)
            ->where('short_name', 'EST-UN')
            ->value('id');
        if ($existing) {
            return (int) $existing;
        }

        return (int) DB::table('units')->insertGetId([
            'business_id' => $businessId,
            'actual_name' => 'Unidade Estoque Fix',
            'short_name' => 'EST-UN',
            'allow_decimal' => 1,
            'created_by' => self::userId($businessId),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Cria um produto stock-managed do tipo `single` com 1 variação DUMMY.
     *
     * @param  bool  $enableStock  false pra exercitar INV-5 (produto sem controle de saldo).
     */
    public static function singleProduct(int $businessId, bool $enableStock = true): EstoqueProduto
    {
        $unitId = self::unitId($businessId);
        $sku = 'ESTFIX-' . strtoupper(bin2hex(random_bytes(4)));

        $product = Product::forceCreate([
            'name' => 'Produto Estoque Fix ' . $sku,
            'business_id' => $businessId,
            'type' => 'single',
            'unit_id' => $unitId,
            'tax_type' => 'exclusive',
            'enable_stock' => $enableStock ? 1 : 0,
            'sku' => $sku,
            'barcode_type' => 'C128',
            'created_by' => self::userId($businessId),
        ]);

        $variations = [self::attachVariation((int) $product->id, 'DUMMY', $sku, 1)];

        return new EstoqueProduto($businessId, (int) $product->id, $unitId, $variations);
    }

    /**
     * Cria um produto `variable` com N variações reais (não-DUMMY). Útil pra provar que
     * o saldo é por (variação × local) — mexer uma variação não mexe a irmã.
     */
    public static function variableProduct(int $businessId, int $nVariations = 2): EstoqueProduto
    {
        $unitId = self::unitId($businessId);
        $sku = 'ESTFIXV-' . strtoupper(bin2hex(random_bytes(4)));

        $product = Product::forceCreate([
            'name' => 'Produto Variável Estoque Fix ' . $sku,
            'business_id' => $businessId,
            'type' => 'variable',
            'unit_id' => $unitId,
            'tax_type' => 'exclusive',
            'enable_stock' => 1,
            'sku' => $sku,
            'barcode_type' => 'C128',
            'created_by' => self::userId($businessId),
        ]);

        $variations = [];
        for ($i = 1; $i <= $nVariations; $i++) {
            $variations[] = self::attachVariation((int) $product->id, 'Var ' . $i, $sku . '-' . $i, 0);
        }

        return new EstoqueProduto($businessId, (int) $product->id, $unitId, $variations);
    }

    /**
     * Insere product_variation + variation via INSERT direto (evita ProductUtil::
     * createSingleProductVariation, que chama Media::uploadMedia(request()) — flaky em teste).
     *
     * @return array{variation_id:int,product_variation_id:int}
     */
    private static function attachVariation(int $productId, string $name, string $subSku, int $isDummy): array
    {
        $productVariationId = (int) DB::table('product_variations')->insertGetId([
            'product_id' => $productId,
            'name' => $name,
            'is_dummy' => $isDummy,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $variationId = (int) DB::table('variations')->insertGetId([
            'product_id' => $productId,
            'product_variation_id' => $productVariationId,
            'name' => $name,
            'sub_sku' => $subSku,
            'default_purchase_price' => 10,
            'dpp_inc_tax' => 10,
            'profit_percent' => 0,
            'default_sell_price' => 20,
            'sell_price_inc_tax' => 20,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['variation_id' => $variationId, 'product_variation_id' => $productVariationId];
    }

    /**
     * Define o SALDO INICIAL conhecido de uma variação num local (INSERT/UPDATE direto
     * em VLD — estado de partida independente, NÃO pelo mutador sob teste).
     */
    public static function setStock(EstoqueProduto $p, int $variationIndex, int $locationId, float $qty): void
    {
        $v = $p->variations[$variationIndex];
        DB::table('variation_location_details')->updateOrInsert(
            [
                'product_id' => $p->productId,
                'variation_id' => $v['variation_id'],
                'product_variation_id' => $v['product_variation_id'],
                'location_id' => $locationId,
            ],
            [
                'qty_available' => $qty,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    /** Saldo atual de uma variação num local (soma pra ser robusto a linhas duplicadas). */
    public static function currentStock(EstoqueProduto $p, int $variationIndex, int $locationId): float
    {
        $v = $p->variations[$variationIndex];

        return (float) DB::table('variation_location_details')
            ->where('product_id', $p->productId)
            ->where('variation_id', $v['variation_id'])
            ->where('location_id', $locationId)
            ->sum('qty_available');
    }

    /** Saldo total do produto (todas as variações × locais). */
    public static function totalStock(EstoqueProduto $p): float
    {
        return (float) DB::table('variation_location_details')
            ->where('product_id', $p->productId)
            ->sum('qty_available');
    }

    /**
     * Cria uma VENDA FINAL (parent) + 1 linha de venda pra exercitar a DEVOLUÇÃO de venda
     * (TransactionUtil::addSellReturn precisa do parent sell + sell_lines).
     *
     * @return array{transaction_id:int,sell_line_id:int}
     */
    public static function saleWithLine(EstoqueProduto $p, int $variationIndex, int $locationId, float $quantity, float $unitPrice = 20.0): array
    {
        $v = $p->variations[$variationIndex];

        $transactionId = (int) DB::table('transactions')->insertGetId([
            'business_id' => $p->businessId,
            'location_id' => $locationId,
            'type' => 'sell',
            'status' => 'final',
            'payment_status' => 'paid',
            'transaction_date' => now(),
            'total_before_tax' => $quantity * $unitPrice,
            'final_total' => $quantity * $unitPrice,
            'created_by' => self::userId($p->businessId),
            'essentials_duration' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sellLineId = (int) DB::table('transaction_sell_lines')->insertGetId([
            'transaction_id' => $transactionId,
            'product_id' => $p->productId,
            'variation_id' => $v['variation_id'],
            'quantity' => $quantity,
            'quantity_returned' => 0,
            'unit_price' => $unitPrice,
            'unit_price_inc_tax' => $unitPrice,
            'item_tax' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['transaction_id' => $transactionId, 'sell_line_id' => $sellLineId];
    }
}
