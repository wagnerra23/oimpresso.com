<?php

declare(strict_types=1);

/**
 * Pest — Sells/Create Parity Parking-Lot P4 (mirror do Edit.tsx).
 *
 * PR #4 da trilha "8 features reais (mirror do Edit)":
 *
 *   - imei_number per-line → campo IMEI/serial inline por linha de produto
 *
 * Paridade de COMPORTAMENTO com Edit.tsx:784-791 (mesmo campo, mesmo payload).
 *
 * NOTA DE PERSISTÊNCIA (gap herdado do Edit, fora do escopo deste PR):
 * não existe coluna `imei` em transaction_sell_lines e o array de sell line em
 * TransactionUtil@createOrUpdateSellLines não lê imei_number — logo o valor é
 * write-only (mesmo comportamento do Edit, que também não lê imei de volta).
 * Persistência real exigiria migração (coluna imei) OU mapear pra sell_line_note
 * — decisão do Wagner, follow-up separado.
 *
 * Pattern estrutural via file_get_contents (espelha SellsEditParkingLotP1P2P3Test).
 *
 * Refs:
 *  - resources/js/Pages/Sells/Create.tsx
 *  - resources/js/Pages/Sells/Edit.tsx:45,239,270,784-791 (fonte do pattern)
 */

const CREATE_TSX_P4 = 'resources/js/Pages/Sells/Create.tsx';

function plP4Root(): string
{
    return dirname(__DIR__, 3);
}

function plP4Read(string $rel): string
{
    return file_get_contents(plP4Root() . DIRECTORY_SEPARATOR . $rel);
}

it('Create.tsx adiciona imei_number ao tipo da linha de produto', function () {
    $src = plP4Read(CREATE_TSX_P4);
    expect($src)->toContain('imei_number?: string;');
});

it('Create.tsx default da linha nova inclui imei_number vazio', function () {
    $src = plP4Read(CREATE_TSX_P4);
    expect($src)->toContain("imei_number: ''");
});

it('Create.tsx adiciona input IMEI/serial inline por linha', function () {
    $src = plP4Read(CREATE_TSX_P4);
    expect($src)
        ->toContain('IMEI / nº série')
        ->toContain('imei_number: e.target.value');
});

it('Create.tsx envia imei_number no payload de produtos', function () {
    $src = plP4Read(CREATE_TSX_P4);
    expect($src)->toContain("imei_number: p.imei_number ?? ''");
});

// ─── Tier 0 — não regredir ──────────────────────────

it('Create.tsx PRESERVA is_direct_sale=1', function () {
    $src = plP4Read(CREATE_TSX_P4);
    expect($src)->toContain('is_direct_sale: 1');
});
