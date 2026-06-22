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

it('o --accent canon (hue 295) está ancorado na fonte de token DTCG (ADR 0190)', function () {
    // Pós-ativação DTCG (#3230): a definição de token saiu do cockpit.css — que agora
    // @importa o CSS gerado — e a FONTE canônica do accent passou a ser
    // resources/css/tokens/semantic.tokens.json (Style Dictionary emite o CSS a partir
    // daqui). O canon continua 295 roxo; só mudou de arquivo. Protege igual contra o 220.
    $json = file_get_contents(accentRepoRoot().'/resources/css/tokens/semantic.tokens.json');

    expect($json)->toMatch('/"accent":\s*\{\s*"\$value":\s*"oklch\(0\.55 0\.15 295\)"/')
        ->and($json)->not->toContain('oklch(0.58 0.09 220)'); // azul antigo off-canon
});
