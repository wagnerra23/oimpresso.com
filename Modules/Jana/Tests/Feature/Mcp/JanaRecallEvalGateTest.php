<?php

declare(strict_types=1);

use Modules\Jana\Console\Commands\JanaRecallEvalCommand;

uses(Tests\TestCase::class);

/**
 * Canary recall@K semanal — contrato do gate real (loop IA-OS #3).
 *
 * ÂNCORA DE CONTRATO (não tautologia — proibicoes.md §"teste que deriva do
 * código"): rotina "FECHAR O LOOP DO IA-OS" item #3 (audit 2026-05-29) —
 * "canary semanal + alerta se recall<80%" — + ADR 0275 (métrica
 * recall_eval_violations, meta → 0: superseded NUNCA no top-N) + handoff
 * 2026-07-05 (recall@5 = 0.815 medido na lane semantic pós-sync-fix #3815;
 * a lane keyword direta do --mode=real mediu 0.074 em 2026-07-05 — o alerta
 * dispara legítimo nela até o retrieval melhorar, next_step #2 do handoff).
 *
 * O contrato exigido do gate:
 *   (a) recall agregado < 0.80 → FAIL (é o alerta do canary);
 *   (b) recall agregado ≥ 0.80 E zero violations → PASS (a lane que atingir
 *       o alvo fica verde e o canary vira detector de regressão);
 *   (c) superseded no top-N (violations > 0) → FAIL mesmo com recall perfeito;
 *   (d) recall@K de uma query = |expected ∩ topK| / |expected| (IR padrão).
 */

// ── (d) métrica por query ────────────────────────────────────────────────

it('computa recall@K padrão de IR por query', function () {
    // 1 de 2 expected no top-K → 0.5
    expect(JanaRecallEvalCommand::recallDaQuery(
        ['0093-multi-tenant', 'briefing:Jana'],
        ['0093-multi-tenant', '0094-constituicao', '0270-ciclo'],
    ))->toBe(0.5);

    // todos os expected presentes → 1.0
    expect(JanaRecallEvalCommand::recallDaQuery(
        ['briefing:Financeiro'],
        ['briefing:Financeiro', 'spec-financeiro'],
    ))->toBe(1.0);

    // nenhum expected presente → 0.0
    expect(JanaRecallEvalCommand::recallDaQuery(['0318-ragas'], ['outro-doc']))->toBe(0.0);

    // expected vazio (defensivo) → 0.0, sem divisão por zero
    expect(JanaRecallEvalCommand::recallDaQuery([], ['x']))->toBe(0.0);
});

// ── (a) alerta recall<80% ────────────────────────────────────────────────

it('reprova quando recall agregado fica abaixo do piso de 80% (o alerta do canary IA-OS #3)', function () {
    expect(JanaRecallEvalCommand::gateRealPassa(
        ['recall_at_k' => 0.79, 'recall_eval_violations' => 0],
        0.80,
    ))->toBeFalse();

    // pré-sync-fix (0.704, handoff 2026-07-05) TINHA que alertar
    expect(JanaRecallEvalCommand::gateRealPassa(
        ['recall_at_k' => 0.704, 'recall_eval_violations' => 0],
        0.80,
    ))->toBeFalse();
});

// ── (b) baseline real passa — canary vira sinal, não ruído ──────────────

it('aprova recall no alvo (0.815 da lane semantic): ≥ 80% + zero violations', function () {
    expect(JanaRecallEvalCommand::gateRealPassa(
        ['recall_at_k' => 0.815, 'recall_eval_violations' => 0],
        0.80,
    ))->toBeTrue();

    // exatamente no piso = passa (alerta é recall < 80%, não ≤)
    expect(JanaRecallEvalCommand::gateRealPassa(
        ['recall_at_k' => 0.80, 'recall_eval_violations' => 0],
        0.80,
    ))->toBeTrue();
});

// ── (c) decaimento: superseded no top-N nunca passa (ADR 0275) ───────────

it('reprova superseded vazando no top-N mesmo com recall perfeito', function () {
    expect(JanaRecallEvalCommand::gateRealPassa(
        ['recall_at_k' => 1.0, 'recall_eval_violations' => 1],
        0.80,
    ))->toBeFalse();
});

it('reprova report real sem métrica (Meilisearch caiu antes de medir ≠ recall ok)', function () {
    expect(JanaRecallEvalCommand::gateRealPassa([], 0.80))->toBeFalse();
});
