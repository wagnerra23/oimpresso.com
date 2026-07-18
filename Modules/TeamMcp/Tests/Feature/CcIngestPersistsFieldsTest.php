<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\TeamMcp\Http\Controllers\Mcp\CcIngestController;

uses(Tests\TestCase::class);

/**
 * MEM-CC-1 — Regressão do bug de validate()-strip no /api/cc/ingest.
 *
 * BUG catalogado (2026-07-17, smoke prod): as 17.686 linhas de `mcp_cc_messages`
 * eram TODAS content-less skeletons — `content_text`, `content_json`, `tokens_in`,
 * `tokens_out` NULL em todos os msg_types. Causa: `CcIngestController::ingest` usava
 * o RETORNO de `$request->validate([...])`, cujas regras só cobrem `messages.*.uuid`
 * e `messages.*.type`. Com `excludeUnvalidatedArrayKeys=true` (default Laravel 9+), o
 * `validated()['messages']` volta STRIPADO só com {uuid,type} — todo o resto era
 * descartado ANTES do insert. Efeito: `cc-search` (FULLTEXT em content_text) nunca
 * teve conteúdo pra buscar; MEM-CC-1 ingeriu vazio desde a origem.
 *
 * Este teste exercita `ingest()` ponta-a-ponta com um payload de campos completos e
 * prova que os campos PERSISTEM. Roda vermelho no controller pré-fix (tudo NULL),
 * verde depois de ler `$request->input('messages')` cru.
 *
 * Estratégia era-sqlite sintética (espelha IngestHeartbeatTest/IngestLivenessTest):
 * monta mcp_cc_sessions + mcp_cc_messages (+ heartbeat best-effort) sqlite-friendly
 * sob demanda; sem RefreshDatabase. Skipa no MySQL persistente do nightly (US-GOV-021).
 *
 * @see Modules\TeamMcp\Http\Controllers\Mcp\CcIngestController::ingest
 */

function ensureCcIngestTables(): void
{
    foreach (['mcp_cc_messages', 'mcp_cc_sessions', 'mcp_ingest_heartbeat'] as $tbl) {
        if (Schema::hasTable($tbl)) {
            Schema::drop($tbl);
        }
    }

    Schema::create('mcp_cc_sessions', function ($t) {
        $t->bigIncrements('id');
        $t->string('session_uuid', 36)->unique();
        $t->unsignedInteger('user_id')->nullable();
        $t->unsignedInteger('business_id')->nullable();
        $t->string('project_path', 500)->nullable();
        $t->string('git_branch', 150)->nullable();
        $t->string('cc_version', 20)->nullable();
        $t->string('entrypoint', 50)->nullable();
        $t->timestamp('started_at')->nullable();
        $t->timestamp('ended_at')->nullable();
        $t->unsignedInteger('total_messages')->default(0);
        $t->unsignedBigInteger('total_tokens')->default(0);
        $t->decimal('total_cost_usd', 12, 6)->default(0);
        $t->decimal('total_cost_brl', 12, 6)->default(0);
        $t->string('status', 30)->nullable();
        $t->json('metadata')->nullable();
        $t->text('summary_auto')->nullable();
        $t->timestamps();
        $t->softDeletes(); // McpCcSession use SoftDeletes → precisa de deleted_at (senão updateOrCreate 500a)
    });

    Schema::create('mcp_cc_messages', function ($t) {
        $t->bigIncrements('id');
        $t->unsignedBigInteger('session_id');
        $t->string('msg_uuid', 36)->unique();
        $t->string('parent_uuid', 36)->nullable();
        $t->unsignedInteger('user_id');
        $t->unsignedInteger('business_id')->nullable();
        $t->string('msg_type', 20);
        $t->string('role', 20)->nullable();
        $t->string('tool_name', 100)->nullable();
        $t->string('model', 60)->nullable();
        $t->mediumText('content_text')->nullable();
        $t->json('content_json')->nullable();
        $t->unsignedBigInteger('blob_id')->nullable();
        $t->unsignedInteger('tokens_in')->nullable();
        $t->unsignedInteger('tokens_out')->nullable();
        $t->unsignedInteger('cache_read')->nullable();
        $t->unsignedInteger('cache_write')->nullable();
        $t->decimal('cost_usd', 10, 8)->nullable();
        $t->timestamp('ts')->nullable();
        $t->timestamps();
    });

    Schema::create('mcp_ingest_heartbeat', function ($t) {
        $t->bigIncrements('id');
        $t->string('host', 500)->unique();
        $t->timestamp('last_ingest_at')->nullable();
        $t->string('last_session_uuid', 36)->nullable();
        $t->unsignedBigInteger('msgs_acc')->default(0);
        $t->timestamps();
    });
}

/**
 * Chama CcIngestController::ingest com um Request POST cru + user resolver fake.
 * User anônimo sem can() → gate RBAC permissivo (mesma convenção dos testes MCP).
 */
function callCcIngest(array $payload)
{
    $user = new class {
        public int $id = 1;

        public int $business_id = 1;
    };

    $request = Request::create('/api/cc/ingest', 'POST', $payload);
    $request->setUserResolver(fn () => $user);

    return app(CcIngestController::class)->ingest($request);
}

beforeEach(function () {
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('era-sqlite: tabelas sintéticas mcp_cc_* só rodam no sqlite (US-GOV-021)');
    }
    ensureCcIngestTables();
});

afterEach(function () {
    if (config('database.default') !== 'sqlite') {
        return;
    }
    foreach (['mcp_cc_messages', 'mcp_cc_sessions', 'mcp_ingest_heartbeat'] as $tbl) {
        if (Schema::hasTable($tbl)) {
            Schema::drop($tbl);
        }
    }
});

function fullFieldPayload(): array
{
    return [
        'session' => [
            'uuid' => 'sess-uuid-0001',
            'project_path' => 'D:\\oimpresso.com',
            'git_branch' => 'main',
            'cc_version' => '2.1.0',
            'entrypoint' => 'claude-code',
            'started_at' => '2026-07-17T10:00:00Z',
            'ended_at' => null,
        ],
        'messages' => [
            [
                'uuid' => 'msg-user-1',
                'type' => 'user',
                'role' => 'user',
                'content_text' => 'como resolvo o 504 do telescope?',
                'ts' => '2026-07-17T10:00:01Z',
            ],
            [
                'uuid' => 'msg-asst-1',
                'type' => 'assistant',
                'role' => 'assistant',
                'model' => 'claude-opus-4-8',
                'content_text' => 'Aumente o timeout do worker e cheque o memory_limit.',
                'content_json' => ['message' => ['model' => 'claude-opus-4-8']],
                'tokens_in' => 100,
                'tokens_out' => 300,
                'cache_read' => 32000,
                'cache_write' => 0,
                'ts' => '2026-07-17T10:00:05Z',
            ],
        ],
    ];
}

it('persiste content_text + tokens (NÃO stripa) — regressão do validate-strip', function () {
    $response = callCcIngest(fullFieldPayload());

    $data = $response->getData(true);
    expect($data['ok'] ?? false)->toBeTrue();
    expect($data['messages_inserted'] ?? null)->toBe(2);

    expect(DB::table('mcp_cc_messages')->count())->toBe(2);

    // ── A REGRESSÃO: pré-fix, TODOS estes campos vinham NULL (validate strip). ──
    $asst = DB::table('mcp_cc_messages')->where('msg_uuid', 'msg-asst-1')->first();
    expect($asst->content_text)->not->toBeNull();
    expect($asst->content_text)->toContain('timeout');
    expect((int) $asst->tokens_out)->toBe(300);
    expect((int) $asst->tokens_in)->toBe(100);
    expect((int) $asst->cache_read)->toBe(32000);
    expect($asst->content_json)->not->toBeNull();
    expect($asst->model)->toBe('claude-opus-4-8');
    expect($asst->role)->toBe('assistant');

    $usr = DB::table('mcp_cc_messages')->where('msg_uuid', 'msg-user-1')->first();
    expect($usr->content_text)->toContain('telescope');
    expect($usr->role)->toBe('user');
});

it('agrega os tokens da sessão (total_tokens = in+out somados)', function () {
    callCcIngest(fullFieldPayload());

    $session = DB::table('mcp_cc_sessions')->where('session_uuid', 'sess-uuid-0001')->first();
    // 100 (in) + 300 (out) = 400 — pré-fix daria 0 (tokens stripados).
    expect((int) $session->total_tokens)->toBe(400);
});

it('idempotente: re-enviar o MESMO msg_uuid não duplica (dedup)', function () {
    callCcIngest(fullFieldPayload());
    $second = callCcIngest(fullFieldPayload());

    $data = $second->getData(true);
    expect($data['messages_inserted'] ?? null)->toBe(0);
    expect($data['messages_duplicated'] ?? null)->toBe(2);
    expect(DB::table('mcp_cc_messages')->count())->toBe(2);
});
