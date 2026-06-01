<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;

/**
 * Smoke da tela /kb/charters — Charter Governance (ADR 0243).
 *
 * Fonte = filesystem: o controller varre resources/js/Pages/**\/*.charter.md
 * (charters são código, não memory/). Valida: rotas registradas, index lista os
 * charters reais do repo, show devolve o conteúdo, anti path-traversal e auth.
 *
 * Tier 0: biz=1 (NUNCA biz=4). Reusa Modules/KB/Tests/Helpers.php só p/ auth.
 */

beforeEach(function () {
    kbBootstrapSchema();
    kbCreateBusinessRow(1);
});

afterEach(function () {
    kbTeardownSchema();
});

it('registra as rotas kb.charters e kb.charters.show', function () {
    expect(\Illuminate\Support\Facades\Route::has('kb.charters'))->toBeTrue();
    expect(\Illuminate\Support\Facades\Route::has('kb.charters.show'))->toBeTrue();
});

it('lista charters varrendo resources/js/Pages', function () {
    kbActAsUser(bizId: 1, permissions: ['copiloto.mcp.memory.manage']);

    $this->get('/kb/charters')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kb/Charters/Index')
            ->has('charters')
            ->where('kpis.total', fn ($t) => $t >= 1)
        );
});

it('devolve o conteúdo de um charter válido', function () {
    kbActAsUser(bizId: 1, permissions: ['copiloto.mcp.memory.manage']);

    // o próprio charter desta tela existe no repo
    $path = 'resources/js/Pages/kb/Charters/Index.charter.md';

    $this->getJson('/kb/charters/show?path='.urlencode($path))
        ->assertOk()
        ->assertJsonPath('path', $path)
        ->assertJsonStructure(['path', 'content_md', 'github_url']);
});

it('bloqueia path traversal e arquivos fora de Pages (404)', function () {
    kbActAsUser(bizId: 1, permissions: ['copiloto.mcp.memory.manage']);

    $this->getJson('/kb/charters/show?path='.urlencode('resources/js/Pages/../../.env'))
        ->assertStatus(404);
    $this->getJson('/kb/charters/show?path='.urlencode('config/app.php'))
        ->assertStatus(404); // não termina em .charter.md / fora de Pages
});

it('exige autenticação', function () {
    $this->get('/kb/charters')->assertRedirect();
});
