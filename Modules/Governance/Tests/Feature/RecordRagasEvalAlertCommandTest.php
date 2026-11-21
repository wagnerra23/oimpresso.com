<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * governance:ragas-eval-alert — sink do eval REAL da Jana (medido no container de
 * staging do CT 100) em mcp_alertas_eventos, via trait canônico PersistsDriftAlert.
 *
 * Contrato (derivado do BURACO medido em 2026-07-27, não da implementação):
 *   - gate_status fail      → persiste 1 alerta drift_ragas_eval_quality (high) e o
 *                             texto NOMEIA qual piso caiu (alerta que só diz "falhou"
 *                             obriga o humano a cavar log no CT 100 — o que este elo
 *                             veio evitar)
 *   - gate_status pass      → NO-OP (não polui mcp_alertas com verde)
 *   - gate_status skipped   → NÃO vira alerta de qualidade (sem infra não é regressão)
 *                             e vira drift_ragas_eval_skipped (medium): o eval não mediu.
 *                             Era no-op até 2026-11-21, e 8 semanas seguidas de skipped
 *                             (09-27 → 11-15, corpus_docs=0) passaram sem ninguém saber
 *   - 2× no mesmo dia       → idempotente (1 alerta/dia, não spam)
 *
 * Caso real que motivou: domingo 2026-07-26 o eval deu fail (context_recall 0.3461 <
 * piso 0.36) e NINGUÉM foi avisado — o onFailure do Kernel não dispara (quem invoca é
 * o cron do host, não o scheduler) e o read-side conta semana `fail` como uptime válido.
 */
beforeEach(function () {
    // delete() (DML transaction-safe) — TRUNCATE dá implicit commit e quebra DatabaseTransactions.
    DB::table('mcp_alertas_eventos')
        ->whereIn('tipo', ['drift_ragas_eval_quality', 'drift_ragas_eval_skipped'])
        ->delete();
});

it('gate_status fail → persiste alerta high com a métrica que caiu nomeada', function () {
    $this->artisan('governance:ragas-eval-alert', [
        '--gate-status' => 'fail',
        '--week' => '2026-07-26',
        '--faithfulness' => '0.6865',
        '--relevancy' => '0.8294',
        '--context-recall' => '0.3461',
        '--n-evaluated' => '51',
    ])->assertExitCode(0);

    $row = DB::table('mcp_alertas_eventos')->where('tipo', 'drift_ragas_eval_quality')->first();
    $meta = json_decode($row->metadata, true);

    expect($row)->not->toBeNull()
        ->and($row->severidade)->toBe('high')
        ->and($row->status)->toBe('aberto')
        ->and($row->business_id)->toBeNull()            // repo-wide (ADR 0093 §Exceção)
        ->and($meta['evidence']['week'])->toBe('2026-07-26')
        ->and($meta['evidence']['context_recall_avg'])->toBe(0.3461)
        ->and($meta['evidence']['n_evaluated'])->toEqual(51); // o JSON devolve 51 inteiro: o contrato é o número, não o tipo float

    // MORDE: a mensagem tem que dizer QUAL piso caiu — e só o do recall caiu neste caso
    // (faithfulness 0.6865 > 0.65 e relevancy 0.8294 > 0.75 estão ACIMA).
    expect($row->descricao)->toContain('context_recall')
        ->and($row->descricao)->not->toContain('faithfulness 0.6865')
        ->and($row->descricao)->not->toContain('answer_relevancy');
});

it('gate_status pass → NO-OP (não cria alerta)', function () {
    $this->artisan('governance:ragas-eval-alert', [
        '--gate-status' => 'pass',
        '--week' => '2026-07-19',
        '--context-recall' => '0.4016',
    ])->assertExitCode(0);

    expect(DB::table('mcp_alertas_eventos')->where('tipo', 'drift_ragas_eval_quality')->count())->toBe(0)
        ->and(DB::table('mcp_alertas_eventos')->where('tipo', 'drift_ragas_eval_skipped')->count())->toBe(0);
});

it('gate_status skipped → alerta de NÃO-MEDIÇÃO, nunca de qualidade', function () {
    $this->artisan('governance:ragas-eval-alert', [
        '--gate-status' => 'skipped',
        '--week' => '2026-11-15',
        '--n-no-context' => '51',
        '--corpus-docs' => '0',
        '--reason' => 'Nenhuma pergunta avaliável (no_context=51, synth_failed=0, judge_failed=0)',
    ])->assertExitCode(0);

    // Sem infra não é regressão: o alerta de qualidade NÃO nasce.
    expect(DB::table('mcp_alertas_eventos')->where('tipo', 'drift_ragas_eval_quality')->count())->toBe(0);

    $row = DB::table('mcp_alertas_eventos')->where('tipo', 'drift_ragas_eval_skipped')->first();
    expect($row)->not->toBeNull();

    $meta = json_decode($row->metadata, true);
    expect($row->severidade)->toBe('medium')
        ->and($row->status)->toBe('aberto')
        ->and($row->business_id)->toBeNull()
        ->and($meta['evidence']['week'])->toBe('2026-11-15')
        ->and($meta['evidence']['corpus_docs'])->toEqual(0)
        ->and($meta['evidence']['n_no_context'])->toEqual(51);

    // MORDE: com corpus 0 o texto aponta a causa; sem isso o humano cava o log no CT 100.
    expect($row->descricao)->toContain('corpus VAZIO')
        ->and($row->descricao)->toContain('no_context=51');
});

it('gate_status skipped com corpus cheio → alerta sai sem acusar corpus vazio', function () {
    $this->artisan('governance:ragas-eval-alert', [
        '--gate-status' => 'skipped',
        '--week' => '2026-11-22',
        '--corpus-docs' => '2555',
        '--reason' => 'OPENAI_API_KEY ausente',
    ])->assertExitCode(0);

    $row = DB::table('mcp_alertas_eventos')->where('tipo', 'drift_ragas_eval_skipped')->first();
    expect($row)->not->toBeNull();
    // Controle: a pista do corpus vazio só aparece quando o dado diz 0.
    expect($row->descricao)->not->toContain('corpus VAZIO')
        ->and($row->descricao)->toContain('OPENAI_API_KEY ausente');
});

it('gate_status skipped 2× no mesmo dia → idempotente', function () {
    $args = ['--gate-status' => 'skipped', '--week' => '2026-11-15', '--corpus-docs' => '0'];

    $this->artisan('governance:ragas-eval-alert', $args)->assertExitCode(0);
    $this->artisan('governance:ragas-eval-alert', $args)->assertExitCode(0);

    expect(DB::table('mcp_alertas_eventos')->where('tipo', 'drift_ragas_eval_skipped')->count())->toBe(1);
});

it('2× no mesmo dia → idempotente (1 alerta/dia, não spam)', function () {
    $args = [
        '--gate-status' => 'fail',
        '--week' => '2026-07-26',
        '--context-recall' => '0.3461',
    ];

    $this->artisan('governance:ragas-eval-alert', $args)->assertExitCode(0);
    $this->artisan('governance:ragas-eval-alert', $args)->assertExitCode(0);

    expect(DB::table('mcp_alertas_eventos')->where('tipo', 'drift_ragas_eval_quality')->count())->toBe(1);
});

it('flags de métrica ausentes → alerta sai mesmo assim, sem inventar número', function () {
    $this->artisan('governance:ragas-eval-alert', [
        '--gate-status' => 'fail',
        '--week' => '2026-07-26',
    ])->assertExitCode(0);

    $row = DB::table('mcp_alertas_eventos')->where('tipo', 'drift_ragas_eval_quality')->first();
    $meta = json_decode($row->metadata, true);

    expect($row)->not->toBeNull()
        ->and($meta['evidence']['context_recall_avg'])->toBeNull()
        ->and($row->descricao)->toContain('piso não identificado'); // a coluna é descricao (PersistsDriftAlert)
});
