<?php

namespace Modules\BI\Tests\Feature;

/**
 * Feature tests for Modules\BI\Http\Controllers\InstallController.
 *
 * Routes: GET/POST /bi/install, GET /bi/install/uninstall, GET /bi/install/update.
 * Middleware stack includes 'auth' and 'CheckUserLogin'.
 */
class InstallControllerTest extends BITestCase
{
    /** @test */
    public function index_exige_autenticacao()
    {
        $this->skipIfAppNotBooted();
        $this->assertRedirectsToLogin('/bi/install');
    }

    /** @test */
    public function install_post_exige_autenticacao()
    {
        $this->skipIfAppNotBooted();
        $response = $this->post('/bi/install', []);
        $this->assertContains($response->getStatusCode(), [302, 401, 419]);
    }

    /** @test */
    public function update_exige_autenticacao()
    {
        $this->skipIfAppNotBooted();
        $this->assertRedirectsToLogin('/bi/install/update');
    }

    /** @test */
    public function uninstall_exige_autenticacao()
    {
        $this->skipIfAppNotBooted();
        $this->assertRedirectsToLogin('/bi/install/uninstall');
    }
}
