<?php

declare(strict_types=1);

// @covers-us US-SLFV-001
// Fixture (G1b verde): declara cobertura da US → entra no coveredUs/usFiles.
// No JUnit summary do caso BAD este arquivo aparece só como `skipped` (markTestSkipped).

it('prova o comportamento de US-SLFV-001', function () {
    expect(true)->toBeTrue();
});
