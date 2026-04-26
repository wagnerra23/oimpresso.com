<?php

namespace Tests\Feature\Modules\Project;

use Modules\Project\Tests\Feature\ProjectTestCase;

/**
 * Smoke + auth/redirect das rotas web do Project (prefix /project).
 *
 * Stack de middlewares (Modules/Project/Routes/web.php:6):
 *   ['web', 'authh', 'SetSessionData', 'auth', 'language', 'timezone',
 *    'AdminSidebarMenu']
 *
 * Resources (Route::resource):
 *   - project              → ProjectController
 *   - project-task         → TaskController
 *   - project-task-comment → TaskCommentController
 *   - project-task-time-logs → ProjectTimeLogController
 *   - activities           → ActivityController (only index)
 *   - invoice              → InvoiceController
 *
 * Guarda contrato: rotas existem, redirect pra login se guest, sem 500
 * com user logado.
 */
class ProjectRoutesAuthTest extends ProjectTestCase
{
    public function test_guest_redireciona_em_project_index(): void
    {
        auth()->logout();
        session()->flush();

        $response = $this->get('/project/project');

        $this->assertContains($response->getStatusCode(), [302, 401]);
    }

    public function test_guest_redireciona_em_task_index(): void
    {
        auth()->logout();
        session()->flush();

        $response = $this->get('/project/project-task');

        $this->assertContains($response->getStatusCode(), [302, 401]);
    }

    public function test_guest_redireciona_em_invoice_index(): void
    {
        auth()->logout();
        session()->flush();

        $response = $this->get('/project/invoice');

        $this->assertContains($response->getStatusCode(), [302, 401]);
    }

    public function test_admin_acessa_project_index_sem_500(): void
    {
        $this->actAsAdmin();

        $response = $this->get('/project/project');

        $this->assertContains($response->getStatusCode(), [200, 302, 403]);
        $this->assertNotEquals(500, $response->getStatusCode());
    }

    public function test_admin_acessa_project_create_sem_500(): void
    {
        $this->actAsAdmin();

        $response = $this->get('/project/project/create');

        $this->assertContains($response->getStatusCode(), [200, 302, 403]);
        $this->assertNotEquals(500, $response->getStatusCode());
        $this->assertNotEquals(404, $response->getStatusCode());
    }

    public function test_admin_acessa_task_index_sem_500(): void
    {
        $this->actAsAdmin();

        $response = $this->get('/project/project-task');

        $this->assertContains($response->getStatusCode(), [200, 302, 403]);
        $this->assertNotEquals(500, $response->getStatusCode());
    }

    public function test_admin_acessa_invoice_index_sem_500(): void
    {
        $this->actAsAdmin();

        $response = $this->get('/project/invoice');

        $this->assertContains($response->getStatusCode(), [200, 302, 403]);
        $this->assertNotEquals(500, $response->getStatusCode());
    }

    public function test_admin_acessa_activities_index_sem_500(): void
    {
        $this->actAsAdmin();

        $response = $this->get('/project/activities');

        $this->assertContains($response->getStatusCode(), [200, 302, 403]);
        $this->assertNotEquals(500, $response->getStatusCode());
    }

    public function test_admin_acessa_project_task_time_logs_sem_500(): void
    {
        $this->actAsAdmin();

        $response = $this->get('/project/project-task-time-logs');

        $this->assertContains($response->getStatusCode(), [200, 302, 403]);
        $this->assertNotEquals(500, $response->getStatusCode());
    }
}
