<?php

declare(strict_types=1);

/**
 * Pest — Sells/Create Parity Parking-Lot P5 (mirror do Edit.tsx).
 *
 * PR #5 (último) da trilha "8 features reais (mirror do Edit)":
 *
 *   - sell_document → upload de documento anexo à venda (multipart)
 *
 * Diferente do imei (P4): o store BACKEND persiste de verdade —
 * SellPosController@store:586 chama uploadFile($request, 'sell_document',
 * 'documents') e grava em transactions.document. Feature real.
 *
 * Pontos sensíveis cobertos:
 *  - File não é serializável → EXCLUÍDO do auto-save draft (localStorage)
 *  - Inertia auto-detecta File no payload e usa FormData só quando há anexo
 *    (caminho JSON comum permanece intacto quando não há documento)
 *  - limite 5MB client-side
 *
 * Pattern estrutural via file_get_contents (espelha SellsEditParkingLotP1P2P3Test).
 *
 * Refs:
 *  - resources/js/Pages/Sells/Create.tsx
 *  - resources/js/Pages/Sells/Edit.tsx:177,955-985 (fonte do pattern)
 *  - app/Http/Controllers/SellPosController.php:586 (uploadFile no store)
 */

const CREATE_TSX_P5 = 'resources/js/Pages/Sells/Create.tsx';

function plP5Root(): string
{
    return dirname(__DIR__, 3);
}

function plP5Read(string $rel): string
{
    return file_get_contents(plP5Root() . DIRECTORY_SEPARATOR . $rel);
}

it('Create.tsx declara sell_document File|null no useForm', function () {
    $src = plP5Read(CREATE_TSX_P5);
    expect($src)->toContain('sell_document: null as File | null');
});

it('Create.tsx adiciona input type=file com accept e limite 5MB', function () {
    $src = plP5Read(CREATE_TSX_P5);
    expect($src)
        ->toContain('Anexar documento')
        ->toContain('accept=".pdf,.csv,.zip,.doc,.docx,.jpg,.jpeg,.png"')
        ->toContain('5 * 1024 * 1024');
});

it('Create.tsx envia sell_document no payload transform', function () {
    $src = plP5Read(CREATE_TSX_P5);
    expect($src)->toContain('sell_document: d.sell_document');
});

it('Create.tsx EXCLUI sell_document do auto-save draft (File não serializa)', function () {
    $src = plP5Read(CREATE_TSX_P5);
    // Destructure que tira o File antes do JSON.stringify do draft.
    expect($src)->toContain('sell_document: _file');
});

it('Create.tsx restaura draft com sell_document=null (File não vem do localStorage)', function () {
    $src = plP5Read(CREATE_TSX_P5);
    expect($src)->toContain('sell_document: null }');
});

// ─── Tier 0 — não regredir ──────────────────────────

it('Create.tsx PRESERVA is_direct_sale=1', function () {
    $src = plP5Read(CREATE_TSX_P5);
    expect($src)->toContain('is_direct_sale: 1');
});
