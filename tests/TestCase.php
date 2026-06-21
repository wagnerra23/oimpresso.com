<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\WithSeededTenant;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use WithSeededTenant;

    protected function setUp(): void
    {
        parent::setUp();

        // (US-GOV-018 A.2 FULLSUITE_FK_OFF removido — REVERTIDO em US-GOV-020 por net-harmful;
        //  era dead-code: a flag nunca mais é setada. Ledger §E.)
    }
}
