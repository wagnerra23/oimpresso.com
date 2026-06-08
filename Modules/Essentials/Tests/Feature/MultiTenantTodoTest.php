<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Essentials\Entities\ToDo;

uses(Tests\TestCase::class);

/**
 * Testa isolamento multi-tenant Tier 0 do Todo do Essentials.
 *
 * ToDo NÃO usa global scope BusinessScope (legado UltimatePOS) — o isolamento
 * vem do Controller (ToDoController filtra `where('business_id', auth user
 * business_id)`). Estes testes validam que dados criados em biz=1 NÃO vazam em
 * queries scoped por business_id=99 (fictício), cobrindo os 5 cenários:
 * list / show / edit / delete / complete.
 *
 * ADR 0093: multi-tenant Tier 0 IRREVOGÁVEL.
 * ADR 0101: tests sempre biz=1 (Wagner WR2) e biz=99 (fictício). NUNCA biz=4 (ROTA LIVRE produção).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema essentials_to_dos requer MySQL UltimatePOS (ADR 0101).');
    }
    if (! Schema::hasTable('essentials_to_dos')) {
        $this->markTestSkipped('essentials_to_dos table missing — rode migrate do módulo Essentials primeiro.');
    }
});

const ESS_BIZ_WAGNER = 1;
const ESS_BIZ_FICTICIO = 99;

/**
 * Helper — cria Todo direto via Model (sem Controller) pra isolar a regra de scope.
 */
function essCreateTodo(int $businessId, string $task, array $extras = []): ToDo
{
    return ToDo::create(array_merge([
        'business_id' => $businessId,
        'created_by'  => 1, // user_id stub — sem global scope no Model, OK
        'task'        => $task,
        'task_id'     => 'TEST-' . substr(md5($task), 0, 10),
        'date'        => now(),
        'status'      => 'new',
        'priority'    => 'medium',
    ], $extras));
}

// ------------------------------------------------------------------
// 1. LIST — Todo biz=1 não aparece em query scoped biz=99
// ------------------------------------------------------------------

it('Todo biz=1 não aparece em listagem scoped biz=99', function () {
    $todo = essCreateTodo(ESS_BIZ_WAGNER, 'Tarefa Wagner Isolamento');

    $resultado = ToDo::where('business_id', ESS_BIZ_FICTICIO)
        ->where('id', $todo->id)
        ->get();

    expect($resultado)->toHaveCount(0);
})->afterEach(function () {
    ToDo::where('task', 'Tarefa Wagner Isolamento')->delete();
});

// ------------------------------------------------------------------
// 2. SHOW — Todo biz=1 não pode ser carregada via where biz=99
// ------------------------------------------------------------------

it('Todo biz=1 NÃO pode ser carregada via show scoped biz=99', function () {
    $todo = essCreateTodo(ESS_BIZ_WAGNER, 'Tarefa Show Isolamento');

    $achado = ToDo::where('business_id', ESS_BIZ_FICTICIO)
        ->where('id', $todo->id)
        ->first();

    expect($achado)->toBeNull();
})->afterEach(function () {
    ToDo::where('task', 'Tarefa Show Isolamento')->delete();
});

// ------------------------------------------------------------------
// 3. EDIT — Todo biz=1 não pode ser editada via where biz=99
// ------------------------------------------------------------------

it('Todo biz=1 NÃO pode ser editada via update scoped biz=99', function () {
    $todo = essCreateTodo(ESS_BIZ_WAGNER, 'Tarefa Edit Isolamento');

    $affected = ToDo::where('business_id', ESS_BIZ_FICTICIO)
        ->where('id', $todo->id)
        ->update(['task' => 'INVADIDO']);

    expect($affected)->toBe(0);

    // Recarrega do banco — task original preservada
    $fresh = ToDo::find($todo->id);
    expect($fresh->task)->toBe('Tarefa Edit Isolamento');
})->afterEach(function () {
    ToDo::where('id', '>', 0)
        ->whereIn('task', ['Tarefa Edit Isolamento', 'INVADIDO'])
        ->delete();
});

// ------------------------------------------------------------------
// 4. DELETE — Todo biz=1 não pode ser deletada via where biz=99
// ------------------------------------------------------------------

it('Todo biz=1 NÃO pode ser deletada via destroy scoped biz=99', function () {
    $todo = essCreateTodo(ESS_BIZ_WAGNER, 'Tarefa Delete Isolamento');

    $deleted = ToDo::where('business_id', ESS_BIZ_FICTICIO)
        ->where('id', $todo->id)
        ->delete();

    expect($deleted)->toBe(0);

    // Ainda existe no banco scoped biz=1
    $sobreviveu = ToDo::where('business_id', ESS_BIZ_WAGNER)
        ->where('id', $todo->id)
        ->first();

    expect($sobreviveu)->not->toBeNull();
})->afterEach(function () {
    ToDo::where('task', 'Tarefa Delete Isolamento')->delete();
});

// ------------------------------------------------------------------
// 5. COMPLETE — Todo biz=1 não pode ter status mudado via where biz=99
// ------------------------------------------------------------------

it('Todo biz=1 NÃO pode ser marcada completed via scope biz=99', function () {
    $todo = essCreateTodo(ESS_BIZ_WAGNER, 'Tarefa Complete Isolamento', [
        'status' => 'in_progress',
    ]);

    $affected = ToDo::where('business_id', ESS_BIZ_FICTICIO)
        ->where('id', $todo->id)
        ->update(['status' => 'completed']);

    expect($affected)->toBe(0);

    // Status original preservado
    $fresh = ToDo::find($todo->id);
    expect($fresh->status)->toBe('in_progress');
})->afterEach(function () {
    ToDo::where('task', 'Tarefa Complete Isolamento')->delete();
});

// ------------------------------------------------------------------
// 6. (smoke) — biz=1 enxerga apenas o que é dele
// ------------------------------------------------------------------

it('Todo biz=1 aparece em listagem scoped biz=1', function () {
    $todo = essCreateTodo(ESS_BIZ_WAGNER, 'Tarefa Wagner Visivel');

    $resultado = ToDo::where('business_id', ESS_BIZ_WAGNER)
        ->where('id', $todo->id)
        ->get();

    expect($resultado)->toHaveCount(1);
    expect($resultado->first()->task)->toBe('Tarefa Wagner Visivel');
})->afterEach(function () {
    ToDo::where('task', 'Tarefa Wagner Visivel')->delete();
});
