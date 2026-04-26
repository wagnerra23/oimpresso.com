<?php

namespace Modules\BI\Tests\Feature;

use Tests\TestCase;

/**
 * Base TestCase for the BI module feature tests.
 *
 * Mirrors Modules/Essentials/Tests/Feature/EssentialsTestCase.php.
 * Routes live under the "bi" prefix and require the "auth" middleware.
 */
abstract class BITestCase extends TestCase
{
    protected function assertRedirectsToLogin($uri)
    {
        $response = $this->get($uri);

        $response->assertStatus(302);

        $location = $response->headers->get('Location') ?? '';
        $this->assertNotEmpty($location, 'Redirect Location header missing for ' . $uri);
        $this->assertMatchesRegularExpression(
            '#/login(\?|/|$)#',
            $location,
            'Expected redirect to /login, got: ' . $location
        );
    }

    protected function skipIfAppNotBooted()
    {
        if (!app()->bound('router')) {
            $this->markTestSkipped('Laravel application not booted in this environment.');
        }
    }
}
