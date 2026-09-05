<?php

// @covers-us US-COPI-136

declare(strict_types=1);

use Modules\Jana\Console\Commands\JanaRagasRealEvalCommand;

uses(Tests\TestCase::class);

/**
 * Bite-test do piso de context_recall (US-COPI-136) + procedência dos pisos.
 *
 * Problema que este arquivo fecha: `jana:ragas-real-eval` tinha ZERO testes — o mesmo
 * "a suite mente" que a auditoria de sentinelas (2026-06-20) achou no system-audit.
 * O comando MEDIA context_recall e jogava fora: imprimia como "(info)" com threshold
 * "—" e o excluía do gatePass. Recall podia cair de 0.3839 pra 0.20 sem nada
 * avermelhar, e nenhum teste ficaria vermelho.
 *
 * Estratégia (mesma do SentinelBiteTest): UNIT do veredito via função PURA — prova a
 * REGRA sem DB, sem OPENAI_API_KEY e sem corpus (o pipeline real só existe no CT 100
 * staging; um teste que dependesse dele nunca rodaria no CI e seria gate fantasma).
 *
 * @see Modules/Jana/Tests/Feature/Smoke/SentinelBiteTest.php
 * @see governance/jana-ragas-real-baseline.json (_derivacao_piso_context_recall)
 */

// ── 1. A MORDIDA — o que a US-COPI-136 compra ────────────────────────────────

test('context_recall ABAIXO do piso DERRUBA o gate (a mordida da US-COPI-136)', function () {
    // Cenário real temido: retriever regride, faith/rel seguem bons (a Jana responde
    // bonito sobre o contexto errado) — exatamente o que passava despercebido antes.
    expect(JanaRagasRealEvalCommand::gateVerdict(
        ['faithfulness' => 0.72, 'answer_relevancy' => 0.85, 'context_recall' => 0.20],
        ['faithfulness' => 0.65, 'answer_relevancy' => 0.75, 'context_recall' => 0.36],
    ))->toBeFalse();
});

test('context_recall no baseline medido (0.3839) PASSA o piso 0.36 — piso não nasce vermelho', function () {
    // Piso que reprova o estado atual seria ignorado no dia seguinte. Os 3 pontos
    // reais medidos (0.3839 / 0.3951 / 0.3939) precisam passar.
    foreach ([0.3839, 0.3951, 0.3939] as $medido) {
        expect(JanaRagasRealEvalCommand::gateVerdict(
            ['faithfulness' => 0.6916, 'answer_relevancy' => 0.8039, 'context_recall' => $medido],
            ['faithfulness' => 0.65, 'answer_relevancy' => 0.75, 'context_recall' => 0.36],
        ))->toBeTrue();
    }
});

test('piso é fronteira fechada: medida IGUAL ao piso passa, um fio abaixo reprova', function () {
    $pisos = ['context_recall' => 0.36];

    expect(JanaRagasRealEvalCommand::gateVerdict(['context_recall' => 0.36], $pisos))->toBeTrue();
    expect(JanaRagasRealEvalCommand::gateVerdict(['context_recall' => 0.3599], $pisos))->toBeFalse();
});

// ── 2. HONESTIDADE — não fabricar regressão nem veredito ─────────────────────

test('métrica NÃO MEDIDA (null) não é julgada — 0.0 fabricaria regressão falsa', function () {
    expect(JanaRagasRealEvalCommand::gateVerdict(
        ['faithfulness' => 0.72, 'answer_relevancy' => 0.85, 'context_recall' => null],
        ['faithfulness' => 0.65, 'answer_relevancy' => 0.75, 'context_recall' => 0.36],
    ))->toBeTrue();
});

test('piso null (métrica sem régua) não julga', function () {
    expect(JanaRagasRealEvalCommand::gateVerdict(
        ['context_recall' => 0.01],
        ['context_recall' => null],
    ))->toBeTrue();
});

test('os pisos irmãos seguem mordendo (não regredi faithfulness/relevancy)', function () {
    $pisos = ['faithfulness' => 0.65, 'answer_relevancy' => 0.75, 'context_recall' => 0.36];

    expect(JanaRagasRealEvalCommand::gateVerdict(
        ['faithfulness' => 0.60, 'answer_relevancy' => 0.85, 'context_recall' => 0.40], $pisos
    ))->toBeFalse();

    expect(JanaRagasRealEvalCommand::gateVerdict(
        ['faithfulness' => 0.72, 'answer_relevancy' => 0.70, 'context_recall' => 0.40], $pisos
    ))->toBeFalse();
});

// ── 3. O BASELINE É O DONO — contrato do arquivo em governance/ ───────────────

test('baseline versionado tem os 3 pisos e o context_recall não reprova o medido', function () {
    $path = base_path('governance/jana-ragas-real-baseline.json');
    expect(file_exists($path))->toBeTrue();

    $json = json_decode((string) file_get_contents($path), true);
    $pisos = $json['thresholds_regressao'] ?? [];

    expect($pisos)->toHaveKeys(['faithfulness', 'answer_relevancy', 'context_recall']);

    // O piso tem que estar ABAIXO do recall medido versionado no mesmo arquivo —
    // senão o alarme nasce tocando e vira ruído que todo mundo aprende a ignorar.
    expect($pisos['context_recall'])->toBeLessThan($json['context_recall_avg']);

    // ...e acima de zero: piso 0 seria régua decorativa (nunca morde).
    expect($pisos['context_recall'])->toBeGreaterThan(0.0);
});

// ── 4. JUIZ MUDO ≠ REGRESSÃO — o discriminador (2026-09-04) ──────────────────
//
// Origem: as semanas 2026-08-09 e 2026-08-16 do trend (órfã governance/ragas-real-trend)
// caíram para context_recall 0,043 e 0,0314 e foram lidas como regressão da Jana. Medido
// no evals.log do CT 100: em 2026-08-16, 31 das 37 falhas listadas tinham faithfulness
// EXATAMENTE 0 — e como a média foi 0,2748 sobre 50 avaliadas, as 19 restantes somam
// média 0,723, estatisticamente igual ao 0,7127 de julho. Não foi a Jana: foi o juiz
// devolvendo 0.0 em erro (RagasJudgeService::callJudge loga warning e nunca lança, então
// n_judge_failed é 0 por construção).

test('as três métricas exatamente 0.0 = juiz mudo (assinatura, não qualidade)', function () {
    expect(JanaRagasRealEvalCommand::ehTriploZeroDoJuiz(0.0, 0.0, 0.0))->toBeTrue();
});

test('retrieval ruim NÃO é triplo zero — relevancy não olha o contexto', function () {
    // Cenário REAL de recall ruim: o contexto não cobre o ground_truth (recall 0), mas
    // a resposta segue coerente com o que foi recuperado e pertinente à pergunta. Foi
    // exatamente o shape de idx=4 e idx=6 na run de 2026-08-16 (0.5/1.0/0 e 0.5/0.4/0).
    expect(JanaRagasRealEvalCommand::ehTriploZeroDoJuiz(0.5, 1.0, 0.0))->toBeFalse();
    expect(JanaRagasRealEvalCommand::ehTriploZeroDoJuiz(0.5, 0.4, 0.0))->toBeFalse();

    // E o inverso: qualidade baixa mas MEDIDA nas três — também não é juiz mudo.
    expect(JanaRagasRealEvalCommand::ehTriploZeroDoJuiz(0.1, 0.1, 0.1))->toBeFalse();
});

test('zero em DUAS métricas ainda não basta — o discriminador exige as três', function () {
    // Controle negativo do próprio critério: afrouxar para "duas zeradas" transformaria
    // recall-zero-com-relevancy-zero (possível com resposta vazia legítima) em falso
    // alarme de infra. O sinal caro é o TRIPLO.
    expect(JanaRagasRealEvalCommand::ehTriploZeroDoJuiz(0.7, 0.0, 0.0))->toBeFalse();
    expect(JanaRagasRealEvalCommand::ehTriploZeroDoJuiz(0.0, 0.0, 0.9))->toBeFalse();
});
