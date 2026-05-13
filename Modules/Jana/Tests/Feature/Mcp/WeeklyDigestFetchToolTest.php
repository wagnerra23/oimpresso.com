<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Mcp\Request as McpRequest;
use Modules\Jana\Mcp\Tools\WeeklyDigestFetchTool;

uses(Tests\TestCase::class);

/**
 * AUDITORIA-KNOWLEDGE-ARCHITECTURE-2026-05-13 §5 (G8 P2) — weekly-digest-fetch tool.
 *
 * Cobre:
 *  001. Sem digest disponível retorna mensagem amigável (smoke)
 *  002. Retorna markdown da semana específica (week=YYYY-Www)
 *  003. Default (sem week) retorna mais recente
 *  004. metrics_only=true retorna JSON métricas
 *  005. week com formato inválido cai no fallback file (sem crash)
 *  006. Fallback file lê memory/sessions/WEEKLY-DIGEST-*.md quando DB ausente
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
    foreach (glob(base_path('memory/sessions/WEEKLY-DIGEST-9999-W*.md') ?: []) as $f) {
        @unlink($f);
    }
});

function callWeeklyDigestTool(array $params = []): \Laravel\Mcp\Response
{
    $tool = new WeeklyDigestFetchTool;
    $request = new McpRequest($params);

    return $tool->handle($request);
}

function seedDigest(string $week, string $markdown, array $metrics = []): void
{
    DB::table('mcp_weekly_digests')->insert([
        'week' => $week,
        'range_start' => '2999-01-01',
        'range_end' => '2999-01-07',
        'digest_markdown' => $markdown,
        'metrics' => json_encode($metrics),
        'tokens_in' => 1000,
        'tokens_out' => 500,
        'cost_brl' => 0.005,
        'model' => 'gpt-4o-mini',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('sem digest disponível retorna mensagem amigável', function () {
    $response = callWeeklyDigestTool();
    $output = (string) $response->content();

    expect($output)->toContain('Weekly digest')
        ->and($output)->toContain('Nenhum digest');
});

test('retorna markdown da semana específica via week=YYYY-Www', function () {
    seedDigest('9999-W10', "# Weekly Digest 9999-W10\n\n## Marco da semana\nTeste seed.\n\n## Trabalho entregue\n- PR #123");

    $response = callWeeklyDigestTool(['week' => '9999-W10']);
    $output = (string) $response->content();

    expect($output)->toContain('Weekly Digest 9999-W10')
        ->and($output)->toContain('Teste seed')
        ->and($output)->toContain('PR #123');
});

test('default sem week retorna o digest mais recente', function () {
    seedDigest('9999-W11', '# Antigo\n\n## Marco da semana\nAntigo');
    DB::table('mcp_weekly_digests')->where('week', '9999-W11')->update(['range_end' => '2999-01-07']);

    seedDigest('9999-W12', '# Recente\n\n## Marco da semana\nRecente');
    DB::table('mcp_weekly_digests')->where('week', '9999-W12')->update(['range_end' => '2999-01-14']);

    $response = callWeeklyDigestTool();
    $output = (string) $response->content();

    expect($output)->toContain('Recente')
        ->and($output)->not->toContain('Antigo');
});

test('metrics_only=true retorna JSON com métricas', function () {
    seedDigest(
        '9999-W13',
        '# Digest com metrics',
        ['commits' => 42, 'prs_merged' => 7, 'us_closed' => 3, 'cycle_progress_pct' => 65]
    );

    $response = callWeeklyDigestTool(['week' => '9999-W13', 'metrics_only' => true]);
    $output = (string) $response->content();

    expect($output)->toContain('métricas')
        ->and($output)->toContain('"commits": 42')
        ->and($output)->toContain('"prs_merged": 7')
        ->and($output)->toContain('"cycle_progress_pct": 65')
        ->and($output)->not->toContain('Digest com metrics'); // markdown NÃO retornado
});

test('week com formato inválido retorna mensagem clara (não crash)', function () {
    seedDigest('9999-W14', '# Digest válido');

    $response = callWeeklyDigestTool(['week' => 'banana-invalida']);
    $output = (string) $response->content();

    // Cai no fallback file (DB rejeitou regex)
    expect($output)->toContain('Weekly digest');
    // Não deve retornar o digest válido (week errado)
    expect($output)->not->toContain('Digest válido');
});

test('fallback file lê memory/sessions/WEEKLY-DIGEST-*.md quando DB ausente', function () {
    Schema::dropIfExists('mcp_weekly_digests'); // simula DB sem tabela

    $path = base_path('memory/sessions/WEEKLY-DIGEST-9999-W20.md');
    file_put_contents(
        $path,
        "---\ntipo: weekly-digest\nsemana: 9999-W20\n---\n\n# Weekly Digest 9999-W20\n\n## Marco da semana\nFallback file ok"
    );

    $response = callWeeklyDigestTool(['week' => '9999-W20']);
    $output = (string) $response->content();

    expect($output)->toContain('Weekly Digest 9999-W20')
        ->and($output)->toContain('Fallback file ok');
});
