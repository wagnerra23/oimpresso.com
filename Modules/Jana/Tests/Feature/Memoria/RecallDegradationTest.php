<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Modules\Jana\Services\Memoria\McpMemoriaDriver;

uses(Tests\TestCase::class);

/**
 * TAREFA 3 (resiliência Meilisearch) — provar + apertar o que já existe.
 *
 * Meilisearch é ponto único de falha do recall (tool `memoria-search` via MCP).
 * Comportamento canon: se cai, o McpMemoriaDriver degrada SILENCIOSAMENTE
 * (retorna [] → chat responde sem memória, NÃO estoura 500). Estes testes:
 *   1. PROVAM a degradação graciosa do driver (chat não estoura).
 *   2. PROVAM que `jana:health-check` agora ALERTA antes da degradação silenciosa.
 *
 * @see Modules/Jana/Services/Memoria/McpMemoriaDriver.php
 * @see Modules/Jana/Console/Commands/HealthCheckCommand.php (checkMemoriaRecallBackend)
 */

// ── Helper — roda o health-check e extrai só o check do recall backend ──────────
function janaRecallBackendCheck(): ?array
{
    Artisan::call('jana:health-check', ['--json' => true]);
    $out = Artisan::output();
    $start = strpos($out, '{');
    if ($start === false) {
        return null;
    }
    $json = json_decode(substr($out, $start), true);

    return collect($json['checks'] ?? [])->firstWhere('name', 'memoria_recall_backend');
}

// ── Driver: degradação graciosa (chat não estoura) ─────────────────────────────

test('McpMemoriaDriver degrada: backend 5xx → buscar() retorna [] (chat responde sem recall, não estoura)', function () {
    config([
        'copiloto.mcp.url' => 'https://mcp.test/api/mcp',
        'copiloto.mcp.system_token' => 'sys-token',
        'copiloto.mcp.timeout_seconds' => 2,
    ]);
    Http::fake(['mcp.test/*' => Http::response('upstream error', 503)]);

    $driver = new McpMemoriaDriver();

    expect($driver->buscar(1, 1, 'como foi meu faturamento', 5))->toBe([]);
});

test('McpMemoriaDriver degrada: exceção de conexão (timeout) → buscar() retorna [] sem estourar', function () {
    config([
        'copiloto.mcp.url' => 'https://mcp.test/api/mcp',
        'copiloto.mcp.system_token' => 'sys-token',
        'copiloto.mcp.timeout_seconds' => 2,
    ]);
    Http::fake(function () {
        throw new \Illuminate\Http\Client\ConnectionException('connection timed out after 2000ms');
    });

    $driver = new McpMemoriaDriver();

    expect($driver->buscar(1, 1, 'qualquer query', 5))->toBe([]);
});

test('McpMemoriaDriver sem token: pula recall e retorna [] (não estoura)', function () {
    config([
        'copiloto.mcp.url' => 'https://mcp.test/api/mcp',
        'copiloto.mcp.system_token' => '',
    ]);
    Http::fake(); // nenhuma chamada deve sair

    $driver = new McpMemoriaDriver();

    expect($driver->buscar(1, 1, 'q', 5))->toBe([]);
    Http::assertNothingSent();
});

// ── Health-check: alarme antes da degradação silenciosa ────────────────────────

test('jana:health-check ALERTA quando recall backend está down', function () {
    config([
        'copiloto.mcp.url' => 'https://mcp.test/api/mcp',
        'copiloto.mcp.system_token' => 'sys-token',
        'copiloto.mcp.timeout_seconds' => 2,
    ]);
    Http::fake(['mcp.test/*' => Http::response('meilisearch down', 503)]);

    $check = janaRecallBackendCheck();

    expect($check)->not->toBeNull();
    expect($check['ok'])->toBeFalse();
    expect($check['message'])->toContain('ALERTA');
});

test('jana:health-check OK quando recall backend responde', function () {
    config([
        'copiloto.mcp.url' => 'https://mcp.test/api/mcp',
        'copiloto.mcp.system_token' => 'sys-token',
        'copiloto.mcp.timeout_seconds' => 2,
    ]);
    Http::fake(['mcp.test/*' => Http::response(['jsonrpc' => '2.0', 'id' => 1, 'result' => ['content' => []]], 200)]);

    $check = janaRecallBackendCheck();

    expect($check)->not->toBeNull();
    expect($check['ok'])->toBeTrue();
});

test('jana:health-check pula recall em dev/CI sem token MCP (não é falha)', function () {
    config([
        'copiloto.mcp.url' => 'https://mcp.test/api/mcp',
        'copiloto.mcp.system_token' => '',
    ]);

    $check = janaRecallBackendCheck();

    expect($check)->not->toBeNull();
    expect($check['ok'])->toBeTrue();
    expect($check['value'])->toBe('n/a');
});
