<?php

declare(strict_types=1);

/**
 * Pest — Sells/Create Parity Parking-Lot P3 (mirror do Edit.tsx).
 *
 * PR #3 da trilha "8 features reais (mirror do Edit)":
 *
 *   - discount_type per-line → toggle R$/% por linha de produto
 *
 * Create já enviava line_discount_amount per-line, mas line_discount_type era
 * hardcoded 'fixed'. Este PR dá ao usuário o toggle R$ vs % por linha (paridade
 * com Edit.tsx:817-846). Backend já suporta (TransactionUtil:348-377
 * calcula desconto por line_discount_type).
 *
 * Pattern estrutural via file_get_contents (espelha SellsEditParkingLotP1P2P3Test).
 *
 * Refs:
 *  - resources/js/Pages/Sells/Create.tsx
 *  - resources/js/Pages/Sells/Edit.tsx:772-846 (fonte do pattern)
 *  - app/Utils/TransactionUtil.php:348-377 (cálculo line_discount_type)
 */

const CREATE_TSX_P3 = 'resources/js/Pages/Sells/Create.tsx';

function plP3Root(): string
{
    return dirname(__DIR__, 3);
}

function plP3Read(string $rel): string
{
    return file_get_contents(plP3Root() . DIRECTORY_SEPARATOR . $rel);
}

it('Create.tsx adiciona discount_type ao tipo da linha de produto', function () {
    $src = plP3Read(CREATE_TSX_P3);
    expect($src)->toContain("discount_type: 'fixed' | 'percentage';");
});

it('Create.tsx default da linha nova é discount_type fixed', function () {
    $src = plP3Read(CREATE_TSX_P3);
    expect($src)->toContain("discount_type: 'fixed' as const");
});

it('Create.tsx payload envia line_discount_type dinâmico (não mais hardcoded)', function () {
    $src = plP3Read(CREATE_TSX_P3);
    expect($src)
        ->toContain("line_discount_type: p.discount_type ?? 'fixed'")
        ->not->toContain("line_discount_type: 'fixed',");
});

it('Create.tsx adiciona toggle R$/% per-line (Select discount_type)', function () {
    $src = plP3Read(CREATE_TSX_P3);
    expect($src)
        ->toContain('handleProductDiscountType')
        ->toContain("v as 'fixed' | 'percentage'")
        ->toContain('<SelectItem value="percentage">%</SelectItem>')
        ->toContain('<SelectItem value="fixed">R$</SelectItem>');
});

it('Create.tsx desconto % calcula sobre o bruto da linha (paridade Edit)', function () {
    $src = plP3Read(CREATE_TSX_P3);
    expect($src)
        ->toContain("p.discount_type === 'percentage'");
});

// ─── Tier 0 — não regredir ──────────────────────────

it('Create.tsx PRESERVA is_direct_sale=1', function () {
    $src = plP3Read(CREATE_TSX_P3);
    expect($src)->toContain('is_direct_sale: 1');
});
