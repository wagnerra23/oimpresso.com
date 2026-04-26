<?php

namespace Modules\Essentials\Tests\Feature;

/**
 * Feature tests for Modules\Essentials\Http\Controllers\DocumentController.
 *
 * Resource route: essentials/document, only [index, store, destroy, show]
 * + GET essentials/document/download/{id}.
 */
class DocumentControllerTest extends EssentialsTestCase
{
    /** @test */
    public function index_exige_autenticacao()
    {
        $this->skipIfAppNotBooted();
        $this->assertRedirectsToLogin('/essentials/document');
    }

    /** @test */
    public function show_exige_autenticacao()
    {
        $this->skipIfAppNotBooted();
        $this->assertRedirectsToLogin('/essentials/document/1');
    }

    /** @test */
    public function store_exige_autenticacao()
    {
        $this->skipIfAppNotBooted();
        $response = $this->post('/essentials/document', []);
        $this->assertContains($response->getStatusCode(), [302, 401, 419]);
    }

    /** @test */
    public function destroy_exige_autenticacao()
    {
        $this->skipIfAppNotBooted();
        $response = $this->delete('/essentials/document/1');
        $this->assertContains($response->getStatusCode(), [302, 401, 419]);
    }

    /** @test */
    public function download_exige_autenticacao()
    {
        $this->skipIfAppNotBooted();
        $this->assertRedirectsToLogin('/essentials/document/download/1');
    }
}
