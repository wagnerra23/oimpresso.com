<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\Ai;
use Laravel\Ai\AnonymousAgent;
use Modules\Jana\Services\Summarizer\AutoSummarizerHelper;
use Modules\Jana\Services\Summarizer\AutoSummarizerService;
use Modules\Jana\Services\Summarizer\SummaryResult;

uses(Tests\TestCase::class);

/**
 * Onda 5 — Agent A1 (Auto-summary docs longos) — guarda regression.
 *
 * Cobre:
 *  001. Threshold: doc < 8KB passa direto (zero LLM)
 *  002. Cache hit: 2ª chamada do mesmo doc NÃO chama LLM
 *  003. Cap=0 → fail-open passthrough + texto truncado
 *  004. Map-reduce: doc 30KB → chunks → mini → reduce → 1 summary
 *  005. Prompt caching markers presentes no payload (sentinel regression guard)
 *  006. Threshold custom override funciona
 *  007. Cost cap reportado em DB cumulativo (sum cost_brl mês)
 *  008. Helper renderFooter expõe transparência (_truncated, _reason, _hash)
 *
 * Mock LLM via Ai::fakeAgent(AnonymousAgent::class, [...]).
 * Tabela mcp_doc_summaries criada in-test (SQLite-compat).
 */
beforeEach(function () {
    // era-sqlite: cria schema mcp_*/jana_* manual (sqlite-friendly). No MySQL persistente
    // do nightly isso corrompe os testes irmãos (lever do floor SDD). Cobertura real é
    // na lane sqlite (per-PR); pula no MySQL.
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('era-sqlite: corruptor de schema compartilhado no MySQL — sqlite-only no burn-down do floor SDD.');
    }

    Schema::dropIfExists('mcp_doc_summaries');
    Schema::create('mcp_doc_summaries', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->char('content_hash', 32)->index('idx_doc_summary_hash');
        $t->unsignedInteger('original_size');
        $t->text('summary');
        $t->unsignedInteger('tokens_in')->default(0);
        $t->unsignedInteger('tokens_out')->default(0);
        $t->decimal('cost_brl', 10, 6)->default(0);
        $t->string('model', 50)->default('gpt-4o-mini');
        $t->timestamps();
        $t->unique(['content_hash', 'model'], 'uniq_doc_summary_hash_model');
    });

    // Defaults conservadores
    config([
        'copiloto.auto_summarizer.enabled' => true,
        'copiloto.auto_summarizer.threshold_chars' => 8000,
        'copiloto.auto_summarizer.target_tokens' => 1500,
        'copiloto.auto_summarizer.chunk_size_chars' => 5000,
        'copiloto.auto_summarizer.cache_ttl_hours' => 24,
        'copiloto.auto_summarizer.model' => 'gpt-4o-mini',
        'copiloto.auto_summarizer.max_cost_brl' => 10,
        // Pricing pra cost calc determinístico
        'copiloto.ai.pricing.gpt-4o-mini' => ['input' => 0.00015, 'output' => 0.0006],
        'copiloto.ai.cambio_brl_usd' => 5.50,
    ]);
});

afterEach(function () {
    if (config('database.default') !== 'sqlite') {
        return;
    }

    Schema::dropIfExists('mcp_doc_summaries');
});

// ============================================================
// 001 — Threshold abaixo: passa direto (zero LLM)
// ============================================================
test('AutoSummarizer: doc abaixo do threshold passa direto (zero LLM)', function () {
    $service = new AutoSummarizerService;
    $smallDoc = str_repeat('Doc pequeno PT-BR. ', 50); // ~1KB

    Ai::fakeAgent(AnonymousAgent::class, ['NUNCA-CHAMADO']);

    $result = $service->summarize($smallDoc);

    expect($result)->toBeInstanceOf(SummaryResult::class)
        ->and($result->reason)->toBe(SummaryResult::REASON_BELOW_THRESHOLD)
        ->and($result->truncated)->toBeFalse()
        ->and($result->summary)->toBe($smallDoc)
        ->and(DB::table('mcp_doc_summaries')->count())->toBe(0);
});

// ============================================================
// 002 — Cache hit: 2ª chamada NÃO chama LLM novamente
// ============================================================
test('AutoSummarizer: 2ª chamada mesmo doc usa cache (zero LLM extra)', function () {
    $service = new AutoSummarizerService;
    // Doc 12KB > threshold 8KB. Map-reduce chama LLM 2 vezes (1 map chunk + 1 reduce)
    $bigDoc = str_repeat("## Seção\n\nConteúdo PT-BR. ", 600);
    expect(mb_strlen($bigDoc))->toBeGreaterThan(8000);

    Ai::fakeAgent(AnonymousAgent::class, [
        '- Bullet mini-summary',
        '### Summary final\n\n- Bullet final\n\nStatus: encerrado',
    ]);

    $first = $service->summarize($bigDoc);
    expect($first->reason)->toBe(SummaryResult::REASON_GENERATED);
    expect(DB::table('mcp_doc_summaries')->count())->toBe(1);

    // 2ª chamada — Ai::fakeAgent vazio simularia exception se LLM fosse chamado
    Ai::fakeAgent(AnonymousAgent::class, []);

    $second = $service->summarize($bigDoc);
    expect($second->reason)->toBe(SummaryResult::REASON_CACHE_HIT)
        ->and($second->summary)->toBe($first->summary)
        ->and($second->hash)->toBe(md5($bigDoc))
        ->and(DB::table('mcp_doc_summaries')->count())->toBe(1); // não duplicou
});

// ============================================================
// 003 — Cap=0 hard-enforce: fail-open + texto truncado
// ============================================================
test('AutoSummarizer: cap=0 hard-enforce fail-open com texto truncado', function () {
    config(['copiloto.auto_summarizer.max_cost_brl' => 0]);

    $service = new AutoSummarizerService;
    $bigDoc = str_repeat('Doc grande PT-BR. ', 1000); // ~18KB

    Ai::fakeAgent(AnonymousAgent::class, ['NUNCA-CHAMADO-CAP-ZERO']);

    $result = $service->summarize($bigDoc);

    expect($result->reason)->toBe(SummaryResult::REASON_CAP_EXCEEDED)
        ->and($result->truncated)->toBeTrue()
        ->and(mb_strlen($result->summary))->toBeLessThan(mb_strlen($bigDoc))
        ->and($result->summary)->toContain('truncado')
        ->and(DB::table('mcp_doc_summaries')->count())->toBe(0); // nada gravado
});

// ============================================================
// 004 — Map-reduce: doc 30KB vira 1 summary final
// ============================================================
test('AutoSummarizer: map-reduce de doc 30KB → multiple chunks → 1 summary', function () {
    $service = new AutoSummarizerService;

    // 30KB com headers H2 (forçam chunks múltiplos)
    $bigDoc = '';
    for ($i = 0; $i < 6; $i++) {
        $bigDoc .= "## Seção {$i}\n\n" . str_repeat("Conteúdo PT-BR seção {$i}. ", 200) . "\n\n";
    }
    expect(mb_strlen($bigDoc))->toBeGreaterThan(25_000);

    // Mock: 6 mini-summaries (map) + 1 reduce final.
    Ai::fakeAgent(AnonymousAgent::class, [
        '- mini 1', '- mini 2', '- mini 3', '- mini 4', '- mini 5', '- mini 6',
        "### Final\n\n- Bullet consolidado\n\nStatus: encerrado",
    ]);

    $result = $service->summarize($bigDoc);

    expect($result->reason)->toBe(SummaryResult::REASON_GENERATED)
        ->and($result->summary)->toContain('Bullet consolidado')
        ->and($result->summary)->toContain('Status: encerrado')
        ->and($result->chunks)->toBeGreaterThan(1)
        ->and($result->tokensIn)->toBeGreaterThan(0)
        ->and($result->tokensOut)->toBeGreaterThan(0)
        ->and($result->costBrl)->toBeGreaterThan(0);

    // Cost gravado no DB
    $row = DB::table('mcp_doc_summaries')->first();
    expect((float) $row->cost_brl)->toBeGreaterThan(0);
});

// ============================================================
// 005 — Prompt caching markers presentes (sentinel regression guard)
// ============================================================
test('AutoSummarizer: prompts contém sentinels cache_control pra futura migração Anthropic', function () {
    $service = new AutoSummarizerService;
    $bigDoc = "## Header\n\n" . str_repeat('Conteúdo. ', 1000);

    // Captura prompts enviados via Ai::fake
    Ai::fakeAgent(AnonymousAgent::class, ['mini', 'final']);

    $service->summarize($bigDoc);

    // Sentinels DEVEM estar nas instructions/prompts (gravados no agent state)
    $reflMap = new ReflectionMethod($service, 'mapSystemPrompt');
    $reflMap->setAccessible(true);
    $mapPrompt = $reflMap->invoke($service);

    $reflReduce = new ReflectionMethod($service, 'reduceSystemPrompt');
    $reflReduce->setAccessible(true);
    $reducePrompt = $reflReduce->invoke($service, 1500);

    expect($mapPrompt)->toContain(AutoSummarizerService::CACHE_BREAKPOINT_SYSTEM)
        ->and($reducePrompt)->toContain(AutoSummarizerService::CACHE_BREAKPOINT_SYSTEM);

    // KB breakpoint vai no user prompt (chunk payload) — sentinel constante existe
    expect(AutoSummarizerService::CACHE_BREAKPOINT_KB)->toContain('JANA_CACHE_BREAKPOINT_KB');
});

// ============================================================
// 006 — Threshold custom override funciona
// ============================================================
test('AutoSummarizer: helper aceita maxSize override', function () {
    config(['copiloto.auto_summarizer.threshold_chars' => 8000]);

    $smallDoc = str_repeat('AB ', 1000); // 3KB
    expect(mb_strlen($smallDoc))->toBeLessThan(8000);

    // Default: passa direto
    $passthrough = AutoSummarizerHelper::summarizeIfLarge($smallDoc);
    expect($passthrough->reason)->toBe(SummaryResult::REASON_BELOW_THRESHOLD);

    // Override threshold pra 100 chars → mesmo doc agora summariza
    Ai::fakeAgent(AnonymousAgent::class, ['mini', '### Final\n\nStatus: encerrado']);

    $summarized = AutoSummarizerHelper::summarizeIfLarge($smallDoc, 100);
    expect($summarized->reason)->toBe(SummaryResult::REASON_GENERATED);
});

// ============================================================
// 007 — Cost cap rastreado cumulativo no mês
// ============================================================
test('AutoSummarizer: monthlySpendBrl soma cost_brl do mês corrente', function () {
    $service = new AutoSummarizerService;

    DB::table('mcp_doc_summaries')->insert([
        'content_hash' => str_repeat('a', 32),
        'original_size' => 10000,
        'summary' => 'x',
        'tokens_in' => 100,
        'tokens_out' => 50,
        'cost_brl' => 3.5,
        'model' => 'gpt-4o-mini',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('mcp_doc_summaries')->insert([
        'content_hash' => str_repeat('b', 32),
        'original_size' => 10000,
        'summary' => 'y',
        'tokens_in' => 100,
        'tokens_out' => 50,
        'cost_brl' => 2.0,
        'model' => 'gpt-4o-mini',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($service->monthlySpendBrl())->toBe(5.5)
        ->and($service->capExceeded())->toBeFalse(); // cap=10, gasto=5.5

    // Suba cap pra abaixo do gasto → exceeded
    config(['copiloto.auto_summarizer.max_cost_brl' => 5]);
    expect($service->capExceeded())->toBeTrue();
});

// ============================================================
// 008 — Helper renderFooter expõe transparência
// ============================================================
test('AutoSummarizerHelper: renderFooter expõe _truncated, _reason, _full_response_id', function () {
    $result = SummaryResult::generated(
        summary: 'X',
        hash: 'abcdef1234567890abcdef1234567890',
        tokensIn: 100,
        tokensOut: 50,
        costBrl: 0.001,
        chunks: 2,
    );

    $footer = AutoSummarizerHelper::renderFooter($result);

    expect($footer)->toContain('Auto-summary aplicado')
        ->and($footer)->toContain('_truncated: true')
        ->and($footer)->toContain('_reason: generated')
        ->and($footer)->toContain('_full_response_id: abcdef1234567890abcdef1234567890')
        ->and($footer)->toContain('_tokens: in=100 / out=50 / chunks=2');

    // Passthrough below_threshold → footer vazio (transparência: nada aconteceu)
    $passthrough = SummaryResult::passthrough('texto', SummaryResult::REASON_BELOW_THRESHOLD);
    expect(AutoSummarizerHelper::renderFooter($passthrough))->toBe('');
});
