<?php

declare(strict_types=1);

namespace Modules\Jana\Tests\Feature\Mcp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Jana\Services\Mcp\Ct100CircuitBreaker;

uses(\Tests\TestCase::class);

/**
 * Circuit breaker do transporte CT 100 (incidente 2026-09-02).
 *
 * CONTRATO (âncora — proibições: "teste sem âncora de contrato = rejeitado"):
 *   - ADR 0062 (Hostinger != CT 100): queda da OUTRA máquina não é erro DESTE app.
 *   - `proibicoes.md` §"Claim sem evidência": alarme repetindo 288x/dia o mesmo
 *     fato treina o operador a ignorar o canal.
 *   - Medido em prod (2026-09-02): `[mcp:sync-memory --reason=cron] failed with
 *     exit code [1]` — 08-28: 144 · 08-29: 148 · 08-31: 146 · 09-01: 147.
 *
 * SEM DB de propósito (classificador puro + Http::fake): roda igual nas lanes
 * sqlite e MySQL, diferente do irmão `McpSyncMemoryRobustezTest`, que monta
 * schema e por isso pula no MySQL persistente.
 */

beforeEach(function () {
    Cache::forget(Ct100CircuitBreaker::CACHE_DOWN_SINCE);
    Cache::forget(Ct100CircuitBreaker::CACHE_WARNED_AT);
});

it('classifica ConnectionException como falha de TRANSPORTE', function () {
    expect(Ct100CircuitBreaker::isTransportFailure(new ConnectionException('cURL error 28')))->toBeTrue();
});

it('NAO classifica erro de aplicacao como transporte (controle negativo)', function () {
    // Se isto virar true, o breaker passa a engolir bug real — o pior desfecho
    // possível desta mudança.
    expect(Ct100CircuitBreaker::isTransportFailure(new \RuntimeException('coluna inexistente')))->toBeFalse();
    expect(Ct100CircuitBreaker::isTransportFailure(null))->toBeFalse();
});

it('acha a falha de transporte embrulhada na cadeia getPrevious', function () {
    // Scout/Laravel embrulham a exceção do cliente HTTP; olhar só o topo perderia.
    $embrulhada = new \RuntimeException('falha ao indexar', 0, new ConnectionException('refused'));

    expect(Ct100CircuitBreaker::isTransportFailure($embrulhada))->toBeTrue();
});

it('nao se aplica quando o Scout nao usa meilisearch', function () {
    config(['scout.driver' => 'collection']);

    expect(Ct100CircuitBreaker::probe()['applicable'])->toBeFalse();
});

it('considera ALCANCAVEL quando o destino responde 200', function () {
    config(['scout.driver' => 'meilisearch', 'scout.meilisearch.host' => 'http://ct100.test:7700']);
    Http::fake(['*' => Http::response(['status' => 'available'], 200)]);

    expect(Ct100CircuitBreaker::probe())->applicable->toBeTrue()->reachable->toBeTrue();
});

it('considera ALCANCAVEL quando responde 5xx — 5xx e erro REAL, nao queda', function () {
    // Coração da regra: servidor de pé e doente NÃO abre o breaker. Se isto virar
    // `reachable => false`, um Meilisearch quebrado passa a sair 0 e o erro real
    // fica invisível.
    config(['scout.driver' => 'meilisearch', 'scout.meilisearch.host' => 'http://ct100.test:7700']);
    Http::fake(['*' => Http::response('boom', 503)]);

    expect(Ct100CircuitBreaker::probe())->reachable->toBeTrue()->detail->toBe('HTTP 503');
});

it('considera INALCANCAVEL quando o destino nao responde', function () {
    config(['scout.driver' => 'meilisearch', 'scout.meilisearch.host' => 'http://ct100.test:7700']);
    Http::fake(fn () => throw new ConnectionException('cURL error 28: Connection timed out'));

    expect(Ct100CircuitBreaker::probe())->applicable->toBeTrue()->reachable->toBeFalse();
});

it('emite UM warning por janela de backoff', function () {
    expect(Ct100CircuitBreaker::shouldWarn())->toBeTrue();   // 1a fala
    expect(Ct100CircuitBreaker::shouldWarn())->toBeFalse();  // 2a cala
    expect(Ct100CircuitBreaker::shouldWarn())->toBeFalse();  // e segue calada
});

it('preserva o INICIO da serie de queda entre runs', function () {
    $primeiro = Ct100CircuitBreaker::recordDown();
    Ct100CircuitBreaker::recordDown();

    // O "desde" é do primeiro a ver, não do último — senão vira sempre "agora".
    expect(Ct100CircuitBreaker::downSince()?->toIso8601String())->toBe($primeiro?->toIso8601String());

    Ct100CircuitBreaker::recordUp();
    expect(Ct100CircuitBreaker::downSince())->toBeNull();
});

it('mcp:sync-memory sai 0 quando o transporte CT 100 esta fora', function () {
    config(['scout.driver' => 'meilisearch', 'scout.meilisearch.host' => 'http://ct100.test:7700']);
    Http::fake(fn () => throw new ConnectionException('cURL error 7: Failed to connect'));

    $this->artisan('mcp:sync-memory', ['--reason' => 'test-breaker'])
        ->expectsOutputToContain('Transporte CT 100 fora')
        ->assertExitCode(0);

    // E deixou o rastro que o jana:health-check lê.
    expect(Ct100CircuitBreaker::downSince())->not->toBeNull();
});
