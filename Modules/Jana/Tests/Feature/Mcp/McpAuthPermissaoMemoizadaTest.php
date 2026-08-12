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
 * Estes testes travam as três propriedades do memo — que ele MEMOIZA, que dá
 * para DESLIGAR sem deploy, e que dá para INVALIDAR sem esperar o TTL. A
 * terceira é de segurança: sem ela, revogar acesso dependeria do relógio.
 *
 * Sem banco de propósito: o dublê conta quantas vezes `can()` foi chamado, que
 * é exatamente o que se quer provar. Um teste com User real mediria o Eloquent
 * junto e não distinguiria memo de cache do Spatie.
 *
 * @see Modules/Jana/Http/Middleware/McpAuthMiddleware.php@podeUsarMcp
 */

/** Dublê que registra quantas vezes o gate foi de fato consultado. */
function usuarioQueConta(int $id, bool $permitido = true): object
{
    return new class($id, $permitido)
    {
        public int $chamadas = 0;

        public function __construct(public int $id, private bool $permitido) {}

        public function can(string $ability): bool
        {
            $this->chamadas++;

            return $this->permitido;
        }
    };
}

/** Invoca o método protegido sem depender de um request HTTP completo. */
function invocarPodeUsarMcp(object $user): bool
{
    $mw = new McpAuthMiddleware();
    $m  = new \ReflectionMethod($mw, 'podeUsarMcp');
    $m->setAccessible(true);

    return (bool) $m->invoke($mw, $user);
}

beforeEach(function () {
    config(['copiloto.mcp.auth_cache_ttl' => 60]);
    Cache::forget(McpAuthMiddleware::chavePermissao(9801));
    Cache::forget(McpAuthMiddleware::chavePermissao(9802));
    Cache::forget(McpAuthMiddleware::chavePermissao(9803));
});

it('consulta o gate uma vez só e memoiza as chamadas seguintes', function () {
    $user = usuarioQueConta(9801);

    expect(invocarPodeUsarMcp($user))->toBeTrue()
        ->and(invocarPodeUsarMcp($user))->toBeTrue()
        ->and(invocarPodeUsarMcp($user))->toBeTrue();

    // O ponto do incidente: 3 requisições pagavam 3× as 3 queries do Spatie.
    expect($user->chamadas)->toBe(1);
});

it('não memoiza quando o TTL está zerado', function () {
    config(['copiloto.mcp.auth_cache_ttl' => 0]);
    $user = usuarioQueConta(9802);

    invocarPodeUsarMcp($user);
    invocarPodeUsarMcp($user);

    // Escape sem deploy: TTL <= 0 volta ao comportamento original.
    expect($user->chamadas)->toBe(2);
});

it('volta a consultar o gate depois de invalidar o user', function () {
    $user = usuarioQueConta(9803);

    invocarPodeUsarMcp($user);
    expect($user->chamadas)->toBe(1);

    McpAuthMiddleware::esquecerPermissao(9803);

    // Sem isto, revogar acesso dependeria de esperar o TTL expirar.
    invocarPodeUsarMcp($user);
    expect($user->chamadas)->toBe(2);
});

it('mantém o memo separado por user', function () {
    $a = usuarioQueConta(9801);
    $b = usuarioQueConta(9802, permitido: false);

    expect(invocarPodeUsarMcp($a))->toBeTrue()
        ->and(invocarPodeUsarMcp($b))->toBeFalse();

    // Chave por id: um user não pode herdar o veredito do outro.
    expect(invocarPodeUsarMcp($a))->toBeTrue()
        ->and(invocarPodeUsarMcp($b))->toBeFalse()
        ->and($a->chamadas)->toBe(1)
        ->and($b->chamadas)->toBe(1);
});
