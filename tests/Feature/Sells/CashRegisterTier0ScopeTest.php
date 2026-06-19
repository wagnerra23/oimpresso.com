<?php

declare(strict_types=1);

/**
 * Tier 0 (ADR 0093 IRREVOGÁVEL) — escopo business_id no fluxo de caixa.
 *
 * Contexto: `App\CashRegister` estende Model SEM global scope (sem
 * HasBusinessScope / addGlobalScope). Logo, TODA query precisa filtrar
 * business_id explicitamente, senão vaza entre tenants. Dois vazamentos
 * reais foram fechados:
 *
 *   1. WRITE — CashRegisterController@postCloseRegister fechava o caixa por
 *      `where('user_id', $request->input('user_id'))` sem business_id. Como o
 *      user_id vem do REQUEST, um tenant podia fechar o caixa de outro business
 *      passando um user_id alheio (cross-tenant write).
 *   2. READ — CashRegisterUtil@getRegisterDetails($id) carregava o caixa (e os
 *      totais financeiros) por `where('cash_registers.id', $id)` sem business_id
 *      (cross-tenant read via show()/getCloseRegister()).
 *
 * Pattern Pest estrutural (lê source, não roda Eloquent — SQLite incompatível
 * com schema UltimatePOS MySQL conforme ADR 0101). Mesmo padrão de
 * SellsCaixaPageTest.php.
 *
 * @see app/Http/Controllers/CashRegisterController.php::postCloseRegister()
 * @see app/Utils/CashRegisterUtil.php::getRegisterDetails()
 * @see app/CashRegister.php
 * @see memory/requisitos/Mwart/ONDA-1-CUTOVER-LEDGER.md (§4 — itens Tier 0 flagados)
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

const CRT_CONTROLLER = 'app/Http/Controllers/CashRegisterController.php';
const CRT_UTIL = 'app/Utils/CashRegisterUtil.php';
const CRT_MODEL = 'app/CashRegister.php';

function crtRead(string $rel): string
{
    return file_get_contents(base_path($rel));
}

// ─── Premissa: model sem global scope → filtro manual é obrigatório ──────────

it('CashRegister não tem global scope (premissa do filtro manual Tier 0)', function () {
    $src = crtRead(CRT_MODEL);
    // Se um dia ganhar global scope, este guard falha e lembra de revisar os filtros manuais.
    expect($src)->not->toMatch('/addGlobalScope|HasBusinessScope|BusinessScope/');
});

// ─── WRITE — postCloseRegister ───────────────────────────────────────────────

it('postCloseRegister filtra business_id no update do caixa (Tier 0)', function () {
    $src = crtRead(CRT_CONTROLLER);
    expect($src)->toMatch("/postCloseRegister[\s\S]*?CashRegister::where\(\s*'business_id'/");
    expect($src)->toMatch("/postCloseRegister[\s\S]*?'business_id'[\s\S]*?->update\(/");
});

it('postCloseRegister deriva business_id da session, nunca do request', function () {
    $src = crtRead(CRT_CONTROLLER);
    expect($src)->toMatch("/postCloseRegister[\s\S]*?session\(\)->get\('user\.business_id'\)/");
});

it('postCloseRegister mantém o gate de permissão close_cash_register', function () {
    $src = crtRead(CRT_CONTROLLER);
    expect($src)->toMatch("/postCloseRegister[\s\S]*?close_cash_register[\s\S]*?abort\(403/");
});

// ─── READ — getRegisterDetails ───────────────────────────────────────────────

it('getRegisterDetails escopa cash_registers.business_id (Tier 0)', function () {
    $src = crtRead(CRT_UTIL);
    expect($src)->toMatch("/getRegisterDetails[\s\S]*?cash_registers\.business_id/");
});

// ─── store() — guard de regressão (já setava business_id da session) ─────────

it('store cria o caixa com business_id da session', function () {
    $src = crtRead(CRT_CONTROLLER);
    expect($src)->toMatch("/function store[\s\S]*?'business_id' => \\\$business_id/");
});
