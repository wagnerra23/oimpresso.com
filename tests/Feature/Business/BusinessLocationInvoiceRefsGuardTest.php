<?php

declare(strict_types=1);

/**
 * Regressão — cadastro de filial (business_locations) dando "erro 500".
 *
 * Causa raiz (2026-06-08): `invoice_scheme_id` e `invoice_layout_id` são
 * NOT NULL + FK em `business_locations`. Quando o select do formulário vai
 * vazio, o valor vira 0, o INSERT/UPDATE estoura `1452 foreign key constraint
 * fails`, e o catch genérico do controller transforma isso no opaco
 * "Algo deu errado" — o usuário não sabe o que faltou.
 *
 * Fix: `validateInvoiceRefs()` valida os campos ANTES de tocar o banco e
 * devolve mensagem clara (`business.select_invoice_scheme_and_layout`),
 * escopada por business_id (Tier 0, ADR 0093).
 *
 * Estes testes leem a fonte (mesmo padrão dos *BaselineTest.php) pra travar
 * o guard — se alguém remover, o teste quebra.
 */

const BL_CONTROLLER_PATH = 'app/Http/Controllers/BusinessLocationController.php';

function readBusinessLocationController(): string
{
    return file_get_contents(base_path(BL_CONTROLLER_PATH));
}

function blMethodBlock(string $method): string
{
    $source = readBusinessLocationController();
    preg_match('/(?:private|public|protected) function '.preg_quote($method, '/').'\(.*?(?=\n    (?:private|public|protected) function )/s', $source, $m);

    return $m[0] ?? '';
}

it('store() chama validateInvoiceRefs antes de criar a filial', function () {
    $block = blMethodBlock('store');
    expect($block)->toContain('validateInvoiceRefs(');

    // guard precisa vir ANTES do BusinessLocation::create (senão a FK já estourou)
    $posGuard = strpos($block, 'validateInvoiceRefs(');
    $posCreate = strpos($block, 'BusinessLocation::create(');
    expect($posGuard)->not->toBeFalse();
    expect($posCreate)->not->toBeFalse();
    expect($posGuard)->toBeLessThan($posCreate);
});

it('update() chama validateInvoiceRefs antes de atualizar a filial', function () {
    $block = blMethodBlock('update');
    expect($block)->toContain('validateInvoiceRefs(');

    $posGuard = strpos($block, 'validateInvoiceRefs(');
    $posUpdate = strpos($block, '->update(');
    expect($posGuard)->not->toBeFalse();
    expect($posUpdate)->not->toBeFalse();
    expect($posGuard)->toBeLessThan($posUpdate);
});

it('validateInvoiceRefs exige invoice_scheme_id e invoice_layout_id (NOT NULL + FK)', function () {
    $block = blMethodBlock('validateInvoiceRefs');
    expect($block)->toContain("'invoice_scheme_id' => InvoiceScheme::class");
    expect($block)->toContain("'invoice_layout_id' => InvoiceLayout::class");
    // checa existência real no banco (não aceita id órfão)
    expect($block)->toContain('->exists()');
});

it('validateInvoiceRefs escopa a checagem por business_id (Tier 0 ADR 0093)', function () {
    $block = blMethodBlock('validateInvoiceRefs');
    expect($block)->toContain("where('business_id', \$business_id)");
});

it('validateInvoiceRefs devolve mensagem de tradução clara (não o genérico)', function () {
    $block = blMethodBlock('validateInvoiceRefs');
    expect($block)->toContain("__('business.select_invoice_scheme_and_layout')");
    expect($block)->not->toContain("messages.something_went_wrong");
});

it('chave de tradução select_invoice_scheme_and_layout existe em pt e en', function () {
    $pt = require base_path('lang/pt/business.php');
    $en = require base_path('lang/en/business.php');
    expect($pt)->toHaveKey('select_invoice_scheme_and_layout');
    expect($en)->toHaveKey('select_invoice_scheme_and_layout');
    expect($pt['select_invoice_scheme_and_layout'])->not->toBeEmpty();
});
