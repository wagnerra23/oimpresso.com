<?php

namespace Tests\Feature\Modules\AssetManagement;

use Modules\AssetManagement\Tests\Feature\AssetManagementTestCase;

/**
 * Smoke das rotas /asset/asset-maintenance/* — AssetMaitenanceController.
 * (sic — typo é no nome da classe upstream do UltimatePOS)
 *
 * Routes: Modules/AssetManagement/Routes/web.php:15
 * Resource padrão Laravel: index, create, store, show, edit, update, destroy.
 */
class AssetMaintenanceTest extends AssetManagementTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actAsAdmin();
    }

    public function test_index_responde_sem_500(): void
    {
        $response = $this->get('/asset/asset-maintenance');

        $this->assertContains($response->getStatusCode(), [200, 302, 403]);
        $this->assertNotEquals(500, $response->getStatusCode());
    }

    public function test_create_responde_sem_500(): void
    {
        $response = $this->get('/asset/asset-maintenance/create');

        $this->assertContains($response->getStatusCode(), [200, 302, 403]);
        $this->assertNotEquals(500, $response->getStatusCode());
        $this->assertNotEquals(404, $response->getStatusCode());
    }

    public function test_store_sem_dados_retorna_validacao(): void
    {
        $response = $this->post('/asset/asset-maintenance', []);

        $this->assertContains(
            $response->getStatusCode(),
            [302, 400, 403, 419, 422]
        );
        $this->assertNotEquals(500, $response->getStatusCode());
    }

    public function test_show_id_inexistente_nao_500(): void
    {
        $response = $this->get('/asset/asset-maintenance/999999999');

        $this->assertNotEquals(500, $response->getStatusCode());
        $this->assertContains($response->getStatusCode(), [200, 302, 403, 404]);
    }
}
