<?php

declare(strict_types=1);

use App\Utils\ProductUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\EstoqueFixture;

uses(DatabaseTransactions::class);

/**
 * CHARACTERIZATION RED — parecer C2 do funcao-scorecard de ProductUtil (2026-07-21).
 *
 * DEFEITO (PARECER, não fix): `fixVariationStockMisMatch($biz,$var,$loc,$stock)` grava
 * `$stock` CRU do request em `qty_available` SEM num_uf/validação
 * (app/Utils/ProductUtil.php:2302-2303). Único consumidor: ReportController::adjustProductStock
 * (rota GET `/reports/adjust-product-stock`, query param `stock`; view manda
 * `stock={{$row->total_stock_calculated}}`) — varredura CONTADA 2026-07-21: 1/1 consumidor.
 *
 * CONTRATO (âncora EXTERNA, não inventado):
 *  - REGRA MESTRE (memory/proibicoes.md, Tier 0): toda escrita de VALOR/ESTOQUE deve ser
 *    locale-safe (num_uf) — origem incidente 2026-06-05.
 *  - DOC-RAIZ-ESTOQUE §10: "usar SEMPRE ProductUtil pra mexer qty_available"; o caminho
 *    numérico canônico do ecossistema é num_uf-based (4 irmãos o aplicam:
 *    updateProductQuantity / addSingleProductOpeningStock / adjustProductStockForInvoice /
 *    createOrUpdatePurchaseLines). fixVariationStockMisMatch é o ÚNICO que não aplica.
 *  - CONTRAPROVA no mesmo arquivo: num_uf('1.500') = 1500 (1 ponto + EXATAMENTE 3 dígitos
 *    = milhar; heurística session-independent, Util::num_uf). O irmão updateProductQuantity
 *    trata '1.500' como 1500; fixVariationStockMisMatch, com o MESMO input, grava 1.5.
 *
 * ESCOPO HONESTO: o fluxo sancionado manda `total_stock_calculated` (float cru, sem
 * agrupamento de milhar) → NÃO corrompe hoje. A falha é (a) reachable por tampering do
 * query param `?stock=1.500` (qualquer user com report.stock_details) e (b) ausência da
 * defesa num_uf que a REGRA MESTRE exige de TODA escrita de estoque.
 *
 * NÃO corrige nada (§0.3 do método) — PARECER provado. Correção = decisão [W] sob REGRA
 * MESTRE (dupla confirmação + impacto antes→depois). O `it` RED fica ->skip() com o recibo
 * do run CT 100 pra não travar a lane required; o PR do fix (quando [W] aprovar) o desskipa.
 *
 * @see memory/governance/scorecards/funcoes/app-utils-productutil.yaml (fixVariationStockMisMatch C2)
 * @see app/Utils/ProductUtil.php:2287-2318
 * @see memory/requisitos/Estoque/DOC-RAIZ-ESTOQUE.md §10
 */
beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS ausente — rode na lane MySQL (estoque-pest) ou CT 100.');
    }
    $this->biz = EstoqueFixture::businessId();
    session(['user.business_id' => $this->biz]);
});

it('CONTRAPROVA: o irmão updateProductQuantity trata "1.500" como 1500 (num_uf) — contrato do ecossistema', function () {
    $loc = EstoqueFixture::locationId($this->biz);
    $p = EstoqueFixture::singleProduct($this->biz);
    EstoqueFixture::setStock($p, 0, $loc, 0.0);

    // uf_data=true (default) → num_uf('1.500') = 1500 (milhar, heurística inequívoca).
    (new ProductUtil)->updateProductQuantity($loc, $p->productId, $p->variations[0]['variation_id'], '1.500', 0);

    expect(EstoqueFixture::currentStock($p, 0, $loc))->toBe(1500.0);
});

it('RED: fixVariationStockMisMatch grava "1.500" CRU (sem num_uf) e corrompe o saldo pra 1.5', function () {
    $loc = EstoqueFixture::locationId($this->biz);
    $p = EstoqueFixture::singleProduct($this->biz);
    EstoqueFixture::setStock($p, 0, $loc, 10.0);

    // MESMO input locale "1.500" (=1500) que updateProductQuantity trata certo:
    (new ProductUtil)->fixVariationStockMisMatch($this->biz, $p->variations[0]['variation_id'], $loc, '1.500');

    // Contrato (REGRA MESTRE + irmão): deveria gravar 1500. Atual: grava 1.5 (ponto vira
    // decimal cru no cast do MySQL, sem num_uf). Este expect FALHA = defeito provado.
    expect(EstoqueFixture::currentStock($p, 0, $loc))->toBe(1500.0);
})->skip('RED-characterization C2 (funcao-scorecard 2026-07-21): defeito PROVADO no CT 100 '
    .'(container oimpresso-staging HEAD 34fe49730, MySQL real → "Failed asserting that 1.5 is '
    .'identical to 1500.0"). Correção = US-PROD-028, decisão [W] sob REGRA MESTRE (dupla '
    .'confirmação + impacto antes→depois). O PR do fix DESSKIPA este teste.');
