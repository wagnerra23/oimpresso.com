<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Mcp\Request as McpRequest;
use Modules\Jana\Mcp\Tools\LgpdEsquecerTitularTool;

uses(Tests\TestCase::class);

/**
 * LgpdEsquecerTitularTool — G1 P0 (AUDIT-SENIOR-2026-05-25 §6) — smoke tool MCP.
 *
 * Cobre validações + happy path + Tier 0:
 *  001. Confirm=false retorna dry-run hint (não executa)
 *  002. Confirm=true + happy path retorna markdown estruturado
 *  003. business_id ausente retorna erro (Tier 0 IRREVOGÁVEL)
 *  004. cpf_or_cnpj ausente retorna erro
 *
 * Schema mínimo SQLite-friendly. Zero LLM call.
 *
 * @see Modules\Jana\Mcp\Tools\LgpdEsquecerTitularTool
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }

    Schema::dropIfExists('jana_mensagens');
    Schema::dropIfExists('jana_conversas');
    Schema::dropIfExists('jana_memoria_facts');
    Schema::dropIfExists('jana_cache_semantico');

    Schema::create('jana_conversas', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedInteger('business_id')->nullable();
        $t->unsignedInteger('user_id')->default(0);
        $t->string('titulo', 200)->nullable();
        $t->string('status', 20)->default('ativa');
        $t->timestamps();
    });

    Schema::create('jana_mensagens', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedBigInteger('conversa_id');
        $t->string('role', 20)->default('user');
        $t->text('content');
        $t->timestamp('created_at')->nullable();
    });

    Schema::create('jana_memoria_facts', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedInteger('business_id');
        $t->unsignedInteger('user_id');
        $t->text('fato');
        $t->text('metadata')->nullable();
        $t->timestamp('valid_from')->nullable();
        $t->timestamp('valid_until')->nullable();
        $t->softDeletes();
        $t->timestamps();
    });

    Schema::create('jana_cache_semantico', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->string('cache_key', 191)->nullable();
        $t->unsignedInteger('business_id')->nullable();
        $t->unsignedInteger('user_id')->nullable();
        $t->text('query_original')->nullable();
        $t->text('query_normalizada')->nullable();
        $t->text('query_embedding')->nullable();
        $t->text('resposta')->nullable();
        $t->text('metadata')->nullable();
        $t->unsignedInteger('hits')->default(0);
        $t->timestamp('ultimo_hit_em')->nullable();
        $t->unsignedInteger('tokens_in')->nullable();
        $t->unsignedInteger('tokens_out')->nullable();
        $t->decimal('custo_brl_original', 10, 4)->default(0);
        $t->timestamp('expira_em')->nullable();
        $t->timestamps();
    });

    // Activity log (Spatie) — JanaAuditService grava aqui via audit trail.
    Schema::dropIfExists('activity_log');
    Schema::create('activity_log', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->string('log_name')->nullable();
        $t->text('description');
        $t->nullableMorphs('subject', 'subject');
        $t->string('event')->nullable();
        $t->nullableMorphs('causer', 'causer');
        $t->json('properties')->nullable();
        $t->uuid('batch_uuid')->nullable();
        $t->timestamps();
    });
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        Schema::dropIfExists('jana_mensagens');
        Schema::dropIfExists('jana_conversas');
        Schema::dropIfExists('jana_memoria_facts');
        Schema::dropIfExists('jana_cache_semantico');
    }
});

function callLgpdTool(array $params = []): \Laravel\Mcp\Response
{
    $tool = new LgpdEsquecerTitularTool();
    $request = new McpRequest($params);

    return $tool->handle($request);
}

it('LgpdEsquecerTitularTool 001 — confirm=false retorna dry-run hint sem executar', function () {
    DB::table('jana_memoria_facts')->insert([
        'business_id' => 1,
        'user_id' => 1,
        'fato' => 'Cliente CPF 123.456.789-00', // pii-allowlist
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = callLgpdTool([
        'cpf_or_cnpj' => '123.456.789-00', // pii-allowlist
        'business_id' => 1,
        'mode' => 'anonymize',
        'confirm' => false,
    ]);

    $texto = (string) $response->content();

    expect($texto)->toContain('Pendente de confirmação')
        ->and($texto)->toContain('confirm=true');

    // Confirma que o dado NÃO foi tocado
    $row = DB::table('jana_memoria_facts')->first();
    expect($row->fato)->toBe('Cliente CPF 123.456.789-00'); // pii-allowlist
});

it('LgpdEsquecerTitularTool 002 — confirm=true + happy path retorna markdown estruturado', function () {
    DB::table('jana_memoria_facts')->insert([
        'business_id' => 1,
        'user_id' => 1,
        'fato' => 'CPF 444.555.666-77 antigo', // pii-allowlist
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = callLgpdTool([
        'cpf_or_cnpj' => '444.555.666-77', // pii-allowlist
        'business_id' => 1,
        'mode' => 'anonymize',
        'confirm' => true,
    ]);

    $texto = (string) $response->content();

    expect($texto)->toContain('DSR Art. 18 §VI')
        ->and($texto)->toContain('[OK]')
        ->and($texto)->toContain('audit_trail_id');

    // Dado foi anonimizado
    $row = DB::table('jana_memoria_facts')->first();
    expect($row->fato)->toContain('[REDACTED:CPF]');
});

it('LgpdEsquecerTitularTool 003 — business_id ausente retorna erro Tier 0', function () {
    $response = callLgpdTool([
        'cpf_or_cnpj' => '123.456.789-00', // pii-allowlist
        'confirm' => true,
    ]);

    expect((string) $response->content())->toContain('business_id');
});

it('LgpdEsquecerTitularTool 004 — cpf_or_cnpj ausente retorna erro', function () {
    $response = callLgpdTool([
        'business_id' => 1,
        'confirm' => true,
    ]);

    expect((string) $response->content())->toContain('cpf_or_cnpj');
});

it('LgpdEsquecerTitularTool 005 — modo inválido rejeita', function () {
    $response = callLgpdTool([
        'cpf_or_cnpj' => '123.456.789-00', // pii-allowlist
        'business_id' => 1,
        'mode' => 'erase-everything-please',
        'confirm' => true,
    ]);

    expect((string) $response->content())->toContain('inválido');
});
