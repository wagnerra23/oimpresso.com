<?php

use Modules\Copiloto\Ai\Agents\ChatCopilotoAgent;
use Modules\Copiloto\Entities\Conversa;
use Modules\Copiloto\Support\ContextoNegocio;

/**
 * Regressão MEM-HOT-2 (ADR 0047, fix Caminho A do ADR 0046).
 *
 * Antes: ChatCopilotoAgent::instructions() era só prompt genérico — agent
 * não sabia faturamento/clientes/metas do negócio. Larissa pergunta
 * "qual meu faturamento" → "preciso que você me informe o período exato".
 *
 * Agora: instructions() recebe ContextoNegocio opcional e formata bloco compacto
 * com EMPRESA / FATURAMENTO 90d / CLIENTES / METAS ATIVAS.
 *
 * BC-compat: ChatCopilotoAgent($conv) sem ctx mantém comportamento anterior.
 */

function fakeConversa(): Conversa
{
    // Conversa não-persistida só pra preencher o construtor; Pest com TestCase
    // genérico não bate em DB (não usamos ->mensagens()).
    $c = new Conversa();
    $c->id = 1;
    $c->business_id = 4;
    $c->user_id = 9;
    return $c;
}

function ctxLarissa(): ContextoNegocio
{
    return new ContextoNegocio(
        businessId: 4,
        businessName: 'ROTA LIVRE',
        faturamento90d: [
            ['mes' => '2026-02', 'valor' => 78450.00],
            ['mes' => '2026-03', 'valor' => 82100.50],
            ['mes' => '2026-04', 'valor' => 24300.00],
        ],
        clientesAtivos: 3,
        modulosAtivos: ['Copiloto', 'Financeiro'],
        metasAtivas: [
            ['nome' => 'Faturamento mensal', 'valor_alvo' => 80000.0, 'realizado' => 24300.0],
        ],
        observacoes: null,
    );
}

it('BC-compat: ChatCopilotoAgent sem ctx mantém prompt genérico', function () {
    $agent = new ChatCopilotoAgent(fakeConversa());

    $prompt = (string) $agent->instructions();

    expect($prompt)->toContain('Você é o Copiloto do oimpresso');
    expect($prompt)->not->toContain('CONTEXTO DO NEGÓCIO');
    expect($prompt)->not->toContain('EMPRESA');
});

it('com ctx Larissa, instructions inclui empresa + faturamento + cliente + metas', function () {
    $agent = new ChatCopilotoAgent(fakeConversa(), '', ctxLarissa());

    $prompt = (string) $agent->instructions();

    expect($prompt)->toContain('CONTEXTO DO NEGÓCIO');
    expect($prompt)->toContain('EMPRESA: ROTA LIVRE (id 4)');
    expect($prompt)->toContain('CLIENTES ATIVOS: 3');
    expect($prompt)->toContain('FATURAMENTO ÚLTIMOS 90 DIAS');
    expect($prompt)->toContain('2026-02: R$ 78.450,00');
    expect($prompt)->toContain('2026-03: R$ 82.100,50');
    expect($prompt)->toContain('2026-04: R$ 24.300,00');
    expect($prompt)->toContain('METAS ATIVAS');
    expect($prompt)->toContain('Faturamento mensal: alvo R$ 80.000,00 / realizado R$ 24.300,00');
    expect($prompt)->toContain('MÓDULOS ATIVOS: Copiloto, Financeiro');
});

it('com ctx + memoria recall, ambos aparecem (ordem: base → ctx → memoria)', function () {
    $memoria = "Você lembra dos seguintes fatos sobre este usuário/business:\n- Larissa quer R$80mil/mês\n";
    $agent = new ChatCopilotoAgent(fakeConversa(), $memoria, ctxLarissa());

    $prompt = (string) $agent->instructions();

    $posBase    = strpos($prompt, 'Você é o Copiloto');
    $posCtx     = strpos($prompt, 'CONTEXTO DO NEGÓCIO');
    $posMemoria = strpos($prompt, 'Você lembra dos seguintes fatos');

    expect($posBase)->toBeLessThan($posCtx);
    expect($posCtx)->toBeLessThan($posMemoria);
    expect($prompt)->toContain('Larissa quer R$80mil/mês');
});

it('ctx com businessId null (plataforma) NÃO mostra "(id ...)"', function () {
    $ctx = new ContextoNegocio(
        businessId: null,
        businessName: 'oimpresso (plataforma)',
        faturamento90d: [],
        clientesAtivos: 0,
        modulosAtivos: [],
        metasAtivas: [],
    );

    $agent = new ChatCopilotoAgent(fakeConversa(), '', $ctx);
    $prompt = (string) $agent->instructions();

    expect($prompt)->toContain('EMPRESA: oimpresso (plataforma)');
    expect($prompt)->not->toContain('id ');
});

it('ctx com seções vazias pula linhas (token economy)', function () {
    $ctx = new ContextoNegocio(
        businessId: 4,
        businessName: 'TESTE',
        faturamento90d: [],
        clientesAtivos: 0, // pula
        modulosAtivos: [], // pula
        metasAtivas: [],   // pula
    );

    $agent = new ChatCopilotoAgent(fakeConversa(), '', $ctx);
    $prompt = (string) $agent->instructions();

    expect($prompt)->toContain('EMPRESA: TESTE');
    expect($prompt)->not->toContain('CLIENTES ATIVOS');
    expect($prompt)->not->toContain('FATURAMENTO');
    expect($prompt)->not->toContain('METAS ATIVAS');
    expect($prompt)->not->toContain('MÓDULOS ATIVOS');
});

it('ctx com observacoes inclui campo no prompt', function () {
    $ctx = new ContextoNegocio(
        businessId: 4,
        businessName: 'ROTA LIVRE',
        faturamento90d: [],
        clientesAtivos: 0,
        modulosAtivos: [],
        metasAtivas: [],
        observacoes: 'Cliente prefere reportes mensais às sextas',
    );

    $agent = new ChatCopilotoAgent(fakeConversa(), '', $ctx);
    $prompt = (string) $agent->instructions();

    expect($prompt)->toContain('OBSERVAÇÕES: Cliente prefere reportes mensais às sextas');
});
