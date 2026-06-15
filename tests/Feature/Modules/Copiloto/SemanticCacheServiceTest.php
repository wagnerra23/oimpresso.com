<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\CacheSemantico;
use Modules\Jana\Entities\Conversa;
use Modules\Jana\Services\Cache\SemanticCacheService;

/**
 * COPI-40 / MEM-CACHE-1 (ADR 0037 Sprint 8) — cache semântico de respostas LLM.
 *
 * Cobre as garantias críticas do contrato:
 *   - Match exato por SHA256(business + user + query_normalizada)
 *   - Isolamento multi-tenant (cross-business e cross-user)
 *   - TTL respeitado (entrada expirada não retorna)
 *   - Invalidação manual por business
 *   - Stats agregadas
 *   - Normalização determinística (Faturamento? === FATURAMENTO === faturamento)
 *
 * Padrão: Schema::create no beforeEach (segue MemoriaMetricaTest), evita
 * RefreshDatabase porque migrations do core UltimatePOS quebram em SQLite.
 *
 * NÃO COBERTO: hit fuzzy via FULLTEXT MATCH AGAINST (SQLite não tem FULLTEXT).
 * Validação fuzzy fica no smoke prod / suite MySQL dedicada (sprint 9 reranker).
 */

beforeEach(function () {
    // era-sqlite: cria schema mcp_*/jana_* manual (sqlite-friendly). No MySQL persistente
    // do nightly isso corrompe os testes irmãos (lever do floor SDD). Cobertura real é
    // na lane sqlite (per-PR); pula no MySQL.
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('era-sqlite: corruptor de schema compartilhado no MySQL — sqlite-only no burn-down do floor SDD.');
    }

    Schema::create('jana_cache_semantico', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->char('cache_key', 64)->unique();
        $t->unsignedInteger('business_id')->nullable();
        $t->unsignedInteger('user_id')->nullable();
        $t->text('query_original');
        $t->text('query_normalizada');
        $t->binary('query_embedding')->nullable();
        $t->mediumText('resposta');
        $t->json('metadata')->nullable();
        $t->unsignedInteger('hits')->default(0);
        $t->timestamp('ultimo_hit_em')->nullable();
        $t->unsignedInteger('tokens_in')->nullable();
        $t->unsignedInteger('tokens_out')->nullable();
        $t->decimal('custo_brl_original', 10, 6)->nullable();
        $t->timestamp('expira_em')->nullable();
        $t->timestamps();
    });
});

afterEach(function () {
    if (config('database.default') !== 'sqlite') {
        return;
    }

    Schema::dropIfExists('jana_cache_semantico');
});

function semCacheConv(int $bizId = 4, int $userId = 12): Conversa
{
    $c = new Conversa();
    $c->id = 1;
    $c->business_id = $bizId;
    $c->user_id = $userId;

    return $c;
}

it('grava e recupera resposta por match exato (round-trip)', function () {
    $svc = new SemanticCacheService();
    $conv = semCacheConv();

    $svc->gravar($conv, 'Quanto vendi este mês?', 'R$ [redacted Tier 0] em vendas.', 100, 50);

    $hit = $svc->buscar($conv, 'Quanto vendi este mês?');

    expect($hit)->not->toBeNull();
    expect($hit->resposta)->toBe('R$ [redacted Tier 0] em vendas.');
    expect($hit->business_id)->toBe(4);
    expect($hit->user_id)->toBe(12);
});

it('match exato é insensível a caso e pontuação (normalização)', function () {
    $svc = new SemanticCacheService();
    $conv = semCacheConv();

    $svc->gravar($conv, 'Qual o faturamento?', 'R$ [redacted Tier 0]k');

    expect($svc->buscar($conv, 'qual o faturamento'))->not->toBeNull();
    expect($svc->buscar($conv, 'QUAL O FATURAMENTO???'))->not->toBeNull();
    expect($svc->buscar($conv, '   Qual    o   faturamento?  '))->not->toBeNull();
});

it('match exato remove acentos (Larissa pergunta com e sem)', function () {
    $svc = new SemanticCacheService();
    $conv = semCacheConv();

    $svc->gravar($conv, 'Qual o faturamento líquido?', 'R$ [redacted Tier 0]');

    expect($svc->buscar($conv, 'qual o faturamento liquido'))->not->toBeNull();
});

it('retorna null quando query é diferente (cache miss)', function () {
    $svc = new SemanticCacheService();
    $conv = semCacheConv();

    $svc->gravar($conv, 'Quanto vendi?', 'R$ [redacted Tier 0]');

    expect($svc->buscar($conv, 'Quem é meu maior cliente?'))->toBeNull();
});

it('isola entre businesses (mesma query, businesses diferentes)', function () {
    $svc = new SemanticCacheService();
    $convA = semCacheConv(bizId: 4, userId: 12);
    $convB = semCacheConv(bizId: 7, userId: 12);

    $svc->gravar($convA, 'Quanto vendi?', 'biz4: R$ [redacted Tier 0]k');

    expect($svc->buscar($convA, 'Quanto vendi?'))->not->toBeNull();
    expect($svc->buscar($convB, 'Quanto vendi?'))->toBeNull();
});

it('isola entre users do mesmo business', function () {
    $svc = new SemanticCacheService();
    $conv1 = semCacheConv(bizId: 4, userId: 12);
    $conv2 = semCacheConv(bizId: 4, userId: 99);

    $svc->gravar($conv1, 'Como está minha meta?', 'meta user 12');

    expect($svc->buscar($conv1, 'Como está minha meta?'))->not->toBeNull();
    expect($svc->buscar($conv2, 'Como está minha meta?'))->toBeNull();
});

it('incrementa hits a cada acerto e atualiza ultimo_hit_em', function () {
    $svc = new SemanticCacheService();
    $conv = semCacheConv();

    $svc->gravar($conv, 'Quanto vendi?', 'R$ [redacted Tier 0]');
    expect(CacheSemantico::first()->hits)->toBe(0);

    $svc->buscar($conv, 'Quanto vendi?');
    $svc->buscar($conv, 'Quanto vendi?');
    $svc->buscar($conv, 'Quanto vendi?');

    $row = CacheSemantico::first();
    expect($row->hits)->toBe(3);
    expect($row->ultimo_hit_em)->not->toBeNull();
});

it('respeita TTL — entrada expirada não retorna', function () {
    $svc = new SemanticCacheService();
    $conv = semCacheConv();

    $svc->gravar($conv, 'Quanto vendi?', 'R$ [redacted Tier 0]');

    // Força expiração no passado
    CacheSemantico::query()->update(['expira_em' => now()->subMinute()]);

    expect($svc->buscar($conv, 'Quanto vendi?'))->toBeNull();
});

it('invalidarPorBusiness expira todas as entradas daquele business', function () {
    $svc = new SemanticCacheService();
    $convA = semCacheConv(bizId: 4, userId: 12);
    $convB = semCacheConv(bizId: 7, userId: 12);

    $svc->gravar($convA, 'q1', 'r1');
    $svc->gravar($convA, 'q2', 'r2');
    $svc->gravar($convB, 'q1', 'r1-bizB');

    $afetadas = $svc->invalidarPorBusiness(4);

    expect($afetadas)->toBe(2);
    expect($svc->buscar($convA, 'q1'))->toBeNull();
    expect($svc->buscar($convA, 'q2'))->toBeNull();
    // Business diferente segue intacto:
    expect($svc->buscar($convB, 'q1'))->not->toBeNull();
});

it('updateOrCreate em gravar — mesmo cache_key sobrescreve sem duplicar', function () {
    $svc = new SemanticCacheService();
    $conv = semCacheConv();

    $svc->gravar($conv, 'Quanto vendi?', 'resposta antiga', 100, 50);
    $svc->gravar($conv, 'Quanto vendi?', 'resposta nova', 200, 80);

    expect(CacheSemantico::count())->toBe(1);
    $row = CacheSemantico::first();
    expect($row->resposta)->toBe('resposta nova');
    expect($row->tokens_in)->toBe(200);
});

it('stats global retorna entradas + hits acumulados + economia em R$', function () {
    $svc = new SemanticCacheService();
    $conv = semCacheConv();

    $svc->gravar($conv, 'q1', 'r1', tokensIn: 1_000, tokensOut: 500);
    $svc->buscar($conv, 'q1');
    $svc->buscar($conv, 'q1');

    $stats = $svc->stats();

    expect($stats['entradas_cache'])->toBe(1);
    expect($stats['total_hits'])->toBe(2);
    expect($stats['hit_rate'])->toBeGreaterThan(0.0);
    expect($stats['r$_economizado'])->toBeGreaterThan(0.0);
});

it('stats com filtro de businessId só conta aquele business', function () {
    $svc = new SemanticCacheService();
    $convA = semCacheConv(bizId: 4, userId: 12);
    $convB = semCacheConv(bizId: 7, userId: 12);

    $svc->gravar($convA, 'q', 'r', tokensIn: 100, tokensOut: 100);
    $svc->gravar($convB, 'q', 'r', tokensIn: 100, tokensOut: 100);
    $svc->buscar($convA, 'q');

    $stats = $svc->stats(businessId: 1);
    expect($stats['entradas_cache'])->toBe(1);
    expect($stats['total_hits'])->toBe(1);
});

it('respeita config copiloto.cache.ttl_segundos ao gravar', function () {
    config(['copiloto.cache.ttl_segundos' => 60]);
    $svc = new SemanticCacheService();
    $conv = semCacheConv();

    $svc->gravar($conv, 'Quanto vendi?', 'R$ [redacted Tier 0]');

    $row = CacheSemantico::first();
    $deltaSec = $row->expira_em->getTimestamp() - now()->getTimestamp();
    // Tolerância 5s pra cobrir overhead do test
    expect($deltaSec)->toBeGreaterThanOrEqual(55)->toBeLessThanOrEqual(65);
});

it('grava custo_brl_original calculado a partir dos tokens', function () {
    $svc = new SemanticCacheService();
    $conv = semCacheConv();

    $svc->gravar($conv, 'Q', 'R', tokensIn: 1_000_000, tokensOut: 1_000_000);

    $row = CacheSemantico::first();
    // gpt-4o-mini @ pricing 2026 + USD 5.5 = ~ (0.15 + 0.60) USD * 5.5 ≈ R$ [redacted Tier 0]
    expect($row->custo_brl_original)->toBeGreaterThan(4.0);
    expect($row->custo_brl_original)->toBeLessThan(4.5);
});

it('totalEconomizado() na entity = hits × custo_brl_original', function () {
    $svc = new SemanticCacheService();
    $conv = semCacheConv();

    $svc->gravar($conv, 'q', 'r', tokensIn: 1000, tokensOut: 500);
    $svc->buscar($conv, 'q');
    $svc->buscar($conv, 'q');

    $row = CacheSemantico::first();
    $esperado = $row->hits * $row->custo_brl_original;
    expect($row->totalEconomizado())->toBe((float) $esperado);
});
