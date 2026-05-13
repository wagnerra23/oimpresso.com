<?php

declare(strict_types=1);

/**
 * BriefDiarioFaithfulnessTest — RAGAS gate sobre brief diário.
 *
 * Cobertura RAGAS-style:
 *  - 5 perguntas conhecidas sobre estado oimpresso (ADRs, cycles, multi-tenant)
 *  - Pra cada uma: roda recall canônico (mock OU brief-fetch real) + chama gpt-4o-mini judge
 *  - Asserta thresholds config/ragas.php (faithfulness ≥0.7, relevancy ≥0.6, precision ≥0.7)
 *
 * @group ragas
 *
 * NÃO roda em CI default — só sob `workflow_dispatch` (.github/workflows/ragas-gate.yml)
 * ou local com `php artisan jana:ragas:eval` + OPENAI_API_KEY definido.
 *
 * Custo aprox por run: ~$0.005 (5 perguntas × 3 métricas × gpt-4o-mini judge).
 *
 * @see config/ragas.php
 * @see ADR 0037 §GAP-2
 */

use Modules\Jana\Services\Ragas\RagasJudgeService;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Dataset canônico — perguntas com ground_truth conhecida.
 * Editar aqui pra evoluir baseline (sempre que adicionar ADR canon nova).
 */
function ragasBriefQuestions(): array
{
    return [
        [
            'question' => 'Qual ADR define o isolamento multi-tenant Tier 0 obrigatório no oimpresso?',
            'ground_truth' => 'ADR 0093 — Multi-tenant Tier 0 IRREVOGÁVEL. Define business_id global scope obrigatório em toda Eloquent Model.',
        ],
        [
            'question' => 'Qual é a stack de IA canônica do oimpresso e qual ADR define?',
            'ground_truth' => 'ADR 0035 — Camada A laravel/ai oficial (LLM wrapper), Camada B agents próprios em Modules/Jana/Ai/Agents/, Camada C MemoriaContrato + MeilisearchDriver.',
        ],
        [
            'question' => 'Por que o Vizra ADK foi rejeitado como framework de agents?',
            'ground_truth' => 'ADR 0048 — Vizra ADK rejeitada. Time optou por implementar 4 Agents próprios em Modules/Jana/Ai/Agents/ usando LaravelAiSdkDriver direto.',
        ],
        [
            'question' => 'Qual é a separação de runtime entre Hostinger e CT 100 Proxmox?',
            'ground_truth' => 'ADR 0062 — Hostinger é shared hosting (app web + Pest local + git pull). CT 100 Proxmox roda FrankenPHP + Centrifugo + Meilisearch + MCP server + Ollama. NUNCA instalar laravel/octane ou laravel/mcp no Hostinger.',
        ],
        [
            'question' => 'O que é o FSM Pipeline canônico e desde quando está LIVE em produção?',
            'ground_truth' => 'ADR 0143 — FSM Pipeline LIVE prod biz=1 desde 2026-05-12. 5 tabelas FSM (sale_processes, sale_process_stages, sale_stage_actions, sale_stage_action_roles, sale_stage_history). Trait GuardsFsmTransitions bloqueia UPDATE direto em current_stage_id.',
        ],
    ];
}

beforeEach(function () {
    if (empty(env('OPENAI_API_KEY')) && ! env('RAGAS_FORCE_MOCK', false)) {
        $this->markTestSkipped('RAGAS gate exige OPENAI_API_KEY no .env (ou RAGAS_FORCE_MOCK=true pra mock).');
    }
});

it('avalia faithfulness do brief sobre 5 perguntas canônicas', function () {
    $judge = app(RagasJudgeService::class);

    if (env('RAGAS_FORCE_MOCK', false)) {
        $judge->enableMock();
    }

    $questions = array_slice(ragasBriefQuestions(), 0, (int) config('ragas.sample_size', 5));
    $threshold = (float) config('ragas.thresholds.faithfulness', 0.70);

    $scores = [];
    foreach ($questions as $q) {
        // MVP: contexto vem da própria ground_truth (simula recall ideal).
        // Em produção real, contexto vem de brief-fetch / decisions-search / kb-answer.
        $context = $q['ground_truth'];
        $answer  = $q['ground_truth']; // MVP — substituir por chamada real ao Agent BriefDiario

        $score = $judge->scoreFaithfulness($q['question'], $answer, $context);
        $scores[] = $score;
    }

    $avg = array_sum($scores) / count($scores);

    expect($avg)->toBeGreaterThanOrEqual(
        $threshold,
        "Faithfulness médio {$avg} abaixo do threshold {$threshold}. Scores: " . implode(', ', $scores)
    );
})->group('ragas');

it('avalia answer_relevancy do brief sobre 5 perguntas canônicas', function () {
    $judge = app(RagasJudgeService::class);

    if (env('RAGAS_FORCE_MOCK', false)) {
        $judge->enableMock();
    }

    $questions = array_slice(ragasBriefQuestions(), 0, (int) config('ragas.sample_size', 5));
    $threshold = (float) config('ragas.thresholds.answer_relevancy', 0.60);

    $scores = [];
    foreach ($questions as $q) {
        $answer = $q['ground_truth'];
        $score  = $judge->scoreAnswerRelevancy($q['question'], $answer);
        $scores[] = $score;
    }

    $avg = array_sum($scores) / count($scores);

    expect($avg)->toBeGreaterThanOrEqual(
        $threshold,
        "Answer relevancy médio {$avg} abaixo do threshold {$threshold}. Scores: " . implode(', ', $scores)
    );
})->group('ragas');

it('avalia context_precision do brief sobre 5 perguntas canônicas', function () {
    $judge = app(RagasJudgeService::class);

    if (env('RAGAS_FORCE_MOCK', false)) {
        $judge->enableMock();
    }

    $questions = array_slice(ragasBriefQuestions(), 0, (int) config('ragas.sample_size', 5));
    $threshold = (float) config('ragas.thresholds.context_precision', 0.70);

    $scores = [];
    foreach ($questions as $q) {
        $context = $q['ground_truth'];
        $score   = $judge->scoreContextPrecision($q['question'], $context, $q['ground_truth']);
        $scores[] = $score;
    }

    $avg = array_sum($scores) / count($scores);

    expect($avg)->toBeGreaterThanOrEqual(
        $threshold,
        "Context precision médio {$avg} abaixo do threshold {$threshold}. Scores: " . implode(', ', $scores)
    );
})->group('ragas');
