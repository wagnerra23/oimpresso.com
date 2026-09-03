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

it('AppShellV2 escreve --accent inline com os L/C do canon nos DOIS temas', function () {
    $src = file_get_contents(accentRepoRoot().'/resources/js/Layouts/AppShellV2.tsx');

    // Até 2026-09-02 este teste casava a string inteira `oklch(0.55 0.15 ${accentHue})`, porque
    // --accent e --accent-2 eram `dark_absent` e tinham UM valor só. A ADR UI-0031 deu par escuro
    // aos dois (protótipo Cowork: 0.70 / 0.76), então o L/C passou a sair de uma variável e a
    // string inteira deixou de existir. O que o gate protege é o mesmo: os L/C canônicos estão
    // ancorados no source, e os valores off-canon não voltam. Agora cobre claro E escuro — o
    // par escuro ausente era justamente o bug que a UI-0031 fechou.
    expect($src)->toContain('0.55 0.15')  // --accent claro (ADR 0190) + --bubble-me
        ->and($src)->toContain('0.62 0.15')  // --accent-2 claro
        ->and($src)->toContain('0.70 0.15')  // --accent escuro (UI-0031)
        ->and($src)->toContain('0.76 0.15')  // --accent-2 escuro (= --accent-hi do protótipo)
        ->and($src)->toContain('${accentHue}') // o hue continua vindo do tweak, não hardcoded
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

/**
 * Gate A3 - todo token escrito INLINE no cockpitStyle que tenha par de tema no DTCG
 * precisa seguir o tema.
 *
 * POR QUE EXISTE: o `style` do cockpitStyle vai no MESMO <div> que carrega o
 * `data-theme`, e estilo inline vence qualquer seletor - inclusive
 * `.cockpit[data-theme="dark"]`. Um token inline com valor de tema claro portanto
 * SOBRESCREVE o par escuro que o Style Dictionary gerou, e o escuro morre em runtime
 * sem alarme nenhum. Foi o caso do --accent-soft: o DTCG declarava
 * `com.oimpresso.dark: oklch(0.32 0.06 295)` e o inline cravava `oklch(0.95 0.04 ...)`
 * (quase branco) por cima, no tema escuro.
 *
 * O guard cruza as DUAS fontes (inline x DTCG) em vez de vigiar um token so, pra morder
 * tambem o proximo token que alguem adicionar ao cockpitStyle.
 *
 * LIMITE HONESTO: e estrutural - le o source, nao renderiza. Prova que os L/C do par
 * escuro EXISTEM no arquivo; nao prova que o browser pintou certo. Quem prova o render
 * e o visual-regression.
 *
 * NOTA DE ESCRITA: as regex abaixo sao deliberadamente livres de barra invertida
 * (classes de caractere no lugar de escapes). Ver lapide 2026-08-19 em proibicoes.md -
 * par de barra colapsa no transporte da escrita e o arquivo nasce invalido.
 */
it('todo token inline do cockpitStyle com par de tema no DTCG segue o tema', function () {
    $src = file_get_contents(accentRepoRoot().'/resources/js/Layouts/AppShellV2.tsx');
    $json = json_decode(
        file_get_contents(accentRepoRoot().'/resources/css/tokens/semantic.tokens.json'),
        true
    );
    expect($json)->toBeArray();

    // 1) recorta o bloco do cockpitStyle sem regex (strpos/substr = zero escape)
    $ini = strpos($src, 'const cockpitStyle');
    expect($ini)->not->toBeFalse();
    $fim = strpos($src, '};', $ini);
    expect($fim)->not->toBeFalse();
    $bloco = substr($src, $ini, $fim - $ini);

    // 2) tokens escritos inline nesse bloco
    preg_match_all("~'(--[a-z0-9-]+)' as never~", $bloco, $mm);
    $inline = $mm[1];
    expect($inline)->not->toBeEmpty();

    // 3) tokens cockpit.* que TEM par de tema (dark declarado e diferente do light)
    $comPar = [];
    foreach ($json['cockpit'] ?? [] as $grupo) {
        if (! is_array($grupo)) {
            continue;
        }
        foreach ($grupo as $nome => $def) {
            if (! is_array($def) || ! isset($def['$value'])) {
                continue;
            }
            $dark = $def['$extensions']['com.oimpresso.dark'] ?? null;
            if ($dark !== null && $dark !== $def['$value']) {
                $comPar['--'.$nome] = $dark;
            }
        }
    }
    expect($comPar)->not->toBeEmpty();

    // 4) intersecao: token inline COM par de tema -> os L/C do escuro tem que estar no source
    $faltando = [];
    foreach ($inline as $tok) {
        if (! isset($comPar[$tok])) {
            continue; // sem par (dark_absent no DTCG) -> um valor so e legitimo
        }
        if (preg_match('~oklch[(]([0-9.]+) ([0-9.]+)~', $comPar[$tok], $lc) !== 1) {
            continue;
        }
        if (! str_contains($src, $lc[1].' '.$lc[2])) {
            $faltando[] = $tok.' (par escuro '.$comPar[$tok].' ausente no AppShellV2.tsx)';
        }
    }

    expect($faltando)->toBe([]);
});

it('--accent-soft inline carrega os DOIS pares, e a escolha depende do tema', function () {
    $src = file_get_contents(accentRepoRoot().'/resources/js/Layouts/AppShellV2.tsx');

    // Instancia concreta do gate A3 - o token que motivou o guard.
    // O par escuro saiu de 0.32 0.06 para 0.33 0.09 na ADR UI-0031 (valor do protótipo Cowork).
    expect($src)->toContain('0.95 0.04')    // par claro, canon DTCG
        ->and($src)->toContain('0.33 0.09')  // par escuro, canon DTCG (UI-0031)
        ->and($src)->toContain('userTheme'); // a escolha do par depende do tema
});
