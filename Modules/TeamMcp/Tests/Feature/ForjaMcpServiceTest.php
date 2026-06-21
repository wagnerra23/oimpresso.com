<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Modules\TeamMcp\Entities\CoworkHandoff;
use Modules\TeamMcp\Entities\McpIngestHeartbeat;
use Modules\TeamMcp\Services\Forja\ForjaMcpService;

uses(Tests\TestCase::class);

/**
 * ForjaMcpService — projeção da aba MCP do cockpit Forja (Fase 1 · ADR 0283).
 *
 * GUARD da SURFACE dos handoffs (PR-A): só leitura/projeção de `cowork_handoffs`
 * + heartbeat do ingest, sem mutação. Prova o contrato que o front consome:
 *   - exclui 'superseded' e pega só a MAIOR version por slug;
 *   - deriva 'stale' de pending velho (> 3d) na LEITURA (cron-independente);
 *   - deriva o gate do `gate_status` com a MESMA regra verde do handoff-ack
 *     (conformance && critique_score>=80 && a11y) — nunca pinta verde sem ler;
 *   - Gap 2 (ADR 0283): cruza o ack VERDE com os required checks REAIS do PR via
 *     PrChecksResolver (GitHub API mockada) — divergência vira 'conflito'; só o verde
 *     é cruzado; degrada pro ack sem token/rede/branch-protection legível;
 *   - resume o body_md (1ª linha), conta arquivos, marca sig;
 *   - heartbeat distingue "transporte sem sinal" (mudo > 60min) de "ok".
 *
 * Harness sintético (espelha HandoffToolsTest + IngestHeartbeatTest): tabelas
 * criadas/destruídas por teste, sqlite-friendly, sem HTTP/auth. Helpers com nome
 * ÚNICO pra não colidir com HandoffToolsTest no mesmo processo Pest.
 */

/** Tabela sintética cowork_handoffs (espelha a migration). */
function mkForjaHandoffTable(): void
{
    if (Schema::hasTable('cowork_handoffs')) {
        Schema::drop('cowork_handoffs');
    }
    Schema::create('cowork_handoffs', function ($t) {
        $t->bigIncrements('id');
        $t->string('slug', 120);
        $t->unsignedInteger('version')->default(1);
        $t->string('tela', 160)->default('');
        $t->string('status', 16)->default('pending');
        $t->string('audited_against', 40)->nullable();
        $t->longText('body_md');
        $t->json('files_json');
        $t->char('source_hash', 64);
        $t->char('sig', 64);
        $t->string('created_by', 40)->default('CC');
        $t->timestamp('created_at')->nullable();
        $t->timestamp('applied_at')->nullable();
        $t->string('applied_by', 60)->nullable();
        $t->text('pr_url')->nullable();
        $t->json('gate_status')->nullable();
        $t->unique(['slug', 'version']);
        $t->index('status');
    });
}

/** Tabela sintética mcp_ingest_heartbeat (compatível com a entity as-coded). */
function mkForjaHeartbeatTable(): void
{
    if (Schema::hasTable('mcp_ingest_heartbeat')) {
        Schema::drop('mcp_ingest_heartbeat');
    }
    Schema::create('mcp_ingest_heartbeat', function ($t) {
        $t->bigIncrements('id');
        $t->string('host', 120);
        $t->timestamp('last_ingest_at')->nullable();
        $t->string('last_session_uuid', 64)->nullable();
        $t->unsignedInteger('msgs_acc')->default(0);
        $t->timestamps();
    });
}

/** Cria um handoff (default: pending, assinado, recém-criado). */
function mkForjaHandoff(string $slug, array $over = []): CoworkHandoff
{
    return CoworkHandoff::create(array_merge([
        'slug'            => $slug,
        'version'         => 1,
        'tela'            => 'Atendimento/CaixaUnificada',
        'status'          => 'pending',
        'audited_against' => 'cb1a546',
        'body_md'         => '# Resumo do handoff' . "\n\ncorpo",
        'files_json'      => ['resources/js/Pages/x.tsx'],
        'source_hash'     => str_repeat('a', 64),
        'sig'             => str_repeat('b', 64),
        'created_by'      => 'CC',
        'created_at'      => now(),
    ], $over));
}

/** Acha 1 handoff serializado pelo slug na saída do service. */
function forjaFind(array $handoffs, string $slug): ?array
{
    foreach ($handoffs as $h) {
        if (($h['slug'] ?? null) === $slug) {
            return $h;
        }
    }

    return null;
}

/**
 * Stub da GitHub API que o PrChecksResolver consome (Gap 2 · ADR 0283), sem rede:
 * PR (head SHA + base) → branch protection (1 required check) → check-runs → status
 * legado (vazio). $checkState controla o estado do required no head SHA:
 * 'success' | 'failure' | 'in_progress'.
 */
function forjaFakeGh(string $checkState = 'success', string $required = 'ci'): void
{
    Http::fake(function (Request $req) use ($checkState, $required) {
        $url = $req->url();

        return match (true) {
            str_contains($url, '/pulls/') => Http::response(
                ['head' => ['sha' => 'deadbeefcafe'], 'base' => ['ref' => 'main']],
                200,
            ),
            str_contains($url, '/protection/required_status_checks') => Http::response(
                ['checks' => [['context' => $required, 'app_id' => 1]], 'contexts' => [$required]],
                200,
            ),
            str_contains($url, '/check-runs') => Http::response([
                'total_count' => 1,
                'check_runs'  => [
                    $checkState === 'in_progress'
                        ? ['name' => $required, 'status' => 'in_progress', 'conclusion' => null]
                        : ['name' => $required, 'status' => 'completed', 'conclusion' => $checkState],
                ],
            ], 200),
            // /commits/{sha}/status (legado) — vazio: o veredito vem dos check-runs.
            default => Http::response(['state' => 'success', 'statuses' => []], 200),
        };
    });
}

beforeEach(function () {
    mkForjaHandoffTable();
    mkForjaHeartbeatTable();
});

afterEach(function () {
    foreach (['cowork_handoffs', 'mcp_ingest_heartbeat'] as $tbl) {
        if (Schema::hasTable($tbl)) {
            Schema::drop($tbl);
        }
    }
});

// ─── handoffs() · projeção ──────────────────────────────────────────────────

it('exclui superseded da lista', function () {
    mkForjaHandoff('vivo');
    mkForjaHandoff('morto', ['status' => 'superseded']);

    $out = (new ForjaMcpService())->handoffs();

    expect(forjaFind($out, 'vivo'))->not->toBeNull();
    expect(forjaFind($out, 'morto'))->toBeNull();
});

it('pega só a MAIOR version por slug', function () {
    mkForjaHandoff('dup', ['version' => 1, 'created_at' => now()->subMinutes(10)]);
    mkForjaHandoff('dup', ['version' => 2, 'created_at' => now()]);

    $out = (new ForjaMcpService())->handoffs();
    $dup = array_values(array_filter($out, fn ($h) => $h['slug'] === 'dup'));

    expect($dup)->toHaveCount(1);
    expect($dup[0]['version'])->toBe(2);
});

it('deriva stale de pending velho (> 3 dias)', function () {
    mkForjaHandoff('velho', ['created_at' => now()->subDays(5)]);
    mkForjaHandoff('novo', ['created_at' => now()->subDay()]);

    $out = (new ForjaMcpService())->handoffs();

    expect(forjaFind($out, 'velho')['status'])->toBe('stale');
    expect(forjaFind($out, 'novo')['status'])->toBe('pending');
});

// ─── gate derivado (MESMA regra verde do handoff-ack) ───────────────────────

it('gate verde quando os 3 do gate_status passam', function () {
    mkForjaHandoff('ok', [
        'status'      => 'applied',
        'gate_status' => ['conformance' => true, 'critique_score' => 90, 'a11y' => true],
    ]);

    expect(forjaFind((new ForjaMcpService())->handoffs(), 'ok')['gate'])->toBe('verde');
});

it('gate vermelho quando critique_score < 80', function () {
    mkForjaHandoff('ruim', [
        'status'      => 'applied',
        'gate_status' => ['conformance' => true, 'critique_score' => 70, 'a11y' => true],
    ]);

    expect(forjaFind((new ForjaMcpService())->handoffs(), 'ruim')['gate'])->toBe('vermelho');
});

it('gate rodando quando applied sem gate_status', function () {
    mkForjaHandoff('rodando', ['status' => 'applied', 'gate_status' => null]);

    expect(forjaFind((new ForjaMcpService())->handoffs(), 'rodando')['gate'])->toBe('rodando');
});

it('gate na (não-avaliado) quando pending sem gate_status', function () {
    mkForjaHandoff('semgate');

    expect(forjaFind((new ForjaMcpService())->handoffs(), 'semgate')['gate'])->toBe('na');
});

// ─── gate CONFLITO: ack verde × required checks REAIS do PR (Gap 2 · ADR 0283) ──
// O gate_status é auto-reportado pelo [CC]; só o ack VERDE é cruzado com o estado real
// do PR no GitHub (PrChecksResolver). Divergência → 'conflito'. Best-effort: sem
// token/rede/branch-protection legível → degrada pro ack (comportamento da Fase 1).

it('gate conflito quando ack verde mas um required check do PR está vermelho', function () {
    config()->set('services.github.token', 'ghp_test');
    Cache::flush();
    forjaFakeGh('failure');

    mkForjaHandoff('mente', [
        'status'      => 'applied',
        'pr_url'      => 'https://github.com/wagnerra23/oimpresso.com/pull/2921',
        'gate_status' => ['conformance' => true, 'critique_score' => 90, 'a11y' => true],
    ]);

    expect(forjaFind((new ForjaMcpService())->handoffs(), 'mente')['gate'])->toBe('conflito');
});

it('gate conflito quando ack verde mas um required check ainda está pendente', function () {
    config()->set('services.github.token', 'ghp_test');
    Cache::flush();
    forjaFakeGh('in_progress');

    mkForjaHandoff('pendente-pr', [
        'status'      => 'applied',
        'pr_url'      => 'https://github.com/wagnerra23/oimpresso.com/pull/2922',
        'gate_status' => ['conformance' => true, 'critique_score' => 85, 'a11y' => true],
    ]);

    expect(forjaFind((new ForjaMcpService())->handoffs(), 'pendente-pr')['gate'])->toBe('conflito');
});

it('mantém verde quando ack verde E os required checks do PR estão verdes', function () {
    config()->set('services.github.token', 'ghp_test');
    Cache::flush();
    forjaFakeGh('success');

    mkForjaHandoff('honesto', [
        'status'      => 'applied',
        'pr_url'      => 'https://github.com/wagnerra23/oimpresso.com/pull/2923',
        'gate_status' => ['conformance' => true, 'critique_score' => 95, 'a11y' => true],
    ]);

    expect(forjaFind((new ForjaMcpService())->handoffs(), 'honesto')['gate'])->toBe('verde');
});

it('NÃO cruza com o GitHub quando o ack não é verde (só verde afirma algo)', function () {
    config()->set('services.github.token', 'ghp_test');
    Cache::flush();
    forjaFakeGh('failure'); // se cruzasse, viraria conflito — mas nem deve chamar a API

    mkForjaHandoff('reprovado', [
        'status'      => 'applied',
        'pr_url'      => 'https://github.com/wagnerra23/oimpresso.com/pull/2924',
        'gate_status' => ['conformance' => true, 'critique_score' => 70, 'a11y' => true], // < 80 → vermelho
    ]);

    expect(forjaFind((new ForjaMcpService())->handoffs(), 'reprovado')['gate'])->toBe('vermelho');
    Http::assertNothingSent();
});

it('degrada pro ack (verde) quando não há token do GitHub', function () {
    config()->set('services.github.token', '');
    Cache::flush();
    Http::fake(); // grava chamadas — não deve haver nenhuma

    mkForjaHandoff('sem-token', [
        'status'      => 'applied',
        'pr_url'      => 'https://github.com/wagnerra23/oimpresso.com/pull/2925',
        'gate_status' => ['conformance' => true, 'critique_score' => 90, 'a11y' => true],
    ]);

    expect(forjaFind((new ForjaMcpService())->handoffs(), 'sem-token')['gate'])->toBe('verde');
    Http::assertNothingSent();
});

it('degrada pro ack (verde) quando a GitHub API falha (best-effort, nunca quebra)', function () {
    config()->set('services.github.token', 'ghp_test');
    Cache::flush();
    Http::fake(fn () => Http::response(['message' => 'Bad credentials'], 401));

    mkForjaHandoff('api-fora', [
        'status'      => 'applied',
        'pr_url'      => 'https://github.com/wagnerra23/oimpresso.com/pull/2926',
        'gate_status' => ['conformance' => true, 'critique_score' => 90, 'a11y' => true],
    ]);

    expect(forjaFind((new ForjaMcpService())->handoffs(), 'api-fora')['gate'])->toBe('verde');
});

it('degrada pro ack quando não dá pra ler a branch protection (evita conflito por check advisory)', function () {
    config()->set('services.github.token', 'ghp_test');
    Cache::flush();
    Http::fake(function (Request $req) {
        $url = $req->url();
        if (str_contains($url, '/pulls/')) {
            return Http::response(['head' => ['sha' => 'abc123'], 'base' => ['ref' => 'main']], 200);
        }
        if (str_contains($url, '/protection/required_status_checks')) {
            return Http::response(['message' => 'Not Found'], 404); // sem admin / sem proteção legível
        }

        return Http::response([], 200);
    });

    mkForjaHandoff('sem-protecao', [
        'status'      => 'applied',
        'pr_url'      => 'https://github.com/wagnerra23/oimpresso.com/pull/2927',
        'gate_status' => ['conformance' => true, 'critique_score' => 90, 'a11y' => true],
    ]);

    expect(forjaFind((new ForjaMcpService())->handoffs(), 'sem-protecao')['gate'])->toBe('verde');
});

// ─── serialize ──────────────────────────────────────────────────────────────

it('serializa files_count, signed e resumo (1ª linha do body_md sem md)', function () {
    mkForjaHandoff('serial', [
        'files_json' => ['a.tsx', 'b.php', 'c.css'],
        'sig'        => str_repeat('c', 64),
        'body_md'    => "## Migrar tela de venda\n\ndetalhe",
    ]);

    $h = forjaFind((new ForjaMcpService())->handoffs(), 'serial');

    expect($h['files_count'])->toBe(3);
    expect($h['signed'])->toBeTrue();
    expect($h['resumo'])->toBe('Migrar tela de venda');
});

it('marca signed=false quando sig vazia', function () {
    mkForjaHandoff('nosig', ['sig' => '']);

    expect(forjaFind((new ForjaMcpService())->handoffs(), 'nosig')['signed'])->toBeFalse();
});

// ─── heartbeat ───────────────────────────────────────────────────────────────

it('heartbeat silent quando não há ingest', function () {
    $hb = (new ForjaMcpService())->heartbeat();

    expect($hb['silent'])->toBeTrue();
    expect($hb['last_ingest_at'])->toBeNull();
});

it('heartbeat não-silent com ingest recente', function () {
    McpIngestHeartbeat::create(['host' => 'ct100', 'last_ingest_at' => now()]);

    $hb = (new ForjaMcpService())->heartbeat();

    expect($hb['silent'])->toBeFalse();
    expect($hb['host'])->toBe('ct100');
});

it('heartbeat silent quando o último ingest passou do teto (> 60min)', function () {
    McpIngestHeartbeat::create(['host' => 'ct100', 'last_ingest_at' => now()->subHours(2)]);

    expect((new ForjaMcpService())->heartbeat()['silent'])->toBeTrue();
});
