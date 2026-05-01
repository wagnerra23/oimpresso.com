<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;

uses(Tests\TestCase::class)->in(__DIR__);

/**
 * US-TR-004 — Webhook dispara mcp:tasks:sync quando SPEC.md é tocada.
 * Testa apenas a detecção do payload (sem DB real).
 */

beforeEach(function () {
    Config::set('copiloto.mcp.sync_webhook_token', 'token-teste');
    Config::set('app.env', 'testing');
});

function payloadPush(array $commits): array
{
    return [
        'ref'     => 'refs/heads/main',
        'commits' => $commits,
    ];
}

it('dispara tasks:sync quando SPEC.md é modificada no push', function () {
    Artisan::spy();

    $payload = payloadPush([
        ['added' => [], 'modified' => ['memory/requisitos/NFSe/SPEC.md'], 'removed' => []],
    ]);

    $this->postJson('/api/mcp/sync-memory', $payload, [
        'X-MCP-Sync-Token' => 'token-teste',
    ])->assertStatus(200);

    Artisan::shouldHaveReceived('call')->with('mcp:tasks:sync')->once();
})->skip('requer DB + IndexarMemoryGitParaDb — testar em CT 100');

it('não dispara tasks:sync quando apenas ADR é modificada', function () {
    Artisan::spy();

    $payload = payloadPush([
        ['added' => [], 'modified' => ['memory/decisions/0053-mcp-server.md'], 'removed' => []],
    ]);

    $this->postJson('/api/mcp/sync-memory', $payload, [
        'X-MCP-Sync-Token' => 'token-teste',
    ])->assertStatus(200);

    Artisan::shouldNotHaveReceived('call', ['mcp:tasks:sync']);
})->skip('requer DB + IndexarMemoryGitParaDb — testar em CT 100');

it('detecta SPEC.md em added', function () {
    Artisan::spy();

    $payload = payloadPush([
        ['added' => ['memory/requisitos/Novo/SPEC.md'], 'modified' => [], 'removed' => []],
    ]);

    $this->postJson('/api/mcp/sync-memory', $payload, [
        'X-MCP-Sync-Token' => 'token-teste',
    ])->assertStatus(200);

    Artisan::shouldHaveReceived('call')->with('mcp:tasks:sync')->once();
})->skip('requer DB + IndexarMemoryGitParaDb — testar em CT 100');

it('retorna 401 com token errado', function () {
    $this->postJson('/api/mcp/sync-memory', [], [
        'X-MCP-Sync-Token' => 'errado',
    ])->assertStatus(401);
});

it('retorna skipped para push em branch diferente de main', function () {
    $this->postJson('/api/mcp/sync-memory', ['ref' => 'refs/heads/feature-x'], [
        'X-MCP-Sync-Token' => 'token-teste',
    ])->assertStatus(200)->assertJson(['skipped' => true]);
});
