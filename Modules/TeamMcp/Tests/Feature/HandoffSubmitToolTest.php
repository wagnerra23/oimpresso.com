<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Mcp\Request as McpRequest;
use Laravel\Mcp\Response as McpResponse;
use Modules\TeamMcp\Entities\CoworkHandoff;
use Modules\TeamMcp\Entities\McpIngestHeartbeat;
use Modules\TeamMcp\Mcp\Tools\HandoffSubmitTool;

uses(Tests\TestCase::class);

/**
 * PR-6a Loop de Handoff Zero-Paste (Fase 0 · ADR 0283) — GUARD do tool
 * handoff-submit (landing-pad HTTP assinado).
 *
 * Provas (critério de aceite + adversário [AH]):
 *   1. sig válida + scope → cria pending com campos certos + pulsa heartbeat
 *   2. sig inválida → erro (A1), NÃO insere, NÃO pulsa heartbeat
 *   3. re-submit idêntico (mesmo source_hash) → no-op, não duplica (idempotência)
 *   4. revisão de 'applied' → nova version pending + anterior superseded (A6)
 *   5. sem scope jana.mcp.handoff.submit → erro (A7), NÃO muta
 *   6. campos obrigatórios faltando → erro
 *
 * Harness espelha HandoffToolsTest (user via auth userResolver + tabela sintética),
 * com nomes ÚNICOS pra não colidir com HandoffIngestTest/HandoffToolsTest no mesmo
 * processo Pest. A assinatura usa a MESMA definição de body do service (corpo
 * CRLF→LF) — contrato self-consistente.
 *
 * Tier 0 ({@see ADR 0093}): cowork_handoffs/mcp_ingest_heartbeat sem business_id.
 *
 * @see Modules\TeamMcp\Mcp\Tools\HandoffSubmitTool
 * @see Modules\TeamMcp\Services\HandoffIngestService
 */

const HANDOFF_SUBMIT_SECRET = 'segredo-de-teste-hmac-pr6';

/** Tabelas sintéticas (espelham as migrations; sqlite-friendly). */
function mkSubmitTables(): void
{
    foreach (['cowork_handoffs', 'mcp_ingest_heartbeat'] as $t) {
        if (Schema::hasTable($t)) {
            Schema::drop($t);
        }
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

    Schema::create('mcp_ingest_heartbeat', function ($t) {
        $t->bigIncrements('id');
        $t->string('host', 500)->unique();
        $t->timestamp('last_ingest_at')->nullable();
        $t->string('last_session_uuid', 36)->nullable();
        $t->unsignedBigInteger('msgs_acc')->default(0);
        $t->timestamps();
    });
}

/** Assina o corpo igual ao service (CRLF→LF antes do HMAC). */
function signSubmitBody(string $body, string $secret = HANDOFF_SUBMIT_SECRET): string
{
    return hash_hmac('sha256', str_replace("\r\n", "\n", $body), $secret);
}

/** User-stub MCP (Authenticatable + can() injetável). */
function mkSubmitUser(bool $granted): Authenticatable
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
            return 9;
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

function actSubmitUser(?Authenticatable $user): void
{
    app('auth')->resolveUsersUsing(fn ($guard = null) => $user);
}

function submitRespArray(McpResponse $response): array
{
    return json_decode((string) $response->content(), true) ?: [];
}

/** Payload base de um submit válido (corpo + sig coerentes). */
function submitArgs(string $slug, string $body, array $over = []): array
{
    return array_merge([
        'slug'            => $slug,
        'body_md'         => $body,
        'sig'             => signSubmitBody($body),
        'files_json'      => ['resources/css/cockpit.css'],
        'tela'            => 'Atendimento/CaixaUnificada',
        'created_by'      => 'CC',
        'audited_against' => 'cb1a546',
    ], $over);
}

beforeEach(function () {
    mkSubmitTables();
    config(['teammcp.handoff_secret' => HANDOFF_SUBMIT_SECRET]);
    actSubmitUser(mkSubmitUser(granted: true));
});

afterEach(function () {
    app('auth')->resolveUsersUsing(fn ($guard = null) => null);
    foreach (['cowork_handoffs', 'mcp_ingest_heartbeat'] as $t) {
        if (Schema::hasTable($t)) {
            Schema::drop($t);
        }
    }
});

it('submit assinado + scope → cria pending e pulsa heartbeat', function () {
    $resp = (new HandoffSubmitTool())->handle(new McpRequest(
        submitArgs('caixa-mobile', "## ONDA A\nDeixa o caixa flutuante no mobile.")
    ));

    expect($resp->isError())->toBeFalse();
    $data = submitRespArray($resp);
    expect($data['ok'])->toBeTrue();
    expect($data['outcome'])->toBe('created');

    $row = CoworkHandoff::where('slug', 'caixa-mobile')->first();
    expect($row)->not->toBeNull();
    expect($row->status)->toBe('pending');
    expect($row->version)->toBe(1);
    expect($row->files_json)->toContain('resources/css/cockpit.css');
    expect($row->audited_against)->toBe('cb1a546');

    // Heartbeat pulsou (transporte vivo).
    expect(McpIngestHeartbeat::where('host', 'handoff-submit')->value('last_ingest_at'))->not->toBeNull();
});

it('submit com sig inválida → erro (A1), NÃO insere nem pulsa heartbeat', function () {
    $resp = (new HandoffSubmitTool())->handle(new McpRequest(
        submitArgs('injetado', "## rm -rf /\ncoisa ruim", ['sig' => str_repeat('0', 64)])
    ));

    expect($resp->isError())->toBeTrue();
    expect((string) $resp->content())->toContain('assinatura inválida');
    expect(DB::table('cowork_handoffs')->count())->toBe(0);
    expect(DB::table('mcp_ingest_heartbeat')->count())->toBe(0);
});

it('re-submit idêntico → no-op, não duplica (idempotência)', function () {
    $args = submitArgs('caixa-mobile', "## ONDA A\nmesmo corpo");

    (new HandoffSubmitTool())->handle(new McpRequest($args));
    $resp = (new HandoffSubmitTool())->handle(new McpRequest($args));

    expect($resp->isError())->toBeFalse();
    expect(submitRespArray($resp)['outcome'])->toBe('no_op');
    expect(DB::table('cowork_handoffs')->count())->toBe(1);
});

it('revisão de applied → nova version pending + anterior superseded [A6]', function () {
    (new HandoffSubmitTool())->handle(new McpRequest(submitArgs('caixa-mobile', "## ONDA A\nv1")));
    CoworkHandoff::where('slug', 'caixa-mobile')->update(['status' => 'applied']);

    $resp = (new HandoffSubmitTool())->handle(new McpRequest(submitArgs('caixa-mobile', "## ONDA A\nv2 corrigido")));

    expect($resp->isError())->toBeFalse();
    expect(submitRespArray($resp)['outcome'])->toBe('revised');
    expect(DB::table('cowork_handoffs')->count())->toBe(2);
    expect(CoworkHandoff::where('slug', 'caixa-mobile')->where('version', 1)->value('status'))->toBe('superseded');
    expect(CoworkHandoff::where('slug', 'caixa-mobile')->where('version', 2)->value('status'))->toBe('pending');
});

it('submit sem scope jana.mcp.handoff.submit → erro (A7), NÃO muta', function () {
    actSubmitUser(mkSubmitUser(granted: false));

    $resp = (new HandoffSubmitTool())->handle(new McpRequest(
        submitArgs('caixa-mobile', "## ONDA A\nqualquer")
    ));

    expect($resp->isError())->toBeTrue();
    expect((string) $resp->content())->toContain('jana.mcp.handoff.submit');
    expect(DB::table('cowork_handoffs')->count())->toBe(0);
});

it('submit com campos obrigatórios faltando → erro', function () {
    $resp = (new HandoffSubmitTool())->handle(new McpRequest([
        'slug' => 'caixa-mobile', // sem body_md/sig
    ]));

    expect($resp->isError())->toBeTrue();
    expect(DB::table('cowork_handoffs')->count())->toBe(0);
});
