<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Copiloto\Entities\Mcp\McpAlerta;
use Modules\Copiloto\Entities\Mcp\McpAuditLog;
use Modules\Copiloto\Entities\Mcp\McpMemoryDocument;
use Modules\Copiloto\Entities\Mcp\McpQuota;
use Modules\Copiloto\Entities\Mcp\McpScope;
use Modules\Copiloto\Entities\Mcp\McpToken;
use Modules\Copiloto\Entities\Mcp\McpUsageDiaria;
use Modules\Copiloto\Entities\Mcp\McpUserScope;

/**
 * MEM-MCP-1.a (ADR 0053) — Schema das 9 tabelas mcp_* + casts + scopes.
 *
 * NÃO usa RefreshDatabase — migrations core UltimatePOS têm
 * `ALTER TABLE ... MODIFY COLUMN ENUM` que SQLite não suporta.
 * Em vez disso, beforeEach cria as tabelas alvo no SQLite in-memory.
 */

beforeEach(function () {
    // Schema mínimo replicando as 9 migrations (sem FK reais — SQLite
    // bate em foreign references a `users`/`business`/`mcp_tokens`).

    Schema::create('mcp_scopes', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->string('slug', 100)->unique();
        $t->string('nome', 150);
        $t->text('descricao')->nullable();
        $t->string('resources_pattern', 200)->nullable();
        $t->string('tools_pattern', 200)->nullable();
        $t->boolean('is_destructive')->default(false);
        $t->boolean('business_required')->default(true);
        $t->boolean('admin_only')->default(false);
        $t->timestamps();
    });

    Schema::create('mcp_user_scopes', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedInteger('user_id');
        $t->unsignedBigInteger('scope_id');
        $t->unsignedInteger('business_id')->nullable();
        $t->unsignedInteger('granted_by')->nullable();
        $t->timestamp('granted_at')->useCurrent();
        $t->timestamp('revoked_at')->nullable();
        $t->unsignedInteger('revoked_by')->nullable();
        $t->timestamps();
    });

    Schema::create('mcp_tokens', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedInteger('user_id');
        $t->string('name', 120);
        $t->string('sha256_token', 64)->unique();
        $t->json('scopes_cache')->nullable();
        $t->string('user_agent', 200)->nullable();
        $t->ipAddress('last_used_ip')->nullable();
        $t->timestamp('last_used_at')->nullable();
        $t->timestamp('expires_at')->nullable();
        $t->timestamp('revoked_at')->nullable();
        $t->unsignedInteger('revoked_by')->nullable();
        $t->timestamps();
    });

    Schema::create('mcp_quotas', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedInteger('user_id');
        $t->string('period', 20)->default('monthly');
        $t->string('kind', 20)->default('brl');
        $t->decimal('limit', 14, 4);
        $t->decimal('current_usage', 14, 4)->default(0);
        $t->timestamp('reset_at');
        $t->boolean('block_on_exceed')->default(true);
        $t->boolean('ativo')->default(true);
        $t->timestamps();
        $t->unique(['user_id', 'period', 'kind'], 'mcp_qt_user_period_kind_ux');
    });

    Schema::create('mcp_audit_log', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->uuid('request_id')->unique();
        $t->unsignedInteger('user_id');
        $t->unsignedInteger('business_id')->nullable();
        $t->timestamp('ts')->useCurrent();
        $t->string('endpoint', 30);
        $t->string('tool_or_resource', 200)->nullable();
        $t->string('scope_required', 100)->nullable();
        $t->string('status', 20);
        $t->string('error_code', 50)->nullable();
        $t->text('error_message')->nullable();
        $t->unsignedInteger('tokens_in')->nullable();
        $t->unsignedInteger('tokens_out')->nullable();
        $t->unsignedInteger('cache_read')->nullable();
        $t->unsignedInteger('cache_write')->nullable();
        $t->decimal('custo_brl', 10, 6)->nullable();
        $t->unsignedInteger('duration_ms')->nullable();
        $t->ipAddress('ip')->nullable();
        $t->string('user_agent', 200)->nullable();
        $t->string('claude_code_session', 36)->nullable();
        $t->unsignedBigInteger('mcp_token_id')->nullable();
        $t->json('payload_summary')->nullable();
        $t->timestamp('created_at')->useCurrent();
    });

    Schema::create('mcp_usage_diaria', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->date('dia');
        $t->unsignedInteger('user_id');
        $t->unsignedInteger('business_id')->nullable();
        $t->unsignedInteger('total_calls')->default(0);
        $t->unsignedInteger('calls_ok')->default(0);
        $t->unsignedInteger('calls_denied')->default(0);
        $t->unsignedInteger('calls_quota_exceeded')->default(0);
        $t->unsignedInteger('calls_error')->default(0);
        $t->unsignedBigInteger('total_tokens_in')->default(0);
        $t->unsignedBigInteger('total_tokens_out')->default(0);
        $t->unsignedBigInteger('total_cache_read')->default(0);
        $t->unsignedBigInteger('total_cache_write')->default(0);
        $t->decimal('custo_brl', 14, 4)->default(0);
        $t->json('top_tools')->nullable();
        $t->unsignedInteger('alertas_disparados')->default(0);
        $t->boolean('excedeu_quota')->default(false);
        $t->timestamps();
        $t->unique(['dia', 'user_id', 'business_id'], 'mcp_ud_dia_user_biz_ux');
    });

    Schema::create('mcp_alertas', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedInteger('user_id')->nullable();
        $t->unsignedInteger('business_id')->nullable();
        $t->string('kind', 30);
        $t->decimal('threshold', 14, 4)->nullable();
        $t->string('canal', 20)->default('in_app');
        $t->boolean('ativo')->default(true);
        $t->json('config_extra')->nullable();
        $t->timestamps();
    });

    Schema::create('mcp_memory_documents', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->string('slug', 200)->unique();
        $t->string('type', 20);
        $t->string('module', 50)->nullable();
        $t->string('title', 250);
        $t->mediumText('content_md');
        $t->string('scope_required', 100)->nullable();
        $t->boolean('admin_only')->default(false);
        $t->json('metadata')->nullable();
        $t->string('git_sha', 40)->nullable();
        $t->string('git_path', 300);
        $t->unsignedSmallInteger('pii_redactions_count')->default(0);
        $t->binary('embedding')->nullable();
        $t->timestamp('indexed_at')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });

    Schema::create('mcp_memory_documents_history', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedBigInteger('document_id');
        $t->string('slug', 200);
        $t->string('git_sha', 40)->nullable();
        $t->string('title', 250);
        $t->mediumText('content_md');
        $t->json('metadata')->nullable();
        $t->timestamp('changed_at')->useCurrent();
        $t->unsignedInteger('changed_by_user_id')->nullable();
        $t->string('change_reason', 100)->nullable();
        $t->timestamp('created_at')->useCurrent();
    });
});

afterEach(function () {
    foreach ([
        'mcp_memory_documents_history',
        'mcp_memory_documents',
        'mcp_alertas',
        'mcp_usage_diaria',
        'mcp_audit_log',
        'mcp_quotas',
        'mcp_tokens',
        'mcp_user_scopes',
        'mcp_scopes',
    ] as $tabela) {
        Schema::dropIfExists($tabela);
    }
});

it('McpScope grava + lê + matchesTool/matchesResource funcionam', function () {
    $scope = McpScope::create([
        'slug'              => 'copiloto.mcp.tasks.read',
        'nome'              => 'Ler tasks atuais',
        'descricao'         => 'Permite chamar tools tasks.*',
        'resources_pattern' => 'oimpresso://memory/current',
        'tools_pattern'     => 'tasks.*',
        'is_destructive'    => false,
        'business_required' => true,
    ]);

    expect($scope->slug)->toBe('copiloto.mcp.tasks.read');
    expect($scope->matchesTool('tasks.current'))->toBeTrue();
    expect($scope->matchesTool('decisions.fetch'))->toBeFalse();
    expect($scope->matchesResource('oimpresso://memory/current'))->toBeTrue();
    expect($scope->matchesResource('oimpresso://memory/decisions/0046'))->toBeFalse();
});

it('McpUserScope ativos + doUser + isAtivo funcionam', function () {
    $scope = McpScope::create(['slug' => 's1', 'nome' => 'S1']);

    $ativo = McpUserScope::create([
        'user_id' => 1, 'scope_id' => $scope->id, 'business_id' => 4,
        'granted_at' => now(),
    ]);
    $revogado = McpUserScope::create([
        'user_id' => 1, 'scope_id' => $scope->id, 'business_id' => 4,
        'granted_at' => now()->subDay(), 'revoked_at' => now(),
    ]);

    expect(McpUserScope::ativos()->doUser(1)->count())->toBe(1);
    expect($ativo->isAtivo())->toBeTrue();
    expect($revogado->isAtivo())->toBeFalse();
});

it('McpToken::gerar + encontrarPorRaw funciona, raw nunca persistido', function () {
    [$token, $raw] = McpToken::gerar(userId: 1, name: 'Wagner laptop');

    expect($raw)->toStartWith('mcp_');
    expect(strlen($raw))->toBe(68); // 'mcp_' + 64 hex chars
    expect($token->sha256_token)->toBe(hash('sha256', $raw));
    expect($token->isAtivo())->toBeTrue();

    $found = McpToken::encontrarPorRaw($raw);
    expect($found?->id)->toBe($token->id);

    $notFound = McpToken::encontrarPorRaw('mcp_invalido');
    expect($notFound)->toBeNull();
});

it('McpToken::revogar marca revoked_at e desativa', function () {
    [$token] = McpToken::gerar(1, 'test');
    expect($token->isAtivo())->toBeTrue();

    $token->revogar(2);
    $token->refresh();

    expect($token->isAtivo())->toBeFalse();
    expect($token->revoked_at)->not->toBeNull();
    expect($token->revoked_by)->toBe(2);
});

it('McpToken::expirado retorna inativo', function () {
    [$token, $raw] = McpToken::gerar(1, 'test', \Carbon\Carbon::yesterday());

    expect($token->isAtivo())->toBeFalse();
    expect(McpToken::encontrarPorRaw($raw))->toBeNull();
});

it('McpQuota::excedeu + percentualUso + resetar', function () {
    $q = McpQuota::create([
        'user_id'         => 1,
        'period'          => 'monthly',
        'kind'            => 'brl',
        'limit'           => 100.0,
        'current_usage'   => 80.0,
        'reset_at'        => now()->addMonth(),
        'block_on_exceed' => true,
    ]);

    expect($q->excedeu())->toBeFalse();
    expect($q->percentualUso())->toBe(0.8);

    $q->incrementar(30.0);
    $q->refresh();

    expect($q->excedeu())->toBeTrue();
    expect($q->percentualUso())->toBe(1.1);

    $q->resetar();
    $q->refresh();
    expect((float) $q->current_usage)->toBe(0.0);
});

it('McpAuditLog::registrar exige campos obrigatórios + UUID + casts', function () {
    expect(fn () => McpAuditLog::registrar(['endpoint' => 'tools/call']))
        ->toThrow(InvalidArgumentException::class);

    $log = McpAuditLog::registrar([
        'user_id'          => 1,
        'business_id'      => 4,
        'endpoint'         => 'tools/call',
        'tool_or_resource' => 'tasks.current',
        'status'           => 'ok',
        'tokens_in'        => 100, 'tokens_out' => 50,
        'cache_read'       => 30000, 'cache_write' => 1000,
        'custo_brl'        => 0.0035,
        'duration_ms'      => 234,
        'ip'               => '192.168.0.10',
        'payload_summary'  => ['args' => ['filter' => 'today']],
    ]);

    expect($log->request_id)->toBeString();
    expect(strlen($log->request_id))->toBe(36); // UUID
    expect($log->isErro())->toBeFalse();
    expect($log->totalTokens())->toBe(31150); // 100+50+30000+1000

    $erro = McpAuditLog::registrar([
        'user_id'  => 1,
        'endpoint' => 'tools/call',
        'status'   => 'denied',
    ]);
    expect($erro->isErro())->toBeTrue();
});

it('McpUsageDiaria scopes + helpers', function () {
    McpUsageDiaria::create(['dia' => '2026-04-29', 'user_id' => 1, 'total_calls' => 100, 'calls_ok' => 95, 'calls_error' => 5, 'custo_brl' => 1.5]);
    McpUsageDiaria::create(['dia' => '2026-04-28', 'user_id' => 1, 'total_calls' => 80, 'calls_ok' => 80, 'custo_brl' => 1.0]);
    McpUsageDiaria::create(['dia' => now()->subDays(45)->toDateString(), 'user_id' => 1, 'total_calls' => 50, 'calls_ok' => 50, 'custo_brl' => 0.5]);

    $rows = McpUsageDiaria::doUser(1)->ultimosDias(30)->get();
    expect($rows->count())->toBeGreaterThanOrEqual(2);

    $primeiro = McpUsageDiaria::doUser(1)->orderBy('dia', 'desc')->first();
    expect($primeiro->taxaErro())->toBeGreaterThan(0);
});

it('McpMemoryDocument grava + busca por tipo + módulo + softDelete', function () {
    $doc = McpMemoryDocument::create([
        'slug'        => '0046-chat-agent-gap',
        'type'        => 'adr',
        'module'      => 'copiloto',
        'title'       => 'ADR 0046 — Gap ChatAgent',
        'content_md'  => '# Gap',
        'git_path'    => 'memory/decisions/0046-chat-agent-gap.md',
        'metadata'    => ['status' => 'aceito'],
        'admin_only'  => false,
    ]);

    expect($doc->type)->toBe('adr');
    expect(McpMemoryDocument::doTipo('adr')->count())->toBe(1);
    expect(McpMemoryDocument::doModulo('copiloto')->count())->toBe(1);

    $doc->delete();
    expect(McpMemoryDocument::count())->toBe(0);
    expect(McpMemoryDocument::withTrashed()->count())->toBe(1);
});

it('McpMemoryDocument::snapshotEAtualizar move versão antiga para history', function () {
    $doc = McpMemoryDocument::create([
        'slug'        => '0001-test',
        'type'        => 'adr',
        'title'       => 'V1',
        'content_md'  => 'conteudo v1',
        'git_path'    => 'memory/decisions/0001-test.md',
        'git_sha'     => 'abc123',
    ]);

    $doc->snapshotEAtualizar([
        'title'      => 'V2',
        'content_md' => 'conteudo v2',
        'git_sha'    => 'def456',
    ], userId: 99, reason: 'manual');

    $doc->refresh();
    expect($doc->title)->toBe('V2');
    expect($doc->git_sha)->toBe('def456');
    expect($doc->history()->count())->toBe(1);

    $hist = $doc->history()->first();
    expect($hist->title)->toBe('V1');
    expect($hist->content_md)->toBe('conteudo v1');
    expect($hist->changed_by_user_id)->toBe(99);
    expect($hist->change_reason)->toBe('manual');
});

it('McpAlerta scopes ativos + kind funcionam', function () {
    McpAlerta::create(['kind' => 'cota_excedida', 'threshold' => 90, 'canal' => 'email', 'ativo' => true]);
    McpAlerta::create(['kind' => 'cota_excedida', 'threshold' => 100, 'canal' => 'whatsapp', 'ativo' => false]);
    McpAlerta::create(['kind' => 'tool_destrutiva', 'canal' => 'in_app', 'ativo' => true]);

    expect(McpAlerta::ativos()->count())->toBe(2);
    expect(McpAlerta::ativos()->kind('cota_excedida')->count())->toBe(1);
});
