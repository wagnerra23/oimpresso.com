<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Services\Privacy\RetentionPurgeService;

uses(Tests\TestCase::class);

/**
 * jana:retention-purge — G1 P0 (AUDIT-SENIOR-2026-05-25 §6) — D7.d LGPD.
 *
 * Cobre 3 invariantes críticos:
 *  001. Purge respeita retention_days config + executa anonymize (default)
 *  002. --dry-run não persiste nada (apenas count)
 *  003. Multi-tenant Tier 0: biz=1 purge NUNCA toca biz=99 (cross-tenant isolation)
 *
 * SQLite-friendly: cria schemas mínimos das tabelas Jana, sem FULLTEXT/JSON_EXTRACT.
 * Pattern dual-mode documentado em reference_tests_pest_canon.md.
 *
 * Pest mock-mode (Pattern H4): force config flag enabled=true via runtime config
 * pra command rodar sem flip env. Zero LLM call (purge é SQL puro).
 *
 * @see Modules\Jana\Console\Commands\RetentionPurgeCommand
 * @see Modules\Jana\Services\Privacy\RetentionPurgeService
 * @see Modules\Jana\Config\retention.php
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }

    // Força enabled=true via runtime — não toca .env real.
    config(['jana.retention.enabled' => true]);
    config(['jana.retention.strategy' => 'anonymize']);

    // Schema mínimo replicando jana_conversas + jana_mensagens + jana_memoria_facts.
    Schema::dropIfExists('jana_mensagens');
    Schema::dropIfExists('jana_conversas');
    Schema::dropIfExists('jana_memoria_facts');
    Schema::dropIfExists('business');

    Schema::create('business', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->string('name', 191)->nullable();
        $t->timestamps();
    });

    Schema::create('jana_conversas', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedInteger('business_id')->nullable();
        $t->unsignedInteger('user_id')->default(0);
        $t->string('titulo', 200)->nullable();
        $t->string('status', 20)->default('ativa');
        $t->timestamp('iniciada_em')->nullable();
        $t->timestamps();
    });

    Schema::create('jana_mensagens', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedBigInteger('conversa_id');
        $t->string('role', 20)->default('user');
        $t->text('content');
        $t->unsignedInteger('tokens_in')->nullable();
        $t->unsignedInteger('tokens_out')->nullable();
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

    // Seed 2 businesses (Tier 0 isolation tests)
    DB::table('business')->insert([
        ['id' => 1, 'name' => 'Wagner WR2 superadmin'],
        ['id' => 99, 'name' => 'Outro tenant (NUNCA mexer)'],
    ]);
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        Schema::dropIfExists('jana_mensagens');
        Schema::dropIfExists('jana_conversas');
        Schema::dropIfExists('jana_memoria_facts');
        Schema::dropIfExists('business');
    }
});

it('RetentionPurge 001 — anonimiza memoria_fato fora do TTL preservando row (default strategy)', function () {
    // memoria_fato TTL = 1825d → cria 1 row 2000d antiga (fora TTL) + 1 row hoje (dentro)
    DB::table('jana_memoria_facts')->insert([
        [
            'business_id' => 1,
            'user_id' => 1,
            'fato' => 'Cliente CPF 123.456.789-00 prefere boleto', // pii-allowlist
            'created_at' => now()->subDays(2000),
            'updated_at' => now()->subDays(2000),
        ],
        [
            'business_id' => 1,
            'user_id' => 1,
            'fato' => 'Outro fato recente do mesmo cliente',
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ],
    ]);

    // Chama service diretamente pra diagnóstico antes do command artisan
    /** @var RetentionPurgeService $svc */
    $svc = app(RetentionPurgeService::class);
    $diagResult = $svc->purgeEntity(
        businessId: 1,
        entityKey: 'memoria_fato',
        retentionDaysOverride: null,
        dryRun: false,
    );

    expect($diagResult['error'])->toBeNull()
        ->and($diagResult['rows_matched'])->toBeGreaterThan(0);

    // Row antiga (2000d) deve ter sido anonimizada — CPF redactado
    $antiga = DB::table('jana_memoria_facts')
        ->where('business_id', 1)
        ->where('created_at', '<', now()->subDays(1000))
        ->first();

    expect($antiga)->not->toBeNull()
        ->and($antiga->fato)->toContain('[REDACTED:CPF]');

    // Row recente (10d) NÃO deve ter sido tocada
    $recente = DB::table('jana_memoria_facts')
        ->where('business_id', 1)
        ->where('created_at', '>', now()->subDays(100))
        ->first();

    expect($recente->fato)->toBe('Outro fato recente do mesmo cliente');
});

it('RetentionPurge 002 — dry-run não persiste nada', function () {
    DB::table('jana_memoria_facts')->insert([
        'business_id' => 1,
        'user_id' => 1,
        'fato' => 'Cliente CPF 111.222.333-44 fato antigo', // pii-allowlist
        'created_at' => now()->subDays(2000),
        'updated_at' => now()->subDays(2000),
    ]);

    $exit = Artisan::call('jana:retention-purge', [
        '--business' => '1',
        '--entity' => 'memoria_fato',
        '--dry-run' => true,
    ]);

    expect($exit)->toBe(0);

    // Dry-run não muda o conteúdo — CPF deve continuar intacto
    $row = DB::table('jana_memoria_facts')->where('business_id', 1)->first();
    expect($row->fato)->toBe('Cliente CPF 111.222.333-44 fato antigo') // pii-allowlist
        ->and($row->fato)->not->toContain('[REDACTED');
});

it('RetentionPurge 003 — Tier 0: biz=1 purge NUNCA toca biz=99 (cross-tenant isolation)', function () {
    // Insere 2 rows antigas — biz=1 e biz=99 — com mesma PII
    DB::table('jana_memoria_facts')->insert([
        [
            'business_id' => 1,
            'user_id' => 1,
            'fato' => 'biz=1: cliente CPF 123.456.789-00', // pii-allowlist
            'created_at' => now()->subDays(2000),
            'updated_at' => now()->subDays(2000),
        ],
        [
            'business_id' => 99,
            'user_id' => 1,
            'fato' => 'biz=99: cliente CPF 123.456.789-00 NUNCA TOCAR', // pii-allowlist
            'created_at' => now()->subDays(2000),
            'updated_at' => now()->subDays(2000),
        ],
    ]);

    $exit = Artisan::call('jana:retention-purge', [
        '--business' => '1',
        '--entity' => 'memoria_fato',
    ]);

    expect($exit)->toBe(0);

    // biz=99 deve estar INTOCADA mesmo com a mesma PII + mesma idade
    $biz99 = DB::table('jana_memoria_facts')->where('business_id', 99)->first();
    expect($biz99->fato)->toBe('biz=99: cliente CPF 123.456.789-00 NUNCA TOCAR') // pii-allowlist
        ->and($biz99->fato)->not->toContain('[REDACTED');

    // biz=1 deve estar anonimizada
    $biz1 = DB::table('jana_memoria_facts')->where('business_id', 1)->first();
    expect($biz1->fato)->toContain('[REDACTED:CPF]');
});

it('RetentionPurge 004 — service listEntities() retorna 7 entidades canon', function () {
    $service = app(RetentionPurgeService::class);

    expect($service->listEntities())
        ->toContain('conversa', 'mensagem', 'sugestao', 'cache_semantico', 'memoria_fato', 'memoria_metrica', 'health_narrative');
});

it('RetentionPurge 005 — entidade desconhecida retorna erro estruturado sem crash', function () {
    $service = app(RetentionPurgeService::class);

    $result = $service->purgeEntity(
        businessId: 1,
        entityKey: 'entidade-fantasma',
        retentionDaysOverride: null,
        dryRun: true,
    );

    expect($result['error'])->toContain('desconhecida')
        ->and($result['rows_purged'])->toBe(0);
});
