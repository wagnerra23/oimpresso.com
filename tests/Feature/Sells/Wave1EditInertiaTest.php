<?php

declare(strict_types=1);

/**
 * Pest INERTIA — Pages/Sells/Edit.tsx + SellController@edit branch Inertia (Wave 1 W1-A).
 *
 * F4 QA do ADR 0104. Cobre:
 *   1. Page + charter existem
 *   2. Persistent Layout AppShellV2
 *   3. Interface SellsEditPageProps + EditFormPayload tipados
 *   4. Controller tem branch X-Inertia em todas as 3 saídas (guard 422 + render)
 *   5. canBeEdited + isReturnExist preservados (422 JSON quando X-Inertia)
 *   6. Inertia::defer pro form payload pesado
 *   7. Tier 0 multi-tenant
 *   8. FSM safety: useForm NÃO tem current_stage_id (ADR 0143)
 *   9. Submit é PUT (RESTful)
 */

const EDIT_PAGE_PATH = 'resources/js/Pages/Sells/Edit.tsx';
const EDIT_CHARTER_PATH = 'resources/js/Pages/Sells/Edit.charter.md';
const EDIT_CONTROLLER_PATH = 'app/Http/Controllers/SellController.php';

function readEditPage(): string
{
    return file_get_contents(base_path(EDIT_PAGE_PATH));
}

function readEditMethod(): string
{
    $source = file_get_contents(base_path(EDIT_CONTROLLER_PATH));
    preg_match('/public function edit\(\$id\).*?(?=public function )/s', $source, $matches);
    return $matches[0] ?? '';
}

it('Page Edit.tsx + Charter Edit.charter.md existem', function () {
    expect(file_exists(base_path(EDIT_PAGE_PATH)))->toBeTrue();
    expect(file_exists(base_path(EDIT_CHARTER_PATH)))->toBeTrue();
});

it('Charter Edit declara mwart_pattern_reuse YAML (ADR 0149)', function () {
    $charter = file_get_contents(base_path(EDIT_CHARTER_PATH));
    expect($charter)->toContain('mwart_pattern_reuse:');
    expect($charter)->toContain('blueprint_cowork:');
});

it('Page Edit usa Persistent Layout AppShellV2', function () {
    $source = readEditPage();
    expect($source)->toContain('@/Layouts/AppShellV2');
    expect($source)->toMatch('/SellsEdit\\.layout\\s*=\\s*\\(page/');
});

it('Page Edit declara SellsEditPageProps + EditFormPayload tipados', function () {
    $source = readEditPage();
    expect($source)->toContain('SellsEditPageProps');
    expect($source)->toContain('EditFormPayload');
    expect($source)->toContain('headline');
    expect($source)->toContain('form?');  // form deferred (opcional inicial)
});

it('Page Edit usa <Deferred data="form"> wrap', function () {
    expect(readEditPage())->toContain('<Deferred data="form"');
});

it('Page Edit NÃO seta current_stage_id no useForm (FSM safety ADR 0143)', function () {
    $source = readEditPage();
    // useForm inicial não pode ter current_stage_id como campo editável.
    expect($source)->not->toMatch('/current_stage_id\s*:/');
});

it('Page Edit usa PUT pra submit (RESTful, urls.submit)', function () {
    expect(readEditPage())->toContain('put(urls.submit');
});

it('Page Edit NÃO usa sessionStorage', function () {
    expect(readEditPage())->not->toContain('sessionStorage');
});

it('Page Edit NÃO usa cor crua proibida', function () {
    expect(readEditPage())->not->toMatch('/bg-(gray|indigo|purple|pink|yellow|red|green)-[0-9]+/');
});

it('Controller edit() tem branch X-Inertia render', function () {
    $method = readEditMethod();
    expect($method)->toContain("request()->header('X-Inertia')");
    expect($method)->toContain("Inertia::render('Sells/Edit'");
});

it('Controller edit() canBeEdited guard retorna 422 JSON quando X-Inertia (back-compat 422)', function () {
    $method = readEditMethod();
    // Tem 2 if (X-Inertia) — um pra canBeEdited, outro pra isReturnExist
    expect($method)->toContain('response()->json([');
    expect($method)->toContain('transaction_edit_not_allowed');
    expect($method)->toContain(', 422');
});

it('Controller edit() isReturnExist guard retorna 422 JSON quando X-Inertia', function () {
    $method = readEditMethod();
    expect($method)->toContain('lang_v1.return_exist');
    expect($method)->toContain(', 422');
});

it('Controller edit() usa Inertia::defer pro form payload (skill inertia-defer-default)', function () {
    expect(readEditMethod())->toContain('Inertia::defer(');
});

it('Controller edit() preserva multi-tenant Tier 0 (ADR 0093)', function () {
    expect(readEditMethod())->toContain("where('business_id', \$business_id)");
});

it('Controller edit() NÃO usa withoutGlobalScopes', function () {
    expect(readEditMethod())->not->toContain('withoutGlobalScopes');
});

it('Controller edit() NÃO menciona current_stage_id (FSM safety)', function () {
    expect(readEditMethod())->not->toContain('current_stage_id');
});

it('Controller edit() permission gate preservado (direct_sell.update OR so.update)', function () {
    $method = readEditMethod();
    expect($method)->toMatch("/can\\('direct_sell\\.update'\\)/");
    expect($method)->toMatch("/can\\('so\\.update'\\)/");
    expect($method)->toContain('abort(403');
});

it('Controller edit() preserva branch Blade legacy view(sell.edit) (back-compat)', function () {
    expect(readEditMethod())->toContain("view('sell.edit')");
});
