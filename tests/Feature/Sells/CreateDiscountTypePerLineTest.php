<?php

declare(strict_types=1);

/**
 * Pest test estrutural (test-first / VERMELHO) — Sells/Create.tsx desconto por
 * linha em R$ OU % (paridade com Edit.tsx).
 *
 * FEATURE "discount-type" per-linha no Create:
 *   Hoje o Create.tsx só trata desconto FIXO por linha (`discount: number`).
 *   O Edit.tsx já tem `discount_type: 'fixed' | 'percentage'` por linha de
 *   produto (interface EditProductLine), com toggle Select R$/% no carrinho e
 *   matemática de percentual `(gross * p.discount) / 100`.
 *
 *   O ALVO ("pronto") é o Create espelhar EXATAMENTE o Edit:
 *     1. Tipo da linha de produto ganha `discount_type: 'fixed' | 'percentage'`
 *     2. Toda linha nova nasce com `discount_type` default (mesmo Edit usa 'fixed')
 *     3. Carrinho renderiza Select com SelectItem "R$" (fixed) + "%" (percentage)
 *     4. handleProductChange/onUpdateProduct aceita atualizar `discount_type`
 *     5. Subtotal da linha honra percentage: `(gross * p.discount) / 100`
 *        em vez de subtrair só `p.discount` cru
 *     6. Payload submit usa `line_discount_type: p.discount_type` (não hardcode 'fixed')
 *
 * Espelha o estilo dos testes estruturais canônicos do módulo:
 *   - SaleSheetComponentTest.php
 *   - CustomerAutoApplyOnSelectTest.php
 * (lê o source via file_get_contents + expect(...)->toContain/->toMatch).
 *
 * Multi-tenant (ADR 0093) / biz=1 (ADR 0101): teste é PURAMENTE estrutural sobre
 * o arquivo .tsx — não toca Model/query/DB, portanto não há business_id em jogo.
 *
 * ESTADO ESPERADO AGORA: VERMELHO. A feature ainda NÃO foi implementada no
 * Create.tsx, então os it() abaixo devem FALHAR até a paridade com Edit existir.
 */

const CREATE_PATH_DT = 'resources/js/Pages/Sells/Create.tsx';
const EDIT_PATH_DT = 'resources/js/Pages/Sells/Edit.tsx';

function readCreateDt(): string
{
    return file_get_contents(base_path(CREATE_PATH_DT));
}

function readEditDt(): string
{
    return file_get_contents(base_path(EDIT_PATH_DT));
}

// === Sanidade: o Edit (referência) realmente tem a feature ===

it('referência — Edit.tsx tem discount_type per-linha (fixed|percentage)', function () {
    $src = readEditDt();
    // Garante que estamos espelhando algo que existe no Edit; se o Edit perder
    // a feature, este teste avisa (não é o alvo, é guarda da referência).
    expect($src)->toMatch("/discount_type:\s*'fixed'\s*\|\s*'percentage'/");
})->note('Guarda da referência — Edit.tsx é a fonte da paridade.');

// === 1. Tipo da linha de produto no Create ganha discount_type ===

it('Create.tsx — tipo da linha de produto declara discount_type fixed|percentage', function () {
    $src = readCreateDt();
    // No Edit é `discount_type: 'fixed' | 'percentage';` dentro do interface da linha.
    // O Create precisa ter a MESMA chave no shape da linha de produto.
    expect($src)->toMatch("/discount_type:\s*'fixed'\s*\|\s*'percentage'/");
})->note('Hoje o shape da linha do Create só tem `discount: number` — VERMELHO.');

// === 2. Linha nova nasce com discount_type default ===

it('Create.tsx — produto novo adicionado nasce com discount_type default', function () {
    $src = readCreateDt();
    // O bloco de push de produto novo (default `discount: 0`) precisa setar
    // também `discount_type` (Edit default é 'fixed').
    expect($src)->toMatch("/discount_type:\s*'fixed'/");
})->note('Bloco de add-produto hoje só seta `discount: 0` — VERMELHO.');

// === 3. Carrinho renderiza toggle R$ / % por linha ===

it('Create.tsx — carrinho tem SelectItem R$ (fixed) por linha', function () {
    $src = readCreateDt();
    expect($src)->toContain('<SelectItem value="fixed">R$</SelectItem>');
})->note('Toggle R$/% per-linha não existe no Create — VERMELHO.');

it('Create.tsx — carrinho tem SelectItem % (percentage) por linha', function () {
    $src = readCreateDt();
    expect($src)->toContain('<SelectItem value="percentage">%</SelectItem>');
})->note('Toggle R$/% per-linha não existe no Create — VERMELHO.');

// === 4. Update da linha aceita mudar discount_type ===

it('Create.tsx — handler de mudança da linha atualiza discount_type', function () {
    $src = readCreateDt();
    // Espelha onUpdateProduct(idx, { discount_type: v as 'fixed' | 'percentage' }) do Edit.
    expect($src)->toMatch("/discount_type:\s*v as 'fixed'\s*\|\s*'percentage'/");
})->note('Create não permite trocar tipo de desconto da linha — VERMELHO.');

// === 5. Subtotal da linha honra percentual ===

it('Create.tsx — subtotal da linha aplica percentual (gross * discount) / 100', function () {
    $src = readCreateDt();
    // Hoje o subtotal é `p.quantity * p.unit_price - p.discount` (só fixo).
    // O alvo é ramificar por discount_type com a fórmula de percentual do Edit.
    expect($src)->toMatch("/p\.discount_type\s*===\s*'percentage'/");
    expect($src)->toMatch('#\(.*\*.*\)\s*/\s*100#');
})->note('Subtotal só subtrai desconto fixo (`- p.discount`) — VERMELHO.');

// === 6. Payload submit não hardcoda line_discount_type=fixed ===

it('Create.tsx — payload usa line_discount_type dinâmico (não hardcode fixed)', function () {
    $src = readCreateDt();
    // Hoje o map de submit faz `line_discount_type: 'fixed'`. O alvo é
    // `line_discount_type: p.discount_type` (espelha intenção do Edit).
    expect($src)->toContain('line_discount_type: p.discount_type');
    expect($src)->not->toContain("line_discount_type: 'fixed'");
})->note("Submit hardcoda `line_discount_type: 'fixed'` — VERMELHO.");
