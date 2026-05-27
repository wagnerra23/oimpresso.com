<?php

declare(strict_types=1);

use App\Utils\TransactionUtil;
use Modules\Compras\Services\ComprasService;

uses(Tests\TestCase::class);

/**
 * Audit sênior 2026-05-25 — Onda ESTABILIZAR Compras PR-0 (Gaps #5 #6 #11).
 *
 * Gap #5: regression guard pro hotfix R1 (TransactionUtil::getListPurchases
 *         JOIN com contacts.business_id) — já aplicado em hotfix/us-com-009.
 * Gap #6: documentação de N/A (getListPurchases SELECT flat — sem N+1).
 * Gap #11: OTel span custom em 3 métodos do ComprasService (D9.a +1pt).
 */

function gapP1_transactionUtilMethodBlock(): string
{
    $src = file_get_contents(
        (new ReflectionMethod(TransactionUtil::class, 'getListPurchases'))->getFileName(),
    );
    $start = strpos($src, 'function getListPurchases');
    return substr($src, $start, 3000);
}

function gapP1_comprasServiceSrc(): string
{
    return file_get_contents(
        (new ReflectionClass(ComprasService::class))->getFileName(),
    );
}

it('Gap #5 INVARIANTE: getListPurchases JOIN contacts scoped por business_id', function () {
    $block = gapP1_transactionUtilMethodBlock();

    expect(str_contains($block, "leftJoin('contacts'"))->toBeTrue('JOIN contacts presente');
    expect(str_contains($block, "'contacts.business_id'"))->toBeTrue('scope contacts.business_id presente');
    expect(str_contains($block, "'BS.business_id'"))->toBeTrue('business_locations scoped');
});

it('Gap #5 INVARIANTE: WHERE transactions.business_id (defense layer 2)', function () {
    $block = gapP1_transactionUtilMethodBlock();

    expect(str_contains($block, "'transactions.business_id'"))->toBeTrue(
        'defense-in-depth: WHERE transactions.business_id é layer 2',
    );
});

it('Gap #6 N/A: getListPurchases SELECT flat columns (justifica ausência de with())', function () {
    $block = gapP1_transactionUtilMethodBlock();

    expect(str_contains($block, "'contacts.name'"))->toBeTrue('contact.name vem flat — sem N+1');
    expect(str_contains($block, "'contacts.supplier_business_name'"))->toBeTrue('supplier_business_name flat');
    expect(str_contains($block, "'BS.name as location_name'"))->toBeTrue('location.name vem flat aliased');
});

it('Gap #11: ComprasService import OtelHelper', function () {
    $src = gapP1_comprasServiceSrc();
    expect(str_contains($src, 'use App\Util\OtelHelper;'))->toBeTrue('import canon');
});

it('Gap #11: listarCompras envolve com spanBiz compras.listarCompras', function () {
    $src = gapP1_comprasServiceSrc();
    expect(str_contains($src, "spanBiz('compras.listarCompras'"))->toBeTrue();
});

it('Gap #11: calcularKpis envolve com spanBiz compras.calcularKpis', function () {
    $src = gapP1_comprasServiceSrc();
    expect(str_contains($src, "spanBiz('compras.calcularKpis'"))->toBeTrue();
});

it('Gap #11: buscarDetalhe envolve com spanBiz compras.buscarDetalhe', function () {
    $src = gapP1_comprasServiceSrc();
    expect(str_contains($src, "spanBiz('compras.buscarDetalhe'"))->toBeTrue();
});

it('Gap #11: spans canon usam prefix compras.* (no module leak)', function () {
    $src = gapP1_comprasServiceSrc();

    preg_match_all("/spanBiz\\('([^']+)'/", $src, $matches);
    expect($matches[1])->not->toBeEmpty('spans devem ser declarados');

    foreach ($matches[1] as $span) {
        expect(str_starts_with($span, 'compras.'))->toBeTrue("span '{$span}' deve ter prefix compras.");
    }
});

it('Gap #11: métodos *Interno são private (callback wrap pattern)', function () {
    $ref = new ReflectionClass(ComprasService::class);

    expect($ref->hasMethod('listarComprasInterno'))->toBeTrue('callback wrap')
        ->and($ref->hasMethod('calcularKpisInterno'))->toBeTrue()
        ->and($ref->hasMethod('buscarDetalheInterno'))->toBeTrue()
        ->and($ref->getMethod('listarComprasInterno')->isPrivate())->toBeTrue('@internal')
        ->and($ref->getMethod('calcularKpisInterno')->isPrivate())->toBeTrue()
        ->and($ref->getMethod('buscarDetalheInterno')->isPrivate())->toBeTrue();
});

it('Gap #11: pelo menos 3 spans canon — target D9.a +1pt', function () {
    $src = gapP1_comprasServiceSrc();
    $count = preg_match_all("/spanBiz\\(/", $src);

    expect($count)->toBeGreaterThanOrEqual(3,
        'pelo menos 3 spans canon — listarCompras + calcularKpis + buscarDetalhe (audit Gap #11)');
});
