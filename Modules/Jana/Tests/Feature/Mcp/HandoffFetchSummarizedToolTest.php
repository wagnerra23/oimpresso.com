<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\Ai;
use Laravel\Mcp\Request as McpRequest;
use Modules\Jana\Mcp\Tools\HandoffFetchSummarizedTool;

uses(Tests\TestCase::class);

/**
 * Auditoria 2026-05-13 §5 (P0) — guarda da tool `handoff-fetch-summarized`.
 *
 * Cobre:
 *  001. Resume + retorna markdown estruturado (smoke)
 *  002. Filtra por since (1d capta hoje, exclui 2d/10d)
 *  003. Respeita limit (limit:2 não retorna 3)
 *  004. Cache hit NÃO duplica row + reporta "cache hit"
 *  005. Filtra por módulo (case-insensitive grep no conteúdo)
 *  006. since inválido retorna erro amigável
 *  007. Sem handoffs na janela retorna mensagem clara
 *  008. format compact persiste só campo compact (não detailed)
 *
 * Mock LLM via Ai::fakeAgent(AnonymousAgent::class, [...]).
 * Isolamento de prod: usa temp dir via config('jana.handoffs_dir').
 */
beforeEach(function () {
    // Cria tabela cache (replica migration em SQLite)
    Schema::dropIfExists('mcp_handoff_summaries');
    Schema::create('mcp_handoff_summaries', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->string('filename', 200);
        $t->string('content_hash', 32);
        $t->text('summary_compact')->nullable();
        $t->text('summary_detailed')->nullable();
        $t->unsignedInteger('tokens_in')->default(0);
        $t->unsignedInteger('tokens_out')->default(0);
        $t->decimal('cost_brl', 10, 6)->default(0);
        $t->string('model', 50)->default('gpt-4o-mini');
        $t->timestamps();
        $t->unique(['filename', 'content_hash'], 'uniq_handoff_filename_hash');
    });

    // Temp dir isolado — NÃO toca em memory/handoffs/ real
    $tempDir = sys_get_temp_dir() . '/handoff_test_' . uniqid();
    File::makeDirectory($tempDir, 0o755, recursive: true);
    config(['jana.handoffs_dir' => $tempDir]);

    // 3 handoffs fake: hoje, 2d atrás, 10d atrás
    $hoje = now()->format('Y-m-d');
    $doisDias = now()->subDays(2)->format('Y-m-d');
    $dezDias = now()->subDays(10)->format('Y-m-d');

    File::put($tempDir . "/{$hoje}-0900-recente-test.md",
        "# Handoff recente teste\n\nPR #999 mergeado. Status: encerrado. Módulo Jana ativo."
    );
    File::put($tempDir . "/{$doisDias}-1000-intermediario-test.md",
        "# Handoff 2d\n\nBug fix Repair. Status: continuation. Módulo Repair tocado."
    );
    File::put($tempDir . "/{$dezDias}-1100-antigo-test.md",
        "# Handoff antigo\n\nFeature Whatsapp. Status: encerrado. Módulo Whatsapp."
    );

    test()->tempDir = $tempDir;
});

afterEach(function () {
    if (isset(test()->tempDir) && File::isDirectory(test()->tempDir)) {
        File::deleteDirectory(test()->tempDir);
    }
    config(['jana.handoffs_dir' => null]);
    Schema::dropIfExists('mcp_handoff_summaries');
});

function callHandoffTool(array $params = []): \Laravel\Mcp\Response
{
    $tool = new HandoffFetchSummarizedTool;
    $request = new McpRequest($params);

    return $tool->handle($request);
}

test('HandoffFetchSummarizedTool resume handoffs e retorna markdown estruturado', function () {
    Ai::fakeAgent(\Laravel\Ai\AnonymousAgent::class, [
        "- Bullet teste 1\n- Bullet teste 2\n- Status: encerrado\n- Próximo passo: ler proxima",
    ]);

    $response = callHandoffTool(['since' => '7d', 'limit' => 3]);
    $output = (string) $response->content();

    expect($output)->toContain('Handoffs resumidos')
        ->and($output)->toContain('Bullet teste')
        ->and($output)->toContain('Status: encerrado');
});

test('HandoffFetchSummarizedTool filtra por since (1d capta só hoje, não 2d atrás)', function () {
    Ai::fakeAgent(\Laravel\Ai\AnonymousAgent::class, [
        "- Resumo fake 1",
        "- Resumo fake 2",
        "- Resumo fake 3",
    ]);

    $response = callHandoffTool(['since' => '1d', 'limit' => 10]);
    $output = (string) $response->content();

    // since=1d: só hoje (recente-test.md)
    expect($output)->toContain('recente-test')
        ->and($output)->not->toContain('intermediario-test')
        ->and($output)->not->toContain('antigo-test');
});

test('HandoffFetchSummarizedTool respeita limit:2 (não retorna 3 handoffs)', function () {
    Ai::fakeAgent(\Laravel\Ai\AnonymousAgent::class, [
        "- Resumo a",
        "- Resumo b",
        "- Resumo c",
    ]);

    $response = callHandoffTool(['since' => '30d', 'limit' => 2]);
    $output = (string) $response->content();

    // since=30d capta os 3, mas limit=2 corta o mais antigo (DESC ordering)
    expect($output)->toContain('recente-test')      // hoje (mais recente)
        ->and($output)->toContain('intermediario-test') // 2d
        ->and($output)->not->toContain('antigo-test');  // 10d (cortado pelo limit)
});

test('HandoffFetchSummarizedTool cache hit NÃO chama LLM novamente', function () {
    Ai::fakeAgent(\Laravel\Ai\AnonymousAgent::class, [
        "- Resumo cacheado",
    ]);

    // 1ª chamada: popula cache
    callHandoffTool(['since' => '1d', 'limit' => 1]);

    $rowsAfterFirst = DB::table('mcp_handoff_summaries')->count();
    expect($rowsAfterFirst)->toBe(1);

    // 2ª chamada idêntica: cache hit (mesmo content_hash MD5)
    $response2 = callHandoffTool(['since' => '1d', 'limit' => 1]);
    $output2 = (string) $response2->content();

    // Output ainda contém resumo (vindo do cache)
    expect($output2)->toContain('Resumo cacheado');

    // Cache não duplicou (unique constraint)
    $rowsAfterSecond = DB::table('mcp_handoff_summaries')->count();
    expect($rowsAfterSecond)->toBe($rowsAfterFirst);

    // Stats reportam cache hit
    expect($output2)->toContain('cache hit');
});

test('HandoffFetchSummarizedTool filtra por módulo (case-insensitive)', function () {
    Ai::fakeAgent(\Laravel\Ai\AnonymousAgent::class, [
        "- Resumo filtrado",
    ]);

    // Filtra módulo "whatsapp" (case-insensitive) — só handoff antigo menciona
    $response = callHandoffTool([
        'since' => '30d',
        'limit' => 10,
        'module' => 'whatsapp',
    ]);
    $output = (string) $response->content();

    expect($output)->toContain('antigo-test')              // menciona Whatsapp
        ->and($output)->not->toContain('recente-test')     // menciona Jana
        ->and($output)->not->toContain('intermediario-test'); // menciona Repair
});

test('HandoffFetchSummarizedTool retorna mensagem amigável sem handoffs na janela', function () {
    Ai::fakeAgent(\Laravel\Ai\AnonymousAgent::class, ["- nunca chamado"]);

    $response = callHandoffTool([
        'since' => '30d',
        'limit' => 3,
        'module' => 'modulo-inexistente-z1z2z3',
    ]);
    $output = (string) $response->content();

    expect($output)->toContain('Nenhum handoff encontrado')
        ->and($output)->toContain('modulo-inexistente-z1z2z3');
});

test('HandoffFetchSummarizedTool format compact persiste só campo compact', function () {
    Ai::fakeAgent(\Laravel\Ai\AnonymousAgent::class, [
        "- Compact ~150 tok",
    ]);

    callHandoffTool(['since' => '1d', 'limit' => 1, 'format' => 'compact']);

    $row = DB::table('mcp_handoff_summaries')->first();
    expect($row->summary_compact)->not->toBeNull()
        ->and($row->summary_detailed)->toBeNull();
});

test('HandoffFetchSummarizedTool since inválido retorna erro amigável', function () {
    $response = callHandoffTool(['since' => 'banana-invalida']);
    $output = (string) $response->content();

    expect($output)->toContain('Erro')
        ->and($output)->toContain('since');
});
