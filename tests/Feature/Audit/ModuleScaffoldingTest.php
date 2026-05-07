<?php

declare(strict_types=1);

/**
 * GUARD-02 — audit estrutural de Modules/.
 *
 * Garante que cada módulo Laravel modular (nWidart) tem as peças mínimas pra
 * funcionar com o fluxo Install 1-clique do UltimatePOS (ADR 0024) e aparecer
 * no menu admin (DataController hooks).
 *
 * Padrões recorrentes que esse teste pega:
 *   1. Esquecer InstallController → toast "Module [X] does not exist!" ao Install
 *      (incidente Wagner ConsultaOs 2026-05-04).
 *   2. Esquecer DataController → módulo somem do menu admin
 *      (audit 2026-04-26, 9 módulos críticos).
 *   3. ServiceProvider sem Provider class na pasta esperada.
 *
 * Estilo: static analysis (nada boota Laravel) — segue padrão de
 * tests/Feature/Modules/RenameRegressionTest.php.
 */

/**
 * Módulos API-only (MCP tools / sem UI / sem botão Install em /manage-modules).
 * Não precisam de InstallController nem DataController.
 */
const API_ONLY_MODULES = [
    'Brief', // L7 Daily Brief — só expõe tool MCP brief-fetch (ADR 0091)
];

function audit_all_modules(): array
{
    return collect(glob(base_path('Modules/*/module.json')))
        ->map(fn ($p) => basename(dirname($p)))
        ->sort()
        ->values()
        ->all();
}

function audit_ui_modules(): array
{
    return array_values(array_diff(audit_all_modules(), API_ONLY_MODULES));
}

it('todo módulo tem module.json válido com providers[]', function () {
    $violators = [];
    foreach (audit_all_modules() as $m) {
        $path = base_path("Modules/{$m}/module.json");
        $json = json_decode(file_get_contents($path), true);
        if (! is_array($json) || ! isset($json['providers']) || ! is_array($json['providers']) || empty($json['providers'])) {
            $violators[] = $m;
        }
    }

    expect($violators)->toBe(
        [],
        'Módulos com module.json inválido (faltando providers[]): '.implode(', ', $violators)
    );
});

// composer.json per-module é opcional — main composer.json registra Modules\\: Modules/ PSR-4 globalmente.

it('todo módulo tem <Name>ServiceProvider.php', function () {
    $violators = [];
    foreach (audit_all_modules() as $m) {
        if (! file_exists(base_path("Modules/{$m}/Providers/{$m}ServiceProvider.php"))) {
            $violators[] = $m;
        }
    }

    expect($violators)->toBe(
        [],
        'Módulos sem ServiceProvider canônico: '.implode(', ', $violators)
    );
});

it('todo módulo UI tem InstallController.php (sem ele, botão Install vai pra "#")', function () {
    $violators = [];
    foreach (audit_ui_modules() as $m) {
        if (! file_exists(base_path("Modules/{$m}/Http/Controllers/InstallController.php"))) {
            $violators[] = $m;
        }
    }

    expect($violators)->toBe(
        [],
        'Módulos sem InstallController (incidente Wagner ConsultaOs 2026-05-04): '.implode(', ', $violators)
    );
});

it('todo módulo UI tem DataController.php (sem ele, módulo somem do menu admin)', function () {
    $violators = [];
    foreach (audit_ui_modules() as $m) {
        if (! file_exists(base_path("Modules/{$m}/Http/Controllers/DataController.php"))) {
            $violators[] = $m;
        }
    }

    expect($violators)->toBe(
        [],
        'Módulos sem DataController (audit 2026-04-26): '.implode(', ', $violators)
    );
});

it('todo módulo API-only listado existe de verdade', function () {
    $missing = [];
    foreach (API_ONLY_MODULES as $m) {
        if (! file_exists(base_path("Modules/{$m}/module.json"))) {
            $missing[] = $m;
        }
    }

    expect($missing)->toBe(
        [],
        'API_ONLY_MODULES referencia módulos que não existem: '.implode(', ', $missing)
    );
});
