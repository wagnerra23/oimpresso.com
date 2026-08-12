<?php

declare(strict_types=1);

namespace Modules\Jana\Tests\Feature\Mcp;

use Illuminate\Support\Facades\Cache;
use Modules\Jana\Http\Middleware\McpAuthMiddleware;

uses(\Tests\TestCase::class);

/**
 * Incidente 2026-08-12 — o gate `jana.mcp.use` custava 3 queries por requisição.
 *
 * `$user->can()` do Spatie lê `model_has_permissions` + `model_has_roles` +
 * `role_has_permissions`. O MCP server roda no CT 100 contra o MySQL do
 * Hostinger, onde cada roundtrip mede ~360ms — logo ~1102ms por request, o
 * maior item isolado de um total de ~3,65s, gastos relendo uma permissão que
 * quase nunca muda. O handshake do cliente faz 4 chamadas, então eram ~4,4s.
 *
 * Estes testes travam as quatro propriedades do memo — que ele MEMOIZA, que dá
 * para DESLIGAR sem deploy, que dá para INVALIDAR sem esperar o TTL, e que não
 * VAZA entre usuários. A terceira é de segurança: sem ela, revogar acesso
 * dependeria do relógio. A quarta também: chave errada daria acesso alheio.
 *
 * Sem banco de propósito: o contador mede quantas vezes o gate caro foi de fato
 * consultado, que é exatamente o que se quer provar. Um teste com User real
 * mediria o Eloquent junto e não distinguiria este memo do cache do Spatie.
 *
 * @see Modules/Jana/Http/Middleware/McpAuthMiddleware.php@podeUsarMcp
 */

/** Invoca o método protegido sem depender de um request HTTP completo. */
function invocarPodeUsarMcp(int $userId, \Closure $gate): bool
{
    $mw = new McpAuthMiddleware();
    $m  = new \ReflectionMethod($mw, 'podeUsarMcp');
    $m->setAccessible(true);

    return (bool) $m->invoke($mw, $userId, $gate);
}

beforeEach(function () {
    config(['copiloto.mcp.auth_cache_ttl' => 60]);
    foreach ([9801, 9802, 9803] as $id) {
        Cache::forget(McpAuthMiddleware::chavePermissao($id));
    }
});

it('consulta o gate uma vez só e memoiza as chamadas seguintes', function () {
    $chamadas = 0;
    $gate = function () use (&$chamadas): bool {
        $chamadas++;

        return true;
    };

    expect(invocarPodeUsarMcp(9801, $gate))->toBeTrue()
        ->and(invocarPodeUsarMcp(9801, $gate))->toBeTrue()
        ->and(invocarPodeUsarMcp(9801, $gate))->toBeTrue();

    // O ponto do incidente: 3 requisições pagavam 3× as 3 queries do Spatie.
    expect($chamadas)->toBe(1);
});

it('não memoiza quando o TTL está zerado', function () {
    config(['copiloto.mcp.auth_cache_ttl' => 0]);

    $chamadas = 0;
    $gate = function () use (&$chamadas): bool {
        $chamadas++;

        return true;
    };

    invocarPodeUsarMcp(9802, $gate);
    invocarPodeUsarMcp(9802, $gate);

    // Escape sem deploy: TTL <= 0 volta ao comportamento original.
    expect($chamadas)->toBe(2);
});

it('volta a consultar o gate depois de invalidar o user', function () {
    $chamadas = 0;
    $gate = function () use (&$chamadas): bool {
        $chamadas++;

        return true;
    };

    invocarPodeUsarMcp(9803, $gate);
    expect($chamadas)->toBe(1);

    McpAuthMiddleware::esquecerPermissao(9803);

    // Sem isto, revogar acesso dependeria de esperar o TTL expirar.
    invocarPodeUsarMcp(9803, $gate);
    expect($chamadas)->toBe(2);
});

it('mantém o memo separado por user', function () {
    $permitido = fn (): bool => true;
    $negado    = fn (): bool => false;

    expect(invocarPodeUsarMcp(9801, $permitido))->toBeTrue()
        ->and(invocarPodeUsarMcp(9802, $negado))->toBeFalse();

    // Chave por id: um user não pode herdar o veredito do outro.
    expect(invocarPodeUsarMcp(9801, $negado))->toBeTrue()
        ->and(invocarPodeUsarMcp(9802, $permitido))->toBeFalse();
});
