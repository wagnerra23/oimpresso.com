<?php

namespace Modules\Essentials\Tests\Feature;

use Modules\Essentials\Entities\ToDo;

class TodoTest extends EssentialsTestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function index_exige_autenticacao(): void
    {
        $this->get('/essentials/todo')->assertRedirect('/login');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function index_retorna_inertia_com_estrutura_esperada(): void
    {
        $this->actAsAdmin();
        $response = $this->inertiaGet('/essentials/todo');

        $this->assertInertiaComponent($response, 'Essentials/Todo/Index');

        $props = $response->json('props');
        // `todos` e `assignableUsers` sao Inertia::defer: na 1a requisicao a prop nem
        // existe no payload — cobra-las aqui fazia o teste falhar desde que o defer
        // entrou (Wave 25 D6.a). Elas sao conferidas abaixo, com partial reload.
        $this->assertArrayHasKey('filtros', $props);
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

        // Paginator Laravel padrão — só chega com partial reload (defer)
        $parcial = $this->withHeaders([
            'X-Inertia'                   => 'true',
            'X-Inertia-Version'           => $this->currentInertiaVersion() ?? '',
            'X-Inertia-Partial-Data'      => 'todos,assignableUsers',
            'X-Inertia-Partial-Component' => 'Essentials/Todo/Index',
            'Accept'                      => 'text/html',
        ])->get('/essentials/todo')->json('props');

        $this->assertArrayHasKey('todos', $parcial);
        $this->assertArrayHasKey('assignableUsers', $parcial);
        $this->assertArrayHasKey('data', $parcial['todos']);
        $this->assertArrayHasKey('current_page', $parcial['todos']);
        $this->assertArrayHasKey('last_page', $parcial['todos']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function filtro_por_status_preservado_nos_props(): void
    {
        $this->actAsAdmin();
        $response = $this->inertiaGet('/essentials/todo', ['status' => 'completed']);

        $this->assertInertiaComponent($response, 'Essentials/Todo/Index');
        $this->assertEquals('completed', $response->json('props.filtros.status'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function filtro_por_priority_preservado_nos_props(): void
    {
        $this->actAsAdmin();
        $response = $this->inertiaGet('/essentials/todo', ['priority' => 'urgent']);

        $this->assertEquals('urgent', $response->json('props.filtros.priority'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
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

    #[\PHPUnit\Framework\Attributes\Test]
    public function store_exige_campos_obrigatorios(): void
    {
        $this->actAsAdmin();

        $response = $this->post('/essentials/todo', [], [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['task', 'date']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
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

    #[\PHPUnit\Framework\Attributes\Test]
    public function add_comment_valida_campos(): void
    {
        $this->actAsAdmin();

        $response = $this->post('/essentials/todo/add-comment', [], [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['task_id', 'comment']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function delete_document_requer_autenticacao(): void
    {
        $response = $this->get('/essentials/todo/delete-document/1');
        $response->assertRedirect('/login');
    }

    #[\PHPUnit\Framework\Attributes\Test]
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

    #[\PHPUnit\Framework\Attributes\Test]
    public function busca_encontra_por_texto_da_tarefa_e_por_codigo(): void
    {
        $admin = $this->actAsAdmin();
        $marca = 'TDBUSCA'.uniqid();

        $alvo = ToDo::create([
            'business_id' => $this->business->id,
            'created_by'  => $admin->id,
            'task'        => "Trocar lampada do galpao {$marca}",
            'date'        => now(),
            'status'      => 'new',
            'task_id'     => "{$marca}-001",
        ]);
        $ruido = ToDo::create([
            'business_id' => $this->business->id,
            'created_by'  => $admin->id,
            'task'        => 'Assunto totalmente diferente '.uniqid(),
            'date'        => now(),
            'status'      => 'new',
            'task_id'     => 'RUIDO-'.uniqid(),
        ]);

        try {
            // Por texto da tarefa
            $ids = $this->listar(['q' => $marca]);
            $this->assertTrue($ids->contains($alvo->id), 'A busca por texto deveria achar a tarefa');
            $this->assertFalse($ids->contains($ruido->id), 'A busca nao deveria trazer o ruido');

            // Por codigo (task_id) — o protótipo busca "tarefa ou ID"
            $ids = $this->listar(['q' => "{$marca}-001"]);
            $this->assertTrue($ids->contains($alvo->id), 'A busca por ID deveria achar a tarefa');
        } finally {
            $alvo->delete();
            $ruido->delete();
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function periodo_de_inicio_aceita_range_aberto(): void
    {
        $admin = $this->actAsAdmin();
        $marca = 'TDRANGE'.uniqid();

        $antiga = ToDo::create([
            'business_id' => $this->business->id, 'created_by' => $admin->id,
            'task' => "Antiga {$marca}", 'date' => now()->subDays(40),
            'status' => 'new', 'task_id' => "{$marca}-A",
        ]);
        $recente = ToDo::create([
            'business_id' => $this->business->id, 'created_by' => $admin->id,
            'task' => "Recente {$marca}", 'date' => now()->subDay(),
            'status' => 'new', 'task_id' => "{$marca}-R",
        ]);

        try {
            // Só o campo "De": o Blade exigia os dois e nao filtrava nada.
            $ids = $this->listar(['q' => $marca, 'start_date' => now()->subDays(7)->format('Y-m-d')]);
            $this->assertTrue($ids->contains($recente->id), 'Range aberto "de" deveria trazer a recente');
            $this->assertFalse($ids->contains($antiga->id), 'Range aberto "de" deveria cortar a antiga');

            // Só o campo "Até"
            $ids = $this->listar(['q' => $marca, 'end_date' => now()->subDays(7)->format('Y-m-d')]);
            $this->assertTrue($ids->contains($antiga->id), 'Range aberto "ate" deveria trazer a antiga');
            $this->assertFalse($ids->contains($recente->id), 'Range aberto "ate" deveria cortar a recente');
        } finally {
            $antiga->delete();
            $recente->delete();
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function ordem_e_whitelist_e_valor_invalido_cai_no_padrao(): void
    {
        $this->actAsAdmin();

        $this->assertSame('prazo', $this->inertiaGet('/essentials/todo', ['ordem' => 'prazo'])
            ->json('props.filtros.ordem'));

        // Nada cru do request chega ao orderBy: fora da whitelist volta ao padrao.
        $this->assertSame('recentes', $this->inertiaGet('/essentials/todo', ['ordem' => 'coluna_inexistente'])
            ->json('props.filtros.ordem'));
        $this->assertSame('recentes', $this->inertiaGet('/essentials/todo')
            ->json('props.filtros.ordem'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function ordena_por_prazo_com_tarefa_sem_fim_no_final(): void
    {
        $admin = $this->actAsAdmin();
        $marca = 'TDORD'.uniqid();

        $semFim = ToDo::create([
            'business_id' => $this->business->id, 'created_by' => $admin->id,
            'task' => "Sem prazo {$marca}", 'date' => now(), 'end_date' => null,
            'status' => 'new', 'task_id' => "{$marca}-S",
        ]);
        $comFim = ToDo::create([
            'business_id' => $this->business->id, 'created_by' => $admin->id,
            'task' => "Com prazo {$marca}", 'date' => now(), 'end_date' => now()->addDays(3),
            'status' => 'new', 'task_id' => "{$marca}-C",
        ]);

        try {
            $ids = $this->listar(['q' => $marca, 'ordem' => 'prazo'])->all();
            $this->assertSame([$comFim->id, $semFim->id], $ids, 'Tarefa sem prazo nao pode encabecar a lista');
        } finally {
            $semFim->delete();
            $comFim->delete();
        }
    }


    /**
     * `todos` é Inertia::defer — sem partial reload a prop nem existe no payload.
     * Mesmo padrão do ComprasContratoFiltrosTest.
     */
    private function listar(array $query): \Illuminate\Support\Collection
    {
        $url = '/essentials/todo?'.http_build_query($query);

        $response = $this->withHeaders([
            'X-Inertia'                   => 'true',
            'X-Inertia-Version'           => $this->currentInertiaVersion() ?? '',
            'X-Inertia-Partial-Data'      => 'todos,filtros',
            'X-Inertia-Partial-Component' => 'Essentials/Todo/Index',
            'Accept'                      => 'text/html',
        ])->get($url);

        $response->assertStatus(200);

        return collect($response->json('props.todos.data') ?? [])->pluck('id');
    }

}
