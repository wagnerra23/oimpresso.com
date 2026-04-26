<?php

namespace Modules\Essentials\Tests\Feature;

/**
 * Feature tests for Modules\Essentials\Http\Controllers\EssentialsHolidayController.
 *
 * Resource route: hrm/holiday (index, store, show, update, destroy).
 * Authorization: superadmin OR module subscription permission, plus the
 * essentials_holidays.business_id tenancy scope (see controller index()).
 */
class EssentialsHolidayControllerTest extends EssentialsTestCase
{
    /** @test */
    public function index_exige_autenticacao()
    {
        $this->skipIfAppNotBooted();
        $this->assertRedirectsToLogin('/hrm/holiday');
    }

    /** @test */
    public function store_exige_autenticacao()
    {
        $this->skipIfAppNotBooted();
        $response = $this->post('/hrm/holiday', []);
        $this->assertContains(
            $response->getStatusCode(),
            [302, 401, 419],
            'Unauthenticated POST /hrm/holiday must be denied.'
        );
    }

    /** @test */
    public function destroy_exige_autenticacao()
    {
        $this->skipIfAppNotBooted();
        $response = $this->delete('/hrm/holiday/1');
        $this->assertContains(
            $response->getStatusCode(),
            [302, 401, 419],
            'Unauthenticated DELETE /hrm/holiday/{id} must be denied.'
        );
    }
}
