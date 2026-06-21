<?php

// FIXTURE (boa) do foundation-ratchet selftest — quarentena COM razão; contadores == baseline.
// NÃO é teste real: vive fora dos roots escaneados (tests/ · Modules/*/Tests/) de propósito.

namespace FoundationRatchetFixtures\Good;

use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * @group legacy-quarantine
 * quarantine-reason: depende de seed global ausente no MySQL do CI — burn-down FV-B1.
 */
class ExemploQuarentenadoTest
{
    use RefreshDatabase;

    public function test_exemplo(): void
    {
        $business = \App\Business::first();
    }
}
