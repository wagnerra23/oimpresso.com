<?php

declare(strict_types=1);

/**
 * Gate A1 — toda tela Inertia (screen entrypoint) renderiza dentro do AppShellV2.
 *
 * Origem: handoff Cowork 2026-06-02 (PROMPT_PARA_CODE_REFORCO-APPSHELL-TESTES §A1).
 * Hoje a convenção vive em CLAUDE.md §10.3/§10.10 SEM enforcement — este teste
 * transforma a convenção em check automático (estrutural, sem DB nem browser).
 *
 * Ground-truth de "é uma tela": o backend a renderiza via `Inertia::render('X/Y')`
 * ou `inertia('X/Y')`. Sub-componentes (drawers, _lib, components) não são alvo de
 * render, então não entram no gate — evita falso-positivo.
 *
 * Allowlist = telas públicas/auth que legitimamente NÃO usam o shell do cockpit
 * (site de marketing, login/registro, aprovação pública de OS por link).
 *
 * Calibrado contra origin/main 2026-06-02 (8ce7ce1e4): 224 alvos → 0 violação.
 *
 * @see resources/js/Layouts/AppShellV2.tsx
 */

// Prefixos de telas públicas/auth isentas do shell cockpit.
const APPSHELL_ALLOWLIST_PREFIXES = [
    'Site/',                        // marketing + auth (Home, Login, Register, Pricing, Blog…)
];

// Telas isentas por caminho exato (público sem login).
const APPSHELL_ALLOWLIST_EXACT = [
    'OficinaAuto/AprovacaoPublica', // aprovação de OS por link público (sem sessão)
];

/**
 * Descobre todos os alvos de `Inertia::render('X/Y')` / `inertia('X/Y')`
 * em app/ e Modules/.
 *
 * @return array<int, string>
 */
function appShellRepoRoot(): string
{
    // tests/Feature/Architecture -> repo root (3 níveis acima). Sem depender de base_path()
    // (este é um teste estrutural de filesystem, não precisa do app bootstrapado).
    return dirname(__DIR__, 3);
}

function inertiaScreenTargets(): array
{
    $roots = [appShellRepoRoot().'/app', appShellRepoRoot().'/Modules'];
    $targets = [];

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

            $code = file_get_contents($file->getPathname());
            if ($code === false || ! str_contains($code, 'nertia')) {
                continue;
            }

            if (preg_match_all(
                "/(?:Inertia::render|inertia)\(\s*'([A-Za-z0-9_\/]+)'/",
                $code,
                $m
            )) {
                foreach ($m[1] as $t) {
                    $targets[$t] = true;
                }
            }
        }
    }

    return array_keys($targets);
}

function isAppShellAllowlisted(string $target): bool
{
    if (in_array($target, APPSHELL_ALLOWLIST_EXACT, true)) {
        return true;
    }

    foreach (APPSHELL_ALLOWLIST_PREFIXES as $prefix) {
        if (str_starts_with($target, $prefix)) {
            return true;
        }
    }

    return false;
}

it('toda tela Inertia renderiza dentro do AppShellV2 (exceto allowlist pública/auth)', function () {
    $targets = inertiaScreenTargets();

    // Sanidade: o crawler tem que achar um volume realista de telas.
    expect(count($targets))->toBeGreaterThan(100);

    $violations = [];

    foreach ($targets as $target) {
        if (isAppShellAllowlisted($target)) {
            continue;
        }

        $tsx = appShellRepoRoot()."/resources/js/Pages/{$target}.tsx";
        if (! is_file($tsx)) {
            // Alvo sem .tsx correspondente (Blade legacy / nome dinâmico) — fora do gate.
            continue;
        }

        $content = file_get_contents($tsx);
        if ($content === false || ! str_contains($content, 'AppShellV2')) {
            $violations[] = $target;
        }
    }

    sort($violations);

    expect($violations)->toBe([], sprintf(
        "Telas Inertia sem AppShellV2 (%d). Envolva em <AppShellV2> ou, se for pública/auth, "
        ."adicione à allowlist em %s:\n  - %s",
        count($violations),
        'tests/Feature/Architecture/AppShellUsageGateTest.php',
        implode("\n  - ", $violations)
    ));
});
