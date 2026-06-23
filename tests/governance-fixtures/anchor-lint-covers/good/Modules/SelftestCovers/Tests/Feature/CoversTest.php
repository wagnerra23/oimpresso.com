<?php

declare(strict_types=1);

// @covers-us US-SLFC-001
// Fixture GOOD (G1a · ADR 0303 emenda): declara a US que cobre → 0 testado_sem_covers.
// Closure Pest (uses(Tests\TestCase::class) + it()) — por isso `covers` é marcador grep,
// não atributo PHP (atributo não anexa a closure it()).

it('prova o comportamento de US-SLFC-001', function () {
    expect(true)->toBeTrue();
});
