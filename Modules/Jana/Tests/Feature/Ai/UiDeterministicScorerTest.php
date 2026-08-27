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
 *  006. fill sólido em pílula de estado (R7) → anti_padroes cai 2
 *  007. CONTROLE do re-mira da R7 (2026-08-26): par soft + tint são PASS
 *
 * 006/007 nasceram no re-mira da R7: até 2026-08-26 a regra estava apontada pro tint (a forma
 * correta) e cega ao fill, e a suíte não tinha NENHUM caso de R7 — verde não provava nada sobre ela.
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

it('R-JANA-DETSCORE-006 — fill sólido em pílula de estado (R7) baixa anti_padroes_ap1_ap8', function () {
    // `destructive` num Badge é o FILL; o par SOFT é `danger` (#6325). 1 AP → 10 - 1*2 = 8.
    $diff = "+++ b/x.tsx\n+<Badge variant=\"destructive\">Pendente</Badge>\n";

    $r = (new UiDeterministicScorer)->score($diff);

    expect($r['anti_padroes_ap1_ap8']['score'])->toBe(8)
        ->and($r['anti_padroes_ap1_ap8']['rationale'])->toContain('AP7');
});

it('R-JANA-DETSCORE-007 — CONTROLE do re-mira da R7: par soft e tint NÃO são anti-padrão', function () {
    // Até 2026-08-26 a R7 casava `bg-<cor>-(50|100|200)` — o TINT, que neste DS é a forma
    // CORRETA. Este caso é o controle negativo do conserto: as duas linhas abaixo eram
    // `fail` na v1 e têm de ser `pass` na v2. Se alguém re-apontar a regra pro tint, aqui cai.
    $diff = "+++ b/x.tsx\n+<Badge variant=\"danger\">Vencido</Badge>\n+<span className=\"bg-amber-100 text-amber-700\">Vencendo</span>\n";

    $r = (new UiDeterministicScorer)->score($diff);

    expect($r['anti_padroes_ap1_ap8']['score'])->toBe(10);
});
