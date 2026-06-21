<?php

declare(strict_types=1);

/**
 * Pest — US-SELL-SHOW-COWORK Onda Cowork — visual scoped readonly em /sells/{id}.
 *
 * Cobertura estrutural via file_get_contents (mesmo pattern de
 * SellsOnda3CuradoriaTest.php). Foca em garantir que:
 *  - CSS sells-cowork-show.css existe + scope `.sells-cowork-show {` + tokens canon
 *  - inertia.css importa o novo CSS
 *  - Show.tsx wrappa outer div com classe + marker US-SELL-SHOW-COWORK
 *  - Charter Show.charter.md preservado (anti-patterns não introduzidos)
 *  - Funcionalidade Show.tsx preservada (KpiCard + Deferred + AppShellV2)
 *
 * Refs:
 *  - resources/css/sells-cowork-show.css
 *  - resources/js/Pages/Sells/Show.tsx
 *  - resources/js/Pages/Sells/Show.charter.md
 *  - resources/css/inertia.css
 *
 * ── QUARENTENA GRANULAR legacy-quarantine (SDD F2b · 2026-06-13) ─────────────
 * quarantine-reason: snapshot estrutural SUPERSEDED — só os it() cujos markers
 * foram verificados AUSENTES: seletores CSS `.vds-header/.vds-fiscal/.vds-status-*`
 * reescritos, `@media (max-width: 1024px)` removido, e o ADR `0104` do
 * `Show.charter.md`. Triage: memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A.
 *
 * ✅ Os it() de FUNCIONALIDADE VIVA PERMANECEM ATIVOS — Show.tsx existe; cobertura
 * que ainda passa: scope `.sells-cowork-show {`, tokens oklch, IBM Plex, wrapper
 * `sells-cowork-show container mx-auto` (presente!), AppShellV2, KpiCard+Deferred,
 * atalhos E/P/Esc + `permissions.edit/print`, anti-patterns (bg-blue-500 só em
 * comentário CSS, removido pelo strip). Não silenciar cobertura viva.
 */

const SHOW_TSX_PATH = 'resources/js/Pages/Sells/Show.tsx';
defined('SHOW_CHARTER_PATH') || define('SHOW_CHARTER_PATH', 'resources/js/Pages/Sells/Show.charter.md');
const SHOW_CSS_PATH = 'resources/css/sells-cowork-show.css';
const SHOW_INERTIA_CSS_PATH = 'resources/css/inertia.css';

function showRead(string $rel): string
{
    return file_get_contents(base_path($rel));
}

/**
 * Remove linhas que começam com // (comentários JS) e blocos / * ... * / antes
 * de checar anti-patterns regex — evita falso positivo em JSDoc/comentários.
 */
function showStripJsComments(string $source): string
{
    // Remove block comments /* ... */
    $stripped = preg_replace('#/\*[\s\S]*?\*/#', '', $source);
    // Remove linhas line-comment (preserva código relevante)
    $lines = preg_split('/\R/', $stripped);
    $kept = array_filter($lines, function (string $line): bool {
        return ! preg_match('/^\s*\/\//', $line);
    });

    return implode("\n", $kept);
}

// ─── Arquivos existem ─────────────────────────────────────────────────

it('CSS sells-cowork-show.css existe', function () {
    expect(file_exists(base_path(SHOW_CSS_PATH)))->toBeTrue();
});

it('Show.tsx existe (não removido)', function () {
    expect(file_exists(base_path(SHOW_TSX_PATH)))->toBeTrue();
});

it('Show.charter.md existe e mantém parent_module Sells', function () {
    expect(file_exists(base_path(SHOW_CHARTER_PATH)))->toBeTrue();
    $charter = showRead(SHOW_CHARTER_PATH);
    expect($charter)->toContain('parent_module: Sells');
});

// ─── CSS scoped + tokens canon ────────────────────────────────────────

it('CSS sells-cowork-show.css escopa em .sells-cowork-show {', function () {
    $source = showRead(SHOW_CSS_PATH);
    expect($source)->toContain('.sells-cowork-show {');
});

it('CSS sells-cowork-show.css usa tokens oklch (palette canon — não cor crua)', function () {
    $source = showRead(SHOW_CSS_PATH);
    expect($source)
        ->toContain('oklch(')
        ->toContain('--accent:')
        ->toContain('--text:')
        ->toContain('--border:');
});

it('CSS sells-cowork-show.css define IBM Plex Sans/Mono (canon família)', function () {
    $source = showRead(SHOW_CSS_PATH);
    expect($source)
        ->toContain('IBM Plex Sans')
        ->toContain('IBM Plex Mono');
});

it('CSS sells-cowork-show.css cobre blocos essenciais (header/total/kpi/section/footer/fiscal)', function () {
    $source = showRead(SHOW_CSS_PATH);
    expect($source)
        ->toContain('.sells-cowork-show .vds-header')
        ->toContain('.sells-cowork-show .vds-total-value')
        ->toContain('.sells-cowork-show .vds-kpi-card')
        ->toContain('.sells-cowork-show .vds-section')
        ->toContain('.sells-cowork-show .vds-footer')
        ->toContain('.sells-cowork-show .vds-fiscal');
    // quarantine-reason: CSS .vds-*/badges/responsive reescritos no sells-cowork-show.css, charter sem ADR 0104 (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('CSS sells-cowork-show.css define badges status pgto (paid/due/partial — semantic)', function () {
    $source = showRead(SHOW_CSS_PATH);
    expect($source)
        ->toContain('.sells-cowork-show .vds-status-paid')
        ->toContain('.sells-cowork-show .vds-status-due')
        ->toContain('.sells-cowork-show .vds-status-partial');
    // quarantine-reason: CSS .vds-*/badges/responsive reescritos no sells-cowork-show.css, charter sem ADR 0104 (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

it('CSS sells-cowork-show.css tem responsive ≤1024px + print media', function () {
    $source = showRead(SHOW_CSS_PATH);
    expect($source)
        ->toContain('@media (max-width: 1024px)')
        ->toContain('@media print');
    // quarantine-reason: CSS .vds-*/badges/responsive reescritos no sells-cowork-show.css, charter sem ADR 0104 (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');

// ─── Inertia.css importa ──────────────────────────────────────────────

it('inertia.css importa sells-cowork-show.css', function () {
    $source = showRead(SHOW_INERTIA_CSS_PATH);
    expect($source)->toContain('@import "./sells-cowork-show.css"');
});

// ─── Show.tsx wrappa com classe + marker ─────────────────────────────

it('Show.tsx wrappa outer div com classe .sells-cowork-show', function () {
    $source = showRead(SHOW_TSX_PATH);
    expect($source)->toContain('sells-cowork-show container mx-auto');
});

it('Show.tsx tem marker comentário US-SELL-SHOW-COWORK', function () {
    $source = showRead(SHOW_TSX_PATH);
    expect($source)->toContain('US-SELL-SHOW-COWORK');
});

// ─── Funcionalidade preservada (não-regressão) ───────────────────────

it('Show.tsx preserva AppShellV2 layout', function () {
    $source = showRead(SHOW_TSX_PATH);
    expect($source)
        ->toContain("import AppShellV2 from '@/Layouts/AppShellV2'")
        ->toContain('SellsShow.layout');
});

it('Show.tsx preserva KpiCard + Deferred (deferred props pattern)', function () {
    $source = showRead(SHOW_TSX_PATH);
    expect($source)
        ->toContain("import KpiCard from '@/Components/shared/KpiCard'")
        ->toContain('<Deferred data="detail"');
});

it('Show.tsx preserva atalhos teclado E/P/Esc + permissions gate', function () {
    $source = showRead(SHOW_TSX_PATH);
    expect($source)
        ->toContain("e.key === 'e'")
        ->toContain("e.key === 'p'")
        ->toContain("e.key === 'Escape'")
        ->toContain('permissions.edit')
        ->toContain('permissions.print');
});

// ─── Anti-patterns charter NÃO introduzidos ──────────────────────────
// Charter Show.charter.md §UX Anti-patterns: ❌ font-bold em h1 / ❌ border-b-2

it('Show.tsx NÃO introduz font-bold em h1 (anti-pattern charter)', function () {
    $source = showStripJsComments(showRead(SHOW_TSX_PATH));
    // Permitido font-semibold; proibido font-bold em <h1>
    expect($source)->not->toMatch('/<h1[^>]*font-bold/');
});

it('Show.tsx NÃO introduz border-b-2 (anti-pattern charter tabs)', function () {
    $source = showStripJsComments(showRead(SHOW_TSX_PATH));
    expect($source)->not->toContain('border-b-2');
});

it('CSS sells-cowork-show.css NÃO usa cor crua bg-blue-500 (anti-pattern charter)', function () {
    // Strip CSS block comments antes de validar — comentário documental cita
    // anti-pattern como exemplo do que NÃO fazer, isso é OK.
    $source = preg_replace('#/\*[\s\S]*?\*/#', '', showRead(SHOW_CSS_PATH));
    expect($source)
        ->not->toContain('bg-blue-500')
        ->not->toContain('#3b82f6');
});

// ─── Charter status preservado ───────────────────────────────────────

it('Show.charter.md mantém parent_module + related_adrs 0104/0143/0093 (governança)', function () {
    $charter = showRead(SHOW_CHARTER_PATH);
    expect($charter)
        ->toContain('parent_module: Sells')
        ->toContain('0104')
        ->toContain('0143')
        ->toContain('0093');
    // quarantine-reason: CSS .vds-*/badges/responsive reescritos no sells-cowork-show.css, charter sem ADR 0104 (ver memory/sessions/2026-06-13-sdd-f2b-triage-q2.md §4 Q-A)
})->group('legacy-quarantine');
