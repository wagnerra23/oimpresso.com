<?php

declare(strict_types=1);

use Modules\Governance\Contracts\DriftChecker;
use Modules\Governance\Services\Checkers\RoutesZombieChecker;
use Modules\Governance\Services\DriftCheckResult;

uses(Tests\TestCase::class);

/**
 * RoutesZombieChecker (ADR 0221) — regression guard do fix PHPStan 2026-05-29.
 *
 * O fix trocou `foreach (Route::getRoutes() as $route)` por
 * `foreach (Route::getRoutes()->getRoutes() as $route)`: o facade Route::getRoutes()
 * devolve RouteCollectionInterface (não-Traversable pro PHPStan level 5 →
 * foreach.nonIterable); `->getRoutes()` devolve Route[] iterável. Comportamento
 * idêntico (RouteCollection::getIterator já itera exatamente getRoutes()), mas
 * agora type-safe. Estes testes travam o contrato pra ninguém reverter.
 *
 * @see memory/decisions/0221-routes-zombie-checker-blast-radius.md
 */

it('implementa DriftChecker + registrado em governance.drift_checkers', function () {
    expect(new RoutesZombieChecker())->toBeInstanceOf(DriftChecker::class)
        ->and((new RoutesZombieChecker())->name())->toBe('routes_zombie')
        ->and((array) config('governance.drift_checkers'))->toContain(RoutesZombieChecker::class);
});

it('check() itera as rotas sem crashar (guard do fix getRoutes()->getRoutes())', function () {
    // Antes do fix, foreach sobre o facade (RouteCollectionInterface) era
    // foreach.nonIterable no PHPStan e arriscava TypeError em runtime.
    $result = (new RoutesZombieChecker())->check();

    expect($result)->toBeInstanceOf(DriftCheckResult::class)
        ->and($result->name)->toBe('routes_zombie');

    // snapshotRoutes() contou as rotas registradas. A chave varia conforme a
    // tabela system_access_log exista (routes_scanned) ou não (routes_total no
    // skip path) — em ambos os casos o snapshot rodou e contou > 0.
    $count = $result->metadata['routes_total']
        ?? $result->metadata['routes_scanned']
        ?? 0;

    expect($count)->toBeGreaterThan(0);
});
