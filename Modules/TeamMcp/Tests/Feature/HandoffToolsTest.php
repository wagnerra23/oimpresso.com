<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Schema;
use Laravel\Mcp\Request as McpRequest;
use Laravel\Mcp\Response as McpResponse;
use Modules\TeamMcp\Entities\CoworkHandoff;
use Modules\TeamMcp\Mcp\Tools\HandoffAckTool;
use Modules\TeamMcp\Mcp\Tools\HandoffPendingTool;

uses(Tests\TestCase::class);

/**
 * PR-2 Loop de Handoff Zero-Paste (Fase 0 · ADR 0283) — GUARD dos tools
 * handoff-pending + handoff-ack.
 *
 * Provas (critério de aceite v2 + adversário [AH]):
 *   pending — lista metadados (sem body); com slug retorna corpo (teto 32k, A8);
 *             conflicts_with entre pendentes nos mesmos arquivos (A5).
 *   ack     — sem scope jana.mcp.handoff.ack → erro, NÃO muta (A7); applied sem
 *             gate verde → erro, fica pending (A3); ack em não-pendente → erro
 *             (idempotência/409); rejected sem note → erro; e o handler NÃO contém
 *             Cache::flush() (A2 — grep no source).
 *
 * Harness espelha AuthorizesMcpMutationTest (user via auth userResolver) + a
 * tabela sintética de IngestHeartbeatTest. Helpers têm nomes ÚNICOS pra não
 * colidir com HandoffIngestTest no mesmo processo Pest.
 *
 * stale_warning não é exercitado aqui (sem GITHUB token em teste, GitMainResolver
 * degrada p/ null — zero HTTP). É contrato do GitMainResolver, coberto à parte.
 */

/** Tabela sintética cowork_handoffs (espelha a migration; sqlite-friendly). */
function mkCoworkHandoffsTable(): void
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

/** Insere um handoff pending. */
function mkPendingHandoff(string $slug, array $files, string $body = '## ONDA A', array $over = []): CoworkHandoff
{
    return CoworkHandoff::create(array_merge([
        'slug'            => $slug,
        'version'         => 1,
        'tela'            => 'Atendimento/CaixaUnificada',
        'status'          => 'pending',
        'audited_against' => 'cb1a546',
        'body_md'         => $body,
        'files_json'      => $files,
        'source_hash'     => str_repeat('a', 64),
        'sig'             => str_repeat('b', 64),
        'created_by'      => 'CC',
        'created_at'      => now(),
    ], $over));
}

/** User-stub MCP (Authenticatable + can() injetável). */
function mkHandoffUser(bool $granted): Authenticatable
{
    return new class($granted) implements Authenticatable
    {
        public function __construct(private bool $granted) {}

        public function can($abilities, $arguments = []): bool
        {
            return $this->granted;
        }

        public function getAuthIdentifierName()
        {
            return 'id';
        }

        public function getAuthIdentifier()
        {
            return 7;
        }

        public function getAuthPasswordName()
        {
            return 'password';
        }

        public function getAuthPassword()
        {
            return '';
        }

        public function getRememberToken()
        {
            return '';
        }

        public function setRememberToken($value) {}

        public function getRememberTokenName()
        {
            return 'remember_token';
        }
    };
}

function actHandoffUser(?Authenticatable $user): void
{
    app('auth')->resolveUsersUsing(fn ($guard = null) => $user);
}

function handoffRespArray(McpResponse $response): array
{
    return json_decode((string) $response->content(), true) ?: [];
}

beforeEach(function () {
    mkCoworkHandoffsTable();
});

afterEach(function () {
    app('auth')->resolveUsersUsing(fn ($guard = null) => null);
    if (Schema::hasTable('cowork_handoffs')) {
        Schema::drop('cowork_handoffs');
    }
});

// ─── handoff-pending ──────────────────────────────────────────────────────────

it('pending lista metadados SEM body quando não passa slug [A8]', function () {
    mkPendingHandoff('caixa-mobile', ['resources/css/cockpit.css']);

    $resp = (new HandoffPendingTool())->handle(new McpRequest([]));
    $data = handoffRespArray($resp);

    expect($data['handoffs'])->toHaveCount(1);
    expect($data['handoffs'][0]['slug'])->toBe('caixa-mobile');
    expect($data['handoffs'][0])->not->toHaveKey('body_md'); // corpo só com slug
});

it('pending COM slug retorna body_md, e trunca corpo > 32k [A8]', function () {
    $big = str_repeat('x', 40000);
    mkPendingHandoff('caixa-mobile', ['resources/css/cockpit.css'], $big);

    $resp = (new HandoffPendingTool())->handle(new McpRequest(['slug' => 'caixa-mobile']));
    $item = handoffRespArray($resp)['handoffs'][0];

    expect($item)->toHaveKey('body_md');
    expect(mb_strlen($item['body_md']))->toBe(32000);
    expect($item['body_truncated'])->toBeTrue();
});

it('pending calcula conflicts_with entre pendentes nos mesmos arquivos [A5]', function () {
    mkPendingHandoff('a', ['resources/css/cockpit.css', 'x.tsx']);
    mkPendingHandoff('b', ['resources/css/cockpit.css']); // colide em cockpit.css

    $data = handoffRespArray((new HandoffPendingTool())->handle(new McpRequest([])));
    $bySlug = collect($data['handoffs'])->keyBy('slug');

    expect($bySlug['a']['conflicts_with'])->toContain('b');
    expect($bySlug['b']['conflicts_with'])->toContain('a');
});

// ─── handoff-ack ──────────────────────────────────────────────────────────────

it('ack NEGA caller sem jana.mcp.handoff.ack e NÃO muta [A7]', function () {
    mkPendingHandoff('caixa-mobile', ['x.css']);
    actHandoffUser(mkHandoffUser(granted: false));

    $resp = (new HandoffAckTool())->handle(new McpRequest([
        'slug' => 'caixa-mobile', 'outcome' => 'applied',
        'pr_url' => 'https://github.com/wagnerra23/oimpresso.com/pull/1',
        'gate_status' => ['conformance' => true, 'critique_score' => 90, 'a11y' => true],
    ]));

    expect($resp->isError())->toBeTrue();
    expect((string) $resp->content())->toContain('jana.mcp.handoff.ack');
    expect(CoworkHandoff::where('slug', 'caixa-mobile')->value('status'))->toBe('pending');
});

it('ack applied com gate verde → status applied + pr_url salvo [A3]', function () {
    mkPendingHandoff('caixa-mobile', ['x.css']);
    actHandoffUser(mkHandoffUser(granted: true));

    $resp = (new HandoffAckTool())->handle(new McpRequest([
        'slug' => 'caixa-mobile', 'outcome' => 'applied',
        'pr_url' => 'https://github.com/wagnerra23/oimpresso.com/pull/42',
        'gate_status' => ['conformance' => true, 'critique_score' => 85, 'a11y' => true],
    ]));

    expect($resp->isError())->toBeFalse();
    $row = CoworkHandoff::where('slug', 'caixa-mobile')->first();
    expect($row->status)->toBe('applied');
    expect($row->pr_url)->toBe('https://github.com/wagnerra23/oimpresso.com/pull/42');
});

it('ack applied com gate NÃO-verde → erro, fica pending [A3]', function () {
    mkPendingHandoff('caixa-mobile', ['x.css']);
    actHandoffUser(mkHandoffUser(granted: true));

    $resp = (new HandoffAckTool())->handle(new McpRequest([
        'slug' => 'caixa-mobile', 'outcome' => 'applied',
        'pr_url' => 'https://github.com/wagnerra23/oimpresso.com/pull/42',
        'gate_status' => ['conformance' => true, 'critique_score' => 70, 'a11y' => true], // <80
    ]));

    expect($resp->isError())->toBeTrue();
    expect((string) $resp->content())->toContain('gates não verdes');
    expect(CoworkHandoff::where('slug', 'caixa-mobile')->value('status'))->toBe('pending');
});

it('ack em handoff não-pendente → erro (idempotência/409)', function () {
    actHandoffUser(mkHandoffUser(granted: true));

    $resp = (new HandoffAckTool())->handle(new McpRequest([
        'slug' => 'nao-existe', 'outcome' => 'rejected', 'note' => 'qualquer',
    ]));

    expect($resp->isError())->toBeTrue();
});

it('ack rejected sem note → erro', function () {
    mkPendingHandoff('caixa-mobile', ['x.css']);
    actHandoffUser(mkHandoffUser(granted: true));

    $resp = (new HandoffAckTool())->handle(new McpRequest([
        'slug' => 'caixa-mobile', 'outcome' => 'rejected',
    ]));

    expect($resp->isError())->toBeTrue();
    expect(CoworkHandoff::where('slug', 'caixa-mobile')->value('status'))->toBe('pending');
});

it('HandoffAckTool NÃO usa Cache::flush() [A2 — grep no source]', function () {
    $src = file_get_contents(dirname(__DIR__, 2) . '/Mcp/Tools/HandoffAckTool.php');
    expect($src)->not->toContain('Cache::flush');
});
