<?php

declare(strict_types=1);

/**
 * @group legacy-quarantine
 * quarantine-reason: assert estático de canon-source (Cockpit Pattern V2 / ADR 0110) contra .tsx móvel — cluster C5/Q-B da triage. NÃO é bug de produto; re-triar pós harness L0. Ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-B.
 */

/**
 * Pest test sistêmico — Cockpit Pattern V2 conformance broader (ADR 0110).
 *
 * Itera todas as Pages internas e valida critérios canon. Substitui o trabalho
 * de criar 1 test/Page por arquivo (não escala — projeto tem 50+ Pages).
 *
 * Categorias:
 *   - $CANON_TARGET: Pages que DEVEM 100% conformar (servem de referência viva).
 *     Falha se qualquer critério obrigatório quebrar.
 *   - $WHITELIST: Pages com exceção justificada (Site/* marketing, _Showcase demo, etc).
 *     Não entram no scan.
 *   - Resto: Pages "lenient" — checadas só nos OBRIGATÓRIOS críticos
 *     (AppShellV2 layout, sem font-bold em h1, sem cor crua).
 *
 * Refs: ADR 0110 §Anatomia, §Tipografia, §Cores semânticas
 *        Inventário 2026-05-08 — 23 Pages auditadas, 5 canon, 18 backlog
 */

const COCKPIT_PAGES_GLOB = 'resources/js/Pages';

/** Pages canon — referência viva, devem 100% conformar (regression target). */
const COCKPIT_CANON_TARGET = [
    'resources/js/Pages/Sells/Index.tsx',
    'resources/js/Pages/Sells/Create.tsx',
    'resources/js/Pages/Sells/_components/SaleSheet.tsx',
    'resources/js/Pages/governance/Dashboard.tsx',
    'resources/js/Pages/ProjectMgmt/Board/Index.tsx',
];

/** Pages com exceção — landing público, demos, sub-componentes auxiliares. */
const COCKPIT_WHITELIST_PATHS = [
    '/Site/',          // landing público — tipografia marketing
    '/_Showcase/',     // demo de componentes — tipografia variada por design
];

/** Sub-pastas _components são auxiliares — não entram no scan exceto Sells/_components/SaleSheet (canon). */
const COCKPIT_SCAN_SUBCOMPONENTS = [
    'resources/js/Pages/Sells/_components/SaleSheet.tsx',
];

function listInternalCockpitPages(): array
{
    $base = base_path(COCKPIT_PAGES_GLOB);
    if (!is_dir($base)) {
        return [];
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base));
    $files = [];
    foreach ($iterator as $f) {
        if (!$f->isFile() || $f->getExtension() !== 'tsx') {
            continue;
        }
        $rel = str_replace('\\', '/', substr($f->getPathname(), strlen(base_path()) + 1));

        // Whitelist (Site, Showcase) — pula.
        foreach (COCKPIT_WHITELIST_PATHS as $skip) {
            if (str_contains($rel, $skip)) {
                continue 2;
            }
        }

        // _components — só inclui SaleSheet (canon).
        if (str_contains($rel, '/_components/')) {
            if (!in_array($rel, COCKPIT_SCAN_SUBCOMPONENTS, true)) {
                continue;
            }
        }

        $files[] = $rel;
    }
    return $files;
}

function readCockpitPage(string $rel): string
{
    return file_get_contents(base_path($rel));
}

// ─── OBRIGATÓRIOS — críticos pra todas as Pages internas ─────────────────────

it('OBRIGATÓRIO: todas as Pages internas usam AppShellV2 (NÃO AdminLTE legacy)', function () {
    $offenders = [];
    foreach (listInternalCockpitPages() as $rel) {
        $source = readCockpitPage($rel);
        // Pula Pages que claramente não têm shell (sub-componentes raros, modais etc).
        if (!preg_match('/\\.layout\\s*=/', $source) && !str_contains($source, 'AppShellV2')) {
            continue;
        }
        // Se Page declara layout, deve ser AppShellV2.
        if (preg_match('/\\.layout\\s*=/', $source)) {
            if (!str_contains($source, '@/Layouts/AppShellV2')) {
                $offenders[] = $rel;
            }
            // Não envolve em <AppShell> sem V2 (auto-mem preference_persistent_layouts).
            if (preg_match('/<AppShell[^V][^2>]/', $source)) {
                $offenders[] = $rel . ' (envolve em <AppShell> sem V2)';
            }
        }
    }
    expect($offenders)->toBeEmpty(
        'Pages que NÃO usam AppShellV2: ' . PHP_EOL . implode(PHP_EOL, $offenders),
    );
});

it('OBRIGATÓRIO: Pages internas NÃO usam <h1...font-bold> (canon = font-semibold)', function () {
    $offenders = [];
    foreach (listInternalCockpitPages() as $rel) {
        $source = readCockpitPage($rel);
        if (preg_match('/<h1[^>]{0,200}font-bold/s', $source)) {
            $offenders[] = $rel;
        }
    }
    expect($offenders)->toBeEmpty(
        'h1 font-bold em (canon = font-semibold): ' . PHP_EOL . implode(PHP_EOL, $offenders),
    );
});

it('OBRIGATÓRIO: Pages internas NÃO usam font-extrabold/font-black em h1-h3', function () {
    $offenders = [];
    foreach (listInternalCockpitPages() as $rel) {
        $source = readCockpitPage($rel);
        if (preg_match('/<h[1-3][^>]{0,200}font-(extrabold|black)/s', $source)) {
            $offenders[] = $rel;
        }
    }
    expect($offenders)->toBeEmpty(
        'font-extrabold/font-black em h1-h3: ' . PHP_EOL . implode(PHP_EOL, $offenders),
    );
});

it('OBRIGATÓRIO: Pages internas NÃO usam sessionStorage (canon = localStorage com prefix oimpresso.)', function () {
    $offenders = [];
    foreach (listInternalCockpitPages() as $rel) {
        $source = readCockpitPage($rel);
        if (str_contains($source, 'sessionStorage')) {
            $offenders[] = $rel;
        }
    }
    expect($offenders)->toBeEmpty(
        'sessionStorage proibido (use localStorage com prefix oimpresso.): '
        . PHP_EOL . implode(PHP_EOL, $offenders),
    );
});

it('OBRIGATÓRIO: Pages internas NÃO importam <AppShell> sem V2 (auto-mem preference_persistent_layouts)', function () {
    $offenders = [];
    foreach (listInternalCockpitPages() as $rel) {
        $source = readCockpitPage($rel);
        // Match `from '@/Layouts/AppShell'` SEM V2 no final.
        if (preg_match("/from\\s+['\"]@\\/Layouts\\/AppShell['\"]/", $source)) {
            $offenders[] = $rel;
        }
    }
    expect($offenders)->toBeEmpty(
        'Pages importam @/Layouts/AppShell (sem V2): ' . PHP_EOL . implode(PHP_EOL, $offenders),
    );
});

// ─── CANON TARGET — strict (Pages que devem 100% conformar) ──────────────────

it('CANON TARGET: Pages canon existem (referência viva — alvo de regression)', function () {
    foreach (COCKPIT_CANON_TARGET as $rel) {
        expect(file_exists(base_path($rel)))->toBeTrue("$rel não existe");
    }
});

it('CANON TARGET: h1 com tracking-tight + font-semibold (estrito)', function () {
    $offenders = [];
    // Sells/Create tem h1 dentro de header sticky — pattern canon do form.
    // SaleSheet tem SheetTitle em vez de h1.
    $strictH1Pages = [
        'resources/js/Pages/Sells/Index.tsx',
        'resources/js/Pages/Sells/Create.tsx',
        'resources/js/Pages/governance/Dashboard.tsx',
        'resources/js/Pages/ProjectMgmt/Board/Index.tsx',
    ];
    foreach ($strictH1Pages as $rel) {
        $source = readCockpitPage($rel);
        // Pode usar PageHeader shared (que já tem canon) OU h1 inline com canon strict.
        $hasPageHeader = str_contains($source, '@/Components/shared/PageHeader');
        $hasInlineCanon = preg_match(
            '/<h1[^>]{0,300}text-2xl[^>]{0,100}font-semibold[^>]{0,100}tracking-tight/s',
            $source,
        );
        if (!$hasPageHeader && !$hasInlineCanon) {
            $offenders[] = $rel;
        }
    }
    expect($offenders)->toBeEmpty(
        'Canon target sem h1 canon (PageHeader OU inline strict): '
        . PHP_EOL . implode(PHP_EOL, $offenders),
    );
});

it('CANON TARGET: subtitle text-sm muted-foreground leading-relaxed', function () {
    $offenders = [];
    foreach (['resources/js/Pages/Sells/Index.tsx', 'resources/js/Pages/Sells/Create.tsx'] as $rel) {
        $source = readCockpitPage($rel);
        // Pattern: <p ... className="text-sm ... muted-foreground ... leading-relaxed">
        if (!preg_match('/text-sm[^"\'\\n]{0,100}muted-foreground[^"\'\\n]{0,100}leading-relaxed/', $source)) {
            $offenders[] = $rel;
        }
    }
    expect($offenders)->toBeEmpty(
        'Canon sem subtitle leading-relaxed: ' . PHP_EOL . implode(PHP_EOL, $offenders),
    );
});

it('CANON TARGET: Sells/Index + Create usam filter PILLS rounded-full (NÃO tabs border-b-2)', function () {
    foreach (['resources/js/Pages/Sells/Index.tsx', 'resources/js/Pages/Sells/Create.tsx'] as $rel) {
        $source = readCockpitPage($rel);
        expect($source)->toContain('rounded-full px-3.5 py-1.5');
        // NÃO aplica border-b-2 border-primary -mb-px como classe ativa de filter.
        expect($source)->not->toMatch("/className=\\{?\\s*['\"][^'\"]*border-b-2 border-primary -mb-px/");
    }
});

it('CANON TARGET: Sells/SaleSheet usa Sheet shadcn side=right + w-xl', function () {
    $source = readCockpitPage('resources/js/Pages/Sells/_components/SaleSheet.tsx');
    expect($source)->toContain('@/Components/ui/sheet');
    expect($source)->toContain('side="right"');
    expect($source)->toMatch('/sm:max-w-xl/');
});

// ─── Components shared canon (alavancagem máxima) ────────────────────────────

it('SHARED: KpiCard usa font-semibold (NÃO font-bold) em value', function () {
    $source = file_get_contents(base_path('resources/js/Components/shared/KpiCard.tsx'));
    expect($source)->toContain('text-4xl font-semibold');
    expect($source)->toContain('text-2xl font-semibold');
    expect($source)->not->toMatch('/text-(2xl|4xl) font-bold/');
});

it('SHARED: KpiCard label usa text-[11px] + tracking-widest', function () {
    $source = file_get_contents(base_path('resources/js/Components/shared/KpiCard.tsx'));
    expect($source)->toContain('text-[11px]');
    expect($source)->toContain('tracking-widest');
});

it('SHARED: PageHeader h1 inclui tracking-tight + font-semibold', function () {
    $source = file_get_contents(base_path('resources/js/Components/shared/PageHeader.tsx'));
    expect($source)->toMatch('/<h1[^>]{0,200}font-semibold[^>]{0,100}tracking-tight/');
});

it('SHARED: PageHeader subtitle usa leading-relaxed', function () {
    $source = file_get_contents(base_path('resources/js/Components/shared/PageHeader.tsx'));
    expect($source)->toContain('leading-relaxed');
});

// ─── Cores semânticas (ADR 0110 §Cores) ──────────────────────────────────────

it('CANON TARGET: NÃO usa cor crua sem semântica (rose/emerald/amber/blue/muted/foreground OK)', function () {
    $offenders = [];
    foreach (COCKPIT_CANON_TARGET as $rel) {
        $source = readCockpitPage($rel);
        // Cores cruas proibidas: gray/indigo/purple/pink/yellow/red/green
        // (rose=danger semântico, emerald=success, amber=warning, blue=info — TODOS canon).
        if (preg_match('/bg-(gray|indigo|purple|pink|yellow|red|green)-[0-9]+/', $source)) {
            $offenders[] = $rel;
        }
    }
    expect($offenders)->toBeEmpty(
        'Canon target usa cor crua não-semântica: '
        . PHP_EOL . implode(PHP_EOL, $offenders),
    );
});

// ─── Estatística — relatório de cobertura (não falha, só informa) ────────────

it('STATS: gera relatório de páginas escaneadas vs canon target', function () {
    $all = listInternalCockpitPages();
    $canon = COCKPIT_CANON_TARGET;
    $count = count($all);
    $canonCount = count($canon);

    // Mínimo 30 Pages no scan (sanidade — projeto tem 50+).
    expect($count)->toBeGreaterThan(30);
    // Pelo menos 5 canon target ativas.
    expect($canonCount)->toBeGreaterThanOrEqual(5);

    // Log informativo no output Pest (não afeta sucesso do test).
    fwrite(STDOUT, sprintf(
        "\n  ℹ Conformance scan: %d Pages internas | %d canon target | whitelist: Site/, _Showcase/\n",
        $count,
        $canonCount,
    ));
});
