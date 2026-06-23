<?php

declare(strict_types=1);

// Fixture BAD (G1a): este teste EXISTE mas NÃO declara @covers-us de nenhuma US.
// É exatamente a brecha do `Testado em: SpatiePermissionsTest` — teste genérico que
// passa o existence-check (não é dead_test) mas não prova nada sobre a US que o cita.

it('passa mas não diz qual US cobre', function () {
    expect(true)->toBeTrue();
});
