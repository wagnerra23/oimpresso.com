<?php

declare(strict_types=1);

/**
 * Pest BASELINE — SellController@edit ANTES da migração MWART (Wave 1 W1-A).
 *
 * F2 BACKEND BASELINE do ADR 0104. Cobre:
 *   1. Permission gate: direct_sell.update OR so.update
 *   2. canBeEdited($id, $edit_days) guard preservado
 *   3. isReturnExist($id) guard preservado
 *   4. business_id global scope (Tier 0 ADR 0093)
 *   5. findOrFail() em Transaction whereIn type [sell, sales_order]
 *   6. SO precisa permissão so.update separada
 *   7. Branch view('sell.edit') preservado fallback
 *
 * NÃO toca current_stage_id (FSM trait GuardsFsmTransitions ADR 0143).
 */

const CONTROLLER_PATH_EDIT = 'app/Http/Controllers/SellController.php';

function readEditController(): string
{
    return file_get_contents(base_path(CONTROLLER_PATH_EDIT));
}

it('Método edit($id) está declarado e público', function () {
    $source = readEditController();
    expect($source)->toMatch('/public function edit\(\$id\)/');
});

it('edit() bloqueia se sem permissão direct_sell.update E sem so.update (403)', function () {
    $source = readEditController();
    preg_match('/public function edit\(\$id\).*?(?=public function )/s', $source, $matches);
    $editBlock = $matches[0] ?? '';
    expect($editBlock)->toMatch("/can\\('direct_sell\\.update'\\)/");
    expect($editBlock)->toMatch("/can\\('so\\.update'\\)/");
    expect($editBlock)->toContain('abort(403');
});

it('edit() preserva guard canBeEdited (transaction_edit_days)', function () {
    $source = readEditController();
    preg_match('/public function edit\(\$id\).*?(?=public function )/s', $source, $matches);
    $editBlock = $matches[0] ?? '';
    expect($editBlock)->toContain('canBeEdited');
    expect($editBlock)->toContain('transaction_edit_days');
});

it('edit() preserva guard isReturnExist (não pode editar venda com devolução)', function () {
    $source = readEditController();
    preg_match('/public function edit\(\$id\).*?(?=public function )/s', $source, $matches);
    $editBlock = $matches[0] ?? '';
    expect($editBlock)->toContain('isReturnExist');
});

it('edit() respeita Tier 0 multi-tenant (business_id ADR 0093)', function () {
    $source = readEditController();
    preg_match('/public function edit\(\$id\).*?(?=public function )/s', $source, $matches);
    $editBlock = $matches[0] ?? '';
    expect($editBlock)->toContain("where('business_id', \$business_id)");
});

it('edit() restringe Transaction type a [sell, sales_order]', function () {
    $source = readEditController();
    preg_match('/public function edit\(\$id\).*?(?=public function )/s', $source, $matches);
    $editBlock = $matches[0] ?? '';
    expect($editBlock)->toContain("whereIn('type', ['sell', 'sales_order'])");
});

it('edit() usa findorfail($id) (404 se venda doutra biz)', function () {
    $source = readEditController();
    preg_match('/public function edit\(\$id\).*?(?=public function )/s', $source, $matches);
    $editBlock = $matches[0] ?? '';
    expect($editBlock)->toMatch('/findorfail\(\$id\)/i');
});

it('edit() preserva view sell.edit (Blade legacy fallback)', function () {
    $source = readEditController();
    preg_match('/public function edit\(\$id\).*?(?=public function )/s', $source, $matches);
    $editBlock = $matches[0] ?? '';
    expect($editBlock)->toContain("view('sell.edit')");
});

it('edit() NÃO menciona current_stage_id (FSM safety ADR 0143)', function () {
    $source = readEditController();
    preg_match('/public function edit\(\$id\).*?(?=public function )/s', $source, $matches);
    $editBlock = $matches[0] ?? '';
    // Trait GuardsFsmTransitions bloqueia. Edit não deve tentar set direto.
    expect($editBlock)->not->toContain('current_stage_id');
});
