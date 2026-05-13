<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\Ai;
use Modules\Jana\Services\MemoriaAutonoma\WeeklyDigestService;

uses(Tests\TestCase::class);

/**
 * AUDITORIA-KNOWLEDGE-ARCHITECTURE-2026-05-13 §5 (G8 P2) — jana:weekly-digest command.
 *
 * Cobre:
 *  001. dry-run NÃO chama LLM nem persiste (smoke)
 *  002. Coleta métricas estruturadas (commits/adrs/handoffs counters)
 *  003. semana inválida retorna erro amigável
 *  004. execução normal grava arquivo + row em `mcp_weekly_digests`
 *  005. --force re-gera sobrescrevendo
 *  006. Primeira semana sem dados retorna métricas zeradas (edge case)
 *
 * Isolamento de prod:
 *  - PATH_OUTPUT desviado pra temp dir via env BASE_PATH_OVERRIDE NÃO disponível,
 *    então usamos limpeza posterior + filename específico de teste (semana futura).
 */
beforeEach(function () {
    Schema::dropIfExists('mcp_weekly_digests');
    Schema::create('mcp_weekly_digests', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->string('week', 8)->unique('uniq_weekly_digest_week');
        $t->date('range_start');
        $t->date('range_end');
        $t->longText('digest_markdown');
        $t->text('metrics')->nullable();
        $t->unsignedInteger('tokens_in')->default(0);
        $t->unsignedInteger('tokens_out')->default(0);
        $t->decimal('cost_brl', 10, 6)->default(0);
        $t->string('model', 50)->default('gpt-4o-mini');
        $t->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('mcp_weekly_digests');
    // Cleanup arquivos teste em memory/sessions/
    foreach (glob(base_path('memory/sessions/WEEKLY-DIGEST-9999-W*.md') ?: []) as $f) {
        @unlink($f);
    }
});

test('dry-run NÃO chama LLM nem grava arquivo nem row DB', function () {
    Ai::fakeAgent(\Laravel\Ai\AnonymousAgent::class, ['NUNCA CHAMADO']);

    $exitCode = Artisan::call('jana:weekly-digest', [
        '--week' => '9999-W01',
        '--dry-run' => true,
    ]);

    expect($exitCode)->toBe(0);

    $output = Artisan::output();
    expect($output)->toContain('dry-run')
        ->and($output)->toContain('MÉTRICAS COLETADAS')
        ->and($output)->toContain('CUSTO ESTIMADO');

    // NÃO criou row DB
    expect(DB::table('mcp_weekly_digests')->count())->toBe(0);

    // NÃO criou arquivo
    expect(file_exists(base_path('memory/sessions/WEEKLY-DIGEST-9999-W01.md')))->toBeFalse();
});

test('coleta métricas estruturadas (commits/adrs/handoffs/cycle_progress_pct)', function () {
    Ai::fakeAgent(\Laravel\Ai\AnonymousAgent::class, ['NUNCA CHAMADO']);

    $service = app(WeeklyDigestService::class);
    $resultado = $service->gerar('9999-W01', dryRun: true);

    expect($resultado['metrics'])->toHaveKeys([
        'commits',
        'prs_merged',
        'us_closed',
        'us_created',
        'adrs_new',
        'handoffs',
        'cycle_progress_pct',
    ]);

    // Tipos corretos (ints)
    foreach ($resultado['metrics'] as $key => $val) {
        expect($val)->toBeInt(message: "Métrica `{$key}` deve ser int");
    }
});

test('semana ISO inválida retorna erro amigável', function () {
    Ai::fakeAgent(\Laravel\Ai\AnonymousAgent::class, ['NUNCA CHAMADO']);

    $exitCode = Artisan::call('jana:weekly-digest', [
        '--week' => 'banana-invalida',
    ]);

    expect($exitCode)->toBe(1);

    $output = Artisan::output();
    expect($output)->toContain('Falhou')
        ->and($output)->toContain('inválida');
});

test('execução normal grava arquivo + row em mcp_weekly_digests', function () {
    Ai::fakeAgent(\Laravel\Ai\AnonymousAgent::class, [
        "## Marco da semana\nSemana de testes Pest.\n\n## Trabalho entregue\n- 3 PRs\n\n## Cycle progress\n50%\n\n## Decisões importantes\n- ADR fake\n\n## Próxima semana — sugestões priorizadas\n- Item 1",
    ]);

    $exitCode = Artisan::call('jana:weekly-digest', [
        '--week' => '9999-W02',
    ]);

    expect($exitCode)->toBe(0);

    // Arquivo gerado
    $path = base_path('memory/sessions/WEEKLY-DIGEST-9999-W02.md');
    expect(file_exists($path))->toBeTrue();

    $content = (string) @file_get_contents($path);
    expect($content)->toContain('Weekly Digest 9999-W02')
        ->and($content)->toContain('Marco da semana')
        ->and($content)->toContain('Trabalho entregue')
        ->and($content)->toContain('Cycle progress')
        ->and($content)->toContain('tipo: weekly-digest');

    // Row DB
    $row = DB::table('mcp_weekly_digests')->where('week', '9999-W02')->first();
    expect($row)->not->toBeNull();
    expect($row->digest_markdown)->toContain('Marco da semana');
    expect($row->model)->toBe('gpt-4o-mini');
});

test('--force re-gera mesmo se arquivo já existe', function () {
    Ai::fakeAgent(\Laravel\Ai\AnonymousAgent::class, [
        '## Marco da semana\nPrimeiro digest\n\n## Trabalho entregue\n—\n\n## Cycle progress\n—\n\n## Decisões importantes\n—\n\n## Próxima semana — sugestões priorizadas\n—',
        '## Marco da semana\nSegundo digest (force)\n\n## Trabalho entregue\n—\n\n## Cycle progress\n—\n\n## Decisões importantes\n—\n\n## Próxima semana — sugestões priorizadas\n—',
    ]);

    // 1ª execução: cria
    Artisan::call('jana:weekly-digest', ['--week' => '9999-W03']);
    $rowsAfterFirst = DB::table('mcp_weekly_digests')->count();
    expect($rowsAfterFirst)->toBe(1);

    // 2ª SEM --force: aborta
    $exitCode = Artisan::call('jana:weekly-digest', ['--week' => '9999-W03']);
    expect($exitCode)->toBe(1);
    expect(Artisan::output())->toContain('já existe');

    // 3ª COM --force: re-gera (upsert, ainda 1 row mas com novo conteúdo)
    $exitCode = Artisan::call('jana:weekly-digest', ['--week' => '9999-W03', '--force' => true]);
    expect($exitCode)->toBe(0);

    $row = DB::table('mcp_weekly_digests')->where('week', '9999-W03')->first();
    expect($row->digest_markdown)->toContain('Segundo digest (force)');

    // Unique constraint preservada (não duplicou row)
    expect(DB::table('mcp_weekly_digests')->count())->toBe(1);
});

test('edge case primeira semana sem dados retorna métricas zeradas mas digest gerado', function () {
    Ai::fakeAgent(\Laravel\Ai\AnonymousAgent::class, [
        "## Marco da semana\nSemana de manutenção — sem marco singular.\n\n## Trabalho entregue\n—\n\n## Cycle progress\n—\n\n## Decisões importantes\n—\n\n## Próxima semana — sugestões priorizadas\n—",
    ]);

    // Semana ISO bem no futuro (sem commits/handoffs reais nesse range)
    $exitCode = Artisan::call('jana:weekly-digest', ['--week' => '9999-W04']);

    expect($exitCode)->toBe(0);

    $row = DB::table('mcp_weekly_digests')->where('week', '9999-W04')->first();
    $metrics = json_decode($row->metrics, true);

    // Semana futura: zero dados de qualquer fonte
    expect($metrics['handoffs'])->toBe(0);
    expect($metrics['us_closed'])->toBe(0);
    expect($metrics['us_created'])->toBe(0);

    // Digest ainda gerado (LLM lida com "—")
    expect($row->digest_markdown)->toContain('Marco da semana');
});
