<?php

namespace Modules\Essentials\Tests\Feature;

/**
 * Feature tests for Modules\Essentials\Http\Controllers\ToDoController.
 *
 * Resource route: essentials/todo (index, create, store, show, edit,
 * update, destroy) plus extra POST/GET helpers.
 */
class ToDoControllerTest extends EssentialsTestCase
{
    /** @test */
    public function index_exige_autenticacao()
    {
        $this->skipIfAppNotBooted();
        $this->assertRedirectsToLogin('/essentials/todo');
    }

    /** @test */
    public function store_exige_autenticacao()
    {
        $this->skipIfAppNotBooted();
        $response = $this->post('/essentials/todo', []);
        $this->assertContains($response->getStatusCode(), [302, 401, 419]);
    }

    /** @test */
    public function update_exige_autenticacao()
    {
        $this->skipIfAppNotBooted();
        $response = $this->put('/essentials/todo/1', []);
        $this->assertContains($response->getStatusCode(), [302, 401, 419]);
    }

    /** @test */
    public function destroy_exige_autenticacao()
    {
        $this->skipIfAppNotBooted();
        $response = $this->delete('/essentials/todo/1');
        $this->assertContains($response->getStatusCode(), [302, 401, 419]);
    }
}
