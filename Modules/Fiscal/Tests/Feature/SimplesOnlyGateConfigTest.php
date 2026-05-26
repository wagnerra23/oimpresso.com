<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * Onda ESTABILIZAR 2026-05-25 — Tests puros do config flag (sem DB).
 *
 * Companheiro de SimplesOnlyGateTest.php (HTTP tests que tocam users table
 * → skipam SQLite). Estes 2 tests rodam SEMPRE — checam que a flag está
 * provisionada no canon correto.
 */

it('flag default = true em produção (segurança audit sênior R1)', function () {
    expect(config('fiscal.sped_simples_only_lock'))->toBeTrue(
        'default deve ser true até GAP-FISCAL-003 eliminar hardcodes',
    );
});

it('config key vive em config/fiscal.php (não em config/app.php nem hardcoded)', function () {
    $contents = file_get_contents(config_path('fiscal.php'));
    expect($contents)->toContain('sped_simples_only_lock')
        ->and($contents)->toContain('FISCAL_SPED_SIMPLES_ONLY_LOCK');
});

it('SpedController referencia a flag no método gerar', function () {
    $src = file_get_contents(
        (new ReflectionClass(\Modules\Fiscal\Http\Controllers\SpedController::class))->getFileName(),
    );
    expect($src)->toContain("config('fiscal.sped_simples_only_lock'")
        ->and($src)->toContain('GAP-FISCAL-003');
});
