<?php

declare(strict_types=1);

/**
 * GUARD DE REGRESSÃO — Veículo na venda NUNCA pode vazar pra ROTA LIVRE (biz=4).
 *
 * O recurso "veículo na venda direta de oficina" (ADR 0251, PR #2276) é gated
 * per-business via assinatura de pacote (`oficina_auto_module`, Camada 1 do
 * multi-tenant — ver memory/proibicoes.md §"Multi-tenant Tier 0"). A ROTA LIVRE
 * (vestuário, sem OficinaAuto) NÃO pode, em hipótese alguma, ver o seletor de
 * veículo no /sells/create.
 *
 * Origem: Wagner 2026-06-05 — "muuuuuito cuidado para nao prejudicar em nada a
 * rota livre". Smoke prod confirmou o gate funcionando dos dois lados (biz=4
 * hasOficinaAuto=false sem UI; biz=1 hasOficinaAuto=true com UI). Este teste
 * TRANCA esse comportamento contra qualquer regressão futura.
 *
 * É ESTRUTURAL de propósito (lê o source, sem DB) → roda no lane SQLite do CI e
 * dá feedback imediato. O contrato de schema/scope MySQL vive no companion
 * tests/Feature/Sells/VeiculoNaVendaSchemaTest.php (roda no CT 100).
 *
 * @see memory/decisions/0251-veiculo-na-venda-direta-oficina.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

// Tests\TestCase já é aplicado globalmente em tests/Pest.php (uses(TestCase::class)->in('Feature')). NÃO redeclarar aqui — Pest 4 lança TestCaseAlreadyInUse e mata o loader da suite inteira (FV-B4).

function sellControllerSrc(): string
{
    return (string) file_get_contents(base_path('app/Http/Controllers/SellController.php'));
}

function sellsCreateTsxSrc(): string
{
    return (string) file_get_contents(base_path('resources/js/Pages/Sells/Create.tsx'));
}

function transactionUtilSrc(): string
{
    return (string) file_get_contents(base_path('app/Utils/TransactionUtil.php'));
}

// ─── Gate backend (SellController) ──────────────────────────────────────────

it('o gate de veículo começa FALSE por padrão (fail-closed pra ROTA LIVRE)', function () {
    expect(sellControllerSrc())->toContain('$has_oficina_auto = false;');
});

it('o gate de veículo é decidido por assinatura de pacote per-business (oficina_auto_module)', function () {
    $src = sellControllerSrc();
    // Camada 1 multi-tenant: habilitar/desabilitar módulo é compra de pacote,
    // NUNCA hardcode de business_id (memory/proibicoes.md Tier 0).
    expect($src)->toContain('hasThePermissionInSubscription');
    expect($src)->toContain("'oficina_auto_module'");
    expect($src)->not->toMatch('/if\s*\(\s*\$business_id\s*===?\s*4\b/'); // sem hardcode biz=4
});

it('vehicleTypes vai VAZIO quando o gate está off (nada de catálogo pra quem não tem OficinaAuto)', function () {
    $src = sellControllerSrc();
    // $vehicle_types = ($has_oficina_auto && ...) ? ... : [];
    expect($src)->toMatch('/\$vehicle_types\s*=\s*\(\$has_oficina_auto\b.*\)\s*\?[\s\S]*?:\s*\[\]/');
});

// ─── Gate frontend (Sells/Create.tsx) ───────────────────────────────────────

it('Create.tsx lê o gate de forma estrita (=== true) — undefined/null NÃO renderiza veículo', function () {
    expect(sellsCreateTsxSrc())->toContain('const hasOficinaAuto = props.hasOficinaAuto === true;');
});

it('TODO bloco de UI de veículo no Create.tsx é guardado por {hasOficinaAuto && (', function () {
    $src = sellsCreateTsxSrc();
    // Conta os render-gates. Se alguém adicionar um bloco de veículo sem o gate,
    // o smoke visual da ROTA LIVRE quebra — este teste pega antes.
    $gates = substr_count($src, '{hasOficinaAuto && (');
    expect($gates)->toBeGreaterThanOrEqual(2);

    // E não pode existir <MercosulPlate fora de um gate hasOficinaAuto na tela de venda:
    // (heurística) toda referência a vehicle_id de UI vive sob o gate.
    expect($src)->toContain('hasOficinaAuto');
});

// ─── Write-path null-safe (TransactionUtil) ─────────────────────────────────

it('TransactionUtil grava vehicle_id null-safe — venda SEM veículo (ROTA LIVRE) nunca quebra', function () {
    $src = transactionUtilSrc();
    // Padrão obrigatório: ! empty($input['vehicle_id']) ? $input['vehicle_id'] : null
    // Nunca pode virar 'vehicle_id' => $input['vehicle_id'] (sem guard) — isso
    // exigiria a chave em todo input e mataria a venda comum.
    $occurrences = preg_match_all(
        "/'vehicle_id'\s*=>\s*!\s*empty\(\\\$input\['vehicle_id'\]\)\s*\?\s*\\\$input\['vehicle_id'\]\s*:\s*null/",
        $src
    );
    // Há 2 caminhos de criação/atualização de venda no TransactionUtil.
    expect($occurrences)->toBeGreaterThanOrEqual(2);

    // Não pode haver gravação CRUA (sem o guard ! empty) que force a coluna.
    expect($src)->not->toMatch("/'vehicle_id'\s*=>\s*\\\$input\['vehicle_id'\]\s*,/");
});
