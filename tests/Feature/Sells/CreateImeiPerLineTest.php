<?php

declare(strict_types=1);

/**
 * Test-first (paridade Create <- Edit) — campo IMEI / nº de série por linha
 * de produto na tela de CRIAR VENDA (Sells/Create.tsx).
 *
 * Hoje só o Edit.tsx tem `imei_number` por linha (tipo l.45, default l.239,
 * payload l.270, render input l.787-788). O Create NÃO tem — eletrônicos/
 * celulares não conseguem rastrear IMEI ao CRIAR a venda, só ao editar.
 *
 * Estes it() ficam VERMELHOS enquanto a feature não for espelhada do Edit;
 * viram VERDES quando o Create ganhar o campo. biz=1 N/A (teste estrutural,
 * não toca Model/query — ADR 0101/0093 não se aplicam aqui).
 */

const CREATE_PATH_IMEI = 'resources/js/Pages/Sells/Create.tsx';
const EDIT_PATH_IMEI = 'resources/js/Pages/Sells/Edit.tsx';

function readCreateImei(): string
{
    return file_get_contents(base_path(CREATE_PATH_IMEI));
}

function readEditImei(): string
{
    return file_get_contents(base_path(EDIT_PATH_IMEI));
}

// ── Referência (sanity da fonte espelhada) ───────────────────────────────────

it('referência — Edit.tsx tem imei_number por linha (fonte a espelhar)', function () {
    $src = readEditImei();
    expect($src)->toContain('imei_number');
});

// ── Alvo: Create.tsx deve ganhar o campo (VERMELHO hoje) ────────────────────

it('Create.tsx tipa imei_number na linha de produto (paridade Edit)', function () {
    $src = readCreateImei();
    expect($src)->toMatch('/imei_number\??\s*:\s*string/');
});

it('Create.tsx inicializa imei_number ao adicionar produto novo', function () {
    $src = readCreateImei();
    // ao adicionar uma linha nova, o campo nasce vazio (igual Edit l.239).
    expect($src)->toContain("imei_number");
    expect($src)->toMatch("/imei_number\s*:\s*''/");
});

it('Create.tsx renderiza um input de IMEI por linha ligado ao handler', function () {
    $src = readCreateImei();
    // input controlado: value do imei_number + onChange atualizando a linha.
    expect($src)->toMatch('/imei_number\b[^\n]{0,120}(value|onChange)/');
});

it('Create.tsx envia imei_number no payload do submit', function () {
    $src = readCreateImei();
    // no build de products[] do submit, a linha carrega imei_number.
    expect($src)->toMatch('/imei_number\s*:\s*[A-Za-z]/');
});

it('Create.tsx tem rótulo/aria de IMEI ou nº de série pra acessibilidade', function () {
    $src = readCreateImei();
    expect($src)->toMatch('/IMEI|série|serie|nº de série|n\. série/i');
});
