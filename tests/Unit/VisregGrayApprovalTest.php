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
