<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\Ai;
use Modules\Jana\Ai\Agents\BriefDiarioAgent;
use Modules\Jana\Entities\Conversa;
use Modules\Jana\Services\BriefDiarioChatTrigger;

uses(Tests\TestCase::class);

/**
 * R-COPI-203 — GUARD tests pra BriefDiarioChatTrigger (US-COPI-203).
 *
 * Cobre detecção de intent + invocação do BriefDiarioAgent quando user
 * digita "brief", "como tá meu negócio", etc no chat /jana.
 *
 *  001. matches() reconhece variações de intent (positives)
 *  002. matches() ignora mensagens normais sem intent (negatives)
 *  003. gerar() com fakeAgent retorna markdown não-vazio
 *  004. gerar() em erro NÃO vaza stack trace — devolve fallback amigável
 *
 * @see memory/decisions/0140-jana-pro-produto-comercial-saas.md
 * @see memory/requisitos/Copiloto/JANA-PRO-PRODUCT-PLAN.md
 */
beforeEach(function () {
    // era-sqlite: cria schema mcp_*/jana_* manual (sqlite-friendly). No MySQL persistente
    // do nightly isso corrompe os testes irmãos (lever do floor SDD). Cobertura real é
    // na lane sqlite (per-PR); pula no MySQL.
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('era-sqlite: corruptor de schema compartilhado no MySQL — sqlite-only no burn-down do floor SDD.');
    }

    // Setup mínimo de schema pra Conversa
    foreach (['jana_conversas', 'jana_mensagens'] as $t) {
        Schema::dropIfExists($t);
    }

    Schema::create('jana_conversas', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedInteger('business_id');
        $t->unsignedInteger('user_id')->nullable();
        $t->string('titulo', 150)->nullable();
        $t->string('status', 20)->default('ativa');
        $t->timestamp('iniciada_em')->nullable();
        $t->json('metadata')->nullable();
        $t->timestamps();
    });
});

it('R-COPI-203-001 — matches() reconhece variacoes de intent brief (positives)', function () {
    $trigger = new BriefDiarioChatTrigger();

    $positives = [
        '/brief',
        'brief',
        'brief diário',
        'Brief Diário',
        'BRIEF DE HOJE',
        'brief do dia',
        'brief executivo',
        'brief jana pro',
        'manda o brief',
        'gera brief',
        'gere brief',
        'quero o brief',
        'me dá o brief',
        'me da o brief',
        'como tá meu negócio',
        'como ta meu negocio',
        'como vai o negócio',
        'como está meu negócio hoje',
        'resumo do dia',
        'panorama hoje',
        'panorama de hoje',
        'como foi a semana',
        'como foi o mês',
        'como foi ontem',
        'JANA PRO',
        '  /brief  ',
    ];

    foreach ($positives as $msg) {
        expect($trigger->matches($msg))->toBeTrue("Não detectou intent: '$msg'");
    }
});

it('R-COPI-203-002 — matches() NAO ativa pra conversas normais (negatives)', function () {
    $trigger = new BriefDiarioChatTrigger();

    $negatives = [
        'oi',
        'tudo bem?',
        'quero criar uma meta de faturamento',
        'qual o melhor cliente?',
        'me mostra as vendas dessa semana',  // info request, não brief
        'tá briefando o time hoje?',          // briefando é palavra diferente
        'cadê o relatório?',
        'fala sobre lucro',
        'crie meta de R$ [redacted Tier 0]k mês',
    ];

    foreach ($negatives as $msg) {
        expect($trigger->matches($msg))->toBeFalse("Falso positivo: '$msg'");
    }
});

it('R-COPI-203-003 — gerar() com fakeAgent retorna markdown nao-vazio', function () {
    $fakeMarkdown = "# 🌅 Brief Diário — Test Co\n\n## ⭐ Destaque\n\n> Vendas +20%\n\nFake response.";

    Ai::fakeAgent(BriefDiarioAgent::class, [$fakeMarkdown]);

    $conv = Conversa::create([
        'business_id' => 1,
        'user_id' => 1,
        'titulo' => 'Test',
        'status' => 'ativa',
        'iniciada_em' => now(),
    ]);

    $trigger = new BriefDiarioChatTrigger();
    $result = $trigger->gerar($conv);

    expect($result)->toContain('Brief Diário')
        ->and($result)->toContain('Vendas')
        ->and(mb_strlen($result))->toBeGreaterThan(50);
});

it('R-COPI-203-004 — gerar() em erro NAO vaza stack trace (fallback amigavel)', function () {
    // Fake retorna string vazia (simula falha LLM)
    Ai::fakeAgent(BriefDiarioAgent::class, ['']);

    $conv = Conversa::create([
        'business_id' => 1,
        'user_id' => 1,
        'titulo' => 'Test',
        'status' => 'ativa',
        'iniciada_em' => now(),
    ]);

    $trigger = new BriefDiarioChatTrigger();
    $result = $trigger->gerar($conv);

    // Devolve fallback PT-BR sem stack trace
    expect($result)->toContain('Tenta de novo')
        ->and($result)->not->toContain('Stack trace')
        ->and($result)->not->toContain('Exception')
        ->and(mb_strlen($result))->toBeGreaterThan(20);
});
