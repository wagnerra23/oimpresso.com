<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;

/**
 * Smoke da tela /kb/charters — Charter Governance (ADR 0243).
 *
 * Valida: rota registrada, index lista os *.charter.md de mcp_memory_documents
 * (reuso do tri-pane), derivação módulo/tela do git_path, robustez do baseQuery
 * (type=charter OU git_path .charter.md) e gate de autenticação.
 *
 * Tier 0: biz=1 (NUNCA biz=4 ROTA LIVRE). Reusa Modules/KB/Tests/Helpers.php.
 */

beforeEach(function () {
    kbBootstrapSchema();
    kbCreateBusinessRow(1);
});

afterEach(function () {
    kbTeardownSchema();
});

it('registra a rota nomeada kb.charters', function () {
    expect(\Illuminate\Support\Facades\Route::has('kb.charters'))->toBeTrue();
});

it('lista charters e deriva módulo/tela do git_path', function () {
    kbCreateMcpDoc(1, 'charter', [
        'slug'       => 'cliente-index-charter',
        'title'      => 'Page Charter — /cliente',
        'content_md' => "# Mission\nListagem de clientes.",
        'git_path'   => 'resources/js/Pages/Cliente/Index.charter.md',
        'admin_only' => 0,
    ]);

    kbActAsUser(bizId: 1, permissions: ['copiloto.mcp.memory.manage']);

    $this->get('/kb/charters')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kb/Charters/Index')
            ->has('charters', 1)
            ->where('charters.0.module', 'Cliente')
            ->where('charters.0.screen', 'Index')
            ->where('kpis.total', 1)
        );
});

it('pega charter por git_path mesmo quando type != charter (robustez baseQuery)', function () {
    kbCreateMcpDoc(1, 'reference', [
        'slug'       => 'sells-create-charter',
        'title'      => 'Charter Sells/Create',
        'git_path'   => 'resources/js/Pages/Sells/Create.charter.md',
        'admin_only' => 0,
    ]);

    kbActAsUser(bizId: 1, permissions: ['copiloto.mcp.memory.manage']);

    $this->get('/kb/charters')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('charters', 1));
});

it('não lista docs que não são charter', function () {
    kbCreateMcpDoc(1, 'adr', [
        'slug'     => '0093-multi-tenant',
        'git_path' => 'memory/decisions/0093-multi-tenant-isolation-tier-0.md',
    ]);

    kbActAsUser(bizId: 1, permissions: ['copiloto.mcp.memory.manage']);

    $this->get('/kb/charters')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('charters', 0)->where('kpis.total', 0));
});

it('exige autenticação', function () {
    $this->get('/kb/charters')->assertRedirect();
});
