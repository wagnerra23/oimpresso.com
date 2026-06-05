<?php

declare(strict_types=1);

use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Modules\Jana\Ai\Agents\PrUiJudgeAgent;

uses(Tests\TestCase::class);

/**
 * R-JANA-UI-JUDGE — GUARD/catraca do PrUiJudgeAgent (juiz de UI · Onda 4.1).
 *
 * Origem 2026-06-05 (parecer PR #2270): o juiz se anunciava "Claude Sonnet 4.5"
 * em 3 lugares (command + workflow) mas rodava #[Provider('openai')] /
 * #[Model('gpt-4o-mini')] — inconsistência ativa ("a mentira do Sonnet").
 * G3 resolveu migrando pra anthropic / claude-sonnet-4-6 (canon do projeto).
 * Estes testes TRAVAM a consistência pra ela não regredir silenciosamente.
 *
 *  001. provider declarado = anthropic (não openai)
 *  002. model declarado = claude-sonnet-4-6 (canon · BrainBAgent/EvalCommands)
 *  003. instructions() carrega contrato G-Eval (rationale ANTES do score)
 *  004. instructions() ainda carrega a Constituição UI v2 (4 camadas + AP1-AP8)
 *
 * @see memory/handoffs/2026-06-05-1430-parecer-pr2270-julgamento-ia-design.md
 * @see memory/decisions/0141-agents-tool-use-pattern-claude-code.md
 */
it('R-JANA-UI-JUDGE-001 — declara provider anthropic (não a mentira do openai)', function () {
    $attrs = (new ReflectionClass(PrUiJudgeAgent::class))->getAttributes(Provider::class);

    expect($attrs)->not->toBeEmpty();
    expect($attrs[0]->getArguments()[0])->toBe('anthropic');
});

it('R-JANA-UI-JUDGE-002 — declara model claude-sonnet-4-6 (canon do projeto)', function () {
    $attrs = (new ReflectionClass(PrUiJudgeAgent::class))->getAttributes(Model::class);

    expect($attrs)->not->toBeEmpty();
    expect($attrs[0]->getArguments()[0])->toBe('claude-sonnet-4-6');
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
