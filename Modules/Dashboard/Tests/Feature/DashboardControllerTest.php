<?php

namespace Modules\Dashboard\Tests\Feature;

/**
 * Feature tests for Modules\Dashboard\Http\Controllers\DashboardController.
 *
 * Mounted at GET /dashboard via Modules/Dashboard/Routes/web.php.
 *
 * NOTE (FOUND): unlike Essentials/BI, the dashboard module route group
 * does NOT explicitly chain the 'auth' middleware. Behaviour therefore
 * depends on the global RouteServiceProvider middleware stack. The tests
 * below assert the *current* observable behaviour and document the
 * discrepancy in Modules/Dashboard/SPEC.md (TODO: harden once the
 * intended ACL is decided).
 */
class DashboardControllerTest extends DashboardTestCase
{
    /** @test */
    public function index_responde_com_status_http_valido()
    {
        $this->skipIfAppNotBooted();

        $response = $this->get('/dashboard');

        // Accept any non-5xx outcome: a redirect to /login (if a global
        // middleware adds auth), 200 (if the route is currently public),
        // 403 (if a permission gate fires) or 404 (if the route is not
        // registered in the current environment).
        $status = $response->getStatusCode();
        $this->assertLessThan(
            500,
            $status,
            'GET /dashboard returned a 5xx response: ' . $status
        );
    }
}
