<?php

declare(strict_types=1);

use Tests\Browser\Support\VisregThreshold;

it('bloqueia zona cinza sem aprovação e libera somente o label explícito', function () {
    $gray = [['screen' => 'Sells/Create', 'ratio' => 0.01, 'diffView' => null]];

    expect(VisregThreshold::grayZoneRequiresApproval([], '0'))->toBeFalse()
        ->and(VisregThreshold::grayZoneRequiresApproval($gray, '0'))->toBeTrue()
        ->and(VisregThreshold::grayZoneRequiresApproval($gray, '1'))->toBeFalse();
});

/**
 * BITE-TEST do conserto de 2026-08-19 — o afterAll bloqueava MUDO.
 *
 * Assinatura observada em 4 branches (runs 32293916799, 32294492239, 32295811223,
 * 32297931647): `Tests: 12 passed (42 assertions)` e então `exit 2`, sem uma linha de
 * causa. O PHPUnit engole o Throwable do afterAll (Framework/TestSuite.php `catch
 * (Throwable $t) {}`) e só o converte em `hasErrors()`; o console printer do Pest não
 * renderiza esse evento. Quem operava o CI via só o exit 2.
 *
 * Estes asserts fixam o que o bloco de log precisa dizer, e não o parágrafo inteiro:
 * asserção sobre prosa quebraria em qualquer melhoria de redação.
 */
it('o relatório de zona cinza nomeia cada tela e o ratio medido', function () {
    $items = [
        ['screen' => 'financeiro-unificado · selecionar-lote · compact', 'ratio' => 0.001158, 'diffView' => 'storage/app/visreg-diffs/x.html'],
        ['screen' => 'compras · abrir-drawer · wide', 'ratio' => 0.0004, 'diffView' => null],
    ];

    $bloqueado = VisregThreshold::grayZoneConsoleReport($items, true);

    expect($bloqueado)
        ->toContain('financeiro-unificado · selecionar-lote · compact')
        ->toContain('0.1158%')                              // o ratio medido, não "divergiu"
        ->toContain('compras · abrir-drawer · wide')
        ->toContain('0.0400%')
        ->toContain('storage/app/visreg-diffs/x.html')      // por onde o [W] abre o diff
        ->toContain('2 tela(s)')
        ->toContain('VISREG_GRAY_APPROVED');                // o que destrava

    // Aprovado pelo label: mesma lista, sem anunciar bloqueio que não existe.
    expect(VisregThreshold::grayZoneConsoleReport($items, false))
        ->toContain('financeiro-unificado · selecionar-lote · compact')
        ->not->toContain('BLOQUEADO');
});

/**
 * CONTRATO com scripts/tests/visreg-flake-retry.sh.
 *
 * Os dois regexes são LIDOS DO SCRIPT, nunca copiados: um duplicado aqui envelheceria
 * em silêncio e o teste passaria a medir a própria cópia (memory/proibicoes.md §5
 * 2026-07-17 — não restatear o que outro sistema sabe melhor).
 *
 * Por que importa: se o texto casasse o PIXEL_DIFF_RE, o retry anunciaria "falha é
 * REGRESSÃO DE PIXEL" para uma falha de zona cinza — afirmar causa não medida, o mesmo
 * defeito que este PR conserta. Se casasse o FLAKE_RE, re-rodaria e mascararia o
 * bloqueio que o [W] precisa ver.
 */
it('o relatório de zona cinza não casa a denylist de pixel nem a allowlist de flake do retry', function () {
    $script = dirname(__DIR__, 2) . '/scripts/tests/visreg-flake-retry.sh';
    expect(is_file($script))->toBeTrue("script do retry não encontrado em {$script}");

    $sh = (string) file_get_contents($script);

    $extrair = function (string $nome) use ($sh, $script): string {
        expect(preg_match("/^{$nome}='([^']+)'/m", $sh, $m))->toBe(1, "{$nome} não encontrado em {$script}");

        return $m[1];
    };

    $pixelRe = $extrair('PIXEL_DIFF_RE');
    $flakeRe = $extrair('FLAKE_RE');

    $texto = VisregThreshold::grayZoneConsoleReport(
        [['screen' => 'financeiro-unificado · selecionar-lote · compact', 'ratio' => 0.001158, 'diffView' => 'x.html']],
        true,
    );

    expect(preg_match('#' . $pixelRe . '#u', $texto))->toBe(0, 'o texto da zona cinza casou a denylist de pixel-diff');
    expect(preg_match('#' . $flakeRe . '#u', $texto))->toBe(0, 'o texto da zona cinza casou a allowlist de flake');

    // Controle positivo: os regexes extraídos MORDEM o que devem morder. Sem isto, um
    // regex vazio/quebrado daria os dois zeros acima e o teste seria carimbo.
    expect(preg_match('#' . $pixelRe . '#u', 'VisregThreshold [x]: diff 3% > τ_alto 2% — REGRESSÃO CLARA.'))->toBe(1);
    expect(preg_match('#' . $flakeRe . '#u', 'Alvo visual não ficou disponível: drawer'))->toBe(1);
});

/**
 * BITE-TEST do conserto de 2026-08-24 — o gate reprovava por tela que o PR nao tocou.
 *
 * O QUE FOI MEDIDO (nao e impressao): `Governance/DsRollout -> 0.1226%` reprovou TRES
 * branches independentes no mesmo dia — runs 32727277038, 32731270402 e 32744800768. O
 * ratio IDENTICO em branches distintas e a prova de que a divergencia vinha da main, nao
 * do PR: o #6175 mergeou 12:58 com o label `visreg-gray-approved` (que aprova o PR, nunca
 * a baseline) e deixou o drift orfao. O #6184, cujo raio real era UMA tela
 * (`Produto/Unificado`, medido pelo classificador), levou o vermelho de uma tela de
 * Governance.
 *
 * Os asserts abaixo sao pares boa/ruim: sem o controle negativo (raio nao confiavel ->
 * bloqueia tudo) este teste seria carimbo, nao gate.
 */
it('zona cinza fora do raio do PR nao bloqueia, dentro do raio bloqueia', function () {
    $dsRollout = ['screen' => 'Governance/DsRollout', 'source' => 'governance/DsRollout', 'ratio' => 0.001226, 'diffView' => null];
    $produto = ['screen' => 'Produto/Unificado', 'source' => 'Produto/Unificado', 'ratio' => 0.003621, 'diffView' => null];

    // Repro do #6184: raio = Produto/Unificado; DsRollout ja divergia na main.
    $raio = ['Produto/Unificado'];

    $so_herdada = VisregThreshold::particionaGrayZone([$dsRollout], $raio, true);
    expect($so_herdada['propria'])->toBe([])
        ->and($so_herdada['herdada'])->toHaveCount(1);
    expect(VisregThreshold::grayZoneRequiresApproval($so_herdada['propria'], '0'))
        ->toBeFalse('divida herdada da main nao pode reprovar PR que nao a causou');

    // MORDE: a tela DENTRO do raio segue bloqueando sem o label.
    $com_propria = VisregThreshold::particionaGrayZone([$dsRollout, $produto], $raio, true);
    expect($com_propria['propria'])->toHaveCount(1)
        ->and($com_propria['propria'][0]['screen'])->toBe('Produto/Unificado')
        ->and($com_propria['herdada'])->toHaveCount(1);
    expect(VisregThreshold::grayZoneRequiresApproval($com_propria['propria'], '0'))
        ->toBeTrue('tela no raio do PR tem que continuar bloqueando');
});

it('sem raio confiavel o bloqueio segue absoluto — o comportamento de antes', function () {
    $itens = [
        ['screen' => 'Governance/DsRollout', 'source' => 'governance/DsRollout', 'ratio' => 0.001226, 'diffView' => null],
    ];

    // CONTROLE NEGATIVO 1 — raio existe mas o classificador nao confia nele (fundacao
    // visual / tokens / toolchain mexem em tela que import nenhum revela).
    $naoConfiavel = VisregThreshold::particionaGrayZone($itens, ['Produto/Unificado'], false);
    expect($naoConfiavel['propria'])->toHaveCount(1)->and($naoConfiavel['herdada'])->toBe([]);

    // CONTROLE NEGATIVO 2 — sem raio nenhum (env ausente / JSON ilegivel).
    $semRaio = VisregThreshold::particionaGrayZone($itens, null, true);
    expect($semRaio['propria'])->toHaveCount(1)->and($semRaio['herdada'])->toBe([]);

    // CONTROLE NEGATIVO 3 — item sem `source` e conservador. Desde 2026-08-26 as 4 suites
    // de estados/fluxos declaram `source` no manifesto, entao este ramo passou a ser a
    // rede de seguranca (suite nova, manifesto sem o campo), nao mais o caso comum.
    $semSource = VisregThreshold::particionaGrayZone(
        [['screen' => 'compras · abrir-drawer · wide', 'ratio' => 0.0004, 'diffView' => null]],
        ['Produto/Unificado'],
        true,
    );
    expect($semSource['propria'])->toHaveCount(1)->and($semSource['herdada'])->toBe([]);
});

/**
 * REPRO do incidente 2026-08-26 (PR #6008 e mais 4 branches).
 *
 * O raio JA funcionava — mas so o PixelBaselineTest passava `source`. As 4 suites de
 * estados/fluxos nao passavam, entao TODO item delas caia no ramo conservador e bloqueava
 * qualquer PR. Medido em 13 runs de visual-regression (25-26/08): 12 tinham zona cinza, e
 * em 11 delas o UNICO item era `financeiro-unificado · estado={default,loading,error}` a
 * 0.1199% — tela que NENHUM dos PRs tocava (raios medidos: `Modules`, `Jana`, `Arquivos`,
 * `governance/DriftAlerts`). 5 branches distintas reprovadas pela mesma divida da main.
 *
 * O par boa/ruim e o que impede o conserto de virar carimbo: com `source` declarado a
 * tela fora do raio para de bloquear, e a MESMA tela dentro do raio segue bloqueando.
 */
it('estado isolado fora do raio nao bloqueia; dentro do raio bloqueia', function () {
    // Shape exato do IsolatedStatesBaselineTest: label kebab+estado, source = namespace.
    $estados = array_map(fn (string $estado) => [
        'screen' => "financeiro-unificado · estado={$estado}",
        'source' => 'Financeiro/Unificado',
        'ratio' => 0.001199,
        'diffView' => null,
    ], ['default', 'loading', 'error']);

    // O raio real do #6008: o PR mexeu em resources/js/Pages/Modules/Index.tsx.
    $foraDoRaio = VisregThreshold::particionaGrayZone($estados, ['Modules'], true);
    expect($foraDoRaio['propria'])->toBe([])
        ->and($foraDoRaio['herdada'])->toHaveCount(3);
    expect(VisregThreshold::grayZoneRequiresApproval($foraDoRaio['propria'], '0'))
        ->toBeFalse('adicionar coluna em Modules/Index nao pode reprovar por drift do Financeiro');

    // MORDE: quem de fato mexeu no Financeiro/Unificado continua bloqueado.
    $dentroDoRaio = VisregThreshold::particionaGrayZone($estados, ['Financeiro/Unificado'], true);
    expect($dentroDoRaio['propria'])->toHaveCount(3)
        ->and($dentroDoRaio['herdada'])->toBe([]);
    expect(VisregThreshold::grayZoneRequiresApproval($dentroDoRaio['propria'], '0'))
        ->toBeTrue('a tela no raio do PR tem que continuar bloqueando');
});

it('o relatorio nomeia a divida herdada sem casar a denylist do retry', function () {
    $herdada = [['screen' => 'Governance/DsRollout', 'source' => 'governance/DsRollout', 'ratio' => 0.001226, 'diffView' => null]];

    // Nenhuma propria + uma herdada: nao pode anunciar bloqueio nem "0 tela(s)" na zona cinza.
    $texto = VisregThreshold::grayZoneConsoleReport([], false, $herdada);

    expect($texto)
        ->toContain('Governance/DsRollout')
        ->toContain('0.1226%')
        ->toContain('FORA DO RAIO DESTE PR')
        ->toContain('herdada')
        ->not->toContain('BLOQUEADO')
        // Nao foi o label que liberou — foi a ausencia de divida DESTE PR. Anunciar o label
        // aqui seria o artefato afirmando o que nao mediu.
        ->not->toContain('Liberado pelo label')
        ->toContain('Nada a aprovar neste PR');

    // Mesmo contrato de texto do outro teste: o retry nao pode ler isto como pixel nem flake.
    $script = dirname(__DIR__, 2) . '/scripts/tests/visreg-flake-retry.sh';
    $sh = (string) file_get_contents($script);
    $extrair = function (string $nome) use ($sh): string {
        expect(preg_match("/^{$nome}='([^']+)'/m", $sh, $m))->toBe(1, "{$nome} nao encontrado");

        return $m[1];
    };

    expect(preg_match('#' . $extrair('PIXEL_DIFF_RE') . '#u', $texto))->toBe(0, 'o texto da divida herdada casou a denylist de pixel-diff');
    expect(preg_match('#' . $extrair('FLAKE_RE') . '#u', $texto))->toBe(0, 'o texto da divida herdada casou a allowlist de flake');
});
