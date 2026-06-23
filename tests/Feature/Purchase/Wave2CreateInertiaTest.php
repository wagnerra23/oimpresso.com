<?php

declare(strict_types=1);

/**
 * F4 QA — Inertia path purchase/create (MWART Wave2 B5).
 *
 * Estrutural: existência + AppShellV2 + interfaces + Tier 0 preservado.
 * Multi-tenant cross-tenant validado via assertion no Controller (createInertia
 * recebe $business_id por parâmetro, não inventa).
 *
 * ADRs: 0104 (MWART), 0093 (Tier 0), 0149 (pattern reuse).
 */

const CREATE_INERTIA_PATH = 'resources/js/Pages/Purchase/Create.tsx';
const CREATE_CHARTER_PATH = 'resources/js/Pages/Purchase/Create.charter.md';
const CREATE_CONTROLLER_PATH = 'app/Http/Controllers/PurchaseController.php';
// Reconciliado 2026-06-22 (US-COM-005): RUNBOOK/visual agora vivem em Purchase/ — alinha o hook
// block-mwart-violation (que deriva o módulo do path da Page). Antes apontavam pra Inventory/ (inexistente).
const CREATE_RUNBOOK_PATH = 'memory/requisitos/Purchase/RUNBOOK-create.md';
const CREATE_VISUAL_PATH = 'memory/requisitos/Purchase/create-visual-comparison.md';

function readCreateInertia(): string
{
    return file_get_contents(base_path(CREATE_INERTIA_PATH));
}

function readCreateControllerInertia(): string
{
    return file_get_contents(base_path(CREATE_CONTROLLER_PATH));
}

// ─── F3 FRONTEND ─────────────────────────────────────────────────────────────

it('Page Inertia existe em Pages/Purchase/Create.tsx', function () {
    expect(file_exists(base_path(CREATE_INERTIA_PATH)))->toBeTrue();
});

it('Charter Create.charter.md existe ao lado (ADR 0149)', function () {
    expect(file_exists(base_path(CREATE_CHARTER_PATH)))->toBeTrue();
    $content = file_get_contents(base_path(CREATE_CHARTER_PATH));
    expect($content)->toContain('mwart_pattern_reuse:');
    expect($content)->toContain('blueprint_cowork:');
    expect($content)->toContain('derived_screens:');
});

it('RUNBOOK existe em memory/requisitos/Purchase/', function () {
    expect(file_exists(base_path(CREATE_RUNBOOK_PATH)))->toBeTrue();
});

it('Visual comparison existe (gate visual ADR 0114)', function () {
    expect(file_exists(base_path(CREATE_VISUAL_PATH)))->toBeTrue();
});

it('Page importa AppShellV2 + PageHeader (canon V2)', function () {
    $source = readCreateInertia();
    expect($source)->toContain('@/Layouts/AppShellV2');
    expect($source)->toContain('@/Components/shared/PageHeader');
    expect($source)->toMatch('/PurchaseCreate\\.layout\\s*=/');
});

it('Page usa useForm Inertia (form state idiomático)', function () {
    $source = readCreateInertia();
    expect($source)->toContain("useForm");
    expect($source)->toContain("@inertiajs/react");
});

it('Page declara interface PurchaseCreatePageProps + tipos estritos', function () {
    $source = readCreateInertia();
    expect($source)->toContain('interface PurchaseCreatePageProps');
    expect($source)->toContain('interface PurchaseLineDraft');
    expect($source)->toContain('type DiscountType');
});

it('Page tem 4 cards canônicos (dados gerais, itens, totais, notas)', function () {
    $source = readCreateInertia();
    expect($source)->toContain('Dados gerais');
    expect($source)->toContain('Itens da compra');
    expect($source)->toContain('Totais');
    expect($source)->toContain('Notas adicionais');
});

it('Page submete POST /purchases via useForm.post (ENG fix)', function () {
    $source = readCreateInertia();
    expect($source)->toContain("form.post('/purchases'");
    expect($source)->toContain('forceFormData');
});

it('Page tem repeater de itens (state linhas + adicionar/remover)', function () {
    $source = readCreateInertia();
    expect($source)->toContain('linhas');
    expect($source)->toContain('adicionarLinhaVazia');
    expect($source)->toContain('removerLinha');
});

it('Page calcula totais reativos via useMemo', function () {
    $source = readCreateInertia();
    expect($source)->toContain('const totais = useMemo');
    expect($source)->toContain('descontoValor');
    expect($source)->toContain('totais.final');
});

// ─── F4 QA: Controller dual path + Tier 0 ────────────────────────────────────

it('Controller create() tem dual path (Inertia atrás de flag ?v=2 OU header)', function () {
    $source = readCreateControllerInertia();
    expect($source)->toMatch("/header\\('X-Inertia'\\)\\s*\\|\\|\\s*request\\(\\)->query\\('v'\\)\\s*===\\s*'2'/");
    expect($source)->toContain('private function createInertia');
    expect($source)->toContain("Inertia::render('Purchase/Create'");
});

it('Controller createInertia PRESERVA business_id Tier 0 (recebe como param)', function () {
    $source = readCreateControllerInertia();
    expect($source)->toMatch('/createInertia\\(\\s*int \\$business_id/');
});

it('Controller createInertia retorna permissions completas pro Page', function () {
    $source = readCreateControllerInertia();
    expect($source)->toContain("\$user->can('supplier.create')");
    expect($source)->toContain("\$user->can('view_purchase_price')");
});

it('Page NÃO tem business_id hardcoded (Tier 0 IRREVOGÁVEL)', function () {
    $source = readCreateInertia();
    expect($source)->not->toMatch('/business_id\\s*=\\s*[0-9]+/');
});

it('Controller createInertia NÃO usa withoutGlobalScopes sem comentário SUPERADMIN', function () {
    $source = readCreateControllerInertia();
    if (str_contains($source, 'withoutGlobalScopes')) {
        expect($source)->toMatch('/SUPERADMIN/i');
    } else {
        expect(true)->toBeTrue();
    }
});

it('Controller PRESERVA path Blade legacy (dual safe — não substituiu)', function () {
    $source = readCreateControllerInertia();
    expect($source)->toContain("return view('purchase.create')");
});

// ─── US-COM-005: modo grade tam×cor (aditivo, não regride o manual) ──────────

it('Page pluga o modo grade tam×cor (US-COM-005)', function () {
    $source = readCreateInertia();
    expect($source)->toContain('@/Pages/Purchase/_components/GradeMatrixInput');
    expect($source)->toContain('@/Pages/Purchase/_components/GradeProductCombobox');
    expect($source)->toContain('/purchases/grade-matrix');
});

it('Controller expõe gradeMatrix com Tier 0 scope (firstOrFail por business_id)', function () {
    $source = readCreateControllerInertia();
    expect($source)->toContain('public function gradeMatrix');
    expect($source)->toMatch("/Product::where\\('business_id', \\\$business_id\\)\\s*->where\\('id', \\\$product_id\\)\\s*->firstOrFail\\(\\)/");
});
