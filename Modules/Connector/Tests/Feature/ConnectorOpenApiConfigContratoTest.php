<?php

declare(strict_types=1);
// @covers-us US-CONN-013

use Knuckles\Scribe\Extracting\Strategies\Responses\ResponseCalls;

uses(Tests\TestCase::class);

/**
 * Contrato da GERAÇÃO SEGURA da documentação OpenAPI do Connector (US-CONN-013, T-01/T-02 de
 * memory/requisitos/Connector/features/openapi-connector/tasks.md).
 *
 * Afirma o comportamento que a configuração produz, não chaves cosméticas:
 *  - AC-1: só /connector/api/* entra; oauth/* nunca (nem por prefixo largo `connector/*`).
 *  - AC-2: o gerador não executa endpoint — zero response call, e a estratégia que as faz não
 *    está na lista (mesmo que alguém volte a ligar methods, nada roda).
 *  - AC-3: exemplos não leem nem gravam Model real (sem databaseFirst / factoryCreate).
 *  - AC-5: o artefato não é gravado em pasta servida pela web nem ganha rota /docs antes da
 *    decisão de audiência ([W]/[F], T-04).
 *  - AC-7: semente do faker fixa e carimbo por commit, para duas gerações no mesmo SHA darem
 *    diff vazio.
 *
 * Só lê config: não toca banco nem rota, roda em qualquer driver.
 */
it('US-CONN-013 · AC-1 · documenta só /connector/api/* e nunca oauth/*', function () {
    $grupos = config('scribe.routes');

    expect($grupos)->toHaveCount(1);
    $prefixos = $grupos[0]['match']['prefixes'];

    expect($prefixos)->toBe(['connector/api/*']);
    foreach ($prefixos as $p) {
        expect(str_starts_with($p, 'oauth'))->toBeFalse();
    }
    expect($grupos[0]['exclude'])->toContain('oauth/*');
});

it('US-CONN-013 · AC-2 · a geração não chama endpoint nenhum', function () {
    $grupo = config('scribe.routes')[0];

    expect($grupo['apply']['response_calls']['methods'])->toBe([])
        ->and(config('scribe.strategies.responses'))->not->toContain(ResponseCalls::class);
});

it('US-CONN-013 · AC-3 · exemplos não leem nem gravam registro real', function () {
    $fontes = config('scribe.examples.models_source');

    expect($fontes)->not->toContain('databaseFirst')
        ->and($fontes)->not->toContain('factoryCreate');
});

it('US-CONN-013 · AC-5 · artefato fora da web e sem rota /docs até a decisão de audiência', function () {
    $saida = (string) config('scribe.static.output_path');

    expect(config('scribe.type'))->toBe('static')
        ->and(str_starts_with(str_replace('\\', '/', $saida), 'public'))->toBeFalse()
        ->and(str_starts_with(str_replace('\\', '/', $saida), 'storage/'))->toBeTrue()
        ->and(config('scribe.laravel.add_routes'))->toBeFalse()
        ->and(config('scribe.try_it_out.enabled'))->toBeFalse();
});

it('US-CONN-013 · AC-7 · geração reprodutível no mesmo commit', function () {
    expect(config('scribe.examples.faker_seed'))->toBeInt()
        ->and((string) config('scribe.last_updated'))->not->toContain('{date');
});
