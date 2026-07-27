<?php

declare(strict_types=1);

use Modules\Jana\Entities\Mcp\McpTask;
use Modules\Jana\Services\TaskRegistry\HitlEscalationService;

uses(Tests\TestCase::class);

/**
 * GUARD do HitlEscalationService — o elo detectar→decidir (2026-07-27).
 *
 * O QUE ESTE TESTE PROVA (o contrato, não a implementação — §5 2026-06-05):
 *   1. MORDE   — pendência nova vira 1 task `blocked`/`wagner` (= o que o brief lê).
 *   2. NÃO SPAMA — re-escalar a MESMA chave atualiza a MESMA task; nunca cria a 2ª.
 *      É o defeito medido em produção: 38 dias de nag (30d→33d→35d→36d→38d).
 *   3. NÃO REABRE — task fechada pelo humano (done/cancelled) fica fechada. Sentinela
 *      que ressuscita o que o dono enterrou é pior que sentinela mudo.
 *   4. NÃO REBAIXA — task em tratamento (todo/doing/review) não volta pra blocked.
 *
 * As provas 3 e 4 são o controle-negativo: sem elas o serviço passaria mesmo se
 * escrevesse por cima da decisão humana toda madrugada.
 *
 * @see Modules\Jana\Services\TaskRegistry\HitlEscalationService
 * @see database/migrations/2026_05_06_172445_fix_brief_procedure_real_schema.php
 */
function hitlSvc(): HitlEscalationService
{
    return app(HitlEscalationService::class);
}

function hitlLimpa(string $taskId = 'HITL-TESTE-CLASSE'): void
{
    McpTask::where('task_id', $taskId)->forceDelete();
}

beforeEach(fn () => hitlLimpa());
afterEach(fn () => hitlLimpa());

it('MORDE: pendência nova vira task blocked do wagner (o canal que o brief lê)', function () {
    $task = hitlSvc()->escalar(
        chave: 'TESTE-CLASSE',
        titulo: '3 pendências há > 3d',
        descricao: 'corpo do alerta',
        modulo: 'TeamMcp',
        prioridade: 'p2',
        origem: 'teste',
    );

    expect($task)->not->toBeNull()
        ->and($task->task_id)->toBe('HITL-TESTE-CLASSE')
        ->and($task->status)->toBe('blocked')
        ->and($task->owner)->toBe('wagner');

    // A query EXATA da procedure do brief (HITL pending Wagner).
    $noBrief = McpTask::where('status', 'blocked')->where('owner', 'wagner')
        ->where('task_id', 'HITL-TESTE-CLASSE')->exists();
    expect($noBrief)->toBeTrue();
});

it('NÃO SPAMA: re-escalar a mesma chave atualiza a MESMA task (o bug dos 38 dias)', function () {
    hitlSvc()->escalar('TESTE-CLASSE', '1 pendência há > 3d', 'dia 1', 'TeamMcp', 'p2', 'teste');
    hitlSvc()->escalar('TESTE-CLASSE', '1 pendência há > 38d', 'dia 38', 'TeamMcp', 'p2', 'teste');

    $todas = McpTask::where('task_id', 'HITL-TESTE-CLASSE')->get();

    expect($todas)->toHaveCount(1)
        ->and($todas->first()->description)->toBe('dia 38')
        ->and($todas->first()->title)->toContain('38d');
});

it('NÃO REABRE: task que o humano fechou fica fechada (done)', function () {
    hitlSvc()->escalar('TESTE-CLASSE', 'pendência', 'corpo', 'TeamMcp', 'p2', 'teste');
    McpTask::where('task_id', 'HITL-TESTE-CLASSE')->update(['status' => 'done']);

    $r = hitlSvc()->escalar('TESTE-CLASSE', 'voltou', 'corpo novo', 'TeamMcp', 'p2', 'teste');

    expect($r)->toBeNull()
        ->and(McpTask::where('task_id', 'HITL-TESTE-CLASSE')->first()->status)->toBe('done');
});

it('NÃO REABRE: cancelled também é decisão humana', function () {
    hitlSvc()->escalar('TESTE-CLASSE', 'pendência', 'corpo', 'TeamMcp', 'p2', 'teste');
    McpTask::where('task_id', 'HITL-TESTE-CLASSE')->update(['status' => 'cancelled']);

    expect(hitlSvc()->escalar('TESTE-CLASSE', 'voltou', 'corpo', 'TeamMcp', 'p2', 'teste'))->toBeNull()
        ->and(McpTask::where('task_id', 'HITL-TESTE-CLASSE')->first()->status)->toBe('cancelled');
});

it('NÃO REBAIXA: task em tratamento (doing) não volta pra blocked', function () {
    hitlSvc()->escalar('TESTE-CLASSE', 'pendência', 'corpo', 'TeamMcp', 'p2', 'teste');
    McpTask::where('task_id', 'HITL-TESTE-CLASSE')->update(['status' => 'doing']);

    $r = hitlSvc()->escalar('TESTE-CLASSE', 'ainda pendente', 'corpo', 'TeamMcp', 'p2', 'teste');

    expect($r)->not->toBeNull()
        ->and($r->status)->toBe('doing')
        ->and($r->description)->toBe('corpo'); // não sobrescreveu o trabalho de quem pegou
});

it('prioridade inválida cai pro default p2 em vez de estourar', function () {
    $t = hitlSvc()->escalar('TESTE-CLASSE', 'x', 'y', 'TeamMcp', 'urgentíssimo', 'teste');

    expect($t->priority)->toBe('p2');
});
