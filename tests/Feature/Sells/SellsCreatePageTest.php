<?php

declare(strict_types=1);

/**
 * Pest test estrutural — Pages/Sells/Create.tsx (US-SELL-003).
 *
 * Cobre F3 FRONTEND INCREMENTAL skeleton do processo MWART canônico (ADR 0104):
 *   1. Page Inertia existe no path esperado
 *   2. Builda no manifest do build:inertia
 *   3. Persistent Layout via AppShellV2 (não envolve em <AppShell> inline)
 *   4. Imports padrão (PageHeader, EmptyState shared)
 *   5. Interface SellsCreatePageProps declarada (TypeScript contract)
 *   6. useForm com defaults (status='final' pra ROTA LIVRE)
 *
 * Anti-padrões catalogados em GOTCHAS.md cockpit-runbook que este test pega:
 *   - sessionStorage em vez de localStorage
 *   - <AppShell> sem V2
 *   - cor crua bg-blue-500 / text-gray-700 (R-DS-002)
 */

const PAGE_PATH = 'resources/js/Pages/Sells/Create.tsx';

function readPage(): string
{
    return file_get_contents(base_path(PAGE_PATH));
}

it('Page Inertia existe em Pages/Sells/Create.tsx', function () {
    expect(file_exists(base_path(PAGE_PATH)))->toBeTrue();
});

it('Page importa AppShellV2 (Persistent Layout)', function () {
    $source = readPage();
    expect($source)->toContain('@/Layouts/AppShellV2');
});

it('Page usa Persistent Layout via .layout = (page) =>', function () {
    $source = readPage();
    expect($source)->toMatch('/SellsCreate\\.layout\\s*=\\s*\\(page/');
    expect($source)->toContain('<AppShellV2>');
});

it('Page NÃO envolve conteúdo em <AppShell> inline (auto-mem preference_persistent_layouts)', function () {
    $source = readPage();
    // <AppShell> SEM V2 = pegadinha. Pode ter <AppShellV2> mas não <AppShell> sozinho.
    expect($source)->not->toMatch('/<AppShell[^V][^2>]/');
});

it('Page importa shared PageHeader + EmptyState (R-DS-001 reutilização)', function () {
    $source = readPage();
    expect($source)->toContain('@/Components/shared/PageHeader');
    expect($source)->toContain('@/Components/shared/EmptyState');
});

it('Page declara interface SellsCreatePageProps (TypeScript contract)', function () {
    $source = readPage();
    expect($source)->toContain('SellsCreatePageProps');
    expect($source)->toContain('businessLocations');
    expect($source)->toContain('walkInCustomer');
    expect($source)->toContain('defaultDatetime');
    expect($source)->toContain('permissions');
});

it('Page usa useForm com defaults conservadores pra ROTA LIVRE (status=final)', function () {
    $source = readPage();
    expect($source)->toContain("status: 'final'");
    expect($source)->toContain('transaction_date: props.defaultDatetime');
    expect($source)->toContain('contact_id: props.walkInCustomer.id');
});

it('Page NÃO usa sessionStorage (auto-mem GOTCHAS — usar localStorage com prefixo oimpresso.)', function () {
    $source = readPage();
    expect($source)->not->toContain('sessionStorage');
});

it('Page NÃO usa cor crua Tailwind (R-DS-002 — usar tokens shadcn)', function () {
    $source = readPage();
    expect($source)->not->toMatch('/bg-(blue|red|green|yellow|gray|indigo|purple|pink)-[0-9]+/');
});

it('Page registrada no manifest do build:inertia (smoke build)', function () {
    $manifestPath = base_path('public/build-inertia/manifest.json');
    if (! file_exists($manifestPath)) {
        $this->markTestSkipped('manifest.json não existe — rodar `npm run build:inertia` primeiro');
    }

    $manifest = json_decode(file_get_contents($manifestPath), true);
    expect($manifest)->toHaveKey('resources/js/Pages/Sells/Create.tsx');
});
