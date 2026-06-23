<?php

declare(strict_types=1);

use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Modules\Jana\Ai\Agents\PrUiJudgeAgent;

uses(Tests\TestCase::class);

/**
 * R-JANA-UI-JUDGE — GUARD/catraca do PrUiJudgeAgent (juiz de UI · Onda 4.1).
 *
 * Origem 2026-06-05 (parecer PR #2270): o juiz se anunciava "Claude Sonnet 4.5"
 * em 3 lugares (command + workflow) mas rodava #[Provider('openai')] /
 * #[Model('gpt-4o-mini')] — inconsistência ativa ("a mentira do Sonnet").
 * G3 resolveu migrando pra anthropic / claude-sonnet-4-6.
 *
 * Atualizado 2026-06-06 (fix CI · projeto OpenAI sem acesso ao gpt-4o):
 * o canon de provider é openai (config('ai.default')='openai'); o model é
 * gpt-4o-mini porque o projeto OpenAI NÃO tem acesso ao gpt-4o ("Project does
 * not have access to model gpt-4o") — o juiz quebrava em TODO PR de tela.
 * Upgrade de qualidade (gpt-4o liberado / Sonnet c/ crédito) → decisão do #2270.
 * Estes testes TRAVAM a consistência model↔anúncio pra não regredir a "mentira".
 *
 *  001. provider declarado = openai (canon pós-migração)
 *  002. model declarado = gpt-4o-mini (modelo com acesso confirmado)
 *  003. instructions() carrega contrato G-Eval (rationale ANTES do score)
 *  004. instructions() ainda carrega a Constituição UI v2 (4 camadas + AP1-AP8)
 *  005. declara #[Temperature] >0 (self-consistency precisa de variância · 2026-06-23)
 *
 * @see memory/handoffs/2026-06-05-1430-parecer-pr2270-julgamento-ia-design.md
 * @see memory/decisions/0141-agents-tool-use-pattern-claude-code.md
 */
it('R-JANA-UI-JUDGE-001 — declara provider openai (canon pós-migração)', function () {
    $attrs = (new ReflectionClass(PrUiJudgeAgent::class))->getAttributes(Provider::class);

    expect($attrs)->not->toBeEmpty();
    expect($attrs[0]->getArguments()[0])->toBe('openai');
});

it('R-JANA-UI-JUDGE-002 — declara model gpt-4o-mini (modelo com acesso no projeto OpenAI)', function () {
    $attrs = (new ReflectionClass(PrUiJudgeAgent::class))->getAttributes(Model::class);

    expect($attrs)->not->toBeEmpty();
    expect($attrs[0]->getArguments()[0])->toBe('gpt-4o-mini');
});

it('R-JANA-UI-JUDGE-003 — instructions() exige rationale ANTES do score (G-Eval)', function () {
    $prompt = (string) (new PrUiJudgeAgent)->instructions();

    expect($prompt)
        ->toContain('G-Eval')
        ->toContain('rationale')
        ->toContain('raciocine ANTES de pontuar')
        ->toContain('dimensão-por-dimensão');
});

it('R-JANA-UI-JUDGE-004 — instructions() carrega Constituição UI v2 (4 camadas + anti-padrões)', function () {
    $prompt = (string) (new PrUiJudgeAgent)->instructions();

    expect($prompt)
        ->toContain('Constituição UI v2')
        ->toContain('PT-01')
        ->toContain('AP1')
        ->toContain('AP8');
});

it('R-JANA-UI-JUDGE-005 — declara #[Temperature] >0 (self-consistency exige variância)', function () {
    // Sem temperatura as N amostras do UiJudgeConsensus seriam idênticas (greedy)
    // e a confiança derivada da concordância seria FALSA (sempre 1.0). Esta catraca
    // impede que um Edit futuro remova o atributo e reintroduza a "alucinação de ok".
    $attrs = (new ReflectionClass(PrUiJudgeAgent::class))->getAttributes(Temperature::class);

    expect($attrs)->not->toBeEmpty();
    expect((float) $attrs[0]->getArguments()[0])->toBeGreaterThan(0.0);
});
