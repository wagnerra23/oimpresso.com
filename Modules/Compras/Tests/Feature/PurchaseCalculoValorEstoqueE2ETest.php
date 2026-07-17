<?php

declare(strict_types=1);

use App\PurchaseLine;
use App\Transaction;
use App\Variation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Tests\Support\EstoqueFixture;

/**
 * G-03 (CAPTERRA-FICHA Compras §6, cap C04) — Teste E2E de cálculo de VALOR + ESTOQUE
 * no fluxo REAL de compra (POST /purchases → PurchaseController::store →
 * ProductUtil::createOrUpdatePurchaseLines + updateProductQuantity).
 *
 * POR QUE EXISTE
 * ==============
 * A FICHA (2026-07-03) achou que NENHUM teste prova que uma compra persiste
 * custo/total/estoque corretos. Os "hardening tests" (GapsHardeningTest /
 * GapsP1HardeningTest) e o PurchaseGradeMatrixTest (parte store()) são
 * `file_get_contents` + `str_contains` no SOURCE — tautológicos: passam mesmo se
 * a conta estiver ERRADA. É o MESMO anti-padrão catalogado em
 * `memory/proibicoes.md §ideias-descartadas (2026-06-05)` que mordeu o Sells
 * (incidente `num_uf` R$ inflado ×100k). Entrada de compra É write de estoque +
 * write de valor → cai na REGRA-MESTRE Tier 0 valor/estoque (proibicoes.md).
 *
 * O QUE ESTE TESTE ASSERTA (com NÚMEROS CONCRETOS, ancorado em CONTRATO)
 * =====================================================================
 * Submete UMA compra real de produto `variable` com grade tam×cor (2×2) + frete +
 * desconto% + imposto via POST /purchases e prova que:
 *   (a) transactions.{final_total,total_before_tax,tax_amount,discount_amount,
 *       shipping_charges} persistiram os valores CERTOS — por DOIS caminhos
 *       independentes (regra-mestre §1): header persistido vs. reconstrução à mão
 *       a partir das linhas + álgebra do total. Um bug `num_uf` ×100 quebra ambos.
 *   (b) cada purchase_line tem quantity × purchase_price certo POR variation_id.
 *   (c) variation_location_details.qty_available incrementou EXATAMENTE a qty
 *       comprada por variação/local (delta a partir de saldo 0 conhecido).
 * Cobre também o modo grade (US-COM-005): GET /purchases/grade-matrix mapeia cada
 * célula tam×cor → variation_id, e a compra é montada A PARTIR desse mapa.
 *
 * ANCORAGEM EM CONTRATO (não na implementação — proibicoes.md §5)
 * ==============================================================
 *   - Os valores esperados são computados À MÃO no teste (subtotal = Σ qty×custo;
 *     final = subtotal − desconto% + frete + imposto), NÃO extraídos do que o
 *     código faz. Se o `store()` corromper qualquer campo, a asserção quebra.
 *   - SPEC US-COM-005 (grade tam×cor: "cada célula = 1 SKU filho = variation_id").
 *   - Regra-mestre valor/estoque (dupla confirmação por 2 caminhos).
 *   - ADR 0093 (multi-tenant Tier 0) · ADR 0101 (tests biz=1, NUNCA biz=4 Larissa).
 *
 * EXECUÇÃO (proibicoes.md — testes só no CT 100, MySQL real biz=1 dogfood)
 * =======================================================================
 *   tailscale ssh root@ct100-mcp \
 *     "docker exec -e DB_CONNECTION=mysql oimpresso-staging \
 *        php artisan test --filter=PurchaseCalculoValorEstoque"
 *
 *   Skip-graceful em sqlite/sem-schema/sem-business (padrão EstoqueFixture): a
 *   tabela `transactions` polimórfica + triggers UPos não vivem em sqlite :memory:.
 *   O gate de verdade é a lane MySQL (CT 100). "Skipa e mente" é combatido: se o
 *   schema existe, NÃO skipa — falha de verdade se a conta ou o estoque drifar.
 *
 * @see app/Http/Controllers/PurchaseController.php::store (fluxo REAL)
 * @see app/Utils/ProductUtil.php::createOrUpdatePurchaseLines + updateProductQuantity
 * @see memory/requisitos/Compras/CAPTERRA-FICHA.md (G-03 / C04)
 * @see memory/requisitos/Compras/SPEC.md (US-COM-005, US-COM-011)
 *
 * @covers-us US-COM-011
 */

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * biz=1 dogfood (ADR 0101 — NUNCA biz=4 Larissa). Grade tam×cor 2×2 canônica.
 * Config das células por LABEL (o mapa célula→variation_id vem do endpoint real).
 */
const G03_BIZ = 1;

/**
 * Config de cada célula da grade: label 2D → (qty inteira, custo unitário string
 * pt-BR sem separador de milhar). Custos com 2 casas + ponto pra exercitar
 * EXATAMENTE o vetor do incidente Sells: `num_uf("25.00")` DEVE dar 25.00, não 2500.
 */
function g03CellConfig(): array
{
    return [
        'P__Preto'  => ['qty' => 10, 'cost' => '25.00'],
        'M__Preto'  => ['qty' => 5,  'cost' => '25.00'],
        'P__Branco' => ['qty' => 8,  'cost' => '30.50'],
        'M__Branco' => ['qty' => 3,  'cost' => '30.50'],
    ];
}

/** Compara floats de coluna decimal(22,4) com tolerância de centavo. */
function g03Close(float $got, float $expected, string $msg): void
{
    expect(abs($got - $expected))->toBeLessThan(
        0.01,
        $msg . " (esperado {$expected}, obtido {$got})"
    );
}

beforeEach(function () {
    // Skip-graceful: sqlite/sem-schema/sem-business → o gate real é MySQL/CT 100.
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped(
            'Schema UltimatePOS (MySQL) + business semeado ausente — rode no CT 100 '
            . '(oimpresso-staging, DB_CONNECTION=mysql). sqlite :memory: não cobre a '
            . 'tabela transactions polimórfica.'
        );
    }

    // biz=1 precisa existir (dogfood). Não inventamos business aqui — usamos o
    // semeado, com currency/date_format reais (SetSessionData popula a sessão).
    $biz = DB::table('business')->where('id', G03_BIZ)->first();
    if (! $biz) {
        $this->markTestSkipped('business_id=1 não semeado neste ambiente (ADR 0101).');
    }
    $this->biz = $biz;

    // Usuário biz=1 com acesso total via role Admin#{biz} — que é o mecanismo
    // canônico do UPos: `AuthServiceProvider::boot` tem `Gate::before` que retorna
    // true pra quem tem `Admin#{business_id}` (concede purchase.create + tudo). É o
    // ator realista de uma compra (admin de compras); este teste mede CÁLCULO, não o
    // gate de permissão. roles.business_id é NOT NULL + FK → passar biz no create.
    $role = Role::firstOrCreate(
        ['name' => 'Admin#' . G03_BIZ, 'guard_name' => 'web'],
        ['business_id' => G03_BIZ]
    );

    // user_type='user' + allow_login=1 são obrigatórios: o middleware CheckUserLogin
    // (grupo admin UPos) aborta 403 sem eles. Admin#{biz} concede as permissions.
    $this->user = App\User::factory()->create([
        'business_id' => G03_BIZ,
        'username' => 'g03_calc_' . uniqid(),
        'user_type' => 'user',
        'allow_login' => 1,
    ]);
    $this->user->assignRole($role);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    // Local: FKs invoice_scheme/layout resolvidos pela fixture canônica.
    $this->locationId = EstoqueFixture::locationId(G03_BIZ, '-G03');

    // Fornecedor (contact_id validado obrigatório no store()).
    $this->contactId = DB::table('contacts')->insertGetId([
        'business_id' => G03_BIZ,
        'type' => 'supplier',
        'name' => 'Fornecedor G03 Teste',
        'contact_id' => 'CT-G03-' . uniqid(),
        'created_by' => $this->user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // ── Produto `variable` com grade tam×cor 2×2 (4 variações reais) ──────────
    // Espelha o helper canônico do Vestuário (product + product_variations parent
    // + variations filhas com sub_sku). enable_stock=1 pra movimentar estoque.
    $unitId = EstoqueFixture::unitId(G03_BIZ);
    $sku = 'G03-' . strtoupper(bin2hex(random_bytes(4)));

    $this->productId = DB::table('products')->insertGetId([
        'business_id' => G03_BIZ,
        'name' => 'Camiseta Grade G03 ' . $sku,
        'type' => 'variable',
        'unit_id' => $unitId,
        'sku' => $sku,
        'enable_stock' => 1,
        'alert_quantity' => 0,
        'tax_type' => 'exclusive',
        'barcode_type' => 'C128',
        'created_by' => $this->user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $this->unitId = $unitId;

    $variationGroupId = DB::table('product_variations')->insertGetId([
        'product_id' => $this->productId,
        'name' => 'Tamanho-Cor',
        'is_dummy' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Nomes compostos "Tam/Cor" → GradeLayoutBuilder detecta modo 2D.
    $this->variationIdByName = [];
    foreach (['P/Preto', 'M/Preto', 'P/Branco', 'M/Branco'] as $nome) {
        $vid = DB::table('variations')->insertGetId([
            'product_id' => $this->productId,
            'product_variation_id' => $variationGroupId,
            'name' => $nome,
            'sub_sku' => $sku . '-' . str_replace('/', '', $nome),
            'default_purchase_price' => 25.00,
            'dpp_inc_tax' => 25.00,
            'profit_percent' => 0,
            'default_sell_price' => 50.00,
            'sell_price_inc_tax' => 50.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->variationIdByName[$nome] = $vid;
    }

    // transaction_date no formato EXATO que uf_date($date, true) espera (date_format
    // do business + sufixo de hora conforme time_format). Robusto à config do biz.
    $dateFormat = (string) ($biz->date_format ?: 'm/d/Y');
    $timeFormat = (int) ($biz->time_format ?? 24);
    $fmt = $dateFormat . ($timeFormat === 12 ? ' h:i A' : ' H:i');
    $this->transactionDate = now()->format($fmt);
});

// ─── US-COM-005: o endpoint de grade mapeia célula tam×cor → variation_id ─────

it('GET /purchases/grade-matrix devolve grade 2D com cada célula → variation_id (US-COM-005)', function () {
    $resp = $this->actingAs($this->user)
        ->getJson('/purchases/grade-matrix?product_id=' . $this->productId);

    $resp->assertStatus(200);
    $json = $resp->json();

    expect($json['mode'])->toBe('2d', 'nomes "Tam/Cor" deveriam montar grade 2D');
    expect((float) $json['unit_cost'])->toBeGreaterThan(0.0);

    // cellVariationMap: cada célula 2D aponta pro variation_id REAL da variação.
    $map = $json['cellVariationMap'];
    expect($map)->toHaveCount(4);
    expect((int) $map['P__Preto'])->toBe((int) $this->variationIdByName['P/Preto']);
    expect((int) $map['M__Preto'])->toBe((int) $this->variationIdByName['M/Preto']);
    expect((int) $map['P__Branco'])->toBe((int) $this->variationIdByName['P/Branco']);
    expect((int) $map['M__Branco'])->toBe((int) $this->variationIdByName['M/Branco']);
});

// ─── G-03: E2E cálculo de VALOR + ESTOQUE (regra-mestre Tier 0) ───────────────

it('POST /purchases (grade+frete+desconto%+imposto) persiste VALOR, LINHAS e ESTOQUE corretos', function () {
    // Isola o side-effect fora de escopo (Observer Financeiro / notificações). O
    // incremento de estoque + a persistência de valor acontecem ANTES do dispatch,
    // dentro do createOrUpdatePurchaseLines — o evento não é o que está sob teste.
    Event::fake([App\Events\PurchaseCreatedOrModified::class]);

    $cells = g03CellConfig();

    // 1) Descobre o mapa célula→variation_id pelo endpoint REAL (US-COM-005).
    $grade = $this->actingAs($this->user)
        ->getJson('/purchases/grade-matrix?product_id=' . $this->productId)
        ->assertStatus(200)
        ->json();
    $cellVariationMap = $grade['cellVariationMap'];

    // 2) Cada célula da grade vira 1 linha de compra (variation_id + qty + custo).
    //    CAMINHO A do cálculo (contrato, à mão): subtotal = Σ qty × custo.
    $purchases = [];
    $subtotalEsperado = 0.0;
    $estoqueEsperadoPorVar = [];
    $custoEsperadoPorVar = [];
    $qtyEsperadaPorVar = [];

    foreach ($cells as $label => $cfg) {
        $variationId = $cellVariationMap[$label];
        $qty = $cfg['qty'];
        $custo = (float) $cfg['cost'];

        $subtotalEsperado += $qty * $custo;
        $estoqueEsperadoPorVar[$variationId] = $qty;
        $custoEsperadoPorVar[$variationId] = $custo;
        $qtyEsperadaPorVar[$variationId] = $qty;

        $purchases[] = [
            'product_id' => $this->productId,
            'variation_id' => $variationId,
            'product_unit_id' => $this->unitId,
            'quantity' => (string) $qty,
            'pp_without_discount' => $cfg['cost'],
            'discount_percent' => '0',
            'purchase_price' => $cfg['cost'],
            'purchase_price_inc_tax' => $cfg['cost'],
            'item_tax' => '0',
            'purchase_line_tax_id' => null,
        ];
    }

    // Saldo inicial conhecido = 0 (variações recém-criadas, nenhuma VLD ainda).
    foreach (array_keys($estoqueEsperadoPorVar) as $variationId) {
        $baseline = (float) DB::table('variation_location_details')
            ->where('variation_id', $variationId)
            ->where('location_id', $this->locationId)
            ->sum('qty_available');
        expect($baseline)->toBe(0.0, "baseline de estoque da variação {$variationId} deveria ser 0");
    }

    // Header (contrato, à mão): desconto 10% + frete 50,00 + imposto 10,55.
    $descontoPercent = 10.0;
    $frete = 50.00;
    $imposto = 10.55;
    // CAMINHO A do total: subtotal − desconto% + frete + imposto.
    $finalEsperado = round($subtotalEsperado * (1 - $descontoPercent / 100) + $frete + $imposto, 2);

    // Números fixos esperados (âncora dura — se a config mudar, o teste avisa):
    expect($subtotalEsperado)->toBe(710.50);
    expect($finalEsperado)->toBe(700.00);

    $refNo = 'G03-CALC-' . uniqid();

    // 3) POST /purchases — fluxo REAL de compra.
    $resp = $this->actingAs($this->user)->post('/purchases', [
        'ref_no' => $refNo,
        'status' => 'received', // received = movimenta estoque (ProductUtil)
        'contact_id' => $this->contactId,
        'transaction_date' => $this->transactionDate,
        'location_id' => $this->locationId,
        'exchange_rate' => '1',
        'total_before_tax' => number_format($subtotalEsperado, 2, '.', ''), // "710.50"
        'discount_type' => 'percentage',
        'discount_amount' => (string) (int) $descontoPercent,               // "10"
        'tax_amount' => number_format($imposto, 2, '.', ''),                // "10.55"
        'shipping_charges' => number_format($frete, 2, '.', ''),            // "50.00"
        'final_total' => number_format($finalEsperado, 2, '.', ''),         // "700.00"
        'purchases' => $purchases,
        'payment' => [], // sem pagamento → payment_status fica 'due' (compra a prazo)
    ]);

    // 4) A compra foi criada? (diagnóstico honesto se subscription/exception barrar.)
    $tx = Transaction::where('business_id', G03_BIZ)
        ->where('type', 'purchase')
        ->where('ref_no', $refNo)
        ->first();

    expect($tx)->not->toBeNull(
        'Compra não foi criada. HTTP=' . $resp->status()
        . ' redirect=' . ($resp->headers->get('Location') ?? '—')
        . ' status_flash=' . json_encode(session('status'))
        . ' — se for expiredResponse/subscription, rode em ambiente subscrito (biz=1 dogfood).'
    );

    // ── (a) VALOR — CAMINHO B (header persistido) vs CAMINHO A (contrato) ─────
    g03Close((float) $tx->total_before_tax, 710.50, 'total_before_tax persistido');
    g03Close((float) $tx->final_total, 700.00, 'final_total persistido');
    g03Close((float) $tx->tax_amount, 10.55, 'tax_amount persistido');
    g03Close((float) $tx->shipping_charges, 50.00, 'shipping_charges persistido');
    // discount_type=percentage → discount_amount guarda o PERCENTUAL cru (não o valor).
    g03Close((float) $tx->discount_amount, 10.0, 'discount_amount (percentual) persistido');

    // Anti-incidente Sells (num_uf ×100): um final inflado seria ~70000, não 700.
    expect((float) $tx->final_total)->toBeLessThan(
        7000.0,
        'REGRA-MESTRE valor: final_total inflado — provável corrupção num_uf (separador decimal).'
    );

    // ── (b) LINHAS — qty × purchase_price certos POR variation_id ─────────────
    $linhas = PurchaseLine::where('transaction_id', $tx->id)->get();
    expect($linhas)->toHaveCount(4, 'uma purchase_line por célula da grade');

    $subtotalReconstruido = 0.0;
    foreach ($linhas as $linha) {
        $vid = (int) $linha->variation_id;
        expect(array_key_exists($vid, $qtyEsperadaPorVar))->toBeTrue("linha de variation_id inesperado {$vid}");

        g03Close((float) $linha->quantity, (float) $qtyEsperadaPorVar[$vid], "quantity da variação {$vid}");
        g03Close((float) $linha->purchase_price, $custoEsperadoPorVar[$vid], "purchase_price da variação {$vid}");
        g03Close((float) $linha->purchase_price_inc_tax, $custoEsperadoPorVar[$vid], "purchase_price_inc_tax da variação {$vid}");

        $subtotalReconstruido += (float) $linha->quantity * (float) $linha->purchase_price;
    }

    // CAMINHO B do subtotal (reconstruído das linhas) reconcilia com o header.
    g03Close($subtotalReconstruido, 710.50, 'Σ(qty×purchase_price) das linhas reconcilia com total_before_tax');
    g03Close($subtotalReconstruido, (float) $tx->total_before_tax, 'linhas × header: subtotal bate');

    // Fecho de álgebra do total (2ª confirmação independente — regra-mestre §1).
    $finalRecalc = round(
        (float) $tx->total_before_tax * (1 - (float) $tx->discount_amount / 100)
        + (float) $tx->shipping_charges + (float) $tx->tax_amount,
        2
    );
    g03Close($finalRecalc, (float) $tx->final_total, 'álgebra do total fecha (subtotal−desc%+frete+imposto = final)');

    // ── (c) ESTOQUE — qty_available += qty comprada por variação/local ────────
    $estoqueTotal = 0.0;
    foreach ($estoqueEsperadoPorVar as $variationId => $qtyEsperada) {
        $saldo = (float) DB::table('variation_location_details')
            ->where('product_id', $this->productId)
            ->where('variation_id', $variationId)
            ->where('location_id', $this->locationId)
            ->sum('qty_available');

        g03Close($saldo, (float) $qtyEsperada, "estoque da variação {$variationId} no local {$this->locationId}");
        $estoqueTotal += $saldo;
    }
    // Σ estoque = Σ qty comprada (26) — nenhuma unidade a mais/a menos.
    g03Close($estoqueTotal, 26.0, 'estoque total incrementado = soma das quantidades compradas');

    // O evento de domínio (fora de escopo) foi disparado uma vez — não engolido.
    Event::assertDispatched(App\Events\PurchaseCreatedOrModified::class);
});
