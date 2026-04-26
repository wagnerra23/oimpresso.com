<?php

namespace Modules\Dashboard\Tests\Feature;

use Tests\TestCase;

/**
 * Base TestCase for the Dashboard module feature tests.
 *
 * Routes live under the "dashboard" prefix.
 * NOTE: in this codebase Modules/Dashboard/Routes/web.php registers
 * GET /dashboard WITHOUT explicit auth middleware — see SPEC.md for
 * details. Tests below document the actual current behaviour.
 */
abstract class DashboardTestCase extends TestCase
{
    protected function skipIfAppNotBooted()
    {
        if (!app()->bound('router')) {
            $this->markTestSkipped('Laravel application not booted in this environment.');
        }
    }
}
