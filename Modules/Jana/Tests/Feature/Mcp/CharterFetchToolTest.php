<?php

declare(strict_types=1);

use Laravel\Mcp\Request as McpRequest;
use Modules\Jana\Mcp\Tools\CharterFetchTool;

uses(Tests\TestCase::class);

/**
 * GAP-ANALYSIS-91-100-2026-05-13 (C1 P0 Onda 4) — charter-fetch tool.
 *
 * Cobre:
 *  001. Resolve charter via path .tsx (.charter.md ao lado)
 *  002. Resolve charter via path .charter.md direto
 *  003. Resolve charter via rota canônica (`/sells`)
 *  004. Retorna seções canônicas (Mission/Goals/Non-Goals/UX targets/...)
 *  005. WARNING quando status: draft
 *  006. WARNING quando status: rascunho
 *  007. SEM warning quando status: live
 *  008. 404 amigável quando page_id inexistente
 *  009. page_id vazio retorna erro de validação
 *  010. format=json retorna estrutura parsed
 */
beforeEach(function () {
    $this->charterDir = sys_get_temp_dir() . '/charter-fetch-test-' . uniqid();
    mkdir($this->charterDir, 0o755, true);
    mkdir($this->charterDir . '/Pages/Mocks', 0o755, true);
    mkdir($this->charterDir . '/Pages/Mocks/Sub', 0o755, true);

    $this->fakeBase = base_path('resources/js/Pages/_FakeCharterTest');
    if (! is_dir($this->fakeBase)) {
        mkdir($this->fakeBase, 0o755, true);
    }
});

afterEach(function () {
    // Limpa fake base no resources/js/Pages
    if (isset($this->fakeBase) && is_dir($this->fakeBase)) {
        foreach (glob($this->fakeBase . '/*') as $f) {
            @unlink($f);
        }
        @rmdir($this->fakeBase);
    }
    if (isset($this->charterDir) && is_dir($this->charterDir)) {
        $rii = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->charterDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($rii as $file) {
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }
        @rmdir($this->charterDir);
    }
});

function callCharterTool(array $params): \Laravel\Mcp\Response
{
    $tool = new CharterFetchTool;
    $request = new McpRequest($params);

    return $tool->handle($request);
}

function makeCharter(string $absPath, string $status, string $page = '/mock', array $extraFront = [], array $sections = []): void
{
    $defaults = [
        'Mission' => 'Listar mocks pra cobertura de teste.',
        'Goals' => "- Goal 1 mock\n- Goal 2 mock",
        'Non-Goals' => "- ❌ Não faz X\n- ❌ Não faz Y",
        'UX targets' => '- p95 < 1500ms',
        'Automation hooks' => '- Endpoint mock',
        'Anti-hooks' => '- ❌ Não dispara emails',
    ];
    $sec = array_merge($defaults, $sections);

    $front = [
        'page' => $page,
        'component' => 'resources/js/Pages/Mock/Index.tsx',
        'owner' => 'wagner',
        'status' => $status,
        'last_validated' => '2026-05-13',
        'parent_module' => 'Mock',
        'tier' => 'A',
    ];
    foreach ($extraFront as $k => $v) {
        $front[$k] = $v;
    }

    $yaml = "---\n";
    foreach ($front as $k => $v) {
        if (is_array($v)) {
            $yaml .= "{$k}: [" . implode(', ', $v) . "]\n";
        } else {
            $yaml .= "{$k}: {$v}\n";
        }
    }
    $yaml .= "---\n\n# Page Charter — {$page}\n\n";

    foreach ($sec as $heading => $body) {
        $yaml .= "## {$heading}\n\n{$body}\n\n";
    }

    file_put_contents($absPath, $yaml);
}

test('resolve charter via path .tsx (.charter.md ao lado)', function () {
    $charterPath = $this->fakeBase . '/IndexTsx.charter.md';
    makeCharter($charterPath, 'live', '/fake-tsx');

    $relTsx = 'resources/js/Pages/_FakeCharterTest/IndexTsx.tsx';
    $response = callCharterTool(['page_id' => $relTsx]);
    $output = (string) $response->content();

    expect($output)->toContain('Charter: /fake-tsx')
        ->and($output)->toContain('status: live')
        ->and($output)->toContain('Mission')
        ->and($output)->toContain('Listar mocks');
});

test('resolve charter via path .charter.md direto', function () {
    $charterPath = $this->fakeBase . '/Direct.charter.md';
    makeCharter($charterPath, 'live', '/fake-direct');

    $rel = 'resources/js/Pages/_FakeCharterTest/Direct.charter.md';
    $response = callCharterTool(['page_id' => $rel]);
    $output = (string) $response->content();

    expect($output)->toContain('Charter: /fake-direct')
        ->and($output)->toContain('status: live');
});

test('resolve charter via rota canônica /xxx', function () {
    $charterPath = $this->fakeBase . '/RouteResolution.charter.md';
    makeCharter($charterPath, 'live', '/fake-route-unique-2026');

    $response = callCharterTool(['page_id' => '/fake-route-unique-2026']);
    $output = (string) $response->content();

    expect($output)->toContain('Charter: /fake-route-unique-2026')
        ->and($output)->toContain('status: live');
});

test('retorna seções canônicas Mission/Goals/Non-Goals/UX targets/Automation hooks/Anti-hooks', function () {
    $charterPath = $this->fakeBase . '/AllSections.charter.md';
    makeCharter($charterPath, 'live', '/fake-sections', [], [
        'Mission' => 'Mission canônica da tela.',
        'Goals' => '- G1: Listar coisas',
        'Non-Goals' => '- ❌ NÃO edita inline',
        'UX targets' => '- 1280px sem scroll',
        'Automation hooks' => '- GET /endpoint',
        'Anti-hooks' => '- ❌ NÃO dispara email',
    ]);

    $response = callCharterTool(['page_id' => 'resources/js/Pages/_FakeCharterTest/AllSections.tsx']);
    $output = (string) $response->content();

    expect($output)->toContain('## Mission')
        ->and($output)->toContain('Mission canônica da tela.')
        ->and($output)->toContain('## Goals')
        ->and($output)->toContain('G1: Listar coisas')
        ->and($output)->toContain('## Non-Goals')
        ->and($output)->toContain('NÃO edita inline')
        ->and($output)->toContain('## UX targets')
        ->and($output)->toContain('1280px sem scroll')
        ->and($output)->toContain('## Automation hooks')
        ->and($output)->toContain('GET /endpoint')
        ->and($output)->toContain('## Anti-hooks')
        ->and($output)->toContain('NÃO dispara email');
});

test('WARNING anexado quando status: draft', function () {
    $charterPath = $this->fakeBase . '/Draft.charter.md';
    makeCharter($charterPath, 'draft', '/fake-draft');

    $response = callCharterTool(['page_id' => 'resources/js/Pages/_FakeCharterTest/Draft.tsx']);
    $output = (string) $response->content();

    expect($output)->toContain('CHARTER STATUS: draft')
        ->and($output)->toContain('NÃO aprovado por Wagner');
});

test('WARNING anexado quando status: rascunho (PT)', function () {
    $charterPath = $this->fakeBase . '/Rascunho.charter.md';
    makeCharter($charterPath, 'rascunho', '/fake-rascunho');

    $response = callCharterTool(['page_id' => 'resources/js/Pages/_FakeCharterTest/Rascunho.tsx']);
    $output = (string) $response->content();

    expect($output)->toContain('CHARTER STATUS: rascunho')
        ->and($output)->toContain('NÃO aprovado por Wagner');
});

test('NENHUM warning quando status: live', function () {
    $charterPath = $this->fakeBase . '/Live.charter.md';
    makeCharter($charterPath, 'live', '/fake-live');

    $response = callCharterTool(['page_id' => 'resources/js/Pages/_FakeCharterTest/Live.tsx']);
    $output = (string) $response->content();

    expect($output)->not->toContain('CHARTER STATUS:')
        ->and($output)->not->toContain('NÃO aprovado por Wagner')
        ->and($output)->toContain('status: live');
});

test('404 amigável quando page_id inexistente', function () {
    $response = callCharterTool(['page_id' => 'resources/js/Pages/NaoExiste/Quetal.tsx']);
    $output = (string) $response->content();

    expect($output)->toContain('Charter não encontrado')
        ->and($output)->toContain('NaoExiste')
        ->and($output)->toContain('charter-write');
});

test('page_id vazio retorna erro de validação', function () {
    $response = callCharterTool(['page_id' => '']);
    $output = (string) $response->content();

    expect($output)->toContain('obrigatório');
});

test('format=json retorna estrutura parsed', function () {
    $charterPath = $this->fakeBase . '/Json.charter.md';
    makeCharter($charterPath, 'live', '/fake-json', ['related_adrs' => ['0093', '0094']]);

    $response = callCharterTool([
        'page_id' => 'resources/js/Pages/_FakeCharterTest/Json.tsx',
        'format' => 'json',
    ]);
    $output = (string) $response->content();

    $parsed = json_decode($output, true);
    expect($parsed)->toBeArray()
        ->and($parsed['status'])->toBe('live')
        ->and($parsed['frontmatter'])->toHaveKey('page')
        ->and($parsed['frontmatter']['page'])->toBe('/fake-json')
        ->and($parsed['sections'])->toHaveKey('Mission')
        ->and($parsed['sections']['Mission'])->toContain('Listar mocks');
});
