<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * Scaffold canônico Accounting — sanity wave 18 (D2.b padrões canônicos).
 *
 * Valida que o esqueleto mínimo do módulo está presente:
 *   - SPEC.md existe
 *   - BRIEFING.md existe
 *   - Config + Routes + Provider canônicos
 *   - Tests Feature dir registrado
 *
 * Padrão ADR 0157 — Scaffold é 1 dos 3 testes canônicos (MultiTenant + Smoke + Scaffold)
 * exigidos pela rubrica módulo-grade v3.
 *
 * @see memory/decisions/0157-pest-coverage-d2-hardened.md
 */

it('Accounting module skeleton — pastas obrigatórias presentes', function () {
    $base = base_path('Modules/Accounting');

    expect(is_dir($base))->toBeTrue('Modules/Accounting/ ausente');
    expect(is_dir("{$base}/Config"))->toBeTrue('Config/ ausente');
    expect(is_dir("{$base}/Entities"))->toBeTrue('Entities/ ausente');
    expect(is_dir("{$base}/Http/Controllers"))->toBeTrue('Http/Controllers/ ausente');
    expect(is_dir("{$base}/Providers"))->toBeTrue('Providers/ ausente');
    expect(is_dir("{$base}/Services"))->toBeTrue('Services/ ausente');
    expect(is_dir("{$base}/Tests/Feature"))->toBeTrue('Tests/Feature/ ausente');
});

it('Accounting module — SPEC.md + BRIEFING.md + CHANGELOG.md canônicos', function () {
    $memBase = base_path('memory/requisitos/Accounting');

    expect(file_exists("{$memBase}/SPEC.md"))->toBeTrue('SPEC.md ausente em memory/requisitos/Accounting');
    expect(file_exists("{$memBase}/BRIEFING.md"))->toBeTrue('BRIEFING.md ausente');
    expect(file_exists("{$memBase}/CHANGELOG.md"))->toBeTrue('CHANGELOG.md ausente');
});

it('Accounting Provider class existe e namespace ok', function () {
    expect(class_exists(\Modules\Accounting\Providers\AccountingServiceProvider::class))
        ->toBeTrue('AccountingServiceProvider class missing');
});

it('Accounting retention.php config existe (LGPD Art. 16 D7.c)', function () {
    expect(file_exists(base_path('Modules/Accounting/Config/retention.php')))
        ->toBeTrue('retention.php missing — LGPD retention policy obrigatória');
});

it('Accounting AccountingHealthCommand está disponível (D9 observability)', function () {
    expect(class_exists(\Modules\Accounting\Console\Commands\AccountingHealthCommand::class))
        ->toBeTrue('AccountingHealthCommand missing');
});
