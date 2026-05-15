<?php

declare(strict_types=1);

use Modules\Jana\Ai\Agents\AnalisarMensagemAgent;

uses(Tests\TestCase::class);

/**
 * US-WA-095 — Garante estabilidade dos enums canônicos do Agent.
 *
 * Migration `messages.analise_*` + dashboard agregam por enum literal.
 * Mudar valor = drift métrica histórica. Este test é regression guard.
 *
 * @see Modules/Jana/Ai/Agents/AnalisarMensagemAgent.php
 * @see Modules/Whatsapp/Database/Migrations/2026_05_15_220000_add_analise_columns_to_messages.php
 */
it('CATEGORIAS canon inclui valores esperados', function () {
    expect(AnalisarMensagemAgent::CATEGORIAS)->toBe([
        'reclamacao', 'elogio', 'duvida', 'pedido',
        'agendamento', 'spam', 'outro',
    ]);
});

it('TEMAS canon inclui valores esperados', function () {
    expect(AnalisarMensagemAgent::TEMAS)->toBe([
        'preco', 'qualidade', 'prazo', 'atendimento',
        'produto', 'pagamento', 'tecnico', 'outro',
    ]);
});

it('URGENCIAS canon inclui valores esperados', function () {
    expect(AnalisarMensagemAgent::URGENCIAS)->toBe(['baixa', 'media', 'alta', 'critica']);
});

it('Agent instructions menciona Jana, business e categorias', function () {
    $agent = new AnalisarMensagemAgent(
        businessName: 'Acme Ltda',
        messageBody: 'teste',
    );
    $instructions = (string) $agent->instructions();
    expect($instructions)
        ->toContain('Jana')
        ->toContain('Acme Ltda')
        ->toContain('reclamacao')
        ->toContain('NÃO invente')
        ->toContain('PII');
});

it('montarPrompt inclui contexto opcional + remetente quando fornecidos', function () {
    $agent = new AnalisarMensagemAgent(
        businessName: 'Acme',
        messageBody: 'mensagem teste',
        contactName: 'João Silva',
        previousContext: "CLIENTE: oi\nEMPRESA: olá",
    );
    $prompt = $agent->montarPrompt();
    expect($prompt)
        ->toContain('mensagem teste')
        ->toContain('João Silva')
        ->toContain('CLIENTE: oi')
        ->toContain('EMPRESA: olá');
});

it('montarPrompt funciona sem contexto/remetente (mínimo)', function () {
    $agent = new AnalisarMensagemAgent(
        businessName: 'Acme',
        messageBody: 'teste',
    );
    $prompt = $agent->montarPrompt();
    expect($prompt)
        ->toContain('teste')
        ->not->toContain('Remetente')
        ->and($prompt)->not->toContain('Contexto da conversa');
});
