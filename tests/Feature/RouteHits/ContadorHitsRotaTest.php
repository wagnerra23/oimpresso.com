<?php

declare(strict_types=1);

use App\Http\Middleware\ContadorHitsRota;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

// Tests\TestCase já aplicado globalmente em tests/Pest.php — NÃO redeclarar uses() aqui.

/**
 * ContadorHitsRota — sinal "servido" (verificação runtime, grade v3).
 *
 * Contratos testados: default OFF é no-op · chave = data + identidade da rota
 * (nome canônico OU URI-pattern) SEM PII/tenant · sem rota casada não conta ·
 * sample_rate 0 não conta. Sqlite-safe: zero DB, cache array.
 *
 * NOTA (proibições §claim-sem-evidência): estes testes provam o CONTRATO do
 * middleware, não o comportamento LiteSpeed prod — rollout exige canary real
 * com ROUTE_HITS_ENABLED=true (ver RUNBOOK-route-hits.md).
 */
beforeEach(function () {
    config(['cache.default' => 'array']);
    Cache::flush();
});

function rhRequestComRota(?string $nome, string $uriPattern, string $path): Request
{
    $request = Request::create('/'.ltrim($path, '/'));
    $route = new RoutingRoute(['GET'], $uriPattern, []);
    if ($nome !== null) {
        $route->name($nome);
    }
    $request->setRouteResolver(fn () => $route);

    return $request;
}

it('não conta nada com a flag desligada (default OFF)', function () {
    config(['route_hits.enabled' => false]);
    $request = rhRequestComRota('sells.index', 'sells', 'sells');

    (new ContadorHitsRota)->terminate($request, new Response);

    $chave = ContadorHitsRota::chaveCache('sells.index', now()->format('Y-m-d'));
    expect(Cache::get($chave))->toBeNull();
});

it('conta hit pela identidade nome-da-rota com a flag ligada', function () {
    config(['route_hits.enabled' => true, 'route_hits.sample_rate' => 1.0]);
    $request = rhRequestComRota('sells.index', 'sells', 'sells');
    $mw = new ContadorHitsRota;

    $mw->terminate($request, new Response);
    $mw->terminate($request, new Response);

    $chave = ContadorHitsRota::chaveCache('sells.index', now()->format('Y-m-d'));
    expect((int) Cache::get($chave))->toBe(2);
});

it('usa URI-pattern (placeholders, sem IDs resolvidos) quando a rota não tem nome', function () {
    config(['route_hits.enabled' => true, 'route_hits.sample_rate' => 1.0]);
    $request = rhRequestComRota(null, 'repair/{id}/edit', 'repair/123/edit');

    (new ContadorHitsRota)->terminate($request, new Response);

    $hoje = now()->format('Y-m-d');
    // conta pelo PATTERN (cardinalidade limitada, zero PII)...
    expect((int) Cache::get(ContadorHitsRota::chaveCache('repair/{id}/edit', $hoje)))->toBe(1)
        // ...NUNCA pela URL resolvida (conteria o ID real)
        ->and(Cache::get(ContadorHitsRota::chaveCache('repair/123/edit', $hoje)))->toBeNull();
});

it('não conta request sem rota casada (404 — cardinalidade não-limitada)', function () {
    config(['route_hits.enabled' => true, 'route_hits.sample_rate' => 1.0]);
    $request = Request::create('/qualquer/coisa/inexistente');
    $request->setRouteResolver(fn () => null);

    (new ContadorHitsRota)->terminate($request, new Response);

    expect(ContadorHitsRota::identidadeRota($request))->toBeNull();
});

it('sample_rate 0.0 nunca conta (amostragem probabilística)', function () {
    config(['route_hits.enabled' => true, 'route_hits.sample_rate' => 0.0]);
    $request = rhRequestComRota('sells.index', 'sells', 'sells');
    $mw = new ContadorHitsRota;

    for ($i = 0; $i < 20; $i++) {
        $mw->terminate($request, new Response);
    }

    expect(Cache::get(ContadorHitsRota::chaveCache('sells.index', now()->format('Y-m-d'))))->toBeNull();
});
