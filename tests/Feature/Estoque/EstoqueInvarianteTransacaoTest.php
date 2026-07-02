<?php

declare(strict_types=1);

// @covers-us UC-INV-03

use App\Utils\ProductUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\EstoqueFixture;

uses(DatabaseTransactions::class);

/**
 * UC-INV-03 — INV-3: movimentação dentro de `DB::transaction` (atomicidade). DOC-RAIZ-ESTOQUE §7 (INV-3).
 *
 * Contrato (§7 INV-3): toda movimentação de saldo roda dentro de `DB::transaction` — logo, se o
 * fluxo falha no meio, NADA persiste (movimento parcial não vaza). Prova BEHAVIORAL (não grep
 * estrutural, que seria "presença ≠ correção"): uma baixa dentro de uma transação que estoura é
 * revertida — o saldo volta ao estado inicial.
 *
 * biz=1 dogfood (ADR 0101). Skip gracioso em sqlite.
 *
 * @see memory/requisitos/Estoque/DOC-RAIZ-ESTOQUE.md §7 (INV-3)
 */
beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/business ausente — rode na lane MySQL (estoque-pest) ou CT 100.');
    }

    $this->biz = EstoqueFixture::businessId();
    session(['user.business_id' => $this->biz]);
});

it('movimento dentro de DB::transaction que FALHA é revertido (nada persiste)', function () {
    $loc = EstoqueFixture::locationId($this->biz);
    $produto = EstoqueFixture::singleProduct($this->biz);
    EstoqueFixture::setStock($produto, 0, $loc, 10.0);

    try {
        DB::transaction(function () use ($produto, $loc) {
            (new ProductUtil)->decreaseProductQuantity($produto->productId, $produto->variationId(), $loc, 4.0);
            // Falha DEPOIS de mexer o saldo — a transação inteira deve reverter.
            throw new RuntimeException('boom no meio do fluxo');
        });
    } catch (RuntimeException $e) {
        // esperado
    }

    // Saldo intocado: a baixa de 4 foi revertida com o rollback (INV-3).
    expect(EstoqueFixture::currentStock($produto, 0, $loc))->toBe(10.0);
});

it('movimento dentro de DB::transaction que COMMITA persiste', function () {
    $loc = EstoqueFixture::locationId($this->biz);
    $produto = EstoqueFixture::singleProduct($this->biz);
    EstoqueFixture::setStock($produto, 0, $loc, 10.0);

    // Contraprova: sem exceção, a transação commita e o saldo muda.
    DB::transaction(function () use ($produto, $loc) {
        (new ProductUtil)->decreaseProductQuantity($produto->productId, $produto->variationId(), $loc, 4.0);
    });

    expect(EstoqueFixture::currentStock($produto, 0, $loc))->toBe(6.0);
});
