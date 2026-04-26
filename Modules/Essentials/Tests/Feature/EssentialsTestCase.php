<?php

namespace Modules\Essentials\Tests\Feature;

use Tests\TestCase;

/**
 * Base TestCase for the Essentials module feature tests.
 *
 * Conservative + TODO style: helpers are intentionally minimal so the
 * tests can run on environments that don't yet have a populated DB.
 * Tests that need DB / business_id wiring should fall back to skip()
 * when the prerequisites are not present.
 */
abstract class EssentialsTestCase extends TestCase
{
    /**
     * Routes inside Modules/Essentials/Http/routes.php live under the
     * "essentials" or "hrm" prefix and require the "auth" middleware.
     *
     * Helper to assert that an unauthenticated GET to a module route is
     * redirected away (HTTP 302) to the login screen.
     */
    protected function assertRedirectsToLogin($uri)
    {
        $response = $this->get($uri);

        $response->assertStatus(302);

        // Either named 'login' route or any /login URL is acceptable.
        $location = $response->headers->get('Location') ?? '';
        $this->assertNotEmpty($location, 'Redirect Location header missing for ' . $uri);
        $this->assertMatchesRegularExpression(
            '#/login(\?|/|$)#',
            $location,
            'Expected redirect to /login, got: ' . $location
        );
    }

    /**
     * Skip the test if the Laravel application failed to bootstrap
     * (e.g. missing vendor/autoload.php or DB connection).
     */
    protected function skipIfAppNotBooted()
    {
        if (!app()->bound('router')) {
            $this->markTestSkipped('Laravel application not booted in this environment.');
        }
    }
}
