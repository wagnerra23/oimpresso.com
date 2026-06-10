<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Contract\AutosaveContractRunner;

/**
 * Contract test do pré-fill de produtos no Sells/Edit (SellController@edit, branch Inertia).
 *
 * Origem 2026-06-10 — incidente prod ROTA LIVRE (biz=4): Guilherme reportou
 * "venda em branco" ao editar. Causa raiz: Edit.tsx lia sl.product?.name /
 * sl.quantity / sl.unit_price_inc_tax, mas o backend serializa o resultado
 * CRU do join SQL com aliases FLAT (product_name, quantity_ordered,
 * sell_price_inc_tax). Toda linha caía no fallback ('—', qtd 1, R$ 0,00).
 *
 * Este test congela o CONTRATO do payload deferred `form.sellDetails`:
 *   1. aliases flat obrigatórios por linha (o que o Edit.tsx consome);
 *   2. sellDetails é LISTA sequencial (array_is_list) — o foreach do edit()
 *      faz unset() de linhas filhas (combo/modificador) e sem ->values()
 *      o gap de keys serializa OBJETO JSON → .map() quebra a tela inteira;
 *   3. linhas filhas (parent_sell_line_id) NÃO aparecem no payload.
 *
 * Multi-tenant Tier 0 (ADR 0093): setupSellsContext autentica user do
 * business + session user.business_id; edit() faz where business_id.
 *
 * @see app/Http/Controllers/SellController.php::edit (branch X-Inertia)
 * @see resources/js/Pages/Sells/Edit.tsx (interface BackendSellDetail)
 * @see ADR 0205 (contract tests canon) · ADR 0093 (Tier 0)
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0101)');
    }
    foreach (['transactions', 'transaction_sell_lines', 'products', 'variations', 'product_variations'] as $table) {
        if (! Schema::hasTable($table)) {
            $this->markTestSkipped("Schema UltimatePOS ausente ({$table}) — rode migrations base.");
        }
    }

    $ctx = AutosaveContractRunner::setupSellsContext($this);
    $this->business = $ctx['business'];
    $this->user = $ctx['user'];
    $this->transactionId = $ctx['transactionId'];

    // Permission explícita — edit() exige direct_sell.update (sem fallback is_admin).
    \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'direct_sell.update', 'guard_name' => 'web']);
    $this->user->givePermissionTo('direct_sell.update');

    $now = now();
    $stamp = substr((string) microtime(true), -5);

    // Unit reusa a do business (seeder UPOS); cria se env vazio.
    $unitId = DB::table('units')->where('business_id', $this->business->id)->value('id')
        ?? DB::table('units')->insertGetId([
            'business_id' => $this->business->id,
            'actual_name' => 'Unidade CT',
            'short_name' => 'Un',
            'allow_decimal' => 1,
            'created_by' => $this->user->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

    $productId = DB::table('products')->insertGetId([
        'business_id' => $this->business->id,
        'name' => 'Produto Contrato Prefill',
        'type' => 'single',
        'unit_id' => $unitId,
        'enable_stock' => 0,
        'sku' => 'CT-PREFILL-' . $stamp,
        'barcode_type' => 'C128',
        'created_by' => $this->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $productVariationId = DB::table('product_variations')->insertGetId([
        'product_id' => $productId,
        'name' => 'DUMMY',
        'is_dummy' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $variationId = DB::table('variations')->insertGetId([
        'product_id' => $productId,
        'product_variation_id' => $productVariationId,
        'name' => 'DUMMY',
        'sub_sku' => 'CT-PREFILL-' . $stamp,
        'default_purchase_price' => 0,
        'dpp_inc_tax' => 0,
        'profit_percent' => 0,
        'default_sell_price' => 100,
        'sell_price_inc_tax' => 110,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $lineBase = [
        'transaction_id' => $this->transactionId,
        'product_id' => $productId,
        'variation_id' => $variationId,
        'unit_price_before_discount' => 110,
        'unit_price' => 110,
        'unit_price_inc_tax' => 110,
        'item_tax' => 0,
        'created_at' => $now,
        'updated_at' => $now,
    ];

    // Linha 0 — normal, com desconto percentual per-line.
    $this->parentLineId = DB::table('transaction_sell_lines')->insertGetId($lineBase + [
        'quantity' => 2.5,
        'line_discount_type' => 'percentage',
        'line_discount_amount' => 5,
    ]);

    // Linha 1 — FILHA (modificador): o edit() faz unset() dela. Sem ->values(),
    // o gap de keys [0,2] viraria objeto JSON e quebraria o .map() do front.
    DB::table('transaction_sell_lines')->insert($lineBase + [
        'quantity' => 1,
        'parent_sell_line_id' => $this->parentLineId,
        'children_type' => 'modifier',
    ]);

    // Linha 2 — normal, sem desconto.
    DB::table('transaction_sell_lines')->insert($lineBase + [
        'quantity' => 1,
    ]);
});

it('Sells/Edit — payload deferred form.sellDetails mantém contrato flat que o Edit.tsx consome', function () {
    $response = $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => '1',
        'X-Inertia-Partial-Component' => 'Sells/Edit',
        'X-Inertia-Partial-Data' => 'form',
    ])->get("/sells/{$this->transactionId}/edit");

    $response->assertStatus(200);
    $page = json_decode($response->getContent(), true);

    expect($page['props'])->toHaveKey('form');
    $form = $page['props']['form'];
    expect($form)->toHaveKey('sellDetails');
    $details = $form['sellDetails'];

    // 1) LISTA sequencial — regressão do ->values() (unset de linha filha) quebra aqui.
    expect(is_array($details))->toBeTrue('sellDetails deve ser array (veio ' . gettype($details) . ')');
    expect(array_is_list($details))->toBeTrue(
        'sellDetails deve ser LISTA sequencial — unset() de linha filha sem ->values() serializa objeto JSON e o .map() do Edit.tsx crasha a tela (keys: ' . implode(',', array_keys($details)) . ')'
    );

    // 2) Linha filha (modificador) excluída — só as 2 linhas normais.
    expect($details)->toHaveCount(2);

    // 3) Aliases FLAT que o Edit.tsx mapeia (bug "venda em branco" = drift aqui).
    $row = $details[0];
    foreach ([
        'transaction_sell_lines_id', 'product_id', 'variation_id', 'product_name',
        'sub_sku', 'quantity_ordered', 'sell_price_inc_tax', 'default_sell_price',
        'line_discount_type', 'line_discount_amount',
    ] as $key) {
        expect(array_key_exists($key, $row))->toBeTrue(
            "alias flat '{$key}' sumiu do payload — Edit.tsx pré-fill volta a renderizar venda em branco"
        );
    }

    // 4) Valores reais — não fallback.
    expect($row['product_name'])->toBe('Produto Contrato Prefill');
    expect((float) $row['quantity_ordered'])->toBe(2.5);
    expect((float) $row['sell_price_inc_tax'])->toBe(110.0);
    expect($row['line_discount_type'])->toBe('percentage');
    expect((float) $row['line_discount_amount'])->toBe(5.0);
    expect((int) $row['transaction_sell_lines_id'])->toBe($this->parentLineId);
});
