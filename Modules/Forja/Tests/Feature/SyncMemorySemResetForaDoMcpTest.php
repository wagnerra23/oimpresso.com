<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Process;
use Modules\Forja\Http\Controllers\Mcp\SyncMemoryWebhookController;

uses(Tests\TestCase::class);

/**
 * O webhook do GitHub chega também ao Hostinger (o hook aponta para oimpresso.com). Lá, o
 * reset do checkout publicava o tip de main em produção a cada push, por fora do deploy.yml,
 * e em 2026-10-02 um deploy lento pinou produção num commit velho por cima de 5 PRs.
 * Só o servidor do MCP (MCP_TOOLS_EXPOSED=true) pode mexer no próprio checkout.
 */
function syncMemoriaSincronizar(): array
{
    $m = new ReflectionMethod(SyncMemoryWebhookController::class, 'sincronizarComOrigin');
    $m->setAccessible(true);

    $req = Request::create('/api/mcp/sync-memory', 'POST', ['ref' => 'refs/heads/main', 'commits' => []]);

    return $m->invoke(app(SyncMemoryWebhookController::class), $req);
}

it('fora do servidor do MCP não roda git nenhum: produção só muda pelo deploy', function () {
    Config::set('mcp.tools_exposed', false);
    Process::fake();

    $r = syncMemoriaSincronizar();

    expect($r['pulled'])->toBeFalse();
    expect($r['reason'])->toBe('deploy_e_do_actions');
    Process::assertNotRan(fn ($p) => str_contains((string) $p->command, 'reset'));
    Process::assertNotRan(fn ($p) => str_contains((string) $p->command, 'fetch'));
});

it('no servidor do MCP segue sincronizando o checkout (fetch e reset)', function () {
    Config::set('mcp.tools_exposed', true);
    Process::fake();

    $r = syncMemoriaSincronizar();

    expect($r['pulled'])->toBeTrue();
    Process::assertRan(fn ($p) => str_contains((string) $p->command, 'fetch origin main'));
    Process::assertRan(fn ($p) => str_contains((string) $p->command, 'origin/main') && str_contains((string) $p->command, 'reset'));
});
