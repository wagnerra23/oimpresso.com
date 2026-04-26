<?php

namespace Modules\Essentials\Tests\Feature;

/**
 * Feature tests for Modules\Essentials\Http\Controllers\AttendanceController.
 *
 * Resource route: hrm/attendance + clock-in/clock-out POST endpoints.
 */
class AttendanceControllerTest extends EssentialsTestCase
{
    /** @test */
    public function index_exige_autenticacao()
    {
        $this->skipIfAppNotBooted();
        $this->assertRedirectsToLogin('/hrm/attendance');
    }

    /** @test */
    public function clock_in_clock_out_exige_autenticacao()
    {
        $this->skipIfAppNotBooted();
        $response = $this->post('/hrm/clock-in-clock-out', []);
        $this->assertContains($response->getStatusCode(), [302, 401, 419]);
    }
}
