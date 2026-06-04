<?php

declare(strict_types=1);

/**
 * Pest test estrutural — feature "secondary-addr" no Pages/Sells/Create.tsx.
 *
 * ESPECIFICAÇÃO (estado-alvo / "pronto"):
 *   O Create deve permitir endereço de cobrança secundário
 *   (`customer_secondary_address`) diferente da entrega, igual o Edit.tsx.
 *   Usado pra NF-e quando o cliente solicita faturamento em endereço diferente.
 *   Paridade Blade legacy `customer_secondary_address`.
 *
 * ESPELHA os nomes EXATOS do Edit.tsx (resources/js/Pages/Sells/Edit.tsx):
 *   - estado inicial useForm: `customer_secondary_address: ''`
 *   - tipo do form data:      `customer_secondary_address: string;`
 *   - <Label htmlFor="customer_secondary_address">
 *   - <Textarea id="customer_secondary_address" value={data.customer_secondary_address}
 *     onChange={(e) => setData('customer_secondary_address', e.target.value)} />
 *
 * TEST-FIRST: a feature ainda NÃO existe no Create.tsx hoje, então todos os
 * it() abaixo ficam VERMELHOS até a implementação. Quando o Create ganhar o
 * campo espelhando o Edit, eles passam. NÃO há asserção trivialmente verde.
 *
 * Estilo idêntico a SaleSheetComponentTest.php / CustomerAutoApplyOnSelectTest.php
 * (leem o source com file_get_contents + expect()->toContain()/->toMatch()).
 */

const CREATE_PATH_SECADDR = 'resources/js/Pages/Sells/Create.tsx';
const EDIT_PATH_SECADDR = 'resources/js/Pages/Sells/Edit.tsx';

function readCreateSecAddr(): string
{
    return file_get_contents(base_path(CREATE_PATH_SECADDR));
}

function readEditSecAddr(): string
{
    return file_get_contents(base_path(EDIT_PATH_SECADDR));
}

// === Sanidade — fonte de verdade (Edit.tsx) tem o campo (deve passar HOJE) ===

it('secondary-addr — Edit.tsx (referência) já tem customer_secondary_address', function () {
    // Guarda: se algum dia o Edit perder o campo, o espelho perde a referência.
    $src = readEditSecAddr();
    expect($src)->toContain('customer_secondary_address');
});

// === Estado-alvo no Create.tsx (VERMELHO até implementar) ===

it('secondary-addr — Create.tsx declara customer_secondary_address no estado inicial do useForm', function () {
    $src = readCreateSecAddr();
    // Espelha Edit.tsx linha 170: `customer_secondary_address: '',`
    expect($src)->toMatch("/customer_secondary_address:\s*''/");
});

it('secondary-addr — Create.tsx tipa customer_secondary_address como string no form data', function () {
    $src = readCreateSecAddr();
    // Espelha Edit.tsx linha 596: `customer_secondary_address: string;`
    expect($src)->toMatch('/customer_secondary_address:\s*string;/');
});

it('secondary-addr — Create.tsx renderiza Label htmlFor="customer_secondary_address"', function () {
    $src = readCreateSecAddr();
    expect($src)->toContain('htmlFor="customer_secondary_address"');
    expect($src)->toContain('Endereço de cobrança');
});

it('secondary-addr — Create.tsx renderiza Textarea id="customer_secondary_address" ligado ao data', function () {
    $src = readCreateSecAddr();
    expect($src)->toContain('id="customer_secondary_address"');
    expect($src)->toContain('value={data.customer_secondary_address}');
});

it('secondary-addr — Create.tsx faz setData(\'customer_secondary_address\', ...) no onChange', function () {
    $src = readCreateSecAddr();
    // Espelha Edit.tsx linha 1041.
    expect($src)->toMatch("/setData\(\s*'customer_secondary_address'\s*,\s*e\.target\.value\s*\)/");
});

it('secondary-addr — Create.tsx explica uso NF-e (cobrança ≠ entrega) no hint', function () {
    $src = readCreateSecAddr();
    // Mesma justificativa do Edit (NF-e / faturamento em endereço diferente).
    expect($src)->toMatch('/NF-?e/i');
});
