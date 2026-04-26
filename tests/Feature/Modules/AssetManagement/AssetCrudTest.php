<?php

namespace Tests\Feature\Modules\AssetManagement;

use Modules\AssetManagement\Entities\Asset;
use Modules\AssetManagement\Tests\Feature\AssetManagementTestCase;

/**
 * CRUD básico de Asset — store/update/destroy via rota resource.
 *
 * Foco: contrato dos endpoints e validação básica. Não popula tabela
 * de fato (sem RefreshDatabase). Valida que:
 *   - POST /asset/assets sem dados → não 500, retorna validação
 *   - GET /asset/assets/{id} com id inexistente → 302/404 (não 500)
 *   - DELETE /asset/assets/{id} idem
 */
class AssetCrudTest extends AssetManagementTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actAsAdmin();
    }

    public function test_store_sem_dados_retorna_validacao(): void
    {
        $response = $this->post('/asset/assets', []);

        // Sem dados/CSRF: 302 (redirect com erros), 422 (validation),
        // 419 (CSRF) ou 403 (perm). NUNCA 500 ou 200 silencioso.
        $this->assertContains(
            $response->getStatusCode(),
            [302, 400, 403, 419, 422]
        );
    }

    public function test_show_de_id_inexistente_nao_500(): void
    {
        $response = $this->get('/asset/assets/999999999');

        $this->assertNotEquals(500, $response->getStatusCode());
        // Pode ser 302 (redirect com erro), 404 (model not found) ou 403.
        $this->assertContains($response->getStatusCode(), [200, 302, 403, 404]);
    }

    public function test_destroy_de_id_inexistente_nao_500(): void
    {
        $response = $this->delete('/asset/assets/999999999');

        $this->assertNotEquals(500, $response->getStatusCode());
    }

    public function test_asset_model_tem_coluna_business_id(): void
    {
        // Schema check: tabela `assets` precisa ter business_id pra tenancy.
        // Asset tem $guarded = ['id'] (mass-assign permitido), então o
        // controller depende do business_id estar na tabela e ser injetado
        // a partir da session.
        $this->assertTrue(
            \Schema::hasColumn('assets', 'business_id'),
            'Tabela assets deve ter coluna business_id para tenancy.'
        );

        // E classe deve existir (sanity).
        $this->assertTrue(class_exists(Asset::class));
    }
}
