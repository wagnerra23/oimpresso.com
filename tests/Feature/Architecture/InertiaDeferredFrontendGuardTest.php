<?php

declare(strict_types=1);

// Tests\TestCase já é aplicado globalmente em tests/Pest.php (uses(TestCase::class)->in('Feature')).
// NÃO redeclarar aqui — Pest 4 lança TestCaseAlreadyInUse.

/**
 * ARCHITECTURE TEST — contrato defer BACKEND↔FRONTEND, repo-wide.
 *
 * Regressão que trava (classe de bug do PR #3862 — Ponto/Dashboard, e a
 * varredura repo-wide 2026-07-06 que achou 19 telas):
 *   Um controller entrega props via `Inertia::defer(fn () => ...)` → a prop é
 *   `undefined` no FIRST render (até o auto-fetch async resolver). Se a Page
 *   `resources/js/Pages/<Comp>.tsx` desreferencia essa prop direto (`.map`,
 *   `.length`, `.data`) sem `<Deferred>` nem guarda `?.`/`?? []` → TypeError →
 *   TELA BRANCA no primeiro paint.
 *
 * O `Modules/Governance/Tests/Feature/InertiaDeferAuditTest` cobre a metade
 * BACKEND (controllers Governance USAM defer). Os
 * `DashboardDeferredContractTest` por-tela (um por Módulo) cobrem por-tela,
 * por-prop, o FRONTEND de telas críticas. Faltava o gate REPO-WIDE do lado
 * frontend — este arquivo.
 *
 * Contrato (coarse, baixo falso-positivo — igual filosofia do
 * InertiaDeferAuditTest): toda Page alvo de um `Inertia::render(...)` cujo
 * array de props contém `Inertia::defer(` DEVE **importar `Deferred` de
 * '@inertiajs/react'** (prova de que usa o padrão de embrulho) **OU** estar na
 * GUARD_ONLY_ALLOWLIST (telas que guardam via `?.`/default-destructure sem
 * `<Deferred>` — padrão SAFE catalogado na varredura).
 *
 * Parse é POR CHAMADA de render (balanced-paren), não por arquivo — evita o
 * falso-positivo "o defer está em OUTRO método do mesmo controller".
 *
 * Regra dos 3 (ADR 0263/0264):
 *   - MORDE: roda no ui-architecture-gate; falha em tela nova com defer sem guarda.
 *   - AUTO-TESTA: caso negativo sintético in-band (prova que o dente morde).
 *   - VIGIA: allowlist explícita versionada (só ENCOLHE, nunca skip silencioso).
 *
 * Filesystem-puro (sem DB/browser). Helpers com prefixo `deferGuard*` pra não
 * colidir com OrphanRenderGateTest/AppShellUsageGateTest no mesmo suite.
 *
 * @see memory/requisitos/_DesignSystem/RUNBOOK-inertia-defer-pattern.md
 * @see Modules/Governance/Tests/Feature/InertiaDeferAuditTest.php (metade backend)
 * @see tests/Feature/Architecture/OrphanRenderGateTest.php (mesmo idioma de gate)
 */

/**
 * Telas que tratam props deferidas SEM importar `<Deferred>` — guardam via
 * optional-chaining / `?? default` / default-destructure. SAFE catalogado na
 * varredura repo-wide 2026-07-06. Esta seção é PERMANENTE (não some).
 */
const DEFER_GUARD_ONLY_ALLOWLIST = [
    'Financeiro/Extrato/Index'      => 'guarda via `lancamentos = []` default + `totais?.` (sessão 2026-07-06 sweep)',
    'Jana/Dashboard'                => 'passa coworkAggregates inteiro pro filho JanaCockpitV2, que guarda `?.`/`?? []`',
    'ProjectMgmt/Backlog/Index'     => 'default-destructure `{ tasks = [], kpis = EMPTY_KPIS, ... }`',
    'ProjectMgmt/Board/Index'       => 'default-destructure `{ epics = [], cycles = [], owners = [] }`',
    'ProjectMgmt/Burndown/Index'    => 'early-return `if (!cycle)` gate + defer group atômico',
    'ProjectMgmt/Inbox/Index'       => 'default-destructure `{ inbox = [], inbox_stats = EMPTY }`',
    'ProjectMgmt/MyWork/Index'      => 'default-destructure `{ my_work = [], inbox = [], kpis = EMPTY }`',
    'ProjectMgmt/Roadmap/Index'     => 'default-destructure `{ quarters = [], kpis = EMPTY }`',
    'ProjectMgmt/Triage/Index'      => 'default-destructure `{ tasks = [], cycles = [], owners = [] }`',
    'Sells/Index'                   => 'usePage + `props.coworkAggregates?.` em todo acesso',
    'team-mcp/CcSessions/Index'     => 'guarda `sessions?.data ?? []`, `kpis ?? {...}` + isLoading gate',
    'team-mcp/Scorecard/Index'      => 'isLoading `facts === undefined` gate + `checks ?? []`',
    'team-mcp/Tasks/Index'          => 'default-destructure `{ modulos = [], owners = [], ... }` + `?? {}`',
    'Essentials/Messages/Index'     => '`messages` semeia useState(messages ?? []) — não dá pra embrulhar em <Deferred>; guarda por default (fix #3867)',
    'Auditoria/Detail'              => 'early-return `if (!activity) { return ... }` antes de qualquer deref (sweep 2026-07-06)',
];

// PENDING allowlist ZERADA (2026-07-06): a varredura achou 19 offenders; os 18
// que viraram <Deferred> foram corrigidos e mergeados (#3866–#3871) — cada Page
// agora importa <Deferred> e é validada direto pelo teste principal. O 19º
// (Essentials/Messages/Index) é guard-only e vive na allowlist PERMANENTE acima.
// Burn-down completo: allowlist transitória chegou a [] (meta cumprida).

function deferGuardRepoRoot(): string
{
    // tests/Feature/Architecture -> repo root (3 níveis acima).
    return dirname(__DIR__, 3);
}

/**
 * Extrai TODA chamada `Inertia::render('Comp', [ ... ])` de um source PHP,
 * associando a cada uma se o SEU array de props contém `Inertia::defer(`.
 * Usa contagem de parênteses balanceados pra pegar o corpo exato do render
 * (as closures de defer têm parênteses aninhados).
 *
 * @return list<array{page: string, has_defer: bool}>
 */
function deferGuardExtractRenders(string $src): array
{
    $out = [];
    $needle = 'Inertia::render(';
    $offset = 0;
    $len = strlen($src);

    while (($pos = strpos($src, $needle, $offset)) !== false) {
        $i = $pos + strlen($needle);
        $depth = 1;
        while ($i < $len && $depth > 0) {
            $ch = $src[$i];
            if ($ch === '(') {
                $depth++;
            } elseif ($ch === ')') {
                $depth--;
            }
            $i++;
        }
        $body = substr($src, $pos + strlen($needle), $i - ($pos + strlen($needle)) - 1);
        $offset = $i;

        // Componente = primeira string literal do corpo. Inclui `-` e `.` porque
        // há componentes hifenizados (`team-mcp/...`) e com ponto (`kb/Index.v2`).
        if (! preg_match('/^\s*[\'"]([A-Za-z0-9_\/.\-]+)[\'"]/', $body, $m)) {
            continue;
        }
        $out[] = [
            'page'      => $m[1],
            'has_defer' => str_contains($body, 'Inertia::defer('),
        ];
    }

    return $out;
}

/**
 * Todos os pares (page, has_defer) varrendo controllers em app/ e Modules/
 * (pula Tests/ pra não pegar render mockado em fixture de teste).
 *
 * @return array<string, bool> page => temDeferEmAlgumRender
 */
function deferGuardRenderMap(): array
{
    $roots = [deferGuardRepoRoot().'/app', deferGuardRepoRoot().'/Modules'];
    $map = [];

    foreach ($roots as $root) {
        if (! is_dir($root)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $path = str_replace('\\', '/', $file->getPathname());
            // Só controllers — é onde vivem os `Inertia::render(...)` reais. Exclui
            // Services/Repositories/Routes/Commands que carregam a STRING
            // 'Inertia::defer(' como heurística de grading (ex ModuleGradeService)
            // ou doc, sem render de verdade — evita alvo-fantasma.
            if (! str_contains($path, '/Http/Controllers/')) {
                continue;
            }
            if (str_contains($path, '/Tests/') || str_contains($path, '/tests/')) {
                continue;
            }
            $code = file_get_contents($file->getPathname());
            if ($code === false || ! str_contains($code, 'Inertia::defer(')) {
                continue;
            }
            foreach (deferGuardExtractRenders($code) as $r) {
                $map[$r['page']] = ($map[$r['page']] ?? false) || $r['has_defer'];
            }
        }
    }

    return $map;
}

/** Page importa `Deferred` de '@inertiajs/react'? */
function deferGuardPageImportsDeferred(string $pageSource): bool
{
    return (bool) preg_match(
        '/import\s*\{[^}]*\bDeferred\b[^}]*\}\s*from\s*[\'"]@inertiajs\/react[\'"]/',
        $pageSource
    );
}

it('toda Page alvo de render com Inertia::defer importa <Deferred> ou está na allowlist guard-only', function () {
    $root = deferGuardRepoRoot();
    $renderMap = deferGuardRenderMap();

    // Sanidade: o crawler tem que achar um volume realista de renders-com-defer.
    $withDefer = array_filter($renderMap);
    expect(count($withDefer))->toBeGreaterThan(15);

    $violations = [];
    foreach (array_keys($withDefer) as $page) {
        if (array_key_exists($page, DEFER_GUARD_ONLY_ALLOWLIST)) {
            continue;
        }

        $tsx = $root.'/resources/js/Pages/'.$page.'.tsx';
        $jsx = $root.'/resources/js/Pages/'.$page.'.jsx';
        $file = is_file($tsx) ? $tsx : (is_file($jsx) ? $jsx : null);

        // Page inexistente = problema do OrphanRenderGateTest, não deste. Skip.
        if ($file === null) {
            continue;
        }

        $source = (string) file_get_contents($file);
        if (! deferGuardPageImportsDeferred($source)) {
            $violations[] = $page;
        }
    }

    sort($violations);

    expect($violations)->toBe([], sprintf(
        "%d Page(s) recebem props via Inertia::defer mas NÃO importam <Deferred> ".
        "nem estão na allowlist guard-only — first render vai crashar (tela branca). ".
        "Embrulhe a região deferida em `<Deferred data=\"...\" fallback={skeleton}>` ".
        "(RUNBOOK-inertia-defer-pattern.md §3), OU — se a tela guarda via ?./?? default ".
        "sem <Deferred> — adicione à DEFER_GUARD_ONLY_ALLOWLIST com justificativa em %s:\n  - %s",
        count($violations),
        'tests/Feature/Architecture/InertiaDeferredFrontendGuardTest.php',
        implode("\n  - ", $violations)
    ));
});

it('AUTO-TESTA: o detector morde uma Page sintética com prop deferida crua sem <Deferred>', function () {
    // Render sintético com defer + Page sintética SEM import de Deferred → deve violar.
    $renders = deferGuardExtractRenders(
        "return Inertia::render('__Synthetic/Offender', ['rows' => Inertia::defer(fn () => \$this->rows())]);"
    );
    expect($renders)->toHaveCount(1);
    expect($renders[0]['page'])->toBe('__Synthetic/Offender');
    expect($renders[0]['has_defer'])->toBeTrue();

    // Page sem <Deferred> importado → detector acusa (contrato do gate).
    $offenderSource = "import { Link } from '@inertiajs/react';\nexport default function X({ rows }) { return rows.map(r => r); }";
    expect(deferGuardPageImportsDeferred($offenderSource))->toBeFalse();

    // Page que importa Deferred → passa.
    $safeSource = "import { Deferred, Link } from '@inertiajs/react';";
    expect(deferGuardPageImportsDeferred($safeSource))->toBeTrue();
});

it('AUTO-TESTA: defer em OUTRO render do mesmo controller não contamina um render eager', function () {
    // Dois renders no mesmo source: um com defer, outro sem. Parse por chamada
    // (balanced-paren) tem que separar corretamente.
    $src = "public function a() { return Inertia::render('Mod/Eager', ['x' => \$rows]); }\n".
           "public function b() { return Inertia::render('Mod/Deferred', ['y' => Inertia::defer(fn () => 1)]); }";
    $renders = deferGuardExtractRenders($src);

    $byPage = [];
    foreach ($renders as $r) {
        $byPage[$r['page']] = $r['has_defer'];
    }
    expect($byPage['Mod/Eager'])->toBeFalse();
    expect($byPage['Mod/Deferred'])->toBeTrue();
});
