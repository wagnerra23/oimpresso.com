<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Mcp\Request as McpRequest;
use Laravel\Mcp\Response as McpResponse;
use Modules\TeamMcp\Entities\CoworkHandoff;
use Modules\TeamMcp\Mcp\Tools\HandoffLeverTool;

uses(Tests\TestCase::class);

/**
 * PR-7 Loop de Handoff Zero-Paste (Fase 2 · ADR 0283) — GUARD do tool
 * handoff-lever (re-disparar / devolver / supersede).
 *
 * Provas (critério de aceite + adversário [AH]):
 *   1. supersede em pending → status superseded (sai da lista ativa, append-only)
 *   2. devolver em rejected → volta a pending + limpa o ack (pr_url/gate/applied_*)
 *   3. re-disparar em pending velho (stale) → re-arma created_at (un-stale)
 *   4. idempotência: lever fora do status de origem → erro, NÃO muta
 *   5. sem scope jana.mcp.handoff.lever → erro (A7), NÃO muta
 *   6. action inválida → erro
 *
 * Harness espelha HandoffSubmitToolTest (user via auth userResolver + tabela
 * sintética sqlite-friendly), com nomes ÚNICOS pra não colidir no mesmo processo Pest.
 *
 * Tier 0 ({@see ADR 0093}): cowork_handoffs sem business_id.
 *
 * @see Modules\TeamMcp\Mcp\Tools\HandoffLeverTool
 */

/** Tabela sintética (espelha a migration; sqlite-friendly). */
function mkLeverTable(): void
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

/** Insere um handoff direto (sem passar pelo ingest) pra montar o cenário. */
function mkLeverHandoff(string $slug, array $over = []): CoworkHandoff
{
    return CoworkHandoff::create(array_merge([
        'slug'        => $slug,
        'version'     => 1,
        'tela'        => 'Atendimento/CaixaUnificada',
        'status'      => 'pending',
        'body_md'     => "## ONDA A\nDeixa o caixa flutuante no mobile.",
        'files_json'  => ['resources/css/cockpit.css'],
        'source_hash' => str_repeat('a', 64),
        'sig'         => str_repeat('b', 64),
        'created_by'  => 'CC',
        'created_at'  => now(),
    ], $over));
}

/** User-stub MCP (Authenticatable + can() injetável). */
function mkLeverUser(bool $granted): Authenticatable
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

function actLeverUser(?Authenticatable $user): void
{
    app('auth')->resolveUsersUsing(fn ($guard = null) => $user);
}

function leverRespArray(McpResponse $response): array
{
    return json_decode((string) $response->content(), true) ?: [];
}

beforeEach(function () {
    mkLeverTable();
    actLeverUser(mkLeverUser(granted: true));
});

afterEach(function () {
    actLeverUser(null);
    if (Schema::hasTable('cowork_handoffs')) {
        Schema::drop('cowork_handoffs');
    }
});

it('supersede em pending → status superseded', function () {
    mkLeverHandoff('caixa-lever');

    $resp = (new HandoffLeverTool())->handle(new McpRequest([
        'slug' => 'caixa-lever', 'action' => 'supersede',
    ]));

    expect($resp->isError())->toBeFalse();
    $data = leverRespArray($resp);
    expect($data['ok'])->toBeTrue();
    expect($data['from_status'])->toBe('pending');
    expect($data['to_status'])->toBe('superseded');
    expect(CoworkHandoff::where('slug', 'caixa-lever')->value('status'))->toBe('superseded');
});

it('devolver em rejected → volta a pending e limpa o ack', function () {
    mkLeverHandoff('caixa-lever', [
        'status'      => 'rejected',
        'applied_at'  => now(),
        'applied_by'  => 'CC',
        'pr_url'      => 'https://github.com/x/y/pull/1',
        'gate_status' => ['conformance' => false, 'critique_score' => 40, 'a11y' => false],
    ]);

    $resp = (new HandoffLeverTool())->handle(new McpRequest([
        'slug' => 'caixa-lever', 'action' => 'devolver', 'note' => 'refaz o header',
    ]));

    expect($resp->isError())->toBeFalse();
    expect(leverRespArray($resp)['to_status'])->toBe('pending');

    $row = CoworkHandoff::where('slug', 'caixa-lever')->first();
    expect($row->status)->toBe('pending');
    expect($row->pr_url)->toBeNull();
    expect($row->gate_status)->toBeNull();
    expect($row->applied_at)->toBeNull();
    expect($row->applied_by)->toBeNull();
});

it('re-disparar em pending velho → re-arma created_at (un-stale)', function () {
    // 10 dias atrás = stale (ForjaMcpService usa > 3d).
    mkLeverHandoff('caixa-lever', ['created_at' => now()->subDays(10)]);

    $resp = (new HandoffLeverTool())->handle(new McpRequest([
        'slug' => 'caixa-lever', 'action' => 're-disparar',
    ]));

    expect($resp->isError())->toBeFalse();
    expect(leverRespArray($resp)['to_status'])->toBe('pending');

    $row = CoworkHandoff::where('slug', 'caixa-lever')->first();
    expect($row->status)->toBe('pending');
    // Re-armado: created_at fresco (não mais > 3d).
    expect($row->created_at->gt(now()->subMinute()))->toBeTrue();
});

it('idempotência: lever fora do status de origem → erro, NÃO muta', function () {
    mkLeverHandoff('caixa-lever', ['status' => 'superseded']);

    // devolver só morde em 'rejected'; superseded não é origem de nada.
    $resp = (new HandoffLeverTool())->handle(new McpRequest([
        'slug' => 'caixa-lever', 'action' => 'devolver',
    ]));

    expect($resp->isError())->toBeTrue();
    expect((string) $resp->content())->toContain('idempotentes');
    expect(CoworkHandoff::where('slug', 'caixa-lever')->value('status'))->toBe('superseded');
});

it('sem scope jana.mcp.handoff.lever → erro (A7), NÃO muta', function () {
    mkLeverHandoff('caixa-lever');
    actLeverUser(mkLeverUser(granted: false));

    $resp = (new HandoffLeverTool())->handle(new McpRequest([
        'slug' => 'caixa-lever', 'action' => 'supersede',
    ]));

    expect($resp->isError())->toBeTrue();
    expect((string) $resp->content())->toContain('jana.mcp.handoff.lever');
    expect(CoworkHandoff::where('slug', 'caixa-lever')->value('status'))->toBe('pending');
});

it('action inválida → erro', function () {
    mkLeverHandoff('caixa-lever');

    $resp = (new HandoffLeverTool())->handle(new McpRequest([
        'slug' => 'caixa-lever', 'action' => 'merge',
    ]));

    expect($resp->isError())->toBeTrue();
    expect(CoworkHandoff::where('slug', 'caixa-lever')->value('status'))->toBe('pending');
});
