<?php

declare(strict_types=1);

/**
 * Pest test estrutural — Bug Wagner @ Larissa 2026-05-27:
 * Larissa @ Rota Livre (vestuário, biz=4) vê "Mecânico" no Edit da venda.
 * Causa: SellController populava urls.commission_split pra TODA venda sem
 * filtrar OficinaAuto instalado. CommissionSplitEditor.tsx tem "Mecânico"/
 * "Balconista" hardcoded (ADR 0192 feature OficinaAuto Martinho).
 *
 * Fix canon: usar moduleUtil->isModuleInstalled('OficinaAuto') como gate.
 * Sem OficinaAuto → URL não populada → Edit.tsx:719 condicional falsy →
 * editor nem renderiza.
 */

it('SellController gateia urls.commission_split por isModuleInstalled OficinaAuto', function () {
    $source = file_get_contents(base_path('app/Http/Controllers/SellController.php'));

    // Gate canon presente
    expect($source)->toContain("isModuleInstalled('OficinaAuto')");

    // Especificamente associado ao commission_split URL (não a outro check qualquer)
    // Pattern: `'commission_split' => $this->moduleUtil->isModuleInstalled('OficinaAuto')`
    expect($source)->toMatch(
        "/'commission_split'\s*=>\s*\\\$this->moduleUtil->isModuleInstalled\\('OficinaAuto'\\)/"
    );

    // Garante que NÃO sobrou commission_split URL sem gate (regressão)
    // Pattern bugado antigo: `'commission_split' => '/sells/' . $id . '/commission-split'` direto sem ternary
    expect($source)->not->toMatch(
        "/'commission_split'\s*=>\s*'\\/sells\\/'\s*\\.\s*\\\$id/"
    );
});

it('SellController usa array_filter no urls pra remover commission_split null (vestuário)', function () {
    $source = file_get_contents(base_path('app/Http/Controllers/SellController.php'));

    // array_filter sem callback remove null/false/empty — Larissa biz sem OficinaAuto:
    // commission_split = null → array_filter remove → frontend não recebe a key.
    expect($source)->toContain("'urls' => array_filter([");
});
