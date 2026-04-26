<?php

namespace Tests\Feature\Modules\Project;

use Modules\Project\Entities\Project;
use Modules\Project\Tests\Feature\ProjectTestCase;

/**
 * CRUD básico do Project — store/update/destroy + endpoint custom postProjectStatus.
 *
 * Routes:
 *   - PUT /project/project/{id}/post-status  ProjectController@postProjectStatus
 *   - PUT /project/project-settings          ProjectController@postSettings
 *   - resource project                        ProjectController
 */
class ProjectCrudTest extends ProjectTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actAsAdmin();
    }

    public function test_store_sem_dados_retorna_validacao(): void
    {
        $response = $this->post('/project/project', []);

        $this->assertContains(
            $response->getStatusCode(),
            [302, 400, 403, 419, 422]
        );
        $this->assertNotEquals(500, $response->getStatusCode());
    }

    public function test_show_id_inexistente_nao_500(): void
    {
        $response = $this->get('/project/project/999999999');

        $this->assertNotEquals(500, $response->getStatusCode());
        $this->assertContains($response->getStatusCode(), [200, 302, 403, 404]);
    }

    public function test_destroy_id_inexistente_nao_500(): void
    {
        $response = $this->delete('/project/project/999999999');

        $this->assertNotEquals(500, $response->getStatusCode());
    }

    public function test_post_status_endpoint_existe(): void
    {
        $response = $this->put('/project/project/999999999/post-status', []);

        // Não deve ser 404 (rota custom existe).
        $this->assertNotEquals(404, $response->getStatusCode(), 'PUT /project/project/{id}/post-status deve existir.');
        $this->assertNotEquals(405, $response->getStatusCode());
    }

    public function test_project_settings_endpoint_existe(): void
    {
        $response = $this->put('/project/project-settings', []);

        $this->assertNotEquals(404, $response->getStatusCode(), 'PUT /project/project-settings deve existir.');
        $this->assertNotEquals(405, $response->getStatusCode());
    }

    public function test_project_model_tabela_tem_business_id(): void
    {
        $this->assertTrue(class_exists(Project::class));

        $project = new Project;
        $table = $project->getTable();

        $this->assertTrue(
            \Schema::hasColumn($table, 'business_id'),
            "Tabela {$table} deve ter business_id para tenancy."
        );
    }
}
