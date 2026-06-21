<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\MemoriaMetrica;
use Modules\Jana\Services\Metricas\MetricasApurador;

/**
 * MEM-MET-2 (ADRs 0050+0051) — Testa cada apurador isolado + integração `apurar()`.
 *
 * Setup: cria as 4 tabelas envolvidas em SQLite in-memory (não usa
 * RefreshDatabase porque migrations do core UltimatePOS quebram).
 */

beforeEach(function () {
    // era-sqlite: cria schema mcp_*/jana_* manual (sqlite-friendly). No MySQL persistente
    // do nightly isso corrompe os testes irmãos (lever do floor SDD). Cobertura real é
    // na lane sqlite (per-PR); pula no MySQL.
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('era-sqlite: corruptor de schema compartilhado no MySQL — sqlite-only no burn-down do floor SDD.');
    }

    // copiloto_memoria_metricas
    Schema::create('jana_memoria_metricas', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->date('apurado_em');
        $t->unsignedInteger('business_id')->nullable();
        $t->decimal('recall_at_3', 4, 3)->nullable();
        $t->decimal('precision_at_3', 4, 3)->nullable();
        $t->decimal('mrr', 4, 3)->nullable();
        $t->unsignedInteger('latencia_p95_ms')->nullable();
        $t->unsignedInteger('tokens_medio_interacao')->nullable();
        $t->decimal('memory_bloat_ratio', 4, 3)->nullable();
        $t->decimal('taxa_contradicoes_pct', 5, 2)->nullable();
        $t->unsignedInteger('cross_tenant_violations')->default(0);
        $t->decimal('faithfulness', 4, 3)->nullable();
        $t->decimal('answer_relevancy', 4, 3)->nullable();
        $t->decimal('context_precision', 4, 3)->nullable();
        $t->unsignedInteger('total_interacoes_dia')->default(0);
        $t->unsignedInteger('total_memorias_ativas')->default(0);
        $t->json('detalhes')->nullable();
        $t->timestamps();
        $t->unique(['apurado_em', 'business_id'], 'mem_metr_ux');
    });

    // copiloto_conversas (mínimo)
    Schema::create('jana_conversas', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedInteger('business_id')->nullable();
        $t->unsignedInteger('user_id');
        $t->string('titulo')->nullable();
        $t->string('status')->default('ativa');
        $t->timestamp('iniciada_em')->useCurrent();
        $t->timestamps();
    });

    // copiloto_mensagens (mínimo, com tokens)
    Schema::create('jana_mensagens', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedBigInteger('conversa_id');
        $t->string('role');
        $t->text('content');
        $t->unsignedInteger('tokens_in')->nullable();
        $t->unsignedInteger('tokens_out')->nullable();
        $t->timestamps();
    });

    // copiloto_memoria_facts (mínimo)
    Schema::create('jana_memoria_facts', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedBigInteger('business_id');
        $t->unsignedBigInteger('user_id');
        $t->text('fato');
        $t->json('metadata')->nullable();
        $t->timestamp('valid_from')->useCurrent();
        $t->timestamp('valid_until')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });
});

afterEach(function () {
    if (config('database.default') !== 'sqlite') {
        return;
    }

    Schema::dropIfExists('jana_memoria_metricas');
    Schema::dropIfExists('jana_conversas');
    Schema::dropIfExists('jana_mensagens');
    Schema::dropIfExists('jana_memoria_facts');
});

it('totalInteracoesDia conta apenas role=user no dia certo do business', function () {
    $hoje = CarbonImmutable::parse('2026-04-29');

    \DB::table('jana_conversas')->insert([
        ['id' => 1, 'business_id' => 1, 'user_id' => 9, 'created_at' => $hoje, 'updated_at' => $hoje, 'iniciada_em' => $hoje],
        ['id' => 2, 'business_id' => 8, 'user_id' => 5, 'created_at' => $hoje, 'updated_at' => $hoje, 'iniciada_em' => $hoje],
    ]);

    $msg = function (array $extra) use ($hoje) {
        return array_merge([
            'conversa_id' => 1, 'role' => 'user', 'content' => 'x',
            'tokens_in' => null, 'tokens_out' => null,
            'created_at' => $hoje, 'updated_at' => $hoje,
        ], $extra);
    };

    \DB::table('jana_mensagens')->insert($msg(['content' => 'a']));
    \DB::table('jana_mensagens')->insert($msg(['content' => 'b']));
    \DB::table('jana_mensagens')->insert($msg(['content' => 'c', 'role' => 'assistant']));
    \DB::table('jana_mensagens')->insert($msg(['conversa_id' => 2, 'content' => 'd']));
    \DB::table('jana_mensagens')->insert($msg(['content' => 'velha', 'created_at' => '2026-04-28 10:00:00', 'updated_at' => '2026-04-28 10:00:00']));

    $apurador = new MetricasApurador();

    expect($apurador->totalInteracoesDia(1, $hoje))->toBe(2);
    expect($apurador->totalInteracoesDia(8, $hoje))->toBe(1);
    expect($apurador->totalInteracoesDia(null, $hoje))->toBe(3); // plataforma agregada
});

it('tokensMedioInteracao calcula média de assistant (in+out) com filtro de business', function () {
    $hoje = CarbonImmutable::parse('2026-04-29');

    \DB::table('jana_conversas')->insert([
        ['id' => 1, 'business_id' => 1, 'user_id' => 9, 'created_at' => $hoje, 'updated_at' => $hoje, 'iniciada_em' => $hoje],
    ]);

    \DB::table('jana_mensagens')->insert([
        'conversa_id' => 1, 'role' => 'assistant', 'content' => 'r1', 'tokens_in' => 100, 'tokens_out' => 50, 'created_at' => $hoje, 'updated_at' => $hoje,
    ]);
    \DB::table('jana_mensagens')->insert([
        'conversa_id' => 1, 'role' => 'assistant', 'content' => 'r2', 'tokens_in' => 200, 'tokens_out' => 100, 'created_at' => $hoje, 'updated_at' => $hoje,
    ]);
    \DB::table('jana_mensagens')->insert([
        'conversa_id' => 1, 'role' => 'user', 'content' => 'q', 'tokens_in' => null, 'tokens_out' => null, 'created_at' => $hoje, 'updated_at' => $hoje,
    ]);

    $apurador = new MetricasApurador();

    // (150 + 300) / 2 = 225
    expect($apurador->tokensMedioInteracao(1, $hoje))->toBe(225);
});

it('totalMemoriasAtivas conta valid_until=null e exclui soft-deleted', function () {
    $hoje = CarbonImmutable::parse('2026-04-29');
    $fato = function (array $extra) use ($hoje) {
        return array_merge([
            'business_id' => 1, 'user_id' => 9, 'fato' => 'x',
            'valid_from' => $hoje, 'valid_until' => null, 'deleted_at' => null,
            'created_at' => $hoje, 'updated_at' => $hoje,
        ], $extra);
    };

    \DB::table('jana_memoria_facts')->insert($fato(['fato' => 'ativo 1']));
    \DB::table('jana_memoria_facts')->insert($fato(['fato' => 'ativo 2']));
    \DB::table('jana_memoria_facts')->insert($fato(['fato' => 'superseded', 'valid_until' => $hoje]));
    \DB::table('jana_memoria_facts')->insert($fato(['fato' => 'esquecido', 'deleted_at' => $hoje]));
    \DB::table('jana_memoria_facts')->insert($fato(['business_id' => 8, 'user_id' => 5, 'fato' => 'outro biz']));

    $apurador = new MetricasApurador();

    expect($apurador->totalMemoriasAtivas(1, $hoje))->toBe(2);
    expect($apurador->totalMemoriasAtivas(8, $hoje))->toBe(1);
    expect($apurador->totalMemoriasAtivas(null, $hoje))->toBe(3);
});

it('memoryBloatRatio = % fatos com valid_from <= 30d / total ativos', function () {
    $hoje = CarbonImmutable::parse('2026-04-29');
    $velho = $hoje->subDays(60);
    $recente = $hoje->subDays(10);

    $insertFato = function (string $fato, $vf) {
        \DB::table('jana_memoria_facts')->insert([
            'business_id' => 1, 'user_id' => 9, 'fato' => $fato,
            'valid_from' => $vf, 'valid_until' => null, 'deleted_at' => null,
            'created_at' => $vf, 'updated_at' => $vf,
        ]);
    };

    $insertFato('rec1', $recente);
    $insertFato('rec2', $recente);
    $insertFato('rec3', $hoje);
    $insertFato('velho', $velho);

    $apurador = new MetricasApurador();

    // 3 recentes / 4 total = 0.750
    expect($apurador->memoryBloatRatio(1, $hoje))->toBe(0.750);
});

it('memoryBloatRatio retorna null quando não há fatos ativos', function () {
    $apurador = new MetricasApurador();
    expect($apurador->memoryBloatRatio(99, CarbonImmutable::parse('2026-04-29')))->toBeNull();
});

it('latenciaP95Ms retorna null quando log do dia não existe', function () {
    $apurador = new MetricasApurador('canal-inexistente-xyz');
    expect($apurador->latenciaP95Ms(1, CarbonImmutable::parse('2026-04-29')))->toBeNull();
});

it('latenciaP95Ms parseia log otel-gen-ai e calcula p95 filtrado por business_id', function () {
    $data = CarbonImmutable::parse('2026-04-29');
    $logPath = storage_path('logs/otel-gen-ai-test-' . uniqid() . '-' . $data->toDateString() . '.log');

    // 20 durations: 100..2000 com step de 100. p95 = índice 18 = 1900
    $linhas = [];
    for ($i = 1; $i <= 20; $i++) {
        $dur = $i * 100;
        $event = json_encode([
            'gen_ai.system'               => 'openai',
            'gen_ai.business_id'          => 1,
            'gen_ai.response.duration_ms' => $dur,
        ]);
        $linhas[] = "[2026-04-29 10:00:00] live.INFO: gen_ai.span {$event}";
    }
    // 5 entradas de outro business (não devem entrar no p95 do biz=1)
    for ($i = 1; $i <= 5; $i++) {
        $event = json_encode([
            'gen_ai.system'               => 'openai',
            'gen_ai.business_id'          => 8,
            'gen_ai.response.duration_ms' => 99999,
        ]);
        $linhas[] = "[2026-04-29 10:00:00] live.INFO: gen_ai.span {$event}";
    }

    file_put_contents($logPath, implode("\n", $linhas) . "\n");

    // Aponta o apurador para o nosso log path customizado
    $logChannel = basename($logPath, '-' . $data->toDateString() . '.log');
    $apurador = new MetricasApurador($logChannel);

    expect($apurador->latenciaP95Ms(1, $data))->toBe(1900); // ceil(0.95 * 20) - 1 = 18 → 1900
    expect($apurador->latenciaP95Ms(8, $data))->toBe(99999);

    @unlink($logPath);
});

it('apurar grava 1 linha em copiloto_memoria_metricas e é idempotente (upsert)', function () {
    $hoje = CarbonImmutable::parse('2026-04-29');

    \DB::table('jana_conversas')->insert([
        ['id' => 1, 'business_id' => 1, 'user_id' => 9, 'created_at' => $hoje, 'updated_at' => $hoje, 'iniciada_em' => $hoje],
    ]);

    \DB::table('jana_mensagens')->insert([
        'conversa_id' => 1, 'role' => 'user', 'content' => 'q', 'tokens_in' => null, 'tokens_out' => null, 'created_at' => $hoje, 'updated_at' => $hoje,
    ]);
    \DB::table('jana_mensagens')->insert([
        'conversa_id' => 1, 'role' => 'assistant', 'content' => 'r', 'tokens_in' => 100, 'tokens_out' => 50, 'created_at' => $hoje, 'updated_at' => $hoje,
    ]);

    \DB::table('jana_memoria_facts')->insert([
        'business_id' => 1, 'user_id' => 9, 'fato' => 'meta R$ [redacted Tier 0]k/mês',
        'valid_from' => $hoje, 'valid_until' => null, 'deleted_at' => null,
        'created_at' => $hoje, 'updated_at' => $hoje,
    ]);

    $apurador = new MetricasApurador();

    $linha1 = $apurador->apurar(1, '2026-04-29');
    expect($linha1)->toBeInstanceOf(MemoriaMetrica::class);
    expect($linha1->total_interacoes_dia)->toBe(1);
    expect($linha1->total_memorias_ativas)->toBe(1);
    expect($linha1->tokens_medio_interacao)->toBe(150);

    // Re-apurar mesmo dia/business → upsert (não cria nova linha)
    $linha2 = $apurador->apurar(1, '2026-04-29');
    expect(MemoriaMetrica::count())->toBe(1);
    expect($linha2->id)->toBe($linha1->id);

    // RAGAS columns vazias até golden set entrar
    expect($linha1->recall_at_3)->toBeNull();
    expect($linha1->faithfulness)->toBeNull();
});

it('apurar para plataforma (business_id=null) agrega tudo', function () {
    $hoje = CarbonImmutable::parse('2026-04-29');

    \DB::table('jana_conversas')->insert([
        ['id' => 1, 'business_id' => 1, 'user_id' => 9, 'created_at' => $hoje, 'updated_at' => $hoje, 'iniciada_em' => $hoje],
        ['id' => 2, 'business_id' => 8, 'user_id' => 5, 'created_at' => $hoje, 'updated_at' => $hoje, 'iniciada_em' => $hoje],
    ]);

    \DB::table('jana_mensagens')->insert([
        'conversa_id' => 1, 'role' => 'user', 'content' => 'a', 'tokens_in' => null, 'tokens_out' => null, 'created_at' => $hoje, 'updated_at' => $hoje,
    ]);
    \DB::table('jana_mensagens')->insert([
        'conversa_id' => 2, 'role' => 'user', 'content' => 'b', 'tokens_in' => null, 'tokens_out' => null, 'created_at' => $hoje, 'updated_at' => $hoje,
    ]);

    $apurador = new MetricasApurador();
    $linha = $apurador->apurar(null, '2026-04-29');

    expect($linha->business_id)->toBeNull();
    expect($linha->total_interacoes_dia)->toBe(2); // biz 1 + biz 8 agregados
});
