<?php

declare(strict_types=1);

/**
 * ANOTAÇÃO REAL de quarentena — DEVE contar.
 * quarantine-reason: fixture do selftest (anotação de docblock em posição de tag).
 * @group legacy-quarantine
 */
test('real quarantine annotation conta', fn () => expect(true)->toBeTrue());
