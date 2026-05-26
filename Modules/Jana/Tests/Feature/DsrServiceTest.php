<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Services\Lgpd\DsrEsquecimentoResult;
use Modules\Jana\Services\Lgpd\DsrService;

uses(Tests\TestCase::class);

/**
 * DsrService — G1 P0 (AUDIT-SENIOR-2026-05-25 §6) — D7.e LGPD Art. 18 §VI.
 *
 * Cobre:
 *  001. esquecerTitular() anonimiza refs cross-entities + preserva audit
 *  002. Prazo legal LGPD <30d cumprido (latência síncrona <5s típico)
 *  003. Multi-tenant Tier 0: biz=1 esquecer NUNCA toca biz=99 com mesmo CPF
 *  004. Documento inválido retorna status=failed sem crash
 *
 * Zero LLM call — DSR é busca SQL + anonimização via PiiRedactor.
 * Schema mínimo SQLite-friendly (jana_conversas + jana_mensagens + jana_memoria_facts).
 *
 * @see Modules\Jana\Services\Lgpd\DsrService
 * @see Modules\Jana\Services\Lgpd\DsrEsquecimentoResult
 */
beforeEach(function () {
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
    Schema::dropIfExists('jana_mensagens');
    Schema::dropIfExists('jana_conversas');
    Schema::dropIfExists('jana_memoria_facts');
    Schema::dropIfExists('jana_cache_semantico');
});

it('DsrService 001 — esquecerTitular() anonimiza refs cross-entities', function () {
    // Seed: titular CPF 123.456.789-00 espalhado em 3 entities biz=1
    $convId = DB::table('jana_conversas')->insertGetId([
        'business_id' => 1,
        'user_id' => 1,
        'titulo' => 'Chat com cliente CPF 123.456.789-00',
        'status' => 'ativa',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('jana_mensagens')->insert([
        'conversa_id' => $convId,
        'role' => 'user',
        'content' => 'Falei com cliente CPF 123.456.789-00 ontem',
        'created_at' => now(),
    ]);

    DB::table('jana_memoria_facts')->insert([
        'business_id' => 1,
        'user_id' => 1,
        'fato' => 'Cliente 12345678900 prefere boleto',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('jana_cache_semantico')->insert([
        'cache_key' => 'cache-xyz',
        'business_id' => 1,
        'user_id' => 1,
        'query_original' => 'sobre CPF 123.456.789-00',
        'resposta' => 'cliente 123.456.789-00 tem 3 vendas',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    /** @var DsrService $dsr */
    $dsr = app(DsrService::class);
    $result = $dsr->esquecerTitular(
        cpfOuCnpj: '123.456.789-00',
        businessId: 1,
        mode: 'anonymize',
    );

    expect($result)->toBeInstanceOf(DsrEsquecimentoResult::class)
        ->and($result->status)->toBe('ok')
        ->and($result->totalRefsEncontradas())->toBeGreaterThan(0)
        ->and($result->totalAcaoTomada())->toBeGreaterThan(0);

    // Verifica que PII foi redactada em todas entities
    $msg = DB::table('jana_mensagens')->first();
    expect($msg->content)->toContain('[REDACTED:CPF]');

    $fato = DB::table('jana_memoria_facts')->first();
    // 12345678900 (sem formatação) também deve ter sido detectado pelo PiiRedactor
    expect($fato->fato)->toContain('[REDACTED:CPF]');

    $cache = DB::table('jana_cache_semantico')->first();
    expect($cache->resposta)->toContain('[REDACTED:CPF]');

    $conv = DB::table('jana_conversas')->first();
    expect($conv->titulo)->toContain('[REDACTED:CPF]');

    // Audit trail UUID estável
    expect($result->auditTrailId)->toMatch('/^[0-9a-f-]{36}$/');
});

it('DsrService 002 — prazo legal LGPD <30d cumprido (latência síncrona <5s típico)', function () {
    // Insere 100 fatos do mesmo titular biz=1 — bench síncrono
    $rows = [];
    for ($i = 0; $i < 100; $i++) {
        $rows[] = [
            'business_id' => 1,
            'user_id' => 1,
            'fato' => "Fato {$i} do CPF 987.654.321-00",
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
    DB::table('jana_memoria_facts')->insert($rows);

    /** @var DsrService $dsr */
    $dsr = app(DsrService::class);
    $result = $dsr->esquecerTitular(
        cpfOuCnpj: '987.654.321-00',
        businessId: 1,
        mode: 'anonymize',
    );

    expect($result->status)->toBe('ok')
        // <30s pra 100 rows é mais que folgado (típico <100ms)
        ->and($result->durationMs)->toBeLessThan(30_000)
        ->and($result->totalAcaoTomada())->toBe(100);
});

it('DsrService 003 — Tier 0: biz=1 esquecer NUNCA toca biz=99 (mesmo CPF)', function () {
    // Mesmo CPF, 2 businesses diferentes — Tier 0 IRREVOGÁVEL
    DB::table('jana_memoria_facts')->insert([
        [
            'business_id' => 1,
            'user_id' => 1,
            'fato' => 'biz=1: CPF 555.444.333-22',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'business_id' => 99,
            'user_id' => 1,
            'fato' => 'biz=99: CPF 555.444.333-22 NUNCA TOCAR',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    /** @var DsrService $dsr */
    $dsr = app(DsrService::class);
    $result = $dsr->esquecerTitular(
        cpfOuCnpj: '555.444.333-22',
        businessId: 1,
        mode: 'anonymize',
    );

    expect($result->status)->toBe('ok');

    // biz=99 INTOCADA — Tier 0 IRREVOGÁVEL
    $biz99 = DB::table('jana_memoria_facts')->where('business_id', 99)->first();
    expect($biz99->fato)->toBe('biz=99: CPF 555.444.333-22 NUNCA TOCAR')
        ->and($biz99->fato)->not->toContain('[REDACTED');

    // biz=1 anonimizada
    $biz1 = DB::table('jana_memoria_facts')->where('business_id', 1)->first();
    expect($biz1->fato)->toContain('[REDACTED:CPF]');
});

it('DsrService 004 — documento inválido retorna status=failed sem crash', function () {
    /** @var DsrService $dsr */
    $dsr = app(DsrService::class);

    $result = $dsr->esquecerTitular(
        cpfOuCnpj: 'abc123',
        businessId: 1,
        mode: 'anonymize',
    );

    expect($result->status)->toBe('failed')
        ->and($result->errorMessage)->not->toBeNull()
        ->and($result->errorMessage)->toContain('inv')
        ->and($result->totalRefsEncontradas())->toBe(0);
});

it('DsrService 005 — modo hard delete remove rows (não anonimiza)', function () {
    DB::table('jana_memoria_facts')->insert([
        'business_id' => 1,
        'user_id' => 1,
        'fato' => 'CPF 111.111.111-11 antigo',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('jana_memoria_facts')->where('business_id', 1)->count())->toBe(1);

    /** @var DsrService $dsr */
    $dsr = app(DsrService::class);
    $result = $dsr->esquecerTitular(
        cpfOuCnpj: '111.111.111-11',
        businessId: 1,
        mode: 'hard',
    );

    expect($result->status)->toBe('ok')
        // Hard delete: row deve ter sumido
        ->and(DB::table('jana_memoria_facts')->where('business_id', 1)->count())->toBe(0);

    // Stats refletem rows_deleted (não rows_anonymized)
    expect($result->refsByEntity['memoria_fato']['rows_deleted'])->toBeGreaterThan(0)
        ->and($result->refsByEntity['memoria_fato']['rows_anonymized'])->toBe(0);
});
