<?php

namespace Tests\Feature\Modules\AssetManagement;

use Modules\AssetManagement\Tests\Feature\AssetManagementTestCase;

/**
 * Smoke + auth/redirect das rotas web do AssetManagement (prefix /asset).
 *
 * Stack de middlewares (Modules/AssetManagement/Routes/web.php:3):
 *   ['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone',
 *    'AdminSidebarMenu']
 *
 * Resources (Route::resource):
 *   - assets        → AssetController
 *   - allocation    → AssetAllocationController
 *   - revocation    → RevokeAllocatedAssetController
 *   - settings      → AssetSettingsController
 *   - asset-maintenance → AssetMaitenanceController
 *
 * Guarda contrato: rotas existem, redirect pra login se guest, sem 500
 * com user logado.
 */
class AssetRoutesAuthTest extends AssetManagementTestCase
{
    public function test_guest_redireciona_em_assets_index(): void
    {
        auth()->logout();
        session()->flush();

        $response = $this->get('/asset/assets');

        $this->assertContains($response->getStatusCode(), [302, 401]);
    }

    public function test_guest_redireciona_em_dashboard(): void
    {
        auth()->logout();
        session()->flush();

        $response = $this->get('/asset/dashboard');

        $this->assertContains($response->getStatusCode(), [302, 401]);
    }

    public function test_admin_acessa_assets_index_sem_500(): void
    {
        $this->actAsAdmin();

        $response = $this->get('/asset/assets');

        $this->assertContains($response->getStatusCode(), [200, 302, 403]);
        $this->assertNotEquals(500, $response->getStatusCode());
    }

    public function test_admin_acessa_assets_create_sem_500(): void
    {
        $this->actAsAdmin();

        $response = $this->get('/asset/assets/create');

        $this->assertContains($response->getStatusCode(), [200, 302, 403]);
        $this->assertNotEquals(500, $response->getStatusCode());
        $this->assertNotEquals(404, $response->getStatusCode());
    }

    public function test_admin_acessa_dashboard_sem_500(): void
    {
        $this->actAsAdmin();

        $response = $this->get('/asset/dashboard');

        $this->assertContains($response->getStatusCode(), [200, 302, 403]);
        $this->assertNotEquals(500, $response->getStatusCode());
    }

    public function test_admin_acessa_allocation_index_sem_500(): void
    {
        $this->actAsAdmin();

        $response = $this->get('/asset/allocation');

        $this->assertContains($response->getStatusCode(), [200, 302, 403]);
        $this->assertNotEquals(500, $response->getStatusCode());
    }

    public function test_admin_acessa_revocation_index_sem_500(): void
    {
        $this->actAsAdmin();

        $response = $this->get('/asset/revocation');

        $this->assertContains($response->getStatusCode(), [200, 302, 403]);
        $this->assertNotEquals(500, $response->getStatusCode());
    }

    public function test_admin_acessa_settings_index_sem_500(): void
    {
        $this->actAsAdmin();

        $response = $this->get('/asset/settings');

        $this->assertContains($response->getStatusCode(), [200, 302, 403]);
        $this->assertNotEquals(500, $response->getStatusCode());
    }

    public function test_admin_acessa_asset_maintenance_index_sem_500(): void
    {
        $this->actAsAdmin();

        $response = $this->get('/asset/asset-maintenance');

        $this->assertContains($response->getStatusCode(), [200, 302, 403]);
        $this->assertNotEquals(500, $response->getStatusCode());
    }
}
