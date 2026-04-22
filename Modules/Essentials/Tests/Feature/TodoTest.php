<?php

namespace Modules\Essentials\Tests\Feature;

use Modules\Essentials\Entities\ToDo;

class TodoTest extends EssentialsTestCase
{
    /** @test */
    public function index_exige_autenticacao(): void
    {
        $this->get('/essentials/todo')->assertRedirect('/login');
    }

    /** @test */
    public function index_retorna_inertia_com_estrutura_esperada(): void
    {
        $this->actAsAdmin();
        $response = $this->inertiaGet('/essentials/todo');

        $this->assertInertiaComponent($response, 'Essentials/Todo/Index');

        $props = $response->json('props');
        $this->assertArrayHasKey('todos', $props);
        $this->assertArrayHasKey('filtros', $props);
        $this->assertArrayHasKey('assignableUsers', $props);
        $this->assertArrayHasKey('statuses', $props);
        $this->assertArrayHasKey('priorities', $props);
        $this->assertArrayHasKey('can', $props);

        // Statuses tem 4 ("new", "in_progress", "on_hold", "completed")
        $statusValues = array_column($props['statuses'], 'value');
        $this->assertEqualsCanonicalizing(
            ['new', 'in_progress', 'on_hold', 'completed'],
            $statusValues
        );

        // Priorities tem 4 ("low", "medium", "high", "urgent")
        $priorityValues = array_column($props['priorities'], 'value');
        $this->assertEqualsCanonicalizing(
            ['low', 'medium', 'high', 'urgent'],
            $priorityValues
        );

        // Paginator Laravel padrão
        $this->assertArrayHasKey('data', $props['todos']);
        $this->assertArrayHasKey('current_page', $props['todos']);
        $this->assertArrayHasKey('last_page', $props['todos']);
    }

    /** @test */
    public function filtro_por_status_preservado_nos_props(): void
    {
        $this->actAsAdmin();
        $response = $this->inertiaGet('/essentials/todo', ['status' => 'completed']);

        $this->assertInertiaComponent($response, 'Essentials/Todo/Index');
        $this->assertEquals('completed', $response->json('props.filtros.status'));
    }

    /** @test */
    public function filtro_por_priority_preservado_nos_props(): void
    {
        $this->actAsAdmin();
        $response = $this->inertiaGet('/essentials/todo', ['priority' => 'urgent']);

        $this->assertEquals('urgent', $response->json('props.filtros.priority'));
    }

    /** @test */
    public function create_retorna_inertia_com_props_necessarios(): void
    {
        $this->actAsAdmin();
        $response = $this->inertiaGet('/essentials/todo/create');

        $this->assertInertiaComponent($response, 'Essentials/Todo/Create');

        $props = $response->json('props');
        $this->assertArrayHasKey('users', $props);
        $this->assertArrayHasKey('statuses', $props);
        $this->assertArrayHasKey('priorities', $props);
        $this->assertArrayHasKey('can', $props);
    }

    /** @test */
    public function store_exige_campos_obrigatorios(): void
    {
        $this->actAsAdmin();

        $response = $this->post('/essentials/todo', [], [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['task', 'date']);
    }

    /** @test */
    public function store_cria_tarefa_e_redireciona_para_show(): void
    {
        $admin = $this->actAsAdmin();
        $before = ToDo::where('business_id', $this->business->id)->count();

        $response = $this->post('/essentials/todo', [
            'task'        => 'Tarefa de teste automatizado',
            'date'        => now()->format('Y-m-d'),
            'priority'    => 'medium',
            'status'      => 'new',
            'description' => 'Criada via TodoTest',
        ]);

        $response->assertStatus(302);
        $after = ToDo::where('business_id', $this->business->id)->count();
        $this->assertSame($before + 1, $after, 'Deveria ter criado 1 tarefa');

        // Cleanup
        ToDo::where('business_id', $this->business->id)
            ->where('task', 'Tarefa de teste automatizado')
            ->delete();
    }

    /** @test */
    public function add_comment_valida_campos(): void
    {
        $this->actAsAdmin();

        $response = $this->post('/essentials/todo/add-comment', [], [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['task_id', 'comment']);
    }

    /** @test */
    public function delete_document_requer_autenticacao(): void
    {
        $response = $this->get('/essentials/todo/delete-document/1');
        $response->assertRedirect('/login');
    }

    /** @test */
    public function view_shared_docs_retorna_json_para_ajax(): void
    {
        $admin = $this->actAsAdmin();

        $todo = ToDo::create([
            'business_id' => $this->business->id,
            'created_by'  => $admin->id,
            'task'        => 'Teste view shared docs',
            'date'        => now(),
            'status'      => 'new',
            'task_id'     => 'TEST-SHARED-DOCS',
        ]);

        $response = $this->get("/essentials/view-todo-{$todo->id}-share-docs", [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept'           => 'application/json',
        ]);

        $response->assertStatus(200);
        $json = $response->json();
        $this->assertArrayHasKey('sheets', $json);
        $this->assertIsArray($json['sheets']);

        // Cleanup
        $todo->delete();
    }
}
