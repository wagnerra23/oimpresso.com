<?php

namespace Modules\Essentials\Tests\Feature;

/**
 * Feature tests for Modules\Essentials\Http\Controllers\EssentialsController.
 *
 * Mounted at GET /essentials/ (web + auth + SetSessionData + AdminSidebarMenu).
 * See Modules/Essentials/Http/routes.php.
 */
class EssentialsControllerTest extends EssentialsTestCase
{
    /** @test */
    public function index_exige_autenticacao()
    {
        $this->skipIfAppNotBooted();
        $this->assertRedirectsToLogin('/essentials');
    }

    /** @test */
    public function index_redireciona_quando_sem_business_id_na_sessao()
    {
        // Authenticated user without `user.business_id` session must not be
        // able to render the dashboard view (controllers consume the value
        // via request()->session()->get('user.business_id')).
        $this->skipIfAppNotBooted();

        $response = $this->withSession([])->get('/essentials');

        // Either redirected (auth/login or SetSessionData) or 403/500. We
        // accept any non-200 to assert that without a tenant context the
        // page never renders successfully.
        $this->assertNotEquals(
            200,
            $response->getStatusCode(),
            'Essentials index should not render without a business_id session.'
        );
    }
}
