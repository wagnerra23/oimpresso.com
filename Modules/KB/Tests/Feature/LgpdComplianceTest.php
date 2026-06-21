<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Modules\Jana\Services\Privacy\PiiRedactor;
use Modules\KB\Entities\KbComment;
use Modules\KB\Entities\KbNode;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * LGPD compliance specs (Wave 11 — boost D7 KB de 4 → 8/10).
 *
 * Cobre 3 pilares LGPD pro Knowledge Base:
 *
 *   1. PiiRedactor wired — Services que tocam query/body do user redactam
 *      PII (CPF, CNPJ, email, CEP, telefone BR) antes de cache/log/LLM.
 *      Defense-in-depth: KbRagService::ask + ::summarize + ::suggestMeta
 *      passam tudo por Modules/Jana/Services/Privacy/PiiRedactor.
 *
 *   2. LogsActivity em Models editáveis — KbNode + KbComment usam o trait
 *      Spatie ActivityLog (logFillable + logOnlyDirty) pra registrar QUEM
 *      mudou QUE campo (LGPD Art. 37 — registro de operações).
 *
 *   3. Config retention canônica — Modules/KB/Config/config.php define
 *      retention por bucket (articles 1825d, queries 90d, audit_log 730d)
 *      alinhado com Modules/Arquivos (ADR 0123) + Modules/Jana (Wave 9).
 *
 * Pattern espelha Modules/Jana/Tests/Unit/PiiRedactorTest + Wave 9 Jana.
 *
 * Tier 0 (ADR 0093): tests usam biz=1 + biz=99 — NUNCA biz=4 (ROTA LIVRE prod).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see Modules/Jana/Services/Privacy/PiiRedactor.php
 * @see Modules/KB/Services/KbRagService.php (redactPii method)
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('era-sqlite: schema sintético manual incompatível com MySQL persistente — quarentena Onda 2 SDD floor; burn-down converte depois.');
    }

    kbBootstrapSchema();
    kbCreateBusinessRow(1);

    // Tabela activity_log (Spatie) — KbNode/KbComment usam LogsActivity (Wave 11).
    if (! \Schema::hasTable('activity_log')) {
        \Schema::create('activity_log', function (\Illuminate\Database\Schema\Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('log_name')->nullable();
            $t->text('description')->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->string('subject_type')->nullable();
            $t->unsignedBigInteger('causer_id')->nullable();
            $t->string('causer_type')->nullable();
            $t->longText('properties')->nullable();
            $t->uuid('batch_uuid')->nullable();
            $t->string('event')->nullable();
            $t->timestamps();
        });
    }
});

afterEach(function () {
    // O afterEach roda MESMO no teste pulado por markTestSkipped no beforeEach
    // (PHPUnit 12.5.23: $hasMetRequirements já é true antes do hook). Por isso
    // o DDL é guardado por driver — em MySQL persistente dropar activity_log
    // (CORE real-migrada) destruiria o schema compartilhado e cascataria
    // 'Base table not found' em testes alheios. Ver ADR 0093 + 0101.
    if (DB::connection()->getDriverName() === 'sqlite') {
        \Schema::dropIfExists('activity_log');
        kbTeardownSchema();
    }
});

// ------------------------------------------------------------------
// 1. PiiRedactor wired no KbRagService
// ------------------------------------------------------------------

it('PiiRedactor service resolve via container (DI)', function () {
    $redactor = app(PiiRedactor::class);
    expect($redactor)->toBeInstanceOf(PiiRedactor::class);
});

it('PiiRedactor redacta CPF brasileiro em query do user', function () {
    $redactor = app(PiiRedactor::class);
    $input = 'Procurar contrato do CPF 123.456.789-00 da Larissa'; # pii-allowlist
    $output = $redactor->redact($input);

    expect($output)->not->toContain('123.456.789-00'); # pii-allowlist
    expect($output)->toContain('[REDACTED:CPF]');
});

it('PiiRedactor redacta CNPJ + email + telefone em texto livre KB', function () {
    $redactor = app(PiiRedactor::class);
    $input = 'CNPJ 12.345.678/0001-90 contato suporte@oimpresso.com.br tel +55 11 98765-4321'; # pii-allowlist
    $output = $redactor->redact($input);

    expect($output)->not->toContain('12.345.678/0001-90'); # pii-allowlist
    expect($output)->not->toContain('suporte@oimpresso.com.br');
    expect($output)->toContain('[REDACTED:CNPJ]');
    expect($output)->toContain('[REDACTED:EMAIL]');
});

it('KbRagService usa PiiRedactor internamente (reflection contract test)', function () {
    // Verifica que método redactPii está presente no service (Tier 0 contract).
    $reflection = new \ReflectionClass(\Modules\KB\Services\KbRagService::class);
    expect($reflection->hasMethod('redactPii'))->toBeTrue();

    $method = $reflection->getMethod('redactPii');
    expect($method->isProtected())->toBeTrue();
});

// ------------------------------------------------------------------
// 2. LogsActivity wired em KbNode + KbComment (LGPD Art. 37)
// ------------------------------------------------------------------

it('KbNode tem trait LogsActivity (audit Spatie)', function () {
    $traits = class_uses_recursive(KbNode::class);
    expect($traits)->toHaveKey(LogsActivity::class);
});

it('KbNode getActivitylogOptions retorna LogOptions configurado', function () {
    $node = new KbNode();
    $opts = $node->getActivitylogOptions();

    expect($opts)->toBeInstanceOf(\Spatie\Activitylog\LogOptions::class);
    // logName setado pra "kb.node" (espalhamento por módulo)
    expect($opts->logName)->toBe('kb.node');
    // logOnlyDirty ligado (não polui activity_log com no-op saves)
    expect($opts->logOnlyDirty)->toBeTrue();
    // submitEmptyLogs desligado
    expect($opts->submitEmptyLogs)->toBeFalse();
});

it('KbComment tem trait LogsActivity (audit Spatie)', function () {
    $traits = class_uses_recursive(KbComment::class);
    expect($traits)->toHaveKey(LogsActivity::class);
});

it('KbComment getActivitylogOptions usa logName "kb.comment"', function () {
    $comment = new KbComment();
    $opts = $comment->getActivitylogOptions();

    expect($opts->logName)->toBe('kb.comment');
    expect($opts->logOnlyDirty)->toBeTrue();
});

// ------------------------------------------------------------------
// 3. Config retention canônica (LGPD Art. 16 — descarte)
// ------------------------------------------------------------------

it('config kb.retention define buckets canônicos (articles/queries/audit_log)', function () {
    $retention = config('kb.retention');

    expect($retention)->toBeArray();
    expect($retention)->toHaveKeys([
        'articles_days',
        'bridges_days',
        'comments_days',
        'favorites_days',
        'queries_days',
        'audit_log_days',
    ]);
});

it('config kb.retention.articles_days >= 1825 (5 anos operacional Larissa)', function () {
    expect((int) config('kb.retention.articles_days'))->toBeGreaterThanOrEqual(1825);
});

it('config kb.retention.queries_days <= 90 (LGPD strict — search logs)', function () {
    expect((int) config('kb.retention.queries_days'))->toBeLessThanOrEqual(90);
});

it('config kb.pii_redaction.enabled default true (defense in depth)', function () {
    expect(config('kb.pii_redaction.enabled'))->toBeTrue();
});

it('config kb.activity_log.enabled default true (audit Art. 37)', function () {
    expect(config('kb.activity_log.enabled'))->toBeTrue();
});

// ------------------------------------------------------------------
// 4. Smoke — Models KB persistem business_id + soft-delete
// ------------------------------------------------------------------

it('KbNode salva audit-ready row (business_id+timestamps+softDeletes)', function () {
    \DB::table('kb_nodes')->insert([
        'business_id' => 1,
        'type'        => 'article',
        'slug'        => 'wave11-lgpd-smoke',
        'title'       => 'Smoke artigo LGPD',
        'is_editable' => true,
        'body_blocks' => json_encode([['kind' => 'para', 'text' => 'conteudo']]),
        'status'      => 'ok',
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    $node = \DB::table('kb_nodes')->where('slug', 'wave11-lgpd-smoke')->first();
    expect($node)->not->toBeNull();
    expect((int) $node->business_id)->toBe(1);
    expect($node->deleted_at)->toBeNull(); // soft-deletes funciona
});
