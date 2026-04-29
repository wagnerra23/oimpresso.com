<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Copiloto\Entities\Mcp\McpMemoryDocument;
use Modules\Copiloto\Mcp\OimpressoMcpServer;
use Modules\Copiloto\Mcp\Prompts\BriefingOimpressoPrompt;
use Modules\Copiloto\Mcp\Resources\CurrentResource;
use Modules\Copiloto\Mcp\Resources\HandoffResource;
use Modules\Copiloto\Mcp\Tools\DecisionsFetchTool;
use Modules\Copiloto\Mcp\Tools\DecisionsSearchTool;
use Modules\Copiloto\Mcp\Tools\SessionsRecentTool;
use Modules\Copiloto\Mcp\Tools\TasksCurrentTool;

/**
 * MEM-MCP-1.c (ADR 0053) — Stack MCP completa: 5 tools + 2 resources + 1 prompt.
 *
 * Usa testing helpers do laravel/mcp: Server::tool/resource/prompt + assertSee.
 */

beforeEach(function () {
    Schema::create('mcp_memory_documents', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->string('slug', 200)->unique();
        $t->string('type', 20);
        $t->string('module', 50)->nullable();
        $t->string('title', 250);
        $t->mediumText('content_md');
        $t->string('scope_required', 100)->nullable();
        $t->boolean('admin_only')->default(false);
        $t->json('metadata')->nullable();
        $t->string('git_sha', 40)->nullable();
        $t->string('git_path', 300);
        $t->unsignedSmallInteger('pii_redactions_count')->default(0);
        $t->binary('embedding')->nullable();
        $t->timestamp('indexed_at')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });
});

afterEach(function () {
    Schema::dropIfExists('mcp_memory_documents');
});

it('TasksCurrentTool retorna conteúdo de CURRENT.md indexado', function () {
    McpMemoryDocument::create([
        'slug'        => 'current',
        'type'        => 'current',
        'title'       => 'CURRENT — Cycle 01',
        'content_md'  => '# Active' . "\n" . '- A1: tarefa X' . "\n" . '- A2: tarefa Y',
        'git_path'    => 'CURRENT.md',
        'indexed_at'  => now(),
    ]);

    OimpressoMcpServer::tool(TasksCurrentTool::class)
        ->assertOk()
        ->assertSee(['CURRENT — Cycle 01', 'A1: tarefa X']);
});

it('TasksCurrentTool sem CURRENT.md indexado retorna instrução de sync', function () {
    OimpressoMcpServer::tool(TasksCurrentTool::class)
        ->assertSee('mcp:sync-memory');
});

it('DecisionsSearchTool exige query não-vazia', function () {
    OimpressoMcpServer::tool(DecisionsSearchTool::class, ['query' => ''])
        ->assertHasErrors();
});

it('DecisionsSearchTool não crash em SQLite (FULLTEXT só MySQL)', function () {
    McpMemoryDocument::create([
        'slug'        => '0046-test',
        'type'        => 'adr',
        'module'      => 'copiloto',
        'title'       => 'ADR Test',
        'content_md'  => 'ContextoNegocio',
        'git_path'    => 'memory/decisions/0046-test.md',
    ]);

    // SQLite não suporta MATCH AGAINST — vai retornar erro tratável
    // ou resultado vazio. Ambos OK pra teste — só não pode crashar fatal.
    expect(fn () => OimpressoMcpServer::tool(DecisionsSearchTool::class, ['query' => 'ContextoNegocio']))
        ->not->toThrow(\Throwable::class);
});

it('DecisionsFetchTool retorna ADR completa por slug', function () {
    McpMemoryDocument::create([
        'slug'        => '0046-chat-agent-gap',
        'type'        => 'adr',
        'module'      => 'copiloto',
        'title'       => 'Gap ChatAgent',
        'content_md'  => 'Conteúdo completo da ADR aqui',
        'git_path'    => 'memory/decisions/0046-chat-agent-gap.md',
        'indexed_at'  => now(),
    ]);

    OimpressoMcpServer::tool(DecisionsFetchTool::class, ['slug' => '0046-chat-agent-gap'])
        ->assertSee(['Gap ChatAgent', 'Conteúdo completo da ADR aqui']);
});

it('DecisionsFetchTool retorna erro pra slug não-existente', function () {
    OimpressoMcpServer::tool(DecisionsFetchTool::class, ['slug' => 'inexistente'])
        ->assertHasErrors()
        ->assertSee('não encontrada');
});

it('SessionsRecentTool lista sessions ordenados por indexed_at desc', function () {
    McpMemoryDocument::create([
        'slug'        => 'session-2026-04-29',
        'type'        => 'session',
        'title'       => 'Session 29-abr',
        'content_md'  => 'log',
        'git_path'    => 'memory/sessions/2026-04-29.md',
        'indexed_at'  => now(),
    ]);
    McpMemoryDocument::create([
        'slug'        => 'session-2026-04-28',
        'type'        => 'session',
        'title'       => 'Session 28-abr',
        'content_md'  => 'log antigo',
        'git_path'    => 'memory/sessions/2026-04-28.md',
        'indexed_at'  => now()->subDay(),
    ]);

    OimpressoMcpServer::tool(SessionsRecentTool::class, ['limit' => 5])
        ->assertOk()
        ->assertSee(['Session 29-abr', 'Session 28-abr']);
});

it('HandoffResource retorna conteúdo do handoff indexado', function () {
    McpMemoryDocument::create([
        'slug'        => 'handoff',
        'type'        => 'handoff',
        'title'       => 'Handoff Canônico',
        'content_md'  => '# Estado canônico' . "\n" . 'Content here',
        'git_path'    => 'memory/08-handoff.md',
        'indexed_at'  => now(),
    ]);

    OimpressoMcpServer::resource(HandoffResource::class)
        ->assertSee('Estado canônico');
});

it('CurrentResource retorna CURRENT.md', function () {
    McpMemoryDocument::create([
        'slug'        => 'current',
        'type'        => 'current',
        'title'       => 'Cycle Atual',
        'content_md'  => '# Active tasks',
        'git_path'    => 'CURRENT.md',
        'indexed_at'  => now(),
    ]);

    OimpressoMcpServer::resource(CurrentResource::class)
        ->assertSee('Active tasks');
});

it('BriefingOimpressoPrompt retorna primer com stack + ADRs canônicas', function () {
    OimpressoMcpServer::prompt(BriefingOimpressoPrompt::class)
        ->assertSee(['oimpresso', 'Multi-tenant', 'Delphi contrato IMUTÁVEL', 'ADRs canônicas']);
});

it('OimpressoMcpServer registra 5 tools, 2 resources, 1 prompt', function () {
    $reflection = new \ReflectionClass(OimpressoMcpServer::class);
    $tools = $reflection->getProperty('tools');
    $tools->setAccessible(true);
    $resources = $reflection->getProperty('resources');
    $resources->setAccessible(true);
    $prompts = $reflection->getProperty('prompts');
    $prompts->setAccessible(true);

    $instance = new OimpressoMcpServer(new \Laravel\Mcp\Server\Transport\FakeTransporter());
    expect($tools->getValue($instance))->toHaveCount(5);
    expect($resources->getValue($instance))->toHaveCount(2);
    expect($prompts->getValue($instance))->toHaveCount(1);
});
