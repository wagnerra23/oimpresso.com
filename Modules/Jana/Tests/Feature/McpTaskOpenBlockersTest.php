<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Jana\Entities\Mcp\McpTask;

// Jana não está no `uses(TestCase::class)->in(...)` do tests/Pest.php (só tests/Feature + KB/RB/WA),
// então o bootstrap vem explícito — mesmo padrão do LgpdEsquecerTitularToolTest.
uses(Tests\TestCase::class);
// DatabaseTransactions (não RefreshDatabase): rollback por teste sem recriar schema — o alvo é o
// MySQL do CT 100, e o foundation-ratchet conta RefreshDatabase.
uses(DatabaseTransactions::class)->group('database');

/**
 * `blocked_by` é histórico: registra de quem a task dependeu e continua verdadeiro depois que o
 * bloqueador fecha. Quem lê o campo cru e anuncia "⛔ bloqueada" no PRESENTE mente — foi assim que
 * US-INFRA-002 (Client Signal) ficou 7 semanas marcada como presa por US-INFRA-001, concluída em
 * 2026-05-28. Ninguém pega task "bloqueada", então o trabalho congela.
 *
 * Os cenários 3-4 são o controle-negativo: sem eles o fix viraria cegueira (sumir com TODO cadeado
 * é tão errado quanto mostrar todos).
 */
it('sume com o cadeado quando o unico bloqueador ja fechou', function () {
    $map = ['US-INFRA-001' => 'done'];

    expect(McpTask::openBlockers(['US-INFRA-001'], $map))->toBe([]);
});

it('trata cancelled como fechado — nao trava mais', function () {
    $map = ['US-X-001' => 'cancelled'];

    expect(McpTask::openBlockers(['US-X-001'], $map))->toBe([]);
});

it('CONTROLE-NEGATIVO — bloqueador aberto MANTEM o cadeado', function (string $status) {
    $map = ['US-X-001' => $status];

    expect(McpTask::openBlockers(['US-X-001'], $map))->toBe(['US-X-001']);
})->with(['todo', 'doing', 'review', 'blocked', 'backlog']);

it('CONTROLE-NEGATIVO — bloqueador desconhecido conta como aberto (fail-safe)', function () {
    // Sem prova de que fechou, o cadeado fica: some só com evidência.
    expect(McpTask::openBlockers(['US-FANTASMA-999'], []))->toBe(['US-FANTASMA-999']);
});

it('em bloqueio multiplo, mantem so os que seguem abertos', function () {
    $map = ['US-A-001' => 'done', 'US-B-002' => 'todo', 'US-C-003' => 'cancelled'];

    expect(McpTask::openBlockers(['US-A-001', 'US-B-002', 'US-C-003'], $map))->toBe(['US-B-002']);
});

it('task sem blocked_by nao inventa bloqueio', function () {
    expect(McpTask::openBlockers(null, []))->toBe([])
        ->and(McpTask::openBlockers([], []))->toBe([]);
});

it('statusMapFor le o status real do banco e resolve o caso US-INFRA-002', function () {
    // Reproduz o caso vivo: a 002 aponta pra 001, que fechou.
    McpTask::create([
        'task_id' => 'US-TEST-001', 'title' => 'Bloqueador que ja fechou',
        'module' => 'Infra', 'status' => 'done', 'priority' => 'p0',
    ]);
    McpTask::create([
        'task_id' => 'US-TEST-002', 'title' => 'Travada por fantasma',
        'module' => 'Infra', 'status' => 'todo', 'priority' => 'p1',
        'blocked_by' => ['US-TEST-001'],
    ]);

    $t = McpTask::where('task_id', 'US-TEST-002')->first();
    $map = McpTask::statusMapFor($t->blocked_by);

    expect($map)->toBe(['US-TEST-001' => 'done'])
        ->and(McpTask::openBlockers($t->blocked_by, $map))->toBe([]);
})->group('database');

it('statusMapFor nao quebra com lista vazia', function () {
    expect(McpTask::statusMapFor([]))->toBe([]);
});
