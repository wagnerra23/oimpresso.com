<?php

declare(strict_types=1);

/**
 * Pest — Sells/Create Parity Parking-Lot P1 (mirror do Edit.tsx).
 *
 * Felipe abriu a trilha "produzir as 8 features reais (mirror do Edit)" test-first.
 * Este é o PR #1 da trilha: paridade Create←Edit para os 2 campos avançados
 * mais simples, que vivem em "Mais opções":
 *
 *   - is_recurring  → checkbox "Assinatura recorrente" (Blade legacy is_recurring)
 *   - staff_note    → textarea "Nota interna (equipe)" (separada de additional_notes)
 *
 * Backend já aceita ambos no store (SellPosController@store linha 379
 * `$request->except('_token')` + TransactionUtil:79/106 lê staff_note/is_recurring).
 * Logo este PR é PURAMENTE frontend — wire-up no Create.tsx + payload transform.
 *
 * Pattern espelha SellsEditParkingLotP1P2P3Test.php (assertions estruturais via
 * file_get_contents — não bootam o container, compatível com worktrees).
 *
 * Refs:
 *  - resources/js/Pages/Sells/Create.tsx
 *  - resources/js/Pages/Sells/Edit.tsx (fonte do pattern: linhas 922-953)
 *  - app/Utils/TransactionUtil.php@createSellTransaction (staff_note + is_recurring)
 */

const CREATE_TSX = 'resources/js/Pages/Sells/Create.tsx';

function plCreateRoot(): string
{
    // Sobe 3 níveis de tests/Feature/Sells → repo root. Compatível com worktrees.
    return dirname(__DIR__, 3);
}

function plCreateRead(string $rel): string
{
    return file_get_contents(plCreateRoot() . DIRECTORY_SEPARATOR . $rel);
}

// ─── is_recurring (Assinatura recorrente) ──────────────────────────

it('Create.tsx declara is_recurring no useForm (0/1 paridade backend)', function () {
    $src = plCreateRead(CREATE_TSX);
    expect($src)
        ->toContain('is_recurring: 0 as 0 | 1');
});

it('Create.tsx adiciona checkbox is_recurring (Assinatura recorrente)', function () {
    $src = plCreateRead(CREATE_TSX);
    expect($src)
        ->toContain('is_recurring')
        ->toContain('Assinatura recorrente')
        ->toContain('checked={data.is_recurring === 1}');
});

it('Create.tsx importa Checkbox (necessário pro is_recurring)', function () {
    $src = plCreateRead(CREATE_TSX);
    expect($src)->toContain("import { Checkbox } from '@/Components/ui/checkbox'");
});

it('Create.tsx envia is_recurring no payload transform (0/1)', function () {
    $src = plCreateRead(CREATE_TSX);
    expect($src)->toContain('is_recurring: d.is_recurring ? 1 : 0');
});

// ─── staff_note (Nota interna equipe) ──────────────────────────

it('Create.tsx declara staff_note no useForm', function () {
    $src = plCreateRead(CREATE_TSX);
    expect($src)->toContain("staff_note: ''");
});

it('Create.tsx adiciona textarea staff_note separada de additional_notes', function () {
    $src = plCreateRead(CREATE_TSX);
    expect($src)
        ->toContain('staff_note')
        ->toContain('Nota interna')
        ->toContain('visível só pra equipe');
});

it('Create.tsx envia staff_note no payload transform', function () {
    $src = plCreateRead(CREATE_TSX);
    expect($src)->toContain('staff_note: d.staff_note');
});

// ─── Tier 0 IRREVOGÁVEL — não regredir invariantes do Create ──────────

it('Create.tsx PRESERVA is_direct_sale=1 no payload (não cai em cashRegister)', function () {
    $src = plCreateRead(CREATE_TSX);
    expect($src)->toContain('is_direct_sale: 1');
});

it('Create.tsx PRESERVA FSM safety (NUNCA seta current_stage_id — ADR 0143)', function () {
    $src = plCreateRead(CREATE_TSX);
    expect($src)->not->toContain("setData('current_stage_id'");
});
