<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Mcp\OimpressoMcpServer;
use Modules\Jana\Mcp\Tools\WhatsActiveTool;

/**
 * ADR 0119 (Tier 1) — WhatsActiveTool.
 *
 * Coordenação Claude-A vs Claude-B: agrega mcp_cc_sessions + mcp_cc_messages
 * pra responder "quem está mexendo em quê AGORA" como alerta passivo.
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }

    // Idempotente: este teste agora roda na lane sqlite compartilhada (:memory:) ao
    // lado de outros — limpa antes de criar pra não estourar "table already exists".
    Schema::dropIfExists('mcp_cc_messages');
    Schema::dropIfExists('mcp_cc_sessions');
    Schema::dropIfExists('users');
    Schema::dropIfExists('mcp_ingest_heartbeat');

    Schema::create('mcp_cc_sessions', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->string('session_uuid', 36)->unique();
        $t->unsignedInteger('user_id');
        $t->unsignedInteger('business_id')->nullable();
        $t->string('project_path', 500);
        $t->string('git_branch', 150)->nullable();
        $t->string('cc_version', 20)->nullable();
        $t->string('entrypoint', 50)->nullable();
        $t->timestamp('started_at')->nullable();
        $t->timestamp('ended_at')->nullable();
        $t->unsignedInteger('total_messages')->default(0);
        $t->unsignedBigInteger('total_tokens')->default(0);
        $t->decimal('total_cost_usd', 12, 6)->default(0);
        $t->decimal('total_cost_brl', 12, 4)->default(0);
        $t->string('status', 20)->default('active');
        $t->json('metadata')->nullable();
        $t->text('summary_auto')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });

    Schema::create('mcp_cc_messages', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedBigInteger('session_id');
        $t->string('msg_uuid', 36)->unique();
        $t->string('parent_uuid', 36)->nullable();
        $t->unsignedInteger('user_id');
        $t->unsignedInteger('business_id')->nullable();
        $t->string('msg_type', 30)->default('tool_use');
        $t->string('role', 20)->nullable();
        $t->string('tool_name', 100)->nullable();
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

    Schema::create('users', function (Blueprint $t) {
        $t->increments('id');
        $t->string('username', 191)->nullable();
        $t->string('email', 191)->nullable();
        $t->string('first_name', 191)->nullable();
        $t->string('last_name', 191)->nullable();
        $t->string('password', 191)->nullable();
        $t->rememberToken();
        $t->timestamps();
    });
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        Schema::dropIfExists('mcp_cc_messages');
        Schema::dropIfExists('mcp_cc_sessions');
        Schema::dropIfExists('users');
        Schema::dropIfExists('mcp_ingest_heartbeat'); // B-SPOF-WA: criada só no teste de liveness
    }
});

function makeUser(array $attrs = []): \App\User
{
    $user = new \App\User();
    $user->id = $attrs['id'] ?? 1;
    $user->username = $attrs['username'] ?? 'wagner';
    $user->email = $attrs['email'] ?? 'wagner@oimpresso.com';
    $user->first_name = $attrs['first_name'] ?? 'Wagner';
    $user->last_name = $attrs['last_name'] ?? 'Rocha';
    DB::table('users')->insert([
        'id' => $user->id,
        'username' => $user->username,
        'email' => $user->email,
        'first_name' => $user->first_name,
        'last_name' => $user->last_name,
    ]);
    return $user;
}

function makeSession(int $sessId, int $userId, string $project, string $branch): void
{
    DB::table('mcp_cc_sessions')->insert([
        'id' => $sessId,
        'session_uuid' => "uuid-sess-{$sessId}",
        'user_id' => $userId,
        'business_id' => 1,
        'project_path' => $project,
        'git_branch' => $branch,
        'started_at' => now()->subMinutes(30),
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function makeEditMsg(int $sessId, int $userId, string $filePath, ?\Carbon\Carbon $ts = null): void
{
    DB::table('mcp_cc_messages')->insert([
        'session_id' => $sessId,
        'msg_uuid' => 'uuid-msg-' . uniqid(),
        'user_id' => $userId,
        'business_id' => 1,
        'msg_type' => 'tool_use',
        'tool_name' => 'Edit',
        'content_json' => json_encode(['input' => ['file_path' => $filePath]]),
        'ts' => ($ts ?? now())->toDateTimeString(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('WhatsActiveTool exige autenticação', function () {
    OimpressoMcpServer::tool(WhatsActiveTool::class)
        ->assertHasErrors()
        ->assertSee('Autenticação requerida');
});

it('WhatsActiveTool retorna mensagem amigável quando ninguém ativo', function () {
    $user = makeUser();

    OimpressoMcpServer::actingAs($user)
        ->tool(WhatsActiveTool::class)
        ->assertOk()
        ->assertSee('Nenhuma sessão Claude Code ativa');
});

it('WhatsActiveTool NÃO dá all-clear falso quando o pipeline de ingest está cego (B-SPOF-WA)', function () {
    // Heartbeat EXISTE mas o último ingest foi há 3h (> STALE_MINUTES=60) → fresh=0:
    // o watcher de ingest está caído, então "nenhuma sessão" pode ser CEGUEIRA, não calmaria.
    Schema::create('mcp_ingest_heartbeat', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->string('host', 500)->unique();
        $t->timestamp('last_ingest_at')->nullable();
        $t->string('last_session_uuid', 36)->nullable();
        $t->unsignedBigInteger('msgs_acc')->default(0);
        $t->timestamps();
    });
    DB::table('mcp_ingest_heartbeat')->insert([
        'host' => 'D:\\oimpresso.com',
        'last_ingest_at' => now()->subHours(3)->toDateTimeString(),
        'msgs_acc' => 5,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $user = makeUser();

    // Sem mensagens recentes (isEmpty) MAS pipeline cego → resposta de INCERTEZA.
    // BITE: se a guarda B-SPOF-WA regredir, volta o all-clear ("Pode pegar qualquer
    // escopo") que NÃO contém estas frases → assertSee falha.
    OimpressoMcpServer::actingAs($user)
        ->tool(WhatsActiveTool::class)
        ->assertOk()
        ->assertSee('NÃO assuma escopo livre')
        ->assertSee('fresh=0');
})->group('ci');

it('WhatsActiveTool lista 2 sessões ativas com paths overlapping', function () {
    $wagner = makeUser(['id' => 1, 'username' => 'wagner', 'first_name' => 'Wagner']);
    $felipe = makeUser(['id' => 2, 'username' => 'felipe', 'first_name' => 'Felipe', 'email' => 'felipe@oimpresso.com']);

    makeSession(10, $wagner->id, 'D:\\oimpresso.com', 'main');
    makeSession(20, $felipe->id, 'D:\\oimpresso.com', 'feature/nfe');

    // Path overlapping: ambos editaram NfeService.php nas últimas 1h
    makeEditMsg(10, $wagner->id, 'D:\\oimpresso.com\\Modules\\NfeBrasil\\Services\\NfeService.php');
    makeEditMsg(20, $felipe->id, 'D:\\oimpresso.com\\Modules\\NfeBrasil\\Services\\NfeService.php');
    // E cada um tem 1 path próprio:
    makeEditMsg(10, $wagner->id, 'D:\\oimpresso.com\\app\\User.php');
    makeEditMsg(20, $felipe->id, 'D:\\oimpresso.com\\Modules\\NfeBrasil\\Http\\Controllers\\NfeController.php');

    OimpressoMcpServer::actingAs($wagner)
        ->tool(WhatsActiveTool::class)
        ->assertOk()
        ->assertSee([
            '2 sessão(ões)',
            'Wagner Rocha',
            'Felipe',
            'Modules\\NfeBrasil\\Services\\NfeService.php',
            'main',
            'feature/nfe',
        ]);
});

it('WhatsActiveTool ignora sessão sem atividade na janela', function () {
    $wagner = makeUser();

    makeSession(10, $wagner->id, 'D:\\oimpresso.com', 'main');
    // Mensagem velha (3h atrás) — fora da janela default 2h
    makeEditMsg(10, $wagner->id, 'D:\\oimpresso.com\\app\\User.php', now()->subHours(3));

    OimpressoMcpServer::actingAs($wagner)
        ->tool(WhatsActiveTool::class)
        ->assertOk()
        ->assertSee('Nenhuma sessão Claude Code ativa');
});

it('WhatsActiveTool aceita parâmetro hours pra ampliar janela', function () {
    $wagner = makeUser();

    makeSession(10, $wagner->id, 'D:\\oimpresso.com', 'main');
    makeEditMsg(10, $wagner->id, 'D:\\oimpresso.com\\app\\User.php', now()->subHours(5));

    OimpressoMcpServer::actingAs($wagner)
        ->tool(WhatsActiveTool::class, ['hours' => 12])
        ->assertOk()
        ->assertSee(['1 sessão(ões)', 'app\\User.php']);
});

it('WhatsActiveTool sem tabelas mcp_cc_* retorna instrução de migration', function () {
    Schema::dropIfExists('mcp_cc_messages');
    Schema::dropIfExists('mcp_cc_sessions');

    $user = makeUser();

    OimpressoMcpServer::actingAs($user)
        ->tool(WhatsActiveTool::class)
        ->assertOk()
        ->assertSee('MEM-CC-1 ainda não está ativo');
});
