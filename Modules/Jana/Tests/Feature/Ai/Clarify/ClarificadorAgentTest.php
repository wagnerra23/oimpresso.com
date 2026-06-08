<?php

declare(strict_types=1);

use Modules\Jana\Ai\Agents\ClarificadorAgent;
use Modules\Jana\Support\ContextoNegocio;

uses(Tests\TestCase::class);

/**
 * R-JANA-ADVISOR-AGENT — GUARD tests do ClarificadorAgent (disambiguador frontier).
 *
 *  001. roteamento de modelo seletivo lê de config (difícil → frontier)
 *  002. provider null quando não configurado (cai no default do SDK)
 *  003. instructions decoupla ambiguidade-de-intenção de falta-de-dado
 *  004. grounding não-PII aparece quando há ContextoNegocio
 *  005. messages() mapeia histórico recente (resolução de dêixis)
 */
it('R-JANA-ADVISOR-AGENT-001 — model() roteia pra frontier via config', function () {
    config(['copiloto.clarify.model' => 'gpt-4o']);
    expect((new ClarificadorAgent('x'))->model())->toBe('gpt-4o');

    config(['copiloto.clarify.model' => 'claude-sonnet-4-6']);
    expect((new ClarificadorAgent('x'))->model())->toBe('claude-sonnet-4-6');

    config(['copiloto.clarify.model' => '']);
    expect((new ClarificadorAgent('x'))->model())->toBeNull();
});

it('R-JANA-ADVISOR-AGENT-002 — provider null quando não configurado', function () {
    config(['copiloto.clarify.provider' => null]);
    expect((new ClarificadorAgent('x'))->provider())->toBeNull();

    config(['copiloto.clarify.provider' => 'anthropic']);
    expect((new ClarificadorAgent('x'))->provider())->toBe('anthropic');
});

it('R-JANA-ADVISOR-AGENT-003 — instructions decoupla intenção de dado', function () {
    $instr = (string) (new ClarificadorAgent('manda a régua'))->instructions();

    expect($instr)
        ->toContain('ambiguo')
        ->toContain('falta_dado')
        ->toContain('claro')
        // a regra de ouro INTENT-SIM (não perguntar quando é só falta de dado)
        ->toContain('GANHO DE INFORMAÇÃO')
        // honestidade anti-alucinação
        ->toContain('NÃO invente');
});

it('R-JANA-ADVISOR-AGENT-004 — grounding não-PII aparece com ContextoNegocio', function () {
    $ctx = new ContextoNegocio(
        businessId: 1,
        businessName: 'ACME LTDA',
        faturamento90d: [['mes' => '2026-05', 'valor' => 47150.0, 'bruto' => 47150.0, 'liquido' => 47150.0, 'caixa' => 40000.0]],
        clientesAtivos: 8856,
        modulosAtivos: ['Financeiro', 'Whatsapp'],
        metasAtivas: [['nome' => 'Faturamento junho', 'valor_alvo' => 50000.0, 'realizado' => 12000.0]],
        observacoes: null,
    );

    $instr = (string) (new ClarificadorAgent('e agora?', [], $ctx))->instructions();

    expect($instr)
        ->toContain('ACME LTDA')
        ->toContain('8856')
        ->toContain('Faturamento junho')
        ->toContain('Financeiro');
});

it('R-JANA-ADVISOR-AGENT-005 — messages() mapeia histórico recente', function () {
    $hist = [
        ['role' => 'user', 'content' => 'quem deve mais?'],
        ['role' => 'assistant', 'content' => 'VARGAS, R$ [redacted Tier 0]k.'],
    ];

    $msgs = iterator_to_array((new ClarificadorAgent('e ele?', $hist))->messages());

    expect($msgs)->toHaveCount(2)
        ->and($msgs[0]->role->value)->toBe('user')
        ->and($msgs[1]->role->value)->toBe('assistant')
        ->and($msgs[1]->content)->toContain('VARGAS');
});
