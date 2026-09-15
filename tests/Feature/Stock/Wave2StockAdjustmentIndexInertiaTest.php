<?php

declare(strict_types=1);

/**
 * F4 QA — Inertia stock_adjustment/index (MWART Wave2 B5).
 *
 * PONTEIRO CONSERTADO EM 2026-09-09: as consts de RUNBOOK e visual-comparison apontavam
 * pra `memory/requisitos/Inventory/`, onde esses arquivos NUNCA estiveram — o material de
 * tela do Estoque vive em `memory/requisitos/Estoque/_telas/`. O diretorio `Inventory/`
 * existe (tem BRIEFING.md e SPEC.md), o que deixou o ponteiro plausivel o bastante pra
 * atravessar revisao. Mesmo defeito, mesma leva (generated_by Agent W2-D, 2026-05-15) e
 * mesmo conserto ja aplicado em Produto (2026-07-26) e Purchase (2026-09-05).
 *
 * E POR QUE o vermelho nunca apareceu: nenhuma lane de PR rodava tests/Feature/Stock/ —
 * medido pelo dono do inventario, test-lane-coverage.mjs, que classificava os 8 arquivos
 * do diretorio como ORFAOS. Este PR liga o diretorio na lane estoque-pest.yml.
 */

const SA_IDX_INERTIA_PATH = 'resources/js/Pages/StockAdjustment/Index.tsx';
const SA_IDX_CHARTER_PATH = 'resources/js/Pages/StockAdjustment/Index.charter.md';
const SA_IDX_CONTROLLER_PATH = 'app/Http/Controllers/StockAdjustmentController.php';
const SA_IDX_RUNBOOK_PATH = 'memory/requisitos/Estoque/_telas/RUNBOOK-stock-adjustment-index.md';
const SA_IDX_VISUAL_PATH = 'memory/requisitos/Estoque/_telas/stock-adjustment-index-visual-comparison.md';

function readSAIndexInertia(): string
{
    return file_get_contents(base_path(SA_IDX_INERTIA_PATH));
}

function readSAControllerInertia(): string
{
    return file_get_contents(base_path(SA_IDX_CONTROLLER_PATH));
}

it('Page Index.tsx existe', function () {
    expect(file_exists(base_path(SA_IDX_INERTIA_PATH)))->toBeTrue();
});

it('Charter + Runbook + Visual existem (ADR 0149 + 0114)', function () {
    expect(file_exists(base_path(SA_IDX_CHARTER_PATH)))->toBeTrue();
    expect(file_exists(base_path(SA_IDX_RUNBOOK_PATH)))->toBeTrue();
    expect(file_exists(base_path(SA_IDX_VISUAL_PATH)))->toBeTrue();
    $charter = file_get_contents(base_path(SA_IDX_CHARTER_PATH));
    expect($charter)->toContain('mwart_pattern_reuse:');
});

it('Page importa AppShellV2 + PageHeader', function () {
    $source = readSAIndexInertia();
    expect($source)->toContain('@/Layouts/AppShellV2');
    expect($source)->toContain('@/Components/shared/PageHeader');
    expect($source)->toMatch('/StockAdjustmentIndex\\.layout\\s*=/');
});

it('Page declara AdjustmentRow + AdjustmentType + Permissions', function () {
    $source = readSAIndexInertia();
    expect($source)->toContain('interface AdjustmentRow');
    expect($source)->toContain("type AdjustmentType");
    expect($source)->toContain('interface Permissions');
});

it('Page distingue o ajuste anormal — badge do DS + trilho por ramificação', function () {
    // Reescrito em 2026-09-09. O assert original procurava as chaves `normal:` / `abnormal:`
    // de um objeto de pill montado à mão; o PR #7079 trocou isso pelo `StatusBadge` do DS
    // mais um trilho condicional com token (`--color-destructive`). Precedência do eixo FORMA
    // (proibicoes.md §5 2026-08-28): o teste que fixou a forma antiga é reescrito, não
    // desabilitado. Ancorado no contrato — os 2 valores do tipo existem, o componente do DS
    // renderiza o estado, e o anormal ganha destaque por ramificação.
    $source = readSAIndexInertia();
    expect($source)->toContain("'normal' | 'abnormal'");
    expect($source)->toContain('StatusBadge');
    expect($source)->toContain("r.adjustment_type === 'abnormal'");
});

it('Page respeita view_purchase_price (esconde valores)', function () {
    $source = readSAIndexInertia();
    expect($source)->toContain('permissions.view_purchase_price');
});

it('Controller index() tem dual path Inertia (?v=2)', function () {
    $source = readSAControllerInertia();
    expect($source)->toContain("Inertia::render('StockAdjustment/Index'");
    expect($source)->toContain('private function indexInertia');
});

it('Controller indexInertia PRESERVA business_id Tier 0 + permitted_locations', function () {
    $source = readSAControllerInertia();
    expect($source)->toContain("'transactions.business_id', \$business_id");
    expect($source)->toContain('permitted_locations');
});

it('Controller indexInertia PRESERVA view_own_purchase ownership scope', function () {
    $source = readSAControllerInertia();
    expect($source)->toContain('view_own_purchase');
});

it('Page NÃO tem business_id hardcoded', function () {
    $source = readSAIndexInertia();
    expect($source)->not->toMatch('/business_id\\s*=\\s*[0-9]+/');
});

it('Controller NÃO usa withoutGlobalScopes sem comentário SUPERADMIN', function () {
    $source = readSAControllerInertia();
    if (str_contains($source, 'withoutGlobalScopes')) {
        expect($source)->toMatch('/SUPERADMIN/i');
    } else {
        expect(true)->toBeTrue();
    }
});
