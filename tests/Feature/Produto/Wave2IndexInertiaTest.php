<?php

declare(strict_types=1);

/**
 * Pest test F4 QA — Pages/Produto/Index Inertia (Wave 2 B4 Produto · Agent W2-C 2026-05-15)
 *
 * Cobre F4 do processo MWART canônico (ADR 0104):
 *   1. Page Inertia existe no path esperado
 *   2. Imports padrão (AppShellV2, useForm, Deferred)
 *   3. TypeScript estrito sem `any`
 *   4. NÃO usa sessionStorage (LICOES_F3 + GOTCHAS)
 *   5. NÃO usa cor crua não-semântica (ADR 0110)
 */

if (! function_exists('repo_path')) {
    function repo_path(string $relative): string
    {
        return dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . ltrim($relative, '/\\');
    }
}

const PAGE_PATH = 'resources/js/Pages/Produto/Index.tsx';

function readProdutoIndex(): string
{
    return file_get_contents(repo_path(PAGE_PATH));
}

it('Page Inertia existe em Pages/Produto/Index.tsx', function () {
    expect(file_exists(repo_path(PAGE_PATH)))->toBeTrue();
});

it('Page importa AppShellV2 (Persistent Layout)', function () {
    expect(readProdutoIndex())->toContain('@/Layouts/AppShellV2');
});

it('Page usa Persistent Layout via .layout = (page) =>', function () {
    $source = readProdutoIndex();
    expect($source)->toMatch('/ProdutoIndex\\.layout\\s*=\\s*\\(page/');
    expect($source)->toContain('<AppShellV2');
});

it('Page importa Deferred (Inertia::defer Tier 0 2026-05-15)', function () {
    expect(readProdutoIndex())->toContain('Deferred');
});

it('Page declara interface ProdutoIndexPageProps (TypeScript contract)', function () {
    $source = readProdutoIndex();
    expect($source)->toContain('ProdutoIndexPageProps');
    expect($source)->toContain('permissions');
    expect($source)->toContain('filters');
});

it('Page NÃO usa sessionStorage (GOTCHAS — usar localStorage com prefixo oimpresso.produto.)', function () {
    expect(readProdutoIndex())->not->toContain('sessionStorage');
});

it('Page usa localStorage com prefixo oimpresso.produto.', function () {
    $source = readProdutoIndex();
    expect($source)->toContain("'oimpresso.produto.");
});

it('Page NÃO usa cor crua não-semântica (ADR 0110 §Cores semânticas)', function () {
    $source = readProdutoIndex();
    // rose/emerald/amber/blue/stone são tokens canon. gray/indigo/purple/pink/yellow/red/green crus proibidos.
    expect($source)->not->toMatch('/bg-(gray|indigo|purple|pink|yellow|red|green)-[0-9]+/');
});

it('Page NÃO contém `: any` em type annotations (TypeScript estrito)', function () {
    $source = readProdutoIndex();
    // permite "any" em string ou comentário; proíbe `: any` ou `<any>` em type position
    expect($source)->not->toMatch('/:\s*any\b/');
});

it('Page tem KPI strip 4 cards (Total · Ativos · Categorias · Populares)', function () {
    $source = readProdutoIndex();
    expect($source)->toContain('Total');
    expect($source)->toContain('Ativos');
    expect($source)->toContain('Categorias');
    expect($source)->toContain('Populares');
});

it('Page tem search bar com aria-label', function () {
    $source = readProdutoIndex();
    expect($source)->toContain('Buscar produto');
    expect($source)->toContain('aria-label');
});

it('Page tem toggle "Mostrar inativos"', function () {
    expect(readProdutoIndex())->toContain('Mostrar inativos');
});

it('Charter ao lado existe (MWART F1 requisito)', function () {
    expect(file_exists(repo_path('resources/js/Pages/Produto/Index.charter.md')))->toBeTrue();
});

it('Charter declara mwart_pattern_reuse (ADR 0149)', function () {
    $charter = file_get_contents(repo_path('resources/js/Pages/Produto/Index.charter.md'));
    expect($charter)->toContain('mwart_pattern_reuse:');
    expect($charter)->toContain('blueprint_cowork:');
    expect($charter)->toContain('produto-cockpit');
});

it('RUNBOOK existe (MWART F1 requisito)', function () {
    expect(file_exists(repo_path('memory/requisitos/Inventory/RUNBOOK-produto-index.md')))->toBeTrue();
});

it('Visual comparison existe', function () {
    expect(file_exists(repo_path('memory/requisitos/Inventory/produto-index-visual-comparison.md')))->toBeTrue();
});
