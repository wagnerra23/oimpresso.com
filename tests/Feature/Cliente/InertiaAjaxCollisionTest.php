<?php

declare(strict_types=1);

// Fix 2026-05-21 — Inertia partial reload colidindo com `request()->ajax()` legacy.
//
// Contexto: ContactController@index linha 187 (e 7 outros métodos) tinham
// `if (request()->ajax()) { return DataTable JSON }` ANTES do branch Inertia render.
// Inertia partial reload (X-Inertia: true) também seta X-Requested-With: XMLHttpRequest,
// fazendo request()->ajax() retornar true → caía no DataTable branch → JSON cru →
// Inertia client erra "All Inertia requests must receive a valid Inertia response".
//
// Fix: helper privado `isLegacyAjax()` que adiciona `! request()->hasHeader('X-Inertia')`.

test('ContactController — helper isLegacyAjax existe e exclui X-Inertia header', function () {
    $controllerPath = __DIR__ . '/../../../app/Http/Controllers/ContactController.php';
    $contents = file_get_contents($controllerPath);

    expect($contents)
        ->toContain('private function isLegacyAjax(): bool')
        ->toContain("hasHeader('X-Inertia')")
        ->toContain('return request()->ajax() && ! request()->hasHeader');
});

test('ContactController — TODAS as chamadas ajax usam isLegacyAjax() (zero usos diretos)', function () {
    $controllerPath = __DIR__ . '/../../../app/Http/Controllers/ContactController.php';
    $contents = file_get_contents($controllerPath);

    // Remove comentários multi-line + single-line antes de procurar
    $stripped = preg_replace('#/\*.*?\*/#s', '', $contents);
    $stripped = preg_replace('#//.*$#m', '', $stripped);

    // O ÚNICO uso direto permitido é dentro do helper isLegacyAjax() em si.
    // Conta `request()->ajax()` no código stripped — deve ser exatamente 1.
    $directCalls = substr_count($stripped, 'request()->ajax()');

    expect($directCalls)->toBe(1, 'Esperado exatamente 1 uso direto de request()->ajax() (dentro do helper isLegacyAjax). Encontrado: ' . $directCalls);
});

test('ContactController — 8+ branches usam isLegacyAjax (paridade com legacy ajax checks)', function () {
    $controllerPath = __DIR__ . '/../../../app/Http/Controllers/ContactController.php';
    $contents = file_get_contents($controllerPath);

    // Conta chamadas $this->isLegacyAjax(). Pre-fix havia 8 branches `if (request()->ajax())`.
    // Pós-fix devem virar 8 `if ($this->isLegacyAjax())`.
    $helperCalls = substr_count($contents, '$this->isLegacyAjax()');

    expect($helperCalls)->toBeGreaterThanOrEqual(8, "Esperado ≥8 usos de \$this->isLegacyAjax() (paridade com branches ajax legacy). Encontrado: {$helperCalls}");
});
