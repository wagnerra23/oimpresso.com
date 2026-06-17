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
 * PR-7 Loop de Handoff Zero-Paste (Fase 2 · ADR 0283) — GUARD do tool handoff-lever
 * (levers re-disparar/devolver/supersede sobre cowork_handoffs).
 *
 * Provas (critério de aceite + adversário [AH]):
 *   1. sem scope jana.mcp.handoff.lever → erro (A7), NÃO muta
 *   2. action inválida → erro
 *   3. re-disparar (pending) → lápide na atual + novo pending v+1 (append-only)
 *   4. re-disparar fora de pending → erro (estado/"409"), NÃO muta
 *   5. devolver (rejected) → novo pending v+1; rejected FICA como histórico
 *   6. devolver fora de rejected → erro
 *   7. supersede (pending) → status superseded; (applied) idem
 *   8. supersede em rejected/superseded → erro
 *   9. drift: version divergente → erro (stale_view A4), NÃO muta
 *  10. append-only: nada é deletado (contagem só cresce/preserva lápides)
 *
 * Harness espelha HandoffToolsTest/HandoffSubmitToolTest (user via auth userResolver
 * + tabela sintética sqlite-friendly), com nomes ÚNICOS pra não colidir com os
 * outros testes de handoff no mesmo processo Pest.
 *
 * Tier 0 ({@see ADR 0093}): cowork_handoffs sem business_id por design.
 *
 * @see Modules\TeamMcp\Mcp\Tools\HandoffLeverTool
 * @see Modules\TeamMcp\Services\HandoffLeverService
 */

/** Tabela sintética cowork_handoffs (espelha a migration; sqlite-friendly). */
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

/** Insere um handoff (default pending v1) — over sobrescreve status/version etc. */
function mkLeverHandoff(string $slug, array $over = []): CoworkHandoff
{
    return CoworkHandoff::create(array_merge([
        'slug'            => $slug,
        'version'         => 1,
        'tela'            => 'Atendimento/CaixaUnificada',
        'status'          => 'pending',
        'audited_against' => 'cb1a546',
        'body_md'         => "## ONDA A\nDeixa o caixa flutuante.",
        'files_json'      => ['resources/css/cockpit.css'],
        'source_hash'     => str_repeat('a', 64),
        'sig'             => str_repeat('b', 64),
        'created_by'      => 'CC',
        'created_at'      => now()->subDays(5), // "parado" (stale na leitura)
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
            return 11;
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

function callLever(array $args): McpResponse
{
    return (new HandoffLeverTool())->handle(new McpRequest($args));
}

beforeEach(function () {
    mkLeverTable();
    actLeverUser(mkLeverUser(granted: true));
});

afterEach(function () {
    app('auth')->resolveUsersUsing(fn ($guard = null) => null);
    if (Schema::hasTable('cowork_handoffs')) {
        Schema::drop('cowork_handoffs');
    }
});

it('NEGA caller sem jana.mcp.handoff.lever e NÃO muta [A7]', function () {
    mkLeverHandoff('caixa-mobile');
    actLeverUser(mkLeverUser(granted: false));

    $resp = callLever(['action' => 'supersede', 'slug' => 'caixa-mobile']);

    expect($resp->isError())->toBeTrue();
    expect((string) $resp->content())->toContain('jana.mcp.handoff.lever');
    expect(CoworkHandoff::where('slug', 'caixa-mobile')->value('status'))->toBe('pending');
});

it('action inválida → erro', function () {
    mkLeverHandoff('caixa-mobile');

    $resp = callLever(['action' => 'deletar', 'slug' => 'caixa-mobile']);

    expect($resp->isError())->toBeTrue();
    expect((string) $resp->content())->toContain('action inválida');
});

it('re-disparar (pending parado) → lápide na atual + novo pending v+1 [append-only]', function () {
    mkLeverHandoff('caixa-mobile');

    $resp = callLever(['action' => 're-disparar', 'slug' => 'caixa-mobile']);

    expect($resp->isError())->toBeFalse();
    expect(leverRespArray($resp)['outcome'])->toBe('redisparado');
    // v1 vira lápide; v2 pending fresco. Nada deletado: 2 linhas.
    expect(DB::table('cowork_handoffs')->count())->toBe(2);
    expect(CoworkHandoff::where('slug', 'caixa-mobile')->where('version', 1)->value('status'))->toBe('superseded');
    expect(CoworkHandoff::where('slug', 'caixa-mobile')->where('version', 2)->value('status'))->toBe('pending');
});

it('re-disparar fora de pending → erro (estado), NÃO muta', function () {
    mkLeverHandoff('caixa-mobile', ['status' => 'applied']);

    $resp = callLever(['action' => 're-disparar', 'slug' => 'caixa-mobile']);

    expect($resp->isError())->toBeTrue();
    expect(DB::table('cowork_handoffs')->count())->toBe(1);
    expect(CoworkHandoff::where('slug', 'caixa-mobile')->value('status'))->toBe('applied');
});

it('devolver (rejected) → novo pending v+1; rejected fica histórico', function () {
    mkLeverHandoff('caixa-mobile', ['status' => 'rejected']);

    $resp = callLever(['action' => 'devolver', 'slug' => 'caixa-mobile']);

    expect($resp->isError())->toBeFalse();
    expect(leverRespArray($resp)['outcome'])->toBe('devolvido');
    expect(DB::table('cowork_handoffs')->count())->toBe(2);
    // O rejeitado NÃO vira superseded (terminal/histórico — espelha HandoffIngestService).
    expect(CoworkHandoff::where('slug', 'caixa-mobile')->where('version', 1)->value('status'))->toBe('rejected');
    expect(CoworkHandoff::where('slug', 'caixa-mobile')->where('version', 2)->value('status'))->toBe('pending');
});

it('devolver fora de rejected → erro', function () {
    mkLeverHandoff('caixa-mobile'); // pending

    $resp = callLever(['action' => 'devolver', 'slug' => 'caixa-mobile']);

    expect($resp->isError())->toBeTrue();
    expect(DB::table('cowork_handoffs')->count())->toBe(1);
    expect(CoworkHandoff::where('slug', 'caixa-mobile')->value('status'))->toBe('pending');
});

it('supersede (pending) → status superseded [lápide]', function () {
    mkLeverHandoff('caixa-mobile');

    $resp = callLever(['action' => 'supersede', 'slug' => 'caixa-mobile']);

    expect($resp->isError())->toBeFalse();
    expect(leverRespArray($resp)['outcome'])->toBe('superseded');
    // Append-only: a linha continua existindo, só vira lápide.
    expect(DB::table('cowork_handoffs')->count())->toBe(1);
    expect(CoworkHandoff::where('slug', 'caixa-mobile')->value('status'))->toBe('superseded');
});

it('supersede (applied) → status superseded', function () {
    mkLeverHandoff('caixa-mobile', ['status' => 'applied']);

    $resp = callLever(['action' => 'supersede', 'slug' => 'caixa-mobile']);

    expect($resp->isError())->toBeFalse();
    expect(CoworkHandoff::where('slug', 'caixa-mobile')->value('status'))->toBe('superseded');
});

it('supersede em rejected → erro (estado)', function () {
    mkLeverHandoff('caixa-mobile', ['status' => 'rejected']);

    $resp = callLever(['action' => 'supersede', 'slug' => 'caixa-mobile']);

    expect($resp->isError())->toBeTrue();
    expect(CoworkHandoff::where('slug', 'caixa-mobile')->value('status'))->toBe('rejected');
});

it('drift: version divergente → erro (stale_view A4), NÃO muta', function () {
    mkLeverHandoff('caixa-mobile'); // v1 pending

    $resp = callLever(['action' => 'supersede', 'slug' => 'caixa-mobile', 'version' => 2]);

    expect($resp->isError())->toBeTrue();
    expect((string) $resp->content())->toContain('fila mudou');
    expect(CoworkHandoff::where('slug', 'caixa-mobile')->value('status'))->toBe('pending');
});

it('slug inexistente → erro (not_found), NÃO cria nada', function () {
    $resp = callLever(['action' => 'supersede', 'slug' => 'nao-existe']);

    expect($resp->isError())->toBeTrue();
    expect(DB::table('cowork_handoffs')->count())->toBe(0);
});

it('opera na MAIOR versão do slug', function () {
    mkLeverHandoff('caixa-mobile', ['version' => 1, 'status' => 'superseded']);
    mkLeverHandoff('caixa-mobile', ['version' => 2, 'status' => 'pending']);

    $resp = callLever(['action' => 'supersede', 'slug' => 'caixa-mobile']);

    expect($resp->isError())->toBeFalse();
    expect(leverRespArray($resp)['version'])->toBe(2);
    expect(CoworkHandoff::where('slug', 'caixa-mobile')->where('version', 2)->value('status'))->toBe('superseded');
});

it('HandoffLeverTool NÃO usa Cache::flush() no CÓDIGO [A2]', function () {
    $src = file_get_contents(dirname(__DIR__, 2) . '/Mcp/Tools/HandoffLeverTool.php');

    // Tokeniza e descarta comentários/docblocks antes de procurar a chamada
    // (espelha o guard A2 de HandoffToolsTest) — morde só Cache::flush() real no código.
    $codeOnly = '';
    foreach (token_get_all($src) as $token) {
        if (is_array($token)) {
            if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                continue;
            }
            $codeOnly .= $token[1];
        } else {
            $codeOnly .= $token;
        }
    }

    expect($codeOnly)->not->toContain('Cache::flush');
});
