<?php

// FIXTURE (ruim) do foundation-ratchet selftest — quarentena sem motivo escrito
// e contadores acima do baseline (tudo 0). O gate TEM que ficar vermelho aqui.

namespace FoundationRatchetFixtures\Bad;

use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * @group legacy-quarantine
 */
class QuarentenaSemRazaoTest
{
    use RefreshDatabase;

    public function test_exemplo(): void
    {
        $business = \App\Business::first();
    }
}
