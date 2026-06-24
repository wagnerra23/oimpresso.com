<?php

declare(strict_types=1);

// Fixture (ARMING grandfather): teste existe mas NÃO declara @covers-us de nenhuma US →
// US-SLEB-001 fica sem teste-que-cobre. Quem decide se MORDE é o --baseline (bad NÃO isenta).

it('passa mas não diz qual US cobre', function () {
    expect(true)->toBeTrue();
});
