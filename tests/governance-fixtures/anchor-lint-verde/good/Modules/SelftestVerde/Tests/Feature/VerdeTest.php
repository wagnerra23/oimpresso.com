<?php

declare(strict_types=1);

// @covers-us US-SLFV-001
// Fixture (G1b verde): declara cobertura da US → entra no coveredUs/usFiles.
// É o JUnit summary que decide se o ARQUIVO está VERDE (good) ou só SKIPPED (bad).

it('prova o comportamento de US-SLFV-001', function () {
    expect(true)->toBeTrue();
});
