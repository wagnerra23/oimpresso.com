<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Modules\Governance\Contracts\DriftChecker;
use Modules\Governance\Services\Checkers\DeployDriftChecker;
use Modules\Governance\Services\Checkers\McpServedDriftChecker;
use Modules\Governance\Services\DriftFinding;

uses(Tests\TestCase::class);

/**
 * McpServedDriftChecker (Onda 1 — sentinela transporte CT100→main) — consome
 * GET <env>/api/mcp/version (ADR 0256) e compara o commit SERVIDO com GitHub main.
 * Fecha o "~19 dias stale e ninguém viu". Http::fake substitui a rede; a fonte de
 * main reusa o DeployDriftChecker (arquivo do webhook), fakeada via config aqui.
 */

beforeEach(function () {
    config()->set('copiloto.mcp.drift_token', 'fake-drift-token');
    config()->set('governance.deploy_drift_envs', [
        ['nome' => 'mcp', 'url' => 'https://mcp.test'],
    ]);
});

it('implementa DriftChecker + registrado em governance.drift_checkers', function () {
    expect(new McpServedDriftChecker())->toBeInstanceOf(DriftChecker::class)
        ->and((new McpServedDriftChecker())->name())->toBe('mcp_served_drift')
        ->and((new McpServedDriftChecker())->severity())->toBe('high')
        ->and((new McpServedDriftChecker())->enforcement())->toBe('warn')
        ->and((new McpServedDriftChecker())->cadence())->toBe('daily')
        ->and((array) config('governance.drift_checkers'))->toContain(McpServedDriftChecker::class);
});

it('commit servido == main → clean (sem finding)', function () {
    // Fonte de "main" reusada do DeployDriftChecker = arquivo do webhook (cross-process).
    // Escrevemos no path canônico pra latestMainSha() devolver o SHA controlado.
    $sha = 'abc1234def5678';
    file_put_contents(DeployDriftChecker::shaFilePath(), $sha."\n");
    Http::fake([
        'mcp.test/api/mcp/version' => Http::response([
            'service' => 'oimpresso-mcp',
            'commit' => $sha,
            'commit_short' => substr($sha, 0, 9),
            'deployed_at' => now()->toIso8601String(),
        ], 200),
    ]);

    $result = (new McpServedDriftChecker())->check();

    expect($result->ok)->toBeTrue()
        ->and($result->drift_count)->toBe(0);

    @unlink(DeployDriftChecker::shaFilePath());
});

it('commit servido != main → drifted high', function () {
    $main = 'aaaaaaa1111111';
    file_put_contents(DeployDriftChecker::shaFilePath(), $main."\n");
    Http::fake([
        'mcp.test/api/mcp/version' => Http::response([
            'service' => 'oimpresso-mcp',
            'commit' => 'bbbbbbb2222222',
            'commit_short' => 'bbbbbbb22',
            'deployed_at' => now()->subDays(19)->toIso8601String(),
        ], 200),
    ]);

    $result = (new McpServedDriftChecker())->check();

    expect($result->ok)->toBeFalse()
        ->and($result->drift_count)->toBe(1)
        ->and($result->findings[0])->toBeInstanceOf(DriftFinding::class)
        ->and($result->findings[0]->severity)->toBe('high')
        ->and($result->findings[0]->target)->toBe('mcp')
        ->and($result->findings[0]->message)->toContain('!= GitHub main')
        ->and($result->findings[0]->evidence['served'])->toBe('bbbbbbb2222222');

    @unlink(DeployDriftChecker::shaFilePath());
});

it('HTTP 500 → finding low, NÃO derruba o audit', function () {
    file_put_contents(DeployDriftChecker::shaFilePath(), "aaaaaaa1111111\n");
    Http::fake([
        'mcp.test/api/mcp/version' => Http::response(['error' => 'Misconfigured'], 500),
    ]);

    $result = (new McpServedDriftChecker())->check();

    expect($result->ok)->toBeFalse()
        ->and($result->drift_count)->toBe(1)
        ->and($result->findings[0]->severity)->toBe('low')
        ->and($result->findings[0]->message)->toContain('HTTP 500');

    @unlink(DeployDriftChecker::shaFilePath());
});

it('timeout/conexão recusada → finding info, sem throw', function () {
    file_put_contents(DeployDriftChecker::shaFilePath(), "aaaaaaa1111111\n");
    Http::fake(function () {
        throw new \Illuminate\Http\Client\ConnectionException('Connection timed out');
    });

    $result = (new McpServedDriftChecker())->check();

    expect($result->ok)->toBeFalse()
        ->and($result->drift_count)->toBe(1)
        ->and($result->findings[0]->severity)->toBe('info')
        ->and($result->findings[0]->message)->toContain('Não consegui contatar');

    @unlink(DeployDriftChecker::shaFilePath());
});

it('env respondeu sem commit (unknown no boot) → finding info', function () {
    file_put_contents(DeployDriftChecker::shaFilePath(), "aaaaaaa1111111\n");
    Http::fake([
        'mcp.test/api/mcp/version' => Http::response([
            'service' => 'oimpresso-mcp',
            'commit' => null,
            'commit_short' => null,
            'deployed_at' => null,
        ], 200),
    ]);

    $result = (new McpServedDriftChecker())->check();

    expect($result->ok)->toBeFalse()
        ->and($result->findings[0]->severity)->toBe('info')
        ->and($result->findings[0]->message)->toContain('sem commit servido');

    @unlink(DeployDriftChecker::shaFilePath());
});
