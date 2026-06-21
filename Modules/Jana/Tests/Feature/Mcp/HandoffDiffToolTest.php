<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Laravel\Mcp\Request as McpRequest;
use Modules\Jana\Mcp\Tools\HandoffDiffTool;

uses(Tests\TestCase::class);

/**
 * Auditoria 2026-05-13 §5 (P0) — guarda da tool `handoff-diff`.
 *
 * Cobre:
 *  001. since=1d com mcp_tasks fechadas hoje retorna no diff
 *  002. since inválido retorna erro amigável
 *  003. Categorias filtram seções (categorias=[us] esconde PRs)
 *  004. Format json retorna estrutura JSON parseable
 *  005. Sem eventos retorna mensagem "nenhum"/"_nenhuma_"
 *  006. Cache hit NÃO duplica row
 *  007. since=last sem handoffs cai pra default 1d
 *
 * Mock: Process::fake() pra `gh` e `git`. Stubs em mcp_tasks/mcp_cycles via DB direto.
 */
beforeEach(function () {
    // era-sqlite: cria schema mcp_*/jana_* manual (sqlite-friendly). No MySQL persistente
    // do nightly isso corrompe os testes irmãos (lever do floor SDD). Cobertura real é
    // na lane sqlite (per-PR); pula no MySQL.
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('era-sqlite: corruptor de schema compartilhado no MySQL — sqlite-only no burn-down do floor SDD.');
    }

    // Tabela cache (replica migration em SQLite)
    Schema::dropIfExists('mcp_handoff_diffs');
    Schema::create('mcp_handoff_diffs', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->string('since', 50);
        $t->string('events_hash', 32);
        $t->longText('output_md')->nullable();
        $t->longText('output_json')->nullable();
        $t->unsignedInteger('tokens')->default(0);
        $t->decimal('cost_brl', 10, 6)->default(0);
        $t->timestamps();
        $t->unique(['since', 'events_hash'], 'uniq_handoff_diff_since_hash');
    });

    // Tabela mcp_tasks stub (mínima pro teste)
    if (! Schema::hasTable('mcp_tasks')) {
        Schema::create('mcp_tasks', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('task_id', 50);
            $t->string('title')->nullable();
            $t->string('status', 30)->default('todo');
            $t->string('owner', 50)->nullable();
            $t->timestamps();
        });
    }

    // Tabela mcp_cycles stub
    if (! Schema::hasTable('mcp_cycles')) {
        Schema::create('mcp_cycles', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('key', 50);
            $t->string('status', 30)->default('active');
            $t->text('goal')->nullable();
            $t->timestamps();
        });
    }

    // Temp dir handoffs vazio (testes que precisam de handoff criam manualmente)
    $tempDir = sys_get_temp_dir() . '/handoff_diff_test_' . uniqid();
    File::makeDirectory($tempDir, 0o755, recursive: true);
    config(['jana.handoffs_dir' => $tempDir]);
    test()->tempDir = $tempDir;
});

afterEach(function () {
    if (config('database.default') !== 'sqlite') {
        return;
    }

    if (isset(test()->tempDir) && File::isDirectory(test()->tempDir)) {
        File::deleteDirectory(test()->tempDir);
    }
    config(['jana.handoffs_dir' => null]);
    Schema::dropIfExists('mcp_handoff_diffs');
    DB::table('mcp_tasks')->truncate();
    DB::table('mcp_cycles')->truncate();
});

function callDiffTool(array $params = []): \Laravel\Mcp\Response
{
    // Process::fake() faz gh/git retornar vazio — testes focam em DB+filtros
    Process::fake();

    $tool = new HandoffDiffTool;
    $request = new McpRequest($params);

    return $tool->handle($request);
}

test('HandoffDiffTool since=1d capta US fechada hoje em mcp_tasks', function () {
    DB::table('mcp_tasks')->insert([
        'task_id' => 'US-COPI-999',
        'title' => 'Tool handoff-diff implementada',
        'status' => 'done',
        'owner' => 'claude',
        'created_at' => now()->subHours(2),
        'updated_at' => now()->subMinutes(30),
    ]);

    $response = callDiffTool(['since' => '1d']);
    $output = (string) $response->content();

    expect($output)->toContain('Diff desde 1d')
        ->and($output)->toContain('US fechadas (1)')
        ->and($output)->toContain('US-COPI-999')
        ->and($output)->toContain('Tool handoff-diff implementada');
});

test('HandoffDiffTool since inválido retorna erro amigável', function () {
    $response = callDiffTool(['since' => 'banana-z9z9']);
    $output = (string) $response->content();

    expect($output)->toContain('Erro')
        ->and($output)->toContain('since');
});

test('HandoffDiffTool categorias filtra seções (categorias=[us] esconde PRs)', function () {
    DB::table('mcp_tasks')->insert([
        'task_id' => 'US-FIN-001',
        'title' => 'feature financeiro',
        'status' => 'done',
        'updated_at' => now()->subHours(1),
        'created_at' => now()->subHours(2),
    ]);

    $response = callDiffTool([
        'since' => '1d',
        'categorias' => ['us'],
    ]);
    $output = (string) $response->content();

    // Categoria us incluída — header com count
    expect($output)->toContain('US fechadas (1)')
        ->and($output)->toContain('US-FIN-001');

    // PRs/ADRs/Cycles/Files com count 0 (categorias filtradas, eventos vazios)
    expect($output)->toContain('PRs mergeados (0)')
        ->and($output)->toContain('ADRs novas (0)');
});

test('HandoffDiffTool format json retorna estrutura parseable', function () {
    DB::table('mcp_tasks')->insert([
        'task_id' => 'US-JANA-100',
        'title' => 'feature json test',
        'status' => 'done',
        'updated_at' => now()->subHours(1),
        'created_at' => now()->subHours(2),
    ]);

    $response = callDiffTool(['since' => '1d', 'format' => 'json']);
    $output = (string) $response->content();

    $payload = json_decode($output, true);
    expect($payload)->toBeArray()
        ->and($payload['since'])->toBe('1d')
        ->and($payload['counts']['us'])->toBe(1)
        ->and($payload['eventos']['us'][0]['task_id'])->toBe('US-JANA-100');
});

test('HandoffDiffTool sem eventos retorna mensagens "nenhum"', function () {
    // Nenhuma US fechada, nenhum cycle, gh/git fake retorna vazio

    $response = callDiffTool(['since' => '1d']);
    $output = (string) $response->content();

    expect($output)->toContain('Diff desde 1d')
        ->and($output)->toContain('PRs mergeados (0)')
        ->and($output)->toContain('US fechadas (0)')
        ->and($output)->toContain('ADRs novas (0)')
        ->and($output)->toContain('_nenhum');
});

test('HandoffDiffTool cache hit NÃO duplica row em mcp_handoff_diffs', function () {
    DB::table('mcp_tasks')->insert([
        'task_id' => 'US-CACHE-001',
        'title' => 'cache test',
        'status' => 'done',
        'updated_at' => now()->subHour(),
        'created_at' => now()->subHours(2),
    ]);

    // 1ª chamada popula cache
    callDiffTool(['since' => '1d']);
    $rowsAfterFirst = DB::table('mcp_handoff_diffs')->count();
    expect($rowsAfterFirst)->toBe(1);

    // 2ª chamada idêntica — mesmo events_hash → cache hit, sem duplicar
    callDiffTool(['since' => '1d']);
    $rowsAfterSecond = DB::table('mcp_handoff_diffs')->count();
    expect($rowsAfterSecond)->toBe(1);
});

test('HandoffDiffTool since=last sem handoffs no diretório cai pra default 1d', function () {
    // tempDir está vazio (sem handoffs) — fallback CarbonImmutable::now()->subDay()
    $response = callDiffTool(['since' => 'last']);
    $output = (string) $response->content();

    expect($output)->toContain('Diff desde last');
});

test('HandoffDiffTool since=last com handoff de 3d atrás usa essa data', function () {
    $h3d = now()->subDays(3)->format('Y-m-d');
    File::put(test()->tempDir . "/{$h3d}-0900-fake-handoff.md", '# handoff fake');

    // US de 2d atrás (dentro da janela 3d) deveria aparecer
    DB::table('mcp_tasks')->insert([
        'task_id' => 'US-LAST-001',
        'title' => 'capted by last',
        'status' => 'done',
        'updated_at' => now()->subDays(2),
        'created_at' => now()->subDays(3),
    ]);

    $response = callDiffTool(['since' => 'last']);
    $output = (string) $response->content();

    expect($output)->toContain('US-LAST-001');
});
