<?php

declare(strict_types=1);

use Modules\Jana\Ai\UiJudgeConsensus;

uses(Tests\TestCase::class);

/**
 * R-JANA-UI-CONSENSUS — GUARD da agregação self-consistency do PR UI Judge.
 *
 * Origem: dossiê 2026-06-23 (arte-validacao-L3-humano-judge §3b). O juiz single-shot
 * alucina "ok"; a robustez é amostrar N vezes e agregar a MEDIANA + derivar confiança
 * da concordância. Estes testes travam a matemática PURA de UiJudgeConsensus::aggregate()
 * — sem LLM, sem DB — pra que ela nunca regrida silenciosamente.
 *
 * @see Modules/Jana/Ai/UiJudgeConsensus.php
 */

/**
 * Monta uma amostra parseada do juiz (3 dims semânticas) com scores/rationales dados.
 *
 * @param  array{0:int,1:int,2:int}  $scores  [hierarquia, pt_01_slot, pt_br_voice]
 * @param  array<string, string>  $rationales  por dim (opcional)
 * @param  list<array<string,mixed>>  $violacoes
 * @param  list<string>  $sugestoes
 */
function consensusSample(array $scores, array $rationales = [], array $violacoes = [], array $sugestoes = []): array
{
    return [
        'dimensoes' => [
            'hierarquia_4_camadas' => ['score' => $scores[0], 'rationale' => $rationales['h'] ?? 'h'],
            'pt_01_slot_adherence' => ['score' => $scores[1], 'rationale' => $rationales['p'] ?? 'p'],
            'pt_br_voice_tone' => ['score' => $scores[2], 'rationale' => $rationales['v'] ?? 'v'],
        ],
        'violacoes_estruturais' => $violacoes,
        'sugestoes' => $sugestoes,
        'lembretes' => [],
    ];
}

it('R-JANA-UI-CONSENSUS-001 — score agregado é a MEDIANA dos N (mata o single-shot com sorte)', function () {
    // pt_01_slot: [3,7,9] → mediana 7 (não a média 6.33, não o outlier 9)
    $agg = UiJudgeConsensus::aggregate([
        consensusSample([8, 3, 8]),
        consensusSample([8, 7, 8]),
        consensusSample([8, 9, 8]),
    ]);

    expect($agg['dimensoes']['pt_01_slot_adherence']['score'])->toBe(7)
        ->and($agg['dimensoes']['hierarquia_4_camadas']['score'])->toBe(8)
        ->and($agg['samples'])->toBe(3);
});

it('R-JANA-UI-CONSENSUS-002 — confiança por dim = 1 - spread/10 (variância vira sinal)', function () {
    $agg = UiJudgeConsensus::aggregate([
        consensusSample([8, 3, 8]),
        consensusSample([8, 7, 8]),
        consensusSample([8, 9, 8]),
    ]);

    // pt_01_slot spread 9-3=6 → conf 0.4 ; dims acordadas spread 0 → conf 1.0
    expect($agg['confianca_por_dim']['pt_01_slot_adherence'])->toBe(0.4)
        ->and($agg['confianca_por_dim']['hierarquia_4_camadas'])->toBe(1.0);
});

it('R-JANA-UI-CONSENSUS-003 — confiança GERAL = a menor entre as dims (a discordante manda)', function () {
    $agg = UiJudgeConsensus::aggregate([
        consensusSample([8, 3, 8]),
        consensusSample([8, 7, 8]),
        consensusSample([8, 9, 8]),
    ]);

    // min(1.0, 0.4, 1.0) = 0.4
    expect($agg['confianca'])->toBe(0.4);
});

it('R-JANA-UI-CONSENSUS-004 — ABSTÉM quando confiança < limiar (zona cinza · anti-alucina-ok)', function () {
    $samples = [
        consensusSample([8, 3, 8]),
        consensusSample([8, 7, 8]),
        consensusSample([8, 9, 8]),
    ];

    // limiar default 0.6 > 0.4 → abstém
    expect(UiJudgeConsensus::aggregate($samples)['abstem'])->toBeTrue();
    // limiar 0.3 < 0.4 → NÃO abstém (limiar é honrado)
    expect(UiJudgeConsensus::aggregate($samples, 0.3)['abstem'])->toBeFalse();
});

it('R-JANA-UI-CONSENSUS-005 — alta concordância = confiança 1.0, sem abstenção', function () {
    $agg = UiJudgeConsensus::aggregate([
        consensusSample([8, 8, 8]),
        consensusSample([8, 8, 8]),
        consensusSample([8, 8, 8]),
    ]);

    expect($agg['confianca'])->toBe(1.0)
        ->and($agg['abstem'])->toBeFalse();
});

it('R-JANA-UI-CONSENSUS-006 — rationale representativo é o da amostra mais perto da mediana', function () {
    $agg = UiJudgeConsensus::aggregate([
        consensusSample([8, 3, 8], ['p' => 'A-p']),
        consensusSample([8, 7, 8], ['p' => 'B-p']), // score 7 = mediana
        consensusSample([8, 9, 8], ['p' => 'C-p']),
    ]);

    expect($agg['dimensoes']['pt_01_slot_adherence']['rationale'])->toBe('B-p');
});

it('R-JANA-UI-CONSENSUS-007 — 1 amostra degrada pro single-shot (confiança 1.0, sem abstenção)', function () {
    $agg = UiJudgeConsensus::aggregate([consensusSample([8, 3, 8])]);

    expect($agg['samples'])->toBe(1)
        ->and($agg['confianca'])->toBe(1.0)
        ->and($agg['abstem'])->toBeFalse();
});

it('R-JANA-UI-CONSENSUS-008 — 0 amostras válidas = samples 0, abstém (nada a aprovar)', function () {
    $agg = UiJudgeConsensus::aggregate([]);

    expect($agg['samples'])->toBe(0)
        ->and($agg['abstem'])->toBeTrue()
        ->and($agg['dimensoes'])->toBe([]);
});

it('R-JANA-UI-CONSENSUS-009 — violações deduplicadas por (tipo|arquivo|linha) com consenso k/N', function () {
    $v1 = ['tipo' => 'drawer-sobre-modal', 'arquivo' => 'f.tsx', 'linha' => 1, 'severidade' => 'critical'];
    $v2 = ['tipo' => 'slot-custom', 'arquivo' => 'g.tsx', 'linha' => 2, 'severidade' => 'warning'];

    $agg = UiJudgeConsensus::aggregate([
        consensusSample([8, 8, 8], [], [$v1]),
        consensusSample([8, 8, 8], [], [$v1]),
        consensusSample([8, 8, 8], [], [$v2]),
    ]);

    $vs = $agg['violacoes_estruturais'];
    expect($vs)->toHaveCount(2)
        ->and($vs[0]['consenso'])->toBe('2/3')  // a mais consensual primeiro
        ->and($vs[1]['consenso'])->toBe('1/3');
});

it('R-JANA-UI-CONSENSUS-010 — sugestões deduplicadas case-insensitive', function () {
    $agg = UiJudgeConsensus::aggregate([
        consensusSample([8, 8, 8], [], [], ['Use token semântico']),
        consensusSample([8, 8, 8], [], [], ['use token semântico', 'Mover pro slot 1']),
        consensusSample([8, 8, 8], [], [], ['USE TOKEN SEMÂNTICO']),
    ]);

    expect($agg['sugestoes'])->toBe(['Use token semântico', 'Mover pro slot 1']);
});
