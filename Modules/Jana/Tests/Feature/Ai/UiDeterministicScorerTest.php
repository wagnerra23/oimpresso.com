<?php

declare(strict_types=1);

use Modules\Jana\Ai\UiDeterministicScorer;

/**
 * Onda 1 (LLM-judge → determinístico · ADR 0255): prova que o porte PHP dos regex de
 * score-mechanized.mjs computa as 6 dimensões determinísticas a partir do diff — sem LLM,
 * reproduzível. As 3 dims semânticas (hierarquia/slot/voz) seguem no juiz LLM.
 *
 * IDs:
 *  001. diff limpo → 10 em todas as 6 dims
 *  002. cor crua (R1) → tokens_semanticos cai pra 4
 *  003. elemento nativo (R2) → componentes_shared cai pra 4
 *  004. múltiplos anti-padrões → anti_padroes_ap1_ap8 cai proporcional
 *  005. #fff/#000 não conta como cor crua (exceção do R1)
 */
it('R-JANA-DETSCORE-001 — diff limpo pontua 10 nas 6 dims determinísticas', function () {
    $diff = "+++ b/x.tsx\n+<Button variant=\"cowork-primary\">Salvar</Button>\n+<Badge>novo</Badge>\n";

    $r = (new UiDeterministicScorer)->score($diff);

    expect($r['tokens_semanticos']['score'])->toBe(10)
        ->and($r['componentes_shared']['score'])->toBe(10)
        ->and($r['localStorage_prefix_oimpresso']['score'])->toBe(10)
        ->and($r['lucide_iconography_only']['score'])->toBe(10)
        ->and($r['anti_padroes_ap1_ap8']['score'])->toBe(10);
});

it('R-JANA-DETSCORE-002 — cor crua (R1) derruba tokens_semanticos pra 4', function () {
    $diff = "+++ b/x.tsx\n+const cor = 'oklch(0.5 0.1 200)';\n+const outra = '#1572E8';\n";

    $r = (new UiDeterministicScorer)->score($diff);

    expect($r['tokens_semanticos']['score'])->toBe(4)
        ->and($r['tokens_semanticos']['rationale'])->toContain('Cor crua');
});

it('R-JANA-DETSCORE-003 — elemento nativo (R2) derruba componentes_shared pra 4', function () {
    $diff = "+++ b/x.tsx\n+<select className=\"h-9\"><option>a</option></select>\n";

    $r = (new UiDeterministicScorer)->score($diff);

    expect($r['componentes_shared']['score'])->toBe(4);
});

it('R-JANA-DETSCORE-004 — múltiplos anti-padrões grep-áveis baixam anti_padroes_ap1_ap8', function () {
    // R1 (cor crua) + R2 (nativo) = 2 AP → 10 - 2*2 = 6
    $diff = "+++ b/x.tsx\n+const c = '#abc123';\n+<input type=\"text\" />\n";

    $r = (new UiDeterministicScorer)->score($diff);

    expect($r['anti_padroes_ap1_ap8']['score'])->toBe(6);
});

it('R-JANA-DETSCORE-005 — #fff/#000 NÃO conta como cor crua (exceção R1)', function () {
    $diff = "+++ b/x.tsx\n+<div className=\"bg-[#fff] text-[#000]\" />\n";

    $r = (new UiDeterministicScorer)->score($diff);

    expect($r['tokens_semanticos']['score'])->toBe(10);
});
