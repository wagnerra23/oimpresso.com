<?php

namespace Modules\BI\Tests\Feature;

/**
 * Feature tests for Modules\BI\Http\Controllers\ClientController.
 *
 * Resource route: /bi/client (index, create, store, show, edit, update,
 * destroy) plus GET /bi/regenerate. The controller restricts every
 * action to users with the "superadmin" permission and reads
 * `user.business_id` from the session for tenancy scoping.
 */
class ClientControllerTest extends BITestCase
{
    /** @test */
    public function index_exige_autenticacao()
    {
        $this->skipIfAppNotBooted();
        $this->assertRedirectsToLogin('/bi/client');
    }

    /** @test */
    public function show_exige_autenticacao()
    {
        $this->skipIfAppNotBooted();
        $this->assertRedirectsToLogin('/bi/client/1');
    }

    /** @test */
    public function store_exige_autenticacao()
    {
        $this->skipIfAppNotBooted();
        $response = $this->post('/bi/client', []);
        $this->assertContains($response->getStatusCode(), [302, 401, 419]);
    }

    /** @test */
    public function update_exige_autenticacao()
    {
        $this->skipIfAppNotBooted();
        $response = $this->put('/bi/client/1', []);
        $this->assertContains($response->getStatusCode(), [302, 401, 419]);
    }

    /** @test */
    public function destroy_exige_autenticacao()
    {
        $this->skipIfAppNotBooted();
        $response = $this->delete('/bi/client/1');
        $this->assertContains($response->getStatusCode(), [302, 401, 419]);
    }

    /** @test */
    public function regenerate_exige_autenticacao()
    {
        $this->skipIfAppNotBooted();
        $this->assertRedirectsToLogin('/bi/regenerate');
    }
}
