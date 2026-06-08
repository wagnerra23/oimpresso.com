<?php

declare(strict_types=1);

/**
 * Pest — Sells/Create Parity Parking-Lot P2 (mirror do Edit.tsx).
 *
 * PR #2 da trilha "8 features reais (mirror do Edit)":
 *
 *   - customer_secondary_address → endereço de cobrança ≠ entrega (Blade legacy)
 *
 * DIFERENTE do P1 (staff_note/is_recurring que já estavam mapeados em
 * TransactionUtil:79/106): customer_secondary_address NÃO existia no array
 * Transaction::create() do createSellTransaction — logo venda nova descartava
 * o campo silenciosamente. Este PR adiciona o mapeamento backend (create+update)
 * pra paridade REAL (não cosmética).
 *
 * Pattern estrutural via file_get_contents (espelha SellsEditParkingLotP1P2P3Test).
 *
 * Refs:
 *  - resources/js/Pages/Sells/Create.tsx
 *  - resources/js/Pages/Sells/Edit.tsx:1035-1048 (fonte do pattern)
 *  - app/Utils/TransactionUtil.php (createSellTransaction + updateSellTransaction)
 *  - app/Http/Controllers/SellController.php:2879 (coluna lida no payload Edit)
 */

const CREATE_TSX_P2 = 'resources/js/Pages/Sells/Create.tsx';
const TRANSACTION_UTIL = 'app/Utils/TransactionUtil.php';

function plP2Root(): string
{
    return dirname(__DIR__, 3);
}

function plP2Read(string $rel): string
{
    return file_get_contents(plP2Root() . DIRECTORY_SEPARATOR . $rel);
}

// ─── Frontend — paridade Edit ──────────────────────────

it('Create.tsx declara customer_secondary_address no useForm', function () {
    $src = plP2Read(CREATE_TSX_P2);
    expect($src)->toContain("customer_secondary_address: ''");
});

it('Create.tsx adiciona campo customer_secondary_address (Endereço de cobrança)', function () {
    $src = plP2Read(CREATE_TSX_P2);
    expect($src)
        ->toContain('customer_secondary_address')
        ->toContain('Endereço de cobrança');
});

it('Create.tsx envia customer_secondary_address no payload transform', function () {
    $src = plP2Read(CREATE_TSX_P2);
    expect($src)->toContain('customer_secondary_address: d.customer_secondary_address');
});

// ─── Backend — persistência REAL (create + update) ──────────────────────────

it('TransactionUtil createSellTransaction mapeia customer_secondary_address', function () {
    $src = plP2Read(TRANSACTION_UTIL);
    expect($src)->toContain("'customer_secondary_address' => ! empty(\$input['customer_secondary_address']) ? \$input['customer_secondary_address'] : null");
});

it('TransactionUtil mapeia customer_secondary_address em ambos os arrays (create + update)', function () {
    $src = plP2Read(TRANSACTION_UTIL);
    expect(substr_count($src, "'customer_secondary_address' =>"))->toBeGreaterThanOrEqual(2);
});

// ─── Tier 0 — não regredir ──────────────────────────

it('Create.tsx PRESERVA is_direct_sale=1 (não cai em cashRegister)', function () {
    $src = plP2Read(CREATE_TSX_P2);
    expect($src)->toContain('is_direct_sale: 1');
});
