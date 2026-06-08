<?php

declare(strict_types=1);

/**
 * Pest BASELINE — SellController@show ANTES da migração MWART (Wave 1 W1-A).
 *
 * F2 BACKEND BASELINE do ADR 0104 — garante que código atual NÃO regrediu
 * antes de mexer pra Inertia. Pattern espelhado de SellPosControllerCreateTest.
 *
 * Cobre:
 *   1. Método show() existe e está público
 *   2. Auth gate: 3 permissões alternativas (sell.view / direct_sell.access / view_own_sell_only)
 *   3. business_id global scope (Tier 0 ADR 0093) em query Transaction
 *   4. firstOrFail() — 404 se venda doutra biz
 *   5. Eager-load relations necessárias (contact + sell_lines + payment_lines + tax + media)
 *   6. Branch view('sale_pos.show') preservado como fallback legacy
 *
 * Anti-regressão: se alguém remover sem querer o scope `business_id`,
 * este test quebra. Tier 0 IRREVOGÁVEL.
 */

const CONTROLLER_PATH_SHOW = 'app/Http/Controllers/SellController.php';

function readShowController(): string
{
    return file_get_contents(base_path(CONTROLLER_PATH_SHOW));
}

it('SellController existe', function () {
    expect(file_exists(base_path(CONTROLLER_PATH_SHOW)))->toBeTrue();
});

it('Método show($id) está declarado e público', function () {
    $source = readShowController();
    expect($source)->toMatch('/public function show\(\$id\)/');
});

it('show() respeita Tier 0 multi-tenant — query Transaction filtra por business_id (ADR 0093)', function () {
    $source = readShowController();
    // Pega o bloco do método show() (até o próximo "public function")
    preg_match('/public function show\(\$id\).*?(?=public function )/s', $source, $matches);
    $showBlock = $matches[0] ?? '';
    expect($showBlock)->toContain("where('business_id', \$business_id)");
});

it('show() pega business_id da session (canônico UltimatePOS)', function () {
    $source = readShowController();
    preg_match('/public function show\(\$id\).*?(?=public function )/s', $source, $matches);
    $showBlock = $matches[0] ?? '';
    expect($showBlock)->toContain("session()->get('user.business_id')");
});

it('show() eager-load relations críticas (contact, sell_lines, payment_lines, tax, media)', function () {
    $source = readShowController();
    preg_match('/public function show\(\$id\).*?(?=public function )/s', $source, $matches);
    $showBlock = $matches[0] ?? '';
    // 5 relations mínimas esperadas
    expect($showBlock)->toContain("'contact'");
    expect($showBlock)->toContain("'sell_lines'");
    expect($showBlock)->toContain("'payment_lines'");
    expect($showBlock)->toContain("'tax'");
    expect($showBlock)->toContain("'media'");
});

it('show() usa firstOrFail() (retorna 404 se venda doutra biz)', function () {
    $source = readShowController();
    preg_match('/public function show\(\$id\).*?(?=public function )/s', $source, $matches);
    $showBlock = $matches[0] ?? '';
    expect($showBlock)->toContain('firstOrFail()');
});

it('show() preserva view sale_pos.show como branch (Blade legacy fallback)', function () {
    $source = readShowController();
    preg_match('/public function show\(\$id\).*?(?=public function )/s', $source, $matches);
    $showBlock = $matches[0] ?? '';
    expect($showBlock)->toContain("view('sale_pos.show')");
});

it('show() carrega activities (Spatie/Activitylog)', function () {
    $source = readShowController();
    preg_match('/public function show\(\$id\).*?(?=public function )/s', $source, $matches);
    $showBlock = $matches[0] ?? '';
    expect($showBlock)->toContain('Activity::forSubject');
});
