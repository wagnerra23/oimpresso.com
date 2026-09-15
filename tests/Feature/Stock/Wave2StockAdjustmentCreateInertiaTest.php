<?php

declare(strict_types=1);

/**
 * F4 QA — Inertia stock_adjustment/create (MWART Wave2 B5).
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

const SA_CR_INERTIA_PATH = 'resources/js/Pages/StockAdjustment/Create.tsx';
const SA_CR_CHARTER_PATH = 'resources/js/Pages/StockAdjustment/Create.charter.md';
const SA_CR_CONTROLLER_PATH = 'app/Http/Controllers/StockAdjustmentController.php';
const SA_CR_RUNBOOK_PATH = 'memory/requisitos/Estoque/_telas/RUNBOOK-stock-adjustment-create.md';
const SA_CR_VISUAL_PATH = 'memory/requisitos/Estoque/_telas/stock-adjustment-create-visual-comparison.md';

function readSACreateInertia(): string
{
    return file_get_contents(base_path(SA_CR_INERTIA_PATH));
}

function readSACreateControllerInertia(): string
{
    return file_get_contents(base_path(SA_CR_CONTROLLER_PATH));
}

it('Page Create.tsx existe', function () {
    expect(file_exists(base_path(SA_CR_INERTIA_PATH)))->toBeTrue();
});

it('Charter + Runbook + Visual existem (ADR 0149 + 0114)', function () {
    expect(file_exists(base_path(SA_CR_CHARTER_PATH)))->toBeTrue();
    expect(file_exists(base_path(SA_CR_RUNBOOK_PATH)))->toBeTrue();
    expect(file_exists(base_path(SA_CR_VISUAL_PATH)))->toBeTrue();
    $charter = file_get_contents(base_path(SA_CR_CHARTER_PATH));
    expect($charter)->toContain('mwart_pattern_reuse:');
});

it('Page importa AppShellV2 + PageHeader', function () {
    $source = readSACreateInertia();
    expect($source)->toContain('@/Layouts/AppShellV2');
    expect($source)->toContain('@/Components/shared/PageHeader');
    expect($source)->toMatch('/StockAdjustmentCreate\\.layout\\s*=/');
});

it('Page declara AdjustmentLineDraft + AdjustmentType + Permissions interfaces', function () {
    $source = readSACreateInertia();
    expect($source)->toContain('interface AdjustmentLineDraft');
    expect($source)->toContain('type AdjustmentType');
    expect($source)->toContain('interface Permissions');
    expect($source)->toContain('interface StockAdjustmentCreatePageProps');
});

it('Page valida R-ADJ-003 (recovered <= total) client-side', function () {
    $source = readSACreateInertia();
    expect($source)->toContain('recuperadoExcede');
    expect($source)->toContain('AlertCircle');
    expect($source)->toContain('disabled={form.processing || recuperadoExcede}');
});

it('Page destaca o tipo abnormal por ramificação, com token semântico (não cor crua)', function () {
    // Reescrito em 2026-09-09. O assert original travava a cor CRUA
    // `border-rose-300 bg-rose-50/20`, que o PR #7079 substituiu por token semântico ao
    // adotar a forma do protótipo. Pela precedência do eixo FORMA (proibicoes.md §5
    // 2026-08-28), quem perde é o teste que fixou a forma antiga — reescrito, nunca
    // desabilitado. A âncora agora é o CONTRATO ("o anormal se destaca"), não o literal
    // cosmético: a ramificação por tipo + a família de token, que sobrevivem a troca de tom.
    $source = readSACreateInertia();
    expect($source)->toContain("'normal'");
    expect($source)->toContain("'abnormal'");
    expect($source)->toContain("form.data.adjustment_type === 'abnormal'");
    expect($source)->toContain('border-destructive');
});

it('Page respeita view_purchase_price (esconde valor recuperado)', function () {
    $source = readSACreateInertia();
    expect($source)->toContain('permissions.view_purchase_price');
});

it('Page submete POST /stock-adjustments', function () {
    $source = readSACreateInertia();
    expect($source)->toContain("form.post('/stock-adjustments'");
});

it('Controller create() tem dual path Inertia (?v=2)', function () {
    $source = readSACreateControllerInertia();
    expect($source)->toContain("Inertia::render('StockAdjustment/Create'");
    expect($source)->toContain('private function createInertia');
});

it('Controller createInertia PRESERVA business_id Tier 0', function () {
    $source = readSACreateControllerInertia();
    expect($source)->toMatch('/createInertia\\(int \\$business_id/');
});

it('Page NÃO tem business_id hardcoded', function () {
    $source = readSACreateInertia();
    expect($source)->not->toMatch('/business_id\\s*=\\s*[0-9]+/');
});
