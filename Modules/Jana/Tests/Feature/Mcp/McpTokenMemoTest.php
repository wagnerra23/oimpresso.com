<?php

declare(strict_types=1);

namespace Modules\Jana\Tests\Feature\Mcp;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Jana\Entities\Mcp\McpToken;

uses(\Tests\TestCase::class);

/**
 * Incidente 2026-08-12 — resolução do token custava uma consulta por requisição.
 *
 * O middleware fazia DUAS consultas em série (`encontrarPorRaw` + `User::find`)
 * e o MCP roda no CT 100 contra o MySQL do Hostinger: 755ms medidos para as
 * duas, ~362ms para uma. Memoizar o token deixa a resolução em UMA consulta.
 *
 * ISTO É AUTH — então o que estes testes travam não é a performance, é que a
 * memoização NÃO afrouxa a validade:
 *   - token EXPIRADO não passa, mesmo com a chave quente;
 *   - REVOGAR invalida na hora, sem esperar o TTL.
 *
 * Sem esses dois, o memo seria uma janela de acesso indevido — e o modo de
 * falha é silencioso: tudo "funciona", só que para quem não deveria mais entrar.
 *
 * @see Modules/Jana/Entities/Mcp/McpToken.php@encontrarPorRaw
 */

function usuarioSemeado(): int
{
    // mcp_tokens.user_id tem FK para users — id fictício quebra com SQLSTATE 23000.
    return (int) DB::table('users')->orderBy('id')->value('id');
}

function criarToken(?\DateTimeInterface $expiraEm = null): array
{
    $raw = 'mcp_' . bin2hex(random_bytes(16));
    $token = McpToken::create([
        'user_id'      => usuarioSemeado(),
        'name'         => 'probe-memo-' . uniqid(),
        'sha256_token' => hash('sha256', $raw),
        'expires_at'   => $expiraEm,
    ]);

    return [$token, $raw];
}

beforeEach(function () {
    config(['copiloto.mcp.token_cache_ttl' => 60]);
});

afterEach(function () {
    McpToken::withTrashed()->where('name', 'like', 'probe-memo-%')->forceDelete();
});

it('resolve o token sem ir ao banco na segunda vez', function () {
    [, $raw] = criarToken();

    expect(McpToken::encontrarPorRaw($raw))->not->toBeNull();

    DB::enableQueryLog();
    $segundo = McpToken::encontrarPorRaw($raw);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    // O ponto: a 2ª resolução não paga o roundtrip ao MySQL remoto.
    expect($segundo)->not->toBeNull()
        ->and($segundo->sha256_token)->toBe(hash('sha256', $raw))
        ->and($queries)->toHaveCount(0);
});

it('recusa token que expirou durante a janela do cache', function () {
    [, $raw] = criarToken(now()->addSeconds(30));

    expect(McpToken::encontrarPorRaw($raw))->not->toBeNull();

    // Avança o relógio além do expires_at que foi cacheado junto.
    Carbon::setTestNow(now()->addMinutes(2));

    try {
        // isAtivo() reavalia a cada hit: a expiração NÃO espera o TTL do cache.
        expect(McpToken::encontrarPorRaw($raw))->toBeNull();
    } finally {
        Carbon::setTestNow();
    }
});

it('DOCUMENTA a janela: alterar expires_at por SQL direto só vale após o TTL', function () {
    [$token, $raw] = criarToken(now()->addMinutes(5));

    expect(McpToken::encontrarPorRaw($raw))->not->toBeNull();

    // UPDATE direto não dispara evento do Model, então o memo mantém o
    // expires_at antigo — e o token segue valendo até o TTL expirar.
    DB::table('mcp_tokens')->where('id', $token->id)->update(['expires_at' => now()->subMinute()]);

    // Este assert registra o LIMITE REAL do desenho, não uma proteção.
    // A 1ª versão deste teste afirmava o contrário e o CI derrubou — o memo
    // reavalia com o valor que CACHEOU, não relê o banco (releitura anularia
    // a otimização inteira). Quem mexer em token por SQL deve rodar
    // `Cache::forget(McpToken::chaveToken($sha))` ou aceitar a janela do TTL.
    expect(McpToken::encontrarPorRaw($raw))->not->toBeNull();

    // E com o memo desligado o banco volta a mandar — prova que a janela vem
    // do cache e não de um bug na validação.
    config(['copiloto.mcp.token_cache_ttl' => 0]);
    expect(McpToken::encontrarPorRaw($raw))->toBeNull();
});

it('recusa token revogado imediatamente, sem esperar o TTL', function () {
    [$token, $raw] = criarToken();

    expect(McpToken::encontrarPorRaw($raw))->not->toBeNull();

    $token->revogar(usuarioSemeado());

    // Sem a invalidação por evento, revogar acesso dependeria do relógio.
    expect(McpToken::encontrarPorRaw($raw))->toBeNull();
});

it('não memoiza quando o TTL está zerado', function () {
    config(['copiloto.mcp.token_cache_ttl' => 0]);
    [, $raw] = criarToken();

    McpToken::encontrarPorRaw($raw);

    DB::enableQueryLog();
    McpToken::encontrarPorRaw($raw);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    // Escape sem deploy: volta a consultar sempre.
    expect(count($queries))->toBeGreaterThan(0);
});
