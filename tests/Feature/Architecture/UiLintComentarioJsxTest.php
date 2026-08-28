<?php

declare(strict_types=1);

use App\Console\Commands\UiLintCommand;

/**
 * `ui:lint` R1/R3 — o skip de comentário reconhece a forma do JSX (`{/*`).
 *
 * POR QUE EXISTE: o skip só conhecia `//`, `*` e `/*`. Dentro do render de um
 * `.tsx` a ÚNICA forma de comentar abre com chave seguida de barra-asterisco,
 * que não casava no skip — comentário de JS era pulado, o de JSX era escaneado.
 *
 * O efeito é perverso e não é hipótese: quem documentasse a cor ou o emoji
 * proibido CITANDO o nome dele criava a própria violação. No corpus havia três
 * instâncias independentes, todas comentários registrando a REMOÇÃO da coisa
 * proibida — `Fiscal/Sped.tsx` (hex), `Financeiro/Unificado` (emoji) e a de
 * `Ponto/Intercorrencias/Edit`, que motivou o conserto.
 *
 * Cada caso vem em PAR — morde a violação real, solta o comentário — porque um
 * skip novo só é seguro se provar que NÃO afrouxou a regra. Medido no corpus:
 * R1 101→94, R3 55→54, zero cor real perdida.
 *
 * Testa a LÓGICA via reflection (mesmo padrão do UiLintR7BundleParaleloTest),
 * então não depende de arquivo real que amanhã pode ser refatorado.
 *
 * @see app/Console/Commands/UiLintCommand.php COMMENT_LINE_RE
 */
function uiLintR1(string $relPath, string $conteudo): array
{
    $cmd = new UiLintCommand;
    $ref = new ReflectionMethod($cmd, 'checkR1');
    $ref->setAccessible(true);

    return $ref->invoke($cmd, $relPath, explode("\n", $conteudo));
}

function uiLintR3(string $relPath, string $conteudo): array
{
    $cmd = new UiLintCommand;
    $ref = new ReflectionMethod($cmd, 'checkR3');
    $ref->setAccessible(true);

    return $ref->invoke($cmd, $relPath, $conteudo);
}

// ── R1 · cor crua ────────────────────────────────────────────────────────────

it('MORDE: classe de cor crua no CÓDIGO (controle negativo — o lint segue mordendo)', function () {
    $hits = uiLintR1(
        'resources/js/Pages/Ponto/Exemplo.tsx',
        '<span className="text-stone-400">olá</span>',
    );

    expect($hits)->toHaveCount(1);
    expect($hits[0]['rule'])->toBe('R1');
    expect($hits[0]['match'])->toBe('text-stone-400');
});

it('SOLTA: a MESMA classe citada dentro de comentário JSX', function () {
    $hits = uiLintR1(
        'resources/js/Pages/Ponto/Exemplo.tsx',
        '{/* as telas irmãs ainda usam text-stone-400 aqui — dívida do os-page-h */}',
    );

    expect($hits)->toBeEmpty(
        'Comentário JSX não renderiza cor nenhuma. Contá-lo torna impossível '
        . 'DOCUMENTAR a classe proibida sem cometer a violação.'
    );
});

it('MORDE: hex cru no CÓDIGO', function () {
    $hits = uiLintR1(
        'resources/js/Pages/Ponto/Exemplo.tsx',
        "<div style={{ background: '#fafaf9' }} />",
    );

    expect($hits)->toHaveCount(1);
    expect($hits[0]['match'])->toBe('#fafaf9');
});

it('SOLTA: referência a PR dentro de comentário JSX (os dígitos de #1496 são hex válidos)', function () {
    $hits = uiLintR1(
        'resources/js/Pages/Financeiro/Exemplo.tsx',
        '{/* Wave 4: migrado pra <PageHeader> canon v3.8 (PR #1496) */}',
    );

    expect($hits)->toBeEmpty(
        'Nº de PR não é cor. `1496` casa no regex de hex por acidente — e essa '
        . 'era a origem de 4 dos 8 falsos-positivos medidos no corpus.'
    );
});

it('as formas ANTIGAS de comentário seguem soltas (o fix ampliou, não trocou)', function () {
    foreach (['// text-stone-400', ' * text-stone-400', '/* text-stone-400 */'] as $linha) {
        expect(uiLintR1('resources/js/Pages/X/Y.tsx', $linha))->toBeEmpty(
            "A abertura '{$linha}' já era reconhecida antes e tem de continuar."
        );
    }
});

// ── R3 · emoji ───────────────────────────────────────────────────────────────

it('MORDE: emoji no CÓDIGO (controle negativo do R3)', function () {
    $hits = uiLintR3(
        'resources/js/Pages/Ponto/Exemplo.tsx',
        '<span>🗄 Arquivo</span>',
    );

    expect($hits)->not->toBeEmpty();
    expect($hits[0]['rule'])->toBe('R3');
});

it('SOLTA: emoji citado dentro de comentário JSX', function () {
    $hits = uiLintR3(
        'resources/js/Pages/Financeiro/Exemplo.tsx',
        '{/* Fidelidade [W]: emoji 🗄 → lucide Archive (proibição PRE-MERGE-UI AP6) */}',
    );

    expect($hits)->toBeEmpty(
        'O comentário registra que o emoji FOI REMOVIDO. Contá-lo pune quem '
        . 'documentou a correção.'
    );
});

// ── o residual, declarado como teste pra não virar surpresa ──────────────────

it('RESIDUAL DECLARADO: linha de CONTINUAÇÃO de comentário JSX segue escaneada', function () {
    $conteudo = "{/* primeira linha do comentário\n    segunda linha com text-stone-400 */}";

    expect(uiLintR1('resources/js/Pages/X/Y.tsx', $conteudo))->toHaveCount(1,
        'O skip é por PREFIXO DE LINHA — a continuação começa em texto e é '
        . 'escaneada. Mesmo comportamento do comentário `/* */` de sempre. '
        . 'Rastrear o bloco de verdade foi MEDIDO e REJEITADO: a máquina de '
        . 'estado ingênua classificava os hex REAIS do PwaInstallBanner como '
        . '"dentro de comentário" e esconderia violação de verdade. Se este '
        . 'caso um dia mudar, foi decisão consciente — não acidente.'
    );
});
