<?php

declare(strict_types=1);

// Fixture BAD (G1b): teste existe mas NÃO declara @covers-us de nenhuma US →
// a US-SLFE-001 fica sem teste-que-cobre (regra sem teste).

it('passa mas não diz qual US cobre', function () {
    expect(true)->toBeTrue();
});
