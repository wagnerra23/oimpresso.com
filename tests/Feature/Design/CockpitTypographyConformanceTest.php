<?php

declare(strict_types=1);

/**
 * @group legacy-quarantine
 * quarantine-reason: assert estático de tipografia canon (Cockpit V2 / ADR 0110) contra .tsx móvel — cluster C5/Q-B da triage. NÃO é bug de produto; re-triar pós harness L0. Ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-B.
 */

/**
 * Pest test estrutural — conformance Cockpit Pattern V2 (ADR 0110) em todas as Pages.
 *
 * Anti-regressão sistêmica das divergências encontradas no inventário 2026-05-08:
 *   - 33 Pages com h1 font-bold (canon: font-semibold)
 *   - KpiCard shared com font-bold no value (canon: font-semibold)
 *   - KpiCard label tracking-wide + text-xs (canon: tracking-widest + text-[11px])
 *
 * Site/* (landing público marketing) tem regras próprias e NÃO entra nesse teste.
 *
 * Refs: ADR 0110 §Tipografia canon
 */

const PAGES_GLOB = 'resources/js/Pages';

/**
 * Lista todos os .tsx em Pages/ exceto Site/ + _Showcase + _components helpers.
 * @return list<string> caminhos relativos
 */
function listInternalPages(): array
{
    $base = base_path(PAGES_GLOB);
    if (!is_dir($base)) {
        return [];
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base));
    $files = [];
    foreach ($iterator as $f) {
        if ($f->isFile() && $f->getExtension() === 'tsx') {
            $rel = str_replace('\\', '/', substr($f->getPathname(), strlen(base_path()) + 1));
            // Pula Site/ (marketing) + _Showcase (demo) + _components (sub-comp).
            if (str_contains($rel, '/Site/') || str_contains($rel, '/_Showcase/') ||
                str_contains($rel, '/_components/')) {
                continue;
            }
            $files[] = $rel;
        }
    }
    return $files;
}

it('Pages internas NÃO usam <h1...font-bold> (ADR 0110 canon: text-2xl font-semibold)', function () {
    $offenders = [];
    foreach (listInternalPages() as $rel) {
        $source = file_get_contents(base_path($rel));
        // Pattern: <h1 className="...font-bold..."> — pega font-bold em h1.
        // multiline pra cobrir className quebrado em linhas.
        if (preg_match('/<h1[^>]{0,200}font-bold/s', $source)) {
            $offenders[] = $rel;
        }
    }
    expect($offenders)->toBeEmpty(
        'h1 font-bold encontrado em (ADR 0110 manda font-semibold): '
        . PHP_EOL . implode(PHP_EOL, $offenders),
    );
});

it('Components/shared/KpiCard.tsx usa font-semibold (NÃO font-bold) em value (ADR 0110 §Tipografia)', function () {
    $source = file_get_contents(base_path('resources/js/Components/shared/KpiCard.tsx'));
    // Em valueClass switch, deve ter text-4xl font-semibold + text-2xl font-semibold.
    expect($source)->toContain('text-4xl font-semibold');
    expect($source)->toContain('text-2xl font-semibold');
    // NÃO pode ter font-bold em valueClass (regression do bug detectado 2026-05-08).
    expect($source)->not->toMatch('/text-(2xl|4xl) font-bold/');
});

it('Components/shared/KpiCard.tsx label usa text-[11px] + tracking-widest (NÃO text-xs + tracking-wide)', function () {
    $source = file_get_contents(base_path('resources/js/Components/shared/KpiCard.tsx'));
    // Label canon: text-[11px] font-semibold uppercase tracking-widest.
    expect($source)->toContain('text-[11px]');
    expect($source)->toContain('tracking-widest');
    // Pattern antigo divergente NÃO pode existir mais.
    expect($source)->not->toMatch('/text-xs font-medium text-muted-foreground uppercase tracking-wide(?!st)/');
});

it('Components/shared/PageHeader.tsx h1 inclui tracking-tight (canon ADR 0110)', function () {
    $source = file_get_contents(base_path('resources/js/Components/shared/PageHeader.tsx'));
    // Pattern: <h1 ... font-semibold tracking-tight ...>
    expect($source)->toMatch('/<h1[^>]{0,200}tracking-tight/');
});

it('Components/shared/PageHeader.tsx subtitle inclui leading-relaxed (canon ADR 0110)', function () {
    $source = file_get_contents(base_path('resources/js/Components/shared/PageHeader.tsx'));
    expect($source)->toContain('leading-relaxed');
});

it('Pages internas NÃO usam font-extrabold ou font-black (anti-padrão tipográfico)', function () {
    $offenders = [];
    foreach (listInternalPages() as $rel) {
        $source = file_get_contents(base_path($rel));
        if (preg_match('/<h[1-3][^>]{0,200}font-(extrabold|black)/s', $source)) {
            $offenders[] = $rel;
        }
    }
    expect($offenders)->toBeEmpty(
        'font-extrabold/font-black em h1-h3 encontrado em (canon = font-semibold): '
        . PHP_EOL . implode(PHP_EOL, $offenders),
    );
});

it('Sells/Index.tsx + Sells/Create.tsx usam pattern canon V2 estritamente', function () {
    foreach (['resources/js/Pages/Sells/Index.tsx', 'resources/js/Pages/Sells/Create.tsx'] as $rel) {
        $source = file_get_contents(base_path($rel));
        // h1 pattern canon estrito (sem responsive — referência viva)
        expect($source)->toMatch('/<h1[^>]*text-2xl[^>]*font-semibold[^>]*tracking-tight/');
        // Subtitle canon
        expect($source)->toMatch('/text-sm[^"]*muted-foreground[^"]*leading-relaxed/');
    }
});
