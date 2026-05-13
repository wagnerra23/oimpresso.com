<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Entities\Mcp\McpTask;
use Modules\Jana\Services\TaskRegistry\TaskParserService;

uses(Tests\TestCase::class);

/**
 * Bug #2 BUGS-MCP-SYNC-2026-05-13 / ADR 0144 — DB = canon, SPEC = template.
 *
 * Quando webhook GitHub→MCP roda TaskParserService::syncAll(), tasks já
 * existentes no DB NUNCA têm status/owner/sprint/priority sobrescritos.
 * Só campos descritivos (title, description, labels, etc) refletem o SPEC.
 *
 * Tasks NOVAS continuam recebendo estado inicial do SPEC normalmente.
 *
 * Cobertura em 2 modos:
 *  - Testes unit (sem DB) que travam o contrato dos helpers novos
 *  - Testes integração (skip local — rodam em CT 100/MySQL)
 *    porque RefreshDatabase com SQLite quebra na migration legacy
 *    `ALTER TABLE transactions MODIFY COLUMN type ENUM(...)` (UltimatePOS
 *    schema MySQL-only). Issue pré-existente em main.
 */

// ─── Testes unit (rodam local — sem DB) ─────────────────────────────────

it('exporta constante LIVE_STATE_FIELDS com os 4 campos canônicos', function () {
    expect(TaskParserService::LIVE_STATE_FIELDS)
        ->toBe(['status', 'owner', 'sprint', 'priority']);
});

it('extrairCamposDescritivos remove os 4 campos de estado vivo do payload', function () {
    $svc = new TaskParserService();
    $reflect = (new ReflectionClass(TaskParserService::class))
        ->getMethod('extrairCamposDescritivos');
    $reflect->setAccessible(true);

    $cand = [
        'task_id' => 'US-X-1',
        'title' => 'Novo título',
        'description' => 'Nova desc',
        'status' => 'todo',
        'owner' => 'wagner',
        'sprint' => 'CYCLE-08',
        'priority' => 'p1',
        'type' => 'story',
        'labels' => ['frontend'],
        'estimate_h' => 4.0,
        'source_path' => 'memory/requisitos/X/SPEC.md',
    ];

    $out = $reflect->invoke($svc, $cand);

    expect($out)->toHaveKey('task_id')
        ->and($out)->toHaveKey('title')
        ->and($out)->toHaveKey('description')
        ->and($out)->toHaveKey('type')
        ->and($out)->toHaveKey('labels')
        ->and($out)->toHaveKey('estimate_h')
        ->and($out)->toHaveKey('source_path')
        ->and($out)->not->toHaveKey('status')
        ->and($out)->not->toHaveKey('owner')
        ->and($out)->not->toHaveKey('sprint')
        ->and($out)->not->toHaveKey('priority');
});

it('precisaAtualizar IGNORA mudanças em status/owner/sprint/priority no SPEC', function () {
    $svc = new TaskParserService();
    $reflect = (new ReflectionClass(TaskParserService::class))
        ->getMethod('precisaAtualizar');
    $reflect->setAccessible(true);

    // Mock McpTask sem hit no DB — usa unsaved instance.
    $existente = new McpTask([
        'task_id' => 'US-X-1',
        'title' => 'mesmo título',
        'description' => 'mesma desc',
        'status' => 'done',   // DB diz done
        'owner' => 'wagner',
        'sprint' => 'CYCLE-08',
        'priority' => 'p0',
        'type' => 'story',
        'estimate_h' => null,
        'source_path' => 'memory/requisitos/X/SPEC.md#US-X-1',
        'identifier' => null,
        'project_id' => null,
        'epic_id' => null,
        'cycle_id' => null,
        'component_id' => null,
        'story_points' => null,
        'estimate_unit' => 'points',
        'estimate_value' => null,
        'labels' => null,
        'blocked_by' => null,
        'custom_fields' => null,
        'due_date' => null,
    ]);

    // SPEC tem status:todo (divergente) mas title e description IGUAIS
    $candIgualExcetoEstadoVivo = [
        'task_id' => 'US-X-1',
        'title' => 'mesmo título',
        'description' => 'mesma desc',
        'status' => 'todo',   // SPEC diz todo
        'owner' => 'eliana',  // SPEC diz eliana
        'sprint' => 'CYCLE-01',
        'priority' => 'p2',
        'type' => 'story',
        'estimate_h' => null,
        'source_path' => 'memory/requisitos/X/SPEC.md#US-X-1',
        'identifier' => null,
        'project_id' => null,
        'epic_id' => null,
        'cycle_id' => null,
        'component_id' => null,
        'story_points' => null,
        'estimate_unit' => 'points',
        'estimate_value' => null,
        'labels' => null,
        'blocked_by' => null,
        'custom_fields' => null,
        'due_date' => null,
    ];

    // Sem mudança em campo descritivo → não precisa atualizar.
    // (status/owner/sprint/priority divergentes são IGNORADOS — ADR 0144)
    expect($reflect->invoke($svc, $existente, $candIgualExcetoEstadoVivo))
        ->toBeFalse();

    // Agora muda title — DEVE precisar atualizar
    $candComTitleNovo = $candIgualExcetoEstadoVivo;
    $candComTitleNovo['title'] = 'Título novo do PR';
    expect($reflect->invoke($svc, $existente, $candComTitleNovo))
        ->toBeTrue();
});

it('logarSkipsDeEstadoVivo registra divergências quando preservadas', function () {
    $svc = new TaskParserService();
    $reflect = (new ReflectionClass(TaskParserService::class))
        ->getMethod('logarSkipsDeEstadoVivo');
    $reflect->setAccessible(true);

    $existente = new McpTask([
        'task_id' => 'US-X-9',
        'status' => 'done',
        'owner' => 'wagner',
        'sprint' => null,
        'priority' => 'p0',
    ]);

    $cand = [
        'status' => 'todo',
        'owner' => 'wagner', // igual — não loga
        'sprint' => 'A',
        'priority' => 'p0',  // igual — não loga
    ];

    Log::shouldReceive('channel')
        ->with('copiloto-ai')
        ->andReturnSelf();
    Log::shouldReceive('info')
        ->with(
            'TaskParser preservou estado vivo DB (ADR 0144)',
            \Mockery::on(function ($ctx) {
                return $ctx['task_id'] === 'US-X-9'
                    && $ctx['preservados']['status']['db'] === 'done'
                    && $ctx['preservados']['status']['spec'] === 'todo'
                    && $ctx['preservados']['sprint']['db'] === null
                    && $ctx['preservados']['sprint']['spec'] === 'A'
                    && ! isset($ctx['preservados']['owner'])
                    && ! isset($ctx['preservados']['priority']);
            })
        )
        ->once();

    $reflect->invoke($svc, $existente, $cand);
});

// ─── Testes integração (skip local — rodam em CT 100/MySQL) ─────────────

function tpMod(string $mod): string
{
    return '__TestAdr0144_' . $mod;
}

function tpWriteSpec(string $module, string $body): string
{
    $dir = base_path("memory/requisitos/{$module}");
    if (! is_dir($dir)) {
        File::makeDirectory($dir, 0755, true);
    }
    $path = $dir . '/SPEC.md';
    file_put_contents($path, $body);
    return $path;
}

function tpCleanup(): void
{
    foreach (['ADR0144A', 'ADR0144B', 'ADR0144C', 'ADR0144D'] as $m) {
        $dir = base_path('memory/requisitos/' . tpMod($m));
        if (is_dir($dir)) {
            File::deleteDirectory($dir);
        }
    }
}

it('integração: preserva status DONE no DB quando SPEC diz TODO', function () {
    afterEach(fn () => tpCleanup());

    $module = tpMod('ADR0144A');
    tpWriteSpec($module, <<<MD
    ### US-ADR0144A-1 · Algo importante

    > owner: wagner · status: todo · priority: p1

    Descrição original.
    MD);

    $svc = new TaskParserService();
    $svc->syncAll($module);

    $task = McpTask::where('task_id', 'US-ADR0144A-1')->first();
    expect($task)->not->toBeNull()
        ->and($task->status)->toBe('todo');

    $task->update(['status' => 'done']);

    $svc->syncAll($module);

    $task->refresh();
    expect($task->status)->toBe('done');
})->skip('requer MySQL — UltimatePOS migration ALTER TABLE MODIFY ENUM não roda em SQLite. Testar em CT 100 (Tailscale) ou Laragon local com MYSQL_TESTING.');

it('integração: cria task nova com status inicial do SPEC', function () {
    afterEach(fn () => tpCleanup());

    $module = tpMod('ADR0144B');
    tpWriteSpec($module, <<<MD
    ### US-ADR0144B-2 · Task que ainda não está no DB

    > owner: eliana · status: todo · priority: p0

    Desc.
    MD);

    expect(McpTask::where('task_id', 'US-ADR0144B-2')->exists())->toBeFalse();

    $svc = new TaskParserService();
    $svc->syncAll($module);

    $task = McpTask::where('task_id', 'US-ADR0144B-2')->first();
    expect($task)->not->toBeNull()
        ->and($task->status)->toBe('todo')
        ->and($task->owner)->toBe('eliana')
        ->and($task->priority)->toBe('p0');
})->skip('requer MySQL — vide nota anterior. Cobertura unit acima trava o contrato dos helpers.');

it('integração: atualiza TITLE do SPEC mas preserva STATUS DOING do DB', function () {
    afterEach(fn () => tpCleanup());

    $module = tpMod('ADR0144C');
    tpWriteSpec($module, <<<MD
    ### US-ADR0144C-3 · Título inicial

    > owner: wagner · status: todo · priority: p2

    Desc inicial.
    MD);

    $svc = new TaskParserService();
    $svc->syncAll($module);

    $task = McpTask::where('task_id', 'US-ADR0144C-3')->first();
    expect($task->title)->toBe('Título inicial');

    $task->update([
        'status' => 'doing',
        'owner' => 'felipe',
        'priority' => 'p0',
        'sprint' => 'CYCLE-08',
    ]);

    tpWriteSpec($module, <<<MD
    ### US-ADR0144C-3 · Título atualizado pelo Wagner

    > owner: wagner · status: todo · priority: p2

    Descrição totalmente nova depois de discussão.
    MD);

    $svc->syncAll($module);

    $task->refresh();
    expect($task->title)->toBe('Título atualizado pelo Wagner')
        ->and($task->description)->toContain('Descrição totalmente nova')
        ->and($task->status)->toBe('doing')
        ->and($task->owner)->toBe('felipe')
        ->and($task->priority)->toBe('p0')
        ->and($task->sprint)->toBe('CYCLE-08');
})->skip('requer MySQL — vide nota anterior.');

it('integração: NÃO regride task done pra cancelled se for removida do SPEC', function () {
    afterEach(fn () => tpCleanup());

    $module = tpMod('ADR0144A');
    tpWriteSpec($module, <<<MD
    ### US-ADR0144A-9 · Vai virar done e depois sumir

    > owner: wagner · status: todo · priority: p2

    Desc.
    MD);

    $svc = new TaskParserService();
    $svc->syncAll($module);

    McpTask::where('task_id', 'US-ADR0144A-9')->update(['status' => 'done']);

    tpWriteSpec($module, "### US-ADR0144A-OUTRA · Outra coisa\n\n> owner: wagner\n\ndesc\n");

    $svc->syncAll($module);

    $task = McpTask::where('task_id', 'US-ADR0144A-9')->first();
    expect($task->status)->toBe('done');
})->skip('requer MySQL — vide nota anterior.');
