<?php

declare(strict_types=1);

/**
 * Gate A2 — accent do cockpit = ROXO canon (hue 295), nunca o azul 220 antigo.
 *
 * Origem: handoff Cowork 2026-06-02 (bug confirmado): AppShellV2 escrevia
 * `--accent` inline a partir de `accentHue` default 220 (azul), VENCENDO o
 * cascade sobre `cockpit.css .cockpit{ --accent: oklch(0.55 0.15 295) }` (ADR 0190).
 * Resultado: o shell re-azulava o roxo canon pra todo usuário sem tweak salvo.
 *
 * Estrutural (lê o source, sem browser) — protege contra reintrodução do 220.
 *
 * @see resources/js/Layouts/AppShellV2.tsx
 * @see resources/js/Components/cockpit/Sidebar.tsx
 * @see resources/css/cockpit.css
 */

function accentRepoRoot(): string
{
    return dirname(__DIR__, 3);
}

it('AppShellV2 usa hue default 295 (roxo canon), não 220 (azul)', function () {
    $src = file_get_contents(accentRepoRoot().'/resources/js/Layouts/AppShellV2.tsx');

    // Default do accentHue (SSR + fallback localStorage) deve ser 295, nunca 220.
    expect($src)->toContain('return 295;')
        ->and($src)->not->toContain('return 220;');
});

it('AppShellV2 escreve --accent inline com L/C do canon (0.55 0.15)', function () {
    $src = file_get_contents(accentRepoRoot().'/resources/js/Layouts/AppShellV2.tsx');

    expect($src)->toContain('oklch(0.55 0.15 ${accentHue})')   // --accent + --bubble-me
        ->and($src)->toContain('oklch(0.62 0.15 ${accentHue})') // --accent-2
        ->and($src)->not->toContain('oklch(0.58 0.12 ${accentHue})'); // valor antigo off-canon
});

it('Sidebar vibeAccent(workspace) é roxo 295, não azul 220', function () {
    $src = file_get_contents(accentRepoRoot().'/resources/js/Components/cockpit/Sidebar.tsx');

    // A linha do workspace deve apontar pro hue 295.
    expect($src)->toMatch("/case 'workspace':\\s*return 'oklch\\([^)]*295\\)'/")
        ->and($src)->not->toContain('oklch(0.58 0.09 220)');
});

it('cockpit.css ancora o --accent canon em hue 295 (fonte da verdade ADR 0190)', function () {
    $css = file_get_contents(accentRepoRoot().'/resources/css/cockpit.css');

    expect($css)->toContain('--accent:       oklch(0.55 0.15 295)');
});
