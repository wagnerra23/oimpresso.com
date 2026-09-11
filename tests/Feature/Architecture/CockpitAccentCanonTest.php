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
 * 2026-09-08 (ADR UI-0034): o gate MUDOU DE FORMA, não de objetivo. Até aqui ele vigiava
 * se o inline usava os L/C certos; agora vigia que o inline NÃO EXISTE. O seletor de matiz
 * (accentHue + slider "Tom do accent") foi removido — ele deixava a preferência de UM
 * navegador mandar na cor do DS, e o default dele ERA 220 até 2026-06-08, sem migração
 * (quem abriu antes via azul até hoje). Sem mecanismo não há como re-azular: é a MESMA
 * proteção, por um caminho que não depende de alguém acertar valor. Os testes abaixo foram
 * REESCRITOS, nunca desabilitados.
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

it('AppShellV2 nao tem seletor de matiz - nada reescreve o accent em runtime (UI-0034)', function () {
    $src = file_get_contents(accentRepoRoot().'/resources/js/Layouts/AppShellV2.tsx');

    // O state, a persistencia e a interpolacao do hue sairam. O nome pode sobreviver em
    // COMENTARIO (a nota historica que explica a remocao); o que morde e o CODIGO.
    expect($src)->not->toContain('setAccentHue')
        ->and($src)->not->toContain('LS.TW_HUE')
        ->and($src)->not->toContain('return 220;');
});

it('TweaksPanel nao oferece controle de cor - so vibe e densidade (UI-0034)', function () {
    $src = file_get_contents(accentRepoRoot().'/resources/js/Components/cockpit/TweaksPanel.tsx');

    // ALVO DELIMITADO, de proposito: o cabecalho do arquivo carrega a NOTA HISTORICA da
    // UI-0034, e ela CITA o rotulo removido pra explicar por que ele saiu. Medir o arquivo
    // inteiro casaria o proprio comentario - o presence-gate que este repo ja catalogou
    // ("o ratchet pegou o COMENTARIO que citava o anti-padrao, porque o guard casa texto",
    // Manufacturing/Index.tsx). O JSX vive do `export function` pra baixo; e la que se mede.
    $corpo = substr($src, (int) strpos($src, 'export function TweaksPanel'));
    expect(strpos($src, 'export function TweaksPanel'))->not->toBeFalse();

    // Densidade CONTINUA: e layout do usuario. Cor nao: e token do DS.
    expect($corpo)->not->toContain('onHue')
        ->and($corpo)->not->toContain('Tom do accent')
        ->and($corpo)->toContain('onDensity');
});

it('o cockpitStyle nao escreve NENHUM token de cor - so densidade (UI-0034)', function () {
    $src = file_get_contents(accentRepoRoot().'/resources/js/Layouts/AppShellV2.tsx');

    // Antes da UI-0034 este teste conferia se o inline usava os L/C certos. Agora confere que
    // o inline de COR nao existe - protecao mais forte, porque nao depende de ninguem acertar
    // valor. Estilo inline vence .cockpit[data-theme=dark] (e o MESMO div), entao qualquer
    // token de cor aqui volta a matar o par de tema que o Style Dictionary gerou.
    $ini = strpos($src, 'const cockpitStyle');
    expect($ini)->not->toBeFalse();
    $fim = strpos($src, '};', $ini);
    expect($fim)->not->toBeFalse();
    $bloco = substr($src, $ini, $fim - $ini);

    foreach (['--accent', '--accent-2', '--accent-soft', '--bubble-me'] as $proibido) {
        expect($bloco)->not->toContain($proibido);
    }

    // e a densidade, que E legitima aqui, continua.
    expect($bloco)->toContain('--row-h')
        ->and($bloco)->toContain('--card-pad');
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

it('--accent-soft tem os DOIS pares no DTCG - e ninguem os sobrescreve inline (UI-0034)', function () {
    // Instancia concreta do gate A3 - o token que motivou o guard. Antes o par vivia no
    // inline; agora vive so no DTCG, que e onde deveria estar desde sempre.
    $json = file_get_contents(accentRepoRoot().'/resources/css/tokens/semantic.tokens.json');
    $src = file_get_contents(accentRepoRoot().'/resources/js/Layouts/AppShellV2.tsx');

    expect($json)->toContain('oklch(0.95 0.04 295)')
        ->and($json)->toContain('oklch(0.33 0.09 295)')
        ->and($src)->not->toContain('accentSoftLC');
});

it('--bubble-me tem par de tema PROPRIO - o inline nao o segura mais (UI-0034)', function () {
    // Sem o inline, o alias var(--accent) levaria a bolha a 0.70 no escuro e o texto branco
    // FIXO (--bubble-me-fg: #ffffff) cairia de 5,17:1 para 2,81:1 - reprova AA em 4
    // consumidores, incl. as bolhas do Whatsapp. O prototipo declara 0.55 literal e nao
    // redeclara no escuro; o token passou a ter par proprio com esse valor.
    $json = json_decode(
        file_get_contents(accentRepoRoot().'/resources/css/tokens/semantic.tokens.json'),
        true
    );
    $bubble = $json['cockpit']['bubble']['bubble-me'] ?? null;
    expect($bubble)->toBeArray();

    $dark = $bubble['$extensions']['com.oimpresso.dark'] ?? null;
    expect($dark)->toBe('oklch(0.55 0.15 295)');
});
