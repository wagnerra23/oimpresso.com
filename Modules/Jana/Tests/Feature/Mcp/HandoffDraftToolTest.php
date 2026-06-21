<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\Ai;
use Laravel\Mcp\Request as McpRequest;
use Modules\Jana\Mcp\Tools\HandoffDraftTool;

uses(Tests\TestCase::class);

/**
 * Onda 5 H1 (ONDA-5-DOSSIER-2026-05-13 §4 P0) — guarda da tool `handoff-draft`.
 *
 * Cobre:
 *  001. Cria arquivo memory/handoffs/YYYY-MM-DD-HHMM-<slug>.md com conteúdo LLM
 *  002. Reusa HandoffDiffTool internamente (eventos parseados via JSON)
 *  003. force_llm=false + zero eventos → mensagem "nenhum evento" SEM chamar LLM
 *  004. Mock LLM via Ai::fakeAgent retorna skeleton determinístico (sem custo real)
 *  005. Slug auto-gerado embute counts (5prs-3us-...)
 *  006. Slug fornecido pelo usuário é sanitizado (kebab-case)
 *  007. cost_brl trackado em mcp_handoff_drafts quando tabela existe
 *  008. last-handoff sem handoff anterior cai pra default (best-effort, não bloqueia)
 */
beforeEach(function () {
    // era-sqlite: cria schema mcp_*/jana_* manual (sqlite-friendly). No MySQL persistente
    // do nightly isso corrompe os testes irmãos (lever do floor SDD). Cobertura real é
    // na lane sqlite (per-PR); pula no MySQL.
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('era-sqlite: corruptor de schema compartilhado no MySQL — sqlite-only no burn-down do floor SDD.');
    }

    // Tabela cache HandoffDiffTool (composição interna)
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

    // Tabela tracking custo do draft
    Schema::dropIfExists('mcp_handoff_drafts');
    Schema::create('mcp_handoff_drafts', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->string('filename', 200);
        $t->decimal('cost_brl', 10, 6)->default(0);
        $t->string('model', 50)->default('gpt-4o-mini');
        $t->timestamps();
    });

    // Tabelas estado MCP (stub mínimo)
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

    if (! Schema::hasTable('mcp_cycles')) {
        Schema::create('mcp_cycles', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('key', 50);
            $t->string('status', 30)->default('active');
            $t->text('goal')->nullable();
            $t->date('end_date')->nullable();
            $t->timestamps();
        });
    }

    // Temp dir handoffs isolado (NÃO toca em memory/handoffs/ real)
    $tempDir = sys_get_temp_dir() . '/handoff_draft_test_' . uniqid();
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
    Schema::dropIfExists('mcp_handoff_drafts');
    DB::table('mcp_tasks')->truncate();
    DB::table('mcp_cycles')->truncate();
});

function callDraftTool(array $params = []): \Laravel\Mcp\Response
{
    // Process::fake() pra HandoffDiffTool não chamar gh/git real
    Process::fake();

    $tool = new HandoffDraftTool;
    $request = new McpRequest($params);

    return $tool->handle($request);
}

test('HandoffDraftTool cria arquivo memory/handoffs/<filename>.md com conteúdo LLM', function () {
    // Stub: 1 US fechada hoje → diff tem eventos
    DB::table('mcp_tasks')->insert([
        'task_id' => 'US-DRAFT-001',
        'title' => 'Teste handoff-draft H1',
        'status' => 'done',
        'owner' => 'claude',
        'created_at' => now()->subHours(2),
        'updated_at' => now()->subMinutes(30),
    ]);

    Ai::fakeAgent(\Laravel\Ai\AnonymousAgent::class, [
        "# 2026-05-13 14:00 BRT — sessao-teste\n\n> Tipo: handoff (skeleton automático — Wagner edita)\n\n## TL;DR\nSessão teste H1 mock.\n\n## 0 PRs mergeados\n_nenhum_\n\n## 1 US movidas\n- US-DRAFT-001 Teste handoff-draft H1 [claude]\n\n## Próximo passo (sugestão LLM)\nValidar tool em prod.\n",
    ]);

    $response = callDraftTool(['since' => '1d', 'slug' => 'sessao-teste']);
    $output = (string) $response->content();

    // Output reporta sucesso + conteúdo
    expect($output)->toContain('salvo em `memory/handoffs/')
        ->and($output)->toContain('sessao-teste')
        ->and($output)->toContain('Custo: R$')
        ->and($output)->toContain('1 US')
        ->and($output)->toContain('Wagner edita antes de git add');

    // Arquivo realmente foi criado em tempDir
    $files = File::files(test()->tempDir);
    expect($files)->toHaveCount(1);
    $first = $files[0];
    expect($first->getFilename())->toMatch('/^\d{4}-\d{2}-\d{2}-\d{4}-sessao-teste\.md$/');

    // Conteúdo do arquivo é o que LLM retornou
    $conteudo = File::get($first->getPathname());
    expect($conteudo)->toContain('US-DRAFT-001')
        ->and($conteudo)->toContain('Sessão teste H1 mock');
});

test('HandoffDraftTool reusa HandoffDiffTool internamente (eventos parseados)', function () {
    // 2 US fechadas → diff conta 2
    DB::table('mcp_tasks')->insert([
        [
            'task_id' => 'US-DIFF-001',
            'title' => 'primeira',
            'status' => 'done',
            'updated_at' => now()->subHours(1),
            'created_at' => now()->subHours(2),
        ],
        [
            'task_id' => 'US-DIFF-002',
            'title' => 'segunda',
            'status' => 'done',
            'updated_at' => now()->subHours(1),
            'created_at' => now()->subHours(2),
        ],
    ]);

    Ai::fakeAgent(\Laravel\Ai\AnonymousAgent::class, [
        "# Handoff mock\n\nReusa diff tool ok.",
    ]);

    $response = callDraftTool(['since' => '1d', 'slug' => 'reuse-diff']);
    $output = (string) $response->content();

    // Counts incluídos no metadata
    expect($output)->toContain('2 US');
});

test('HandoffDraftTool sem eventos + force_llm=false retorna mensagem (sem chamar LLM)', function () {
    // Nenhuma US, nenhum cycle, Process::fake → diff vazio

    // Mock NÃO deve ser chamado — passamos array vazio só pra garantir que se for
    // chamado quebra (Ai::fakeAgent com [] em alguns setups gera erro)
    Ai::fakeAgent(\Laravel\Ai\AnonymousAgent::class, [
        "NUNCA DEVERIA SER CHAMADO",
    ]);

    $response = callDraftTool(['since' => '1d']);
    $output = (string) $response->content();

    expect($output)->toContain('Nenhum evento')
        ->and($output)->toContain('force_llm=true')
        ->and($output)->not->toContain('NUNCA DEVERIA SER CHAMADO');

    // Nenhum arquivo criado
    $files = File::files(test()->tempDir);
    expect($files)->toHaveCount(0);
});

test('HandoffDraftTool mock LLM retorna skeleton determinístico (sem custo real)', function () {
    DB::table('mcp_tasks')->insert([
        'task_id' => 'US-MOCK-001',
        'title' => 'mock test',
        'status' => 'done',
        'updated_at' => now()->subHour(),
        'created_at' => now()->subHours(2),
    ]);

    $skeletonEsperado = "# Handoff mock determinístico\n\nConteúdo fixo.";
    Ai::fakeAgent(\Laravel\Ai\AnonymousAgent::class, [$skeletonEsperado]);

    $response = callDraftTool(['since' => '1d', 'slug' => 'mock-test']);
    $output = (string) $response->content();

    expect($output)->toContain('Conteúdo fixo');

    $files = File::files(test()->tempDir);
    expect($files)->toHaveCount(1);
    $conteudo = File::get($files[0]->getPathname());
    // Tool aplica trim() na response do LLM (boundary control) — esperado match exato
    expect($conteudo)->toBe($skeletonEsperado);
});

test('HandoffDraftTool slug auto-gerado embute counts (1us-...)', function () {
    DB::table('mcp_tasks')->insert([
        'task_id' => 'US-AUTO-001',
        'title' => 'auto slug',
        'status' => 'done',
        'updated_at' => now()->subHour(),
        'created_at' => now()->subHours(2),
    ]);

    Ai::fakeAgent(\Laravel\Ai\AnonymousAgent::class, ['# Mock']);

    $response = callDraftTool(['since' => '1d']); // sem slug → auto-gerado
    $output = (string) $response->content();

    // Slug auto deve conter "1us" (1 US fechada)
    expect($output)->toMatch('/memory\/handoffs\/\d{4}-\d{2}-\d{2}-\d{4}-1us/');

    $files = File::files(test()->tempDir);
    expect($files)->toHaveCount(1);
    expect($files[0]->getFilename())->toContain('1us');
});

test('HandoffDraftTool slug do usuário é sanitizado pra kebab-case', function () {
    DB::table('mcp_tasks')->insert([
        'task_id' => 'US-SAN-001',
        'title' => 'sanitize',
        'status' => 'done',
        'updated_at' => now()->subHour(),
        'created_at' => now()->subHours(2),
    ]);

    Ai::fakeAgent(\Laravel\Ai\AnonymousAgent::class, ['# Mock sanitize']);

    $response = callDraftTool(['since' => '1d', 'slug' => 'MyFix_Test With  Spaces!!! 2026']);
    $output = (string) $response->content();

    // Slug sanitizado: lowercase, símbolos/espaços/múltiplos → hifens únicos
    expect($output)->toContain('myfix-test-with-spaces-2026');

    $files = File::files(test()->tempDir);
    expect($files)->toHaveCount(1);
    expect($files[0]->getFilename())->toContain('myfix-test-with-spaces-2026');
});

test('HandoffDraftTool tracka custo em mcp_handoff_drafts', function () {
    DB::table('mcp_tasks')->insert([
        'task_id' => 'US-COST-001',
        'title' => 'cost track',
        'status' => 'done',
        'updated_at' => now()->subHour(),
        'created_at' => now()->subHours(2),
    ]);

    Ai::fakeAgent(\Laravel\Ai\AnonymousAgent::class, ['# Mock cost tracking']);

    callDraftTool(['since' => '1d', 'slug' => 'cost-test']);

    $row = DB::table('mcp_handoff_drafts')->first();
    expect($row)->not->toBeNull();
    expect($row->model)->toBe('gpt-4o-mini');
    // Custo positivo (mesmo mock — calculado por proxy mb_strlen)
    expect((float) $row->cost_brl)->toBeGreaterThanOrEqual(0.0);
    expect($row->filename)->toContain('cost-test');
});

test('HandoffDraftTool com last-handoff sem handoff anterior funciona (best-effort)', function () {
    // tempDir vazio (sem handoff prévio) — last-handoff cai pra default 1d
    DB::table('mcp_tasks')->insert([
        'task_id' => 'US-LAST-001',
        'title' => 'last fallback',
        'status' => 'done',
        'updated_at' => now()->subHour(),
        'created_at' => now()->subHours(2),
    ]);

    Ai::fakeAgent(\Laravel\Ai\AnonymousAgent::class, ['# Mock last fallback']);

    $response = callDraftTool(['since' => 'last-handoff', 'slug' => 'last-fb']);
    $output = (string) $response->content();

    // Não deve quebrar — gera draft normalmente
    expect($output)->toContain('salvo em `memory/handoffs/')
        ->and($output)->toContain('last-fb');
});
