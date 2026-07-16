<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Entities\Mcp\McpTask;
use Modules\Jana\Services\TaskRegistry\TaskParserService;

uses(Tests\TestCase::class);

/**
 * SDD C4 — detectar (NÃO resolver) divergência SPEC↔DB em campos descritivos.
 *
 * Quando o sync git→DB atualiza uma task existente, se title OU description do
 * DB divergirem do SPEC, o TaskParserService:
 *   1. loga a divergência (canal copiloto-ai), e
 *   2. incrementa o contador `descritivos_divergentes` no relatório de retorno.
 *
 * NÃO muda quem ganha: git continua canon nos campos descritivos (o update
 * sobrescreve o DB com o SPEC normalmente). Estado vivo (status/owner/sprint/
 * priority) permanece blindado pelo ADR 0144 — zero-regressão.
 *
 * Cobertura em 2 modos (espelha TaskParserPreservaEstadoVivoTest):
 *  - Testes unit (sem DB) que travam o contrato do helper + contador
 *  - Teste integração (skip local — roda em CT 100/MySQL) porque
 *    RefreshDatabase com SQLite quebra na migration legacy UltimatePOS
 *    `ALTER TABLE transactions MODIFY COLUMN type ENUM(...)` (MySQL-only).
 */

// ─── Testes unit (rodam local na lane sqlite — sem DB) ──────────────────

it('expõe DESCRITIVOS_DETECTAVEIS = title + description', function () {
    expect(TaskParserService::DESCRITIVOS_DETECTAVEIS)
        ->toBe(['title', 'description']);
});

it('detectarDivergenciaDescritiva retorna false quando title e description batem', function () {
    $svc = new TaskParserService();
    $reflect = (new ReflectionClass(TaskParserService::class))
        ->getMethod('detectarDivergenciaDescritiva');
    $reflect->setAccessible(true);

    // Log não deve ser chamado quando não há divergência.
    Log::shouldReceive('channel')->never();

    $existente = new McpTask([
        'task_id' => 'US-X-1',
        'title' => 'Mesmo título',
        'description' => 'Mesma descrição',
    ]);

    $cand = [
        'title' => 'Mesmo título',
        'description' => 'Mesma descrição',
    ];

    expect($reflect->invoke($svc, $existente, $cand))->toBeFalse();
});

it('detectarDivergenciaDescritiva retorna true quando description diverge e loga drift', function () {
    $svc = new TaskParserService();
    $reflect = (new ReflectionClass(TaskParserService::class))
        ->getMethod('detectarDivergenciaDescritiva');
    $reflect->setAccessible(true);

    $existente = new McpTask([
        'task_id' => 'US-X-2',
        'title' => 'Título igual',
        'description' => 'Descrição ANTIGA no DB',
    ]);

    $cand = [
        'title' => 'Título igual',
        'description' => 'Descrição NOVA vinda do git/SPEC',
    ];

    Log::shouldReceive('channel')
        ->with('copiloto-ai')
        ->andReturnSelf();
    Log::shouldReceive('info')
        ->with(
            'TaskParser detectou divergência descritiva SPEC↔DB (SDD C4)',
            \Mockery::on(function ($ctx) {
                return $ctx['task_id'] === 'US-X-2'
                    && $ctx['campos'] === ['description']
                    && $ctx['canon'] === 'git'
                    && str_contains($ctx['divergencias']['description']['db'], 'ANTIGA')
                    && str_contains($ctx['divergencias']['description']['spec'], 'NOVA')
                    && ! isset($ctx['divergencias']['title']);
            })
        )
        ->once();

    expect($reflect->invoke($svc, $existente, $cand))->toBeTrue();
});

it('detectarDivergenciaDescritiva pega divergência só no title também', function () {
    $svc = new TaskParserService();
    $reflect = (new ReflectionClass(TaskParserService::class))
        ->getMethod('detectarDivergenciaDescritiva');
    $reflect->setAccessible(true);

    $existente = new McpTask([
        'task_id' => 'US-X-3',
        'title' => 'Título velho',
        'description' => 'desc igual',
    ]);
    $cand = [
        'title' => 'Título renomeado no SPEC',
        'description' => 'desc igual',
    ];

    Log::shouldReceive('channel')->with('copiloto-ai')->andReturnSelf();
    Log::shouldReceive('info')->once();

    expect($reflect->invoke($svc, $existente, $cand))->toBeTrue();
});

it('relatorio inclui chave descritivos_divergentes', function () {
    $svc = new TaskParserService();
    $reflect = (new ReflectionClass(TaskParserService::class))
        ->getMethod('relatorio');
    $reflect->setAccessible(true);

    // Assinatura relatorio(): processadas, ins, upd, can, fechadasAncora, descritivosDivergentes, modulos
    // (fechadas_por_ancora inserido entre canceladas e descritivos — ADR 0337).
    $rel = $reflect->invoke($svc, 5, 1, 2, 0, 0, 3, ['X' => 5]);

    expect($rel)->toHaveKey('descritivos_divergentes')
        ->and($rel['descritivos_divergentes'])->toBe(3)
        ->and($rel)->toHaveKey('atualizadas')
        ->and($rel['atualizadas'])->toBe(2)
        ->and($rel)->toHaveKey('fechadas_por_ancora');
});

// ─── Teste integração (skip local — roda em CT 100/MySQL) ───────────────

function tpDivMod(string $mod): string
{
    return '__TestSddC4_' . $mod;
}

function tpDivWriteSpec(string $module, string $body): string
{
    $dir = base_path("memory/requisitos/{$module}");
    if (! is_dir($dir)) {
        File::makeDirectory($dir, 0755, true);
    }
    $path = $dir . '/SPEC.md';
    file_put_contents($path, $body);
    return $path;
}

function tpDivCleanup(): void
{
    foreach (['DIVA'] as $m) {
        $dir = base_path('memory/requisitos/' . tpDivMod($m));
        if (is_dir($dir)) {
            File::deleteDirectory($dir);
        }
    }
}

it('integração: description divergente → contador ≥1 + estado vivo preservado', function () {
    afterEach(fn () => tpDivCleanup());

    $module = tpDivMod('DIVA');

    // 1) Cria a task a partir do SPEC.
    tpDivWriteSpec($module, <<<MD
    ### US-SDDC4DIVA-1 · Título inicial

    > owner: wagner · status: todo · priority: p2

    Descrição original vinda do SPEC.
    MD);

    $svc = new TaskParserService();
    $svc->syncAll($module);

    $task = McpTask::where('task_id', 'US-SDDC4DIVA-1')->first();
    expect($task)->not->toBeNull()
        ->and($task->description)->toContain('Descrição original');

    // 2) Simula estado vivo evoluindo no DB (tasks-update) + description
    //    editada no DB divergindo do que ainda está no SPEC.
    $task->update([
        'status' => 'doing',
        'owner' => 'felipe',
        'priority' => 'p0',
        'sprint' => 'CYCLE-08',
        'description' => 'Descrição EDITADA direto no DB (drift).',
    ]);

    // 3) SPEC muda a description (git canon) — deve detectar divergência.
    tpDivWriteSpec($module, <<<MD
    ### US-SDDC4DIVA-1 · Título inicial

    > owner: wagner · status: todo · priority: p2

    Descrição NOVA no SPEC depois de discussão.
    MD);

    $rel = $svc->syncAll($module);

    // Contador detectou o drift descritivo.
    expect($rel['descritivos_divergentes'])->toBeGreaterThanOrEqual(1);

    $task->refresh();

    // git venceu o update descritivo (não mudou quem é canon).
    expect($task->description)->toContain('Descrição NOVA no SPEC');

    // Estado vivo preservado (ADR 0144 — zero regressão).
    expect($task->status)->toBe('doing')
        ->and($task->owner)->toBe('felipe')
        ->and($task->priority)->toBe('p0')
        ->and($task->sprint)->toBe('CYCLE-08');
})->skip('requer MySQL — RefreshDatabase com SQLite quebra na migration legacy UltimatePOS ALTER TABLE MODIFY ENUM. Rodar em CT 100 (Tailscale) ou Laragon com MYSQL_TESTING. Cobertura unit acima trava o contrato do helper + contador na lane sqlite.');
