<?php

namespace Modules\BI\Tests\Feature;

/**
 * Feature tests for Modules\BI\Http\Controllers\BIController.
 *
 * Mounted at GET /bi/api (web + auth + ...). See Modules/BI/Http/routes.php.
 */
class BIControllerTest extends BITestCase
{
    /** @test */
    public function index_exige_autenticacao()
    {
        $this->skipIfAppNotBooted();
        $this->assertRedirectsToLogin('/bi/api');
    }
}
