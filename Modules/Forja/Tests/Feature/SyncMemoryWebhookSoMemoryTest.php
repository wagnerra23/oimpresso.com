<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;
use Modules\Forja\Http\Controllers\Mcp\SyncMemoryWebhookController;

/**
 * O webhook /api/mcp/sync-memory atualiza SÓ memory/ e nunca move o código.
 *
 * Até 2026-10-02 ele fazia reset do working tree inteiro para o topo do main a cada
 * push, e isso publicava PHP em produção sem autoload, OPcache nem bundles (medido
 * pelo reflog do servidor × entregas do hook). Decisão [W] 2026-10-02, opção A de
 * memory/decisions/proposals/webhook-sync-memory-sem-reset-do-codigo.md.
 *
 * Process::fake grava os comandos sem executar nada: o teste mede o que o controller
 * PEDE ao git, que é o contrato. Não precisa de banco nem de repositório.
 */
uses(Tests\TestCase::class);

function smwComando($process): string
{
    return is_array($process->command) ? implode(' ', $process->command) : (string) $process->command;
}

function smwSincronizar(array $tocados): array
{
    $request = Request::create('/api/mcp/sync-memory', 'POST', [], [], [], [], json_encode([
        'ref' => 'refs/heads/main',
        'commits' => [['added' => [], 'modified' => $tocados, 'removed' => []]],
    ]));
    $request->headers->set('Content-Type', 'application/json');

    $metodo = (new ReflectionClass(SyncMemoryWebhookController::class))->getMethod('sincronizarComOrigin');
    $metodo->setAccessible(true);

    return $metodo->invoke(new SyncMemoryWebhookController(), $request);
}

it('push que muda código atualiza só memory/ e não reseta o working tree', function () {
    Process::fake();

    $git = smwSincronizar(['app/Http/Controllers/AlgumController.php', 'memory/decisions/0999-x.md']);

    expect($git['pulled'])->toBeTrue();
    expect($git['scope'])->toBe('memory');

    Process::assertRan(fn ($p) => smwComando($p) === 'git fetch origin main');
    Process::assertRan(fn ($p) => smwComando($p) === 'git restore --source=origin/main --staged --worktree -- memory');

    // Antes do conserto este era o comando que publicava código em produção.
    Process::assertDidntRun(fn ($p) => str_contains(smwComando($p), 'reset'));
    Process::assertDidntRun(fn ($p) => str_contains(smwComando($p), 'checkout') || str_contains(smwComando($p), 'pull'));
});

it('push que muda composer.lock também atualiza memory/, sem tocar no código', function () {
    // O filtro antigo pulava o sync inteiro nesse caso, e a memória ficava parada.
    // Agora o código nunca se move aqui, então não há o que pular.
    Process::fake();

    $git = smwSincronizar(['composer.lock']);

    expect($git['pulled'])->toBeTrue();
    Process::assertRan(fn ($p) => str_starts_with(smwComando($p), 'git restore --source=origin/main'));
    Process::assertDidntRun(fn ($p) => str_contains(smwComando($p), 'reset'));
});

it('restore de memory/ que falha devolve pulled=false com o motivo', function () {
    Process::fake([
        'git fetch origin main' => Process::result(),
        '*restore*' => Process::result(errorOutput: 'fatal', exitCode: 128),
        '*' => Process::result(output: 'abc1234'),
    ]);

    $git = smwSincronizar(['memory/decisions/0999-x.md']);

    expect($git['pulled'])->toBeFalse();
    expect($git['reason'])->toBe('git_restore_failed');
});
