<?php

declare(strict_types=1);

use Modules\Jana\Ai\Agents\ProximaPerguntaAgent;

uses(Tests\TestCase::class);

/**
 * R-JANA-ADVISOR-B-AGENT — GUARD tests do ProximaPerguntaAgent (Metade B, ADR 0245).
 *
 *  001. roteamento de modelo lê de config (pautar é difícil → frontier)
 *  002. instructions trazem personas + critério de maior valor + honestidade
 *  003. montarPrompt embute o snapshot real (grounding)
 */
function novoAgente(array $resumo = [], array $personas = []): ProximaPerguntaAgent
{
    return new ProximaPerguntaAgent(
        snapshotResumo: $resumo ?: ['vendas' => ['mes_corrente' => 47150]],
        personas: $personas ?: [
            ['key' => 'larissa', 'label' => 'Balcão', 'foco' => 'vendas'],
            ['key' => 'eliana', 'label' => 'Fiscal', 'foco' => 'inadimplência'],
        ],
        businessName: 'ACME LTDA',
        maxPorPersona: 2,
    );
}

it('R-JANA-ADVISOR-B-AGENT-001 — model() roteia pra frontier via config', function () {
    config(['copiloto.advisor_questions.model' => 'gpt-4o']);
    expect(novoAgente()->model())->toBe('gpt-4o');

    config(['copiloto.advisor_questions.model' => '']);
    expect(novoAgente()->model())->toBeNull();

    config(['copiloto.advisor_questions.provider' => null]);
    expect(novoAgente()->provider())->toBeNull();
    config(['copiloto.advisor_questions.provider' => 'anthropic']);
    expect(novoAgente()->provider())->toBe('anthropic');
});

it('R-JANA-ADVISOR-B-AGENT-002 — instructions trazem personas + valor + honestidade', function () {
    $instr = (string) novoAgente()->instructions();

    expect($instr)
        ->toContain('larissa')
        ->toContain('eliana')
        ->toContain('MAIOR VALOR')
        ->toContain('tem_pergunta=false')
        ->toContain('NÃO invente')
        ->toContain('ACME LTDA');
});

it('R-JANA-ADVISOR-B-AGENT-003 — montarPrompt embute o snapshot (grounding)', function () {
    $resumo = ['inadimplencia' => ['total_devido_atrasado' => 4535636]];
    $prompt = novoAgente($resumo)->montarPrompt();

    expect($prompt)
        ->toContain('4535636')
        ->toContain('snapshot');
});
