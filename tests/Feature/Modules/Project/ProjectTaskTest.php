<?php

namespace Tests\Feature\Modules\Project;

use Modules\Project\Tests\Feature\ProjectTestCase;

/**
 * Smoke das rotas /project/project-task/* — TaskController + endpoints custom.
 *
 * Routes (Modules/Project/Routes/web.php:10-15):
 *   - resource project-task                       TaskController
 *   - GET project-task-get-status                 TaskController@getTaskStatus
 *   - PUT project-task/{id}/post-status           TaskController@postTaskStatus
 *   - PUT project-task/{id}/post-description      TaskController@postTaskDescription
 *   - resource project-task-comment               TaskCommentController
 *   - POST post-media-dropzone-upload             TaskCommentController@postMedia
 */
class ProjectTaskTest extends ProjectTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actAsAdmin();
    }

    public function test_task_index_sem_500(): void
    {
        $response = $this->get('/project/project-task');

        $this->assertContains($response->getStatusCode(), [200, 302, 403]);
        $this->assertNotEquals(500, $response->getStatusCode());
    }

    public function test_task_create_sem_500(): void
    {
        $response = $this->get('/project/project-task/create');

        $this->assertContains($response->getStatusCode(), [200, 302, 403]);
        $this->assertNotEquals(500, $response->getStatusCode());
        $this->assertNotEquals(404, $response->getStatusCode());
    }

    public function test_task_store_sem_dados_validacao(): void
    {
        $response = $this->post('/project/project-task', []);

        $this->assertContains(
            $response->getStatusCode(),
            [302, 400, 403, 419, 422]
        );
        $this->assertNotEquals(500, $response->getStatusCode());
    }

    public function test_task_get_status_endpoint_existe(): void
    {
        $response = $this->get('/project/project-task-get-status');

        $this->assertNotEquals(404, $response->getStatusCode(), 'GET /project/project-task-get-status deve existir.');
        $this->assertNotEquals(500, $response->getStatusCode());
    }

    public function test_task_post_status_endpoint_existe(): void
    {
        $response = $this->put('/project/project-task/999999999/post-status', []);

        $this->assertNotEquals(404, $response->getStatusCode());
        $this->assertNotEquals(405, $response->getStatusCode());
    }

    public function test_task_post_description_endpoint_existe(): void
    {
        $response = $this->put('/project/project-task/999999999/post-description', []);

        $this->assertNotEquals(404, $response->getStatusCode());
        $this->assertNotEquals(405, $response->getStatusCode());
    }

    public function test_task_comment_index_sem_500(): void
    {
        $response = $this->get('/project/project-task-comment');

        $this->assertContains($response->getStatusCode(), [200, 302, 403, 405]);
        $this->assertNotEquals(500, $response->getStatusCode());
    }

    public function test_post_media_dropzone_upload_endpoint_existe(): void
    {
        $response = $this->post('/project/post-media-dropzone-upload', []);

        $this->assertNotEquals(404, $response->getStatusCode(), 'POST /project/post-media-dropzone-upload deve existir.');
        $this->assertNotEquals(405, $response->getStatusCode());
    }
}
