<?php

declare(strict_types=1);

// @covers-us UC-INV-06

use App\Utils\ProductUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\EstoqueFixture;

uses(DatabaseTransactions::class);

/**
 * UC-INV-06 — INV-6: isolamento multi-tenant do saldo. DOC-RAIZ-ESTOQUE §6/§7 (INV-6) + ADR 0093.
 *
 * Contrato (§6): `variation_location_details` NÃO tem `business_id` — o isolamento é TRANSITIVO e
 * seguro porque `variation_id`/`location_id`/`product_id` são PKs GLOBAIS ÚNICAS (não há colisão de
 * IDs entre businesses). Logo uma movimentação de saldo do biz=1 (endereçada por V1/L1) NÃO PODE
 * alcançar o saldo do biz=2 (V2/L2) — endereços distintos.
 *
 * Prova: dois tenants com produtos/locais próprios; mexer o biz=1 deixa o VLD do biz=2 intocado.
 * (A camada de reserva FSM, que TEM business_id + HasBusinessScope, é coberta à parte por
 * StockReservationsTest caso 6.)
 *
 * Requer biz=2 semeado (lane MySQL seed biz=1/biz=2). Skip gracioso se não houver 2º tenant.
 *
 * @see memory/requisitos/Estoque/DOC-RAIZ-ESTOQUE.md §6 §7 (INV-6)
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/business ausente — rode na lane MySQL (estoque-pest) ou CT 100.');
    }

    $this->biz1 = EstoqueFixture::businessId();
    $this->biz2 = EstoqueFixture::secondBusinessId();
    if ($this->biz2 === null) {
        $this->markTestSkipped('Sem 2º business semeado — INV-6 precisa de biz=1 E biz=2 (lane MySQL seed).');
    }
});

it('movimento no biz=1 NÃO toca o saldo do biz=2 (isolamento transitivo por IDs únicos)', function () {
    // Tenant 1
    $loc1 = EstoqueFixture::locationId($this->biz1);
    $prod1 = EstoqueFixture::singleProduct($this->biz1);
    EstoqueFixture::setStock($prod1, 0, $loc1, 10.0);

    // Tenant 2 — produto/local/variação próprios (IDs distintos)
    $loc2 = EstoqueFixture::locationId($this->biz2);
    $prod2 = EstoqueFixture::singleProduct($this->biz2);
    EstoqueFixture::setStock($prod2, 0, $loc2, 10.0);

    // Pré-condição do isolamento transitivo: IDs realmente distintos entre tenants.
    expect($prod2->variationId())->not->toBe($prod1->variationId());
    expect($loc2)->not->toBe($loc1);

    // Mexe SÓ o biz=1.
    session(['user.business_id' => $this->biz1]);
    (new ProductUtil)->decreaseProductQuantity($prod1->productId, $prod1->variationId(), $loc1, 3.0);

    expect(EstoqueFixture::currentStock($prod1, 0, $loc1))->toBe(7.0);  // biz=1 baixou
    expect(EstoqueFixture::currentStock($prod2, 0, $loc2))->toBe(10.0); // biz=2 intocado
});
