<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\Ai;
use Modules\Jana\Ai\Agents\ClarificadorAgent;
use Modules\Jana\Entities\Conversa;
use Modules\Jana\Entities\Mensagem;
use Modules\Jana\Services\Ai\Clarify\ClarifyCascadeService;

uses(Tests\TestCase::class);

/**
 * R-JANA-ADVISOR-A — GUARD tests da cascata clarify reativo (Metade A, proposta §10.4).
 *
 * Cobre o contrato canônico:
 *  001. heurística 1a (pareceCinza) — positivos (cinza) e negativos (acionável)
 *  002. flag OFF → no-op total (sem LLM, sempre responder)
 *  003. heurística acionável curto-circuita o LLM (cascata p/ latência)
 *  004. cinza + LLM ambiguo+pergunta → clarifica (com custo LLM)
 *  005. honestidade — LLM falta_dado → responde (não pergunta)
 *  006. honestidade — LLM ambiguo SEM pergunta → responde
 *  007. anti false-clarify — confiança < mínimo → responde
 *  008. anti-loop — turno seguinte a um clarify → responde
 *  009. fail-open — exceção no disambiguador → responde (chat nunca quebra)
 */
beforeEach(function () {
    // era-sqlite: cria schema mcp_*/jana_* manual (sqlite-friendly). No MySQL persistente
    // do nightly isso corrompe os testes irmãos (lever do floor SDD). Cobertura real é
    // na lane sqlite (per-PR); pula no MySQL.
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('era-sqlite: corruptor de schema compartilhado no MySQL — sqlite-only no burn-down do floor SDD.');
    }

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

    Schema::create('jana_mensagens', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedBigInteger('conversa_id');
        $t->string('role', 20);
        $t->longText('content');
        $t->unsignedInteger('tokens_in')->nullable();
        $t->unsignedInteger('tokens_out')->nullable();
        $t->timestamps();
    });

    // Conversa usa Spatie LogsActivity (D7 LGPD) → loga no create. Garante a tabela
    // (idempotente) — mesma estratégia central do tests/Pest.php p/ RecurringBilling.
    if (! Schema::hasTable('activity_log')) {
        Schema::create('activity_log', function (Blueprint $t) {
            $t->id();
            $t->string('log_name')->nullable();
            $t->text('description')->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->string('subject_type')->nullable();
            $t->unsignedBigInteger('causer_id')->nullable();
            $t->string('causer_type')->nullable();
            $t->json('properties')->nullable();
            $t->string('event')->nullable();
            $t->uuid('batch_uuid')->nullable();
            $t->timestamps();
        });
    }

    Cache::flush();

    // Liga a feature p/ exercitar a cascata (default é OFF).
    config(['copiloto.clarify.enabled' => true]);
    config(['copiloto.clarify.min_confianca' => 0.6]);
});

function novaConversa(): Conversa
{
    return Conversa::create([
        'business_id' => 1,
        'user_id' => 1,
        'titulo' => 'Test',
        'status' => 'ativa',
        'iniciada_em' => now(),
    ]);
}

function cascade(): ClarifyCascadeService
{
    return app(ClarifyCascadeService::class);
}

it('R-JANA-ADVISOR-A-001 — pareceCinza separa ambíguo de acionável', function () {
    $svc = cascade();

    // CINZA (ambíguo de intenção — dêixis / imperativo solto / pergunta vaga curta)
    $cinza = [
        'e aquele cliente?',
        'resolve isso',
        'manda a régua',
        'melhora aí',
        'e agora?',
        'como tá?',
        'faz pra mim',
    ];
    foreach ($cinza as $m) {
        expect($svc->pareceCinza($m))->toBeTrue("Devia ser cinza: '$m'");
    }

    // ACIONÁVEL (não-cinza — saudação, específico, ou longo o bastante)
    $acionavel = [
        'oi',
        'bom dia',
        'obrigado',
        'quanto vendi ontem?',
        'quero criar uma meta de faturamento de R$ [redacted Tier 0] mil pro mês de junho',
        'quais os 5 maiores devedores hoje, ordenados por saldo?',
        '',
    ];
    foreach ($acionavel as $m) {
        expect($svc->pareceCinza($m))->toBeFalse("Não devia ser cinza: '$m'");
    }
});

it('R-JANA-ADVISOR-A-002 — flag OFF é no-op total (sem LLM)', function () {
    config(['copiloto.clarify.enabled' => false]);

    // Se o LLM fosse chamado com este fake, clarificaria — mas flag OFF não chama.
    Ai::fakeAgent(ClarificadorAgent::class, [[
        'tipo' => 'ambiguo', 'confianca' => 0.99, 'pergunta' => 'NUNCA', 'intencoes' => ['a', 'b'],
    ]]);

    $r = cascade()->decidir(novaConversa(), 'e aquele cliente?', 'e aquele cliente?');

    expect($r->acao)->toBe('responder')
        ->and($r->motivo)->toBe('flag_off')
        ->and($r->custoLlm)->toBeFalse();
});

it('R-JANA-ADVISOR-A-003 — heurística acionável curto-circuita o LLM', function () {
    // Fake ambiguo: SE o LLM rodasse, clarificaria. Mensagem é acionável → não roda.
    Ai::fakeAgent(ClarificadorAgent::class, [[
        'tipo' => 'ambiguo', 'confianca' => 0.99, 'pergunta' => 'NUNCA DEVIA APARECER', 'intencoes' => [],
    ]]);

    $msg = 'quero criar uma meta de faturamento de R$ [redacted Tier 0] mil pro mês de junho';
    $r = cascade()->decidir(novaConversa(), $msg, $msg);

    expect($r->acao)->toBe('responder')
        ->and($r->motivo)->toBe('heuristica_acionavel')
        ->and($r->custoLlm)->toBeFalse();
});

it('R-JANA-ADVISOR-A-004 — cinza + LLM ambiguo → clarifica com pergunta', function () {
    Ai::fakeAgent(ClarificadorAgent::class, [[
        'tipo' => 'ambiguo',
        'confianca' => 0.9,
        'pergunta' => 'Você quer dizer a régua de cobrança ou a de reativação?',
        'intencoes' => ['régua de cobrança', 'régua de reativação'],
    ]]);

    $r = cascade()->decidir(novaConversa(), 'manda a régua', 'manda a régua');

    expect($r->deveClarificar())->toBeTrue()
        ->and($r->acao)->toBe('clarificar')
        ->and($r->pergunta)->toContain('régua')
        ->and($r->custoLlm)->toBeTrue();
});

it('R-JANA-ADVISOR-A-005 — honestidade: falta_dado responde (não pergunta)', function () {
    Ai::fakeAgent(ClarificadorAgent::class, [[
        'tipo' => 'falta_dado', 'confianca' => 0.95, 'pergunta' => '', 'intencoes' => [],
    ]]);

    $r = cascade()->decidir(novaConversa(), 'e aquele cliente?', 'e aquele cliente?');

    expect($r->acao)->toBe('responder')
        ->and($r->tipo)->toBe('falta_dado')
        ->and($r->custoLlm)->toBeTrue();
});

it('R-JANA-ADVISOR-A-006 — honestidade: ambiguo sem pergunta responde', function () {
    Ai::fakeAgent(ClarificadorAgent::class, [[
        'tipo' => 'ambiguo', 'confianca' => 0.9, 'pergunta' => '', 'intencoes' => ['a', 'b'],
    ]]);

    $r = cascade()->decidir(novaConversa(), 'resolve isso', 'resolve isso');

    expect($r->acao)->toBe('responder')
        ->and($r->deveClarificar())->toBeFalse();
});

it('R-JANA-ADVISOR-A-007 — anti false-clarify: confiança baixa responde', function () {
    Ai::fakeAgent(ClarificadorAgent::class, [[
        'tipo' => 'ambiguo', 'confianca' => 0.3, 'pergunta' => 'Pergunta de baixa confiança?', 'intencoes' => [],
    ]]);

    $r = cascade()->decidir(novaConversa(), 'faz pra mim', 'faz pra mim');

    expect($r->acao)->toBe('responder')
        ->and($r->deveClarificar())->toBeFalse();
});

it('R-JANA-ADVISOR-A-008 — anti-loop: turno seguinte a clarify responde', function () {
    $conv = novaConversa();

    // 1º turno cinza → clarifica e marca.
    Ai::fakeAgent(ClarificadorAgent::class, [[
        'tipo' => 'ambiguo', 'confianca' => 0.9, 'pergunta' => 'A ou B?', 'intencoes' => ['a', 'b'],
    ]]);
    $r1 = cascade()->decidir($conv, 'manda a régua', 'manda a régua');
    expect($r1->deveClarificar())->toBeTrue();

    // 2º turno (resposta do user) — mesmo sendo cinza, NÃO pergunta de novo.
    Ai::fakeAgent(ClarificadorAgent::class, [[
        'tipo' => 'ambiguo', 'confianca' => 0.99, 'pergunta' => 'NÃO DEVIA', 'intencoes' => [],
    ]]);
    $r2 = cascade()->decidir($conv, 'a de cobrança', 'a de cobrança');

    expect($r2->acao)->toBe('responder')
        ->and($r2->motivo)->toBe('anti_loop_resposta_a_clarify');
});

it('R-JANA-ADVISOR-A-009 — fail-open: exceção no disambiguador responde', function () {
    // Closure que lança → prompt() propaga → service captura → fail-open.
    Ai::fakeAgent(ClarificadorAgent::class, function () {
        throw new RuntimeException('provider down');
    });

    $r = cascade()->decidir(novaConversa(), 'e aquele cliente?', 'e aquele cliente?');

    expect($r->acao)->toBe('responder')
        ->and($r->motivo)->toBe('erro_fail_open');
});
