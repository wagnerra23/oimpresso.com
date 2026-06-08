<?php

declare(strict_types=1);

use Modules\KB\Entities\KbComment;
use Modules\KB\Entities\KbFavorite;
use Modules\KB\Entities\KbNode;

/**
 * Multi-tenant trait + global scope unit specs (Wave 11 — boost D2 KB).
 *
 * Cobre BelongsToBusinessTrait isolation NO MODEL LEVEL — sem dep HTTP.
 * Complementa CrossTenantIsolationTest (HTTP) com nível mais baixo:
 *
 *   - Auto-fill business_id via creating() event quando session populada
 *   - Global scope `business_id` filtra Eloquent query automaticamente
 *   - withoutGlobalScopes preserva acesso superadmin (mas marcado)
 *   - Session de biz=1 NÃO enxerga rows de biz=99 via Model normal
 *
 * Tier 0 (ADR 0093): biz=1 + biz=99 — NUNCA biz=4 (ROTA LIVRE prod).
 *
 * @see Modules/KB/Entities/Concerns/BelongsToBusinessTrait.php
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 */

beforeEach(function () {
    kbBootstrapSchema();
    kbCreateBusinessRow(1);
    kbCreateBusinessRow(99);

    // Tabela activity_log (Spatie) — KbNode/KbComment usam LogsActivity (Wave 11).
    // Schema mínimo só pros tests; Modules/KB/Tests/Helpers.php é shared (compatibilidade).
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
    \Schema::dropIfExists('activity_log');
    kbTeardownSchema();
});

// ------------------------------------------------------------------
// Global scope: session biz=1 só vê rows biz=1
// ------------------------------------------------------------------

it('global scope filtra KbNode por session.user.business_id', function () {
    // Seed 1 node em cada business (raw, bypass scope/auto-fill).
    \DB::table('kb_nodes')->insert([
        ['business_id' => 1,  'type' => 'article', 'slug' => 'biz1-node', 'title' => 'Biz 1',
         'is_editable' => true, 'body_blocks' => json_encode([['kind'=>'para','text'=>'a']]),
         'status' => 'ok', 'created_at' => now(), 'updated_at' => now()],
        ['business_id' => 99, 'type' => 'article', 'slug' => 'biz99-node', 'title' => 'Biz 99',
         'is_editable' => true, 'body_blocks' => json_encode([['kind'=>'para','text'=>'b']]),
         'status' => 'ok', 'created_at' => now(), 'updated_at' => now()],
    ]);

    // Sessão de biz=1: KbNode::all() retorna SÓ rows biz=1
    kbActAsUser(bizId: 1, permissions: []);

    $nodes = KbNode::all();
    expect($nodes)->toHaveCount(1);
    expect($nodes->first()->slug)->toBe('biz1-node');
    expect((int) $nodes->first()->business_id)->toBe(1);
});

it('global scope: session biz=99 NÃO enxerga node biz=1', function () {
    \DB::table('kb_nodes')->insert([
        'business_id' => 1, 'type' => 'article', 'slug' => 'segredo-biz1',
        'title' => 'Segredo biz 1', 'is_editable' => true,
        'body_blocks' => json_encode([['kind'=>'para','text'=>'confidencial']]),
        'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
    ]);

    kbActAsUser(bizId: 99, permissions: []);

    $found = KbNode::where('slug', 'segredo-biz1')->first();
    expect($found)->toBeNull();
});

// ------------------------------------------------------------------
// Auto-fill business_id via creating() event
// ------------------------------------------------------------------

it('creating() auto-popula business_id da sessão quando Model::create sem biz', function () {
    kbActAsUser(bizId: 1, permissions: []);

    // Cria sem passar business_id explícito (deveria ser auto-preenchido)
    $node = KbNode::create([
        'type'        => 'article',
        'slug'        => 'auto-fill-biz',
        'title'       => 'Auto-fill test',
        'is_editable' => true,
        'body_blocks' => [['kind' => 'para', 'text' => 'sem biz no create']],
        'status'      => 'ok',
    ]);

    expect((int) $node->business_id)->toBe(1);

    // Confere na DB
    $row = \DB::table('kb_nodes')->where('slug', 'auto-fill-biz')->first();
    expect((int) $row->business_id)->toBe(1);
});

it('creating() NÃO sobrescreve business_id quando explícito', function () {
    kbActAsUser(bizId: 1, permissions: []);

    // Tenta criar com biz=99 explícito (Tier 0 superadmin scenario — ADR 0093 §4).
    // Trait NÃO deve sobrescrever; valor explícito vence.
    $node = new KbNode([
        'business_id' => 99,
        'type'        => 'article',
        'slug'        => 'explicit-biz99',
        'title'       => 'Explicit 99',
        'is_editable' => true,
        'body_blocks' => [['kind' => 'para', 'text' => 'x']],
        'status'      => 'ok',
    ]);
    $node->save();

    expect((int) $node->business_id)->toBe(99);
});

// ------------------------------------------------------------------
// withoutGlobalScopes — superadmin escape valve (com comentário)
// ------------------------------------------------------------------

it('withoutGlobalScopes permite ver rows de outros businesses (superadmin path)', function () {
    \DB::table('kb_nodes')->insert([
        ['business_id' => 1,  'type' => 'article', 'slug' => 'n1', 'title' => 'n1',
         'is_editable' => true, 'body_blocks' => json_encode([['kind'=>'para','text'=>'a']]),
         'status' => 'ok', 'created_at' => now(), 'updated_at' => now()],
        ['business_id' => 99, 'type' => 'article', 'slug' => 'n99', 'title' => 'n99',
         'is_editable' => true, 'body_blocks' => json_encode([['kind'=>'para','text'=>'b']]),
         'status' => 'ok', 'created_at' => now(), 'updated_at' => now()],
    ]);

    kbActAsUser(bizId: 1, permissions: []);

    // Default scope: 1 row
    expect(KbNode::all())->toHaveCount(1);

    // SUPERADMIN: withoutGlobalScopes → 2 rows
    expect(KbNode::withoutGlobalScopes()->get())->toHaveCount(2);
});

// ------------------------------------------------------------------
// Cobertura de outros Models que usam o trait
// ------------------------------------------------------------------

it('KbComment usa BelongsToBusinessTrait (global scope wired)', function () {
    $traits = class_uses_recursive(KbComment::class);
    expect($traits)->toHaveKey(\Modules\KB\Entities\Concerns\BelongsToBusinessTrait::class);
});

it('KbFavorite usa BelongsToBusinessTrait', function () {
    $traits = class_uses_recursive(KbFavorite::class);
    expect($traits)->toHaveKey(\Modules\KB\Entities\Concerns\BelongsToBusinessTrait::class);
});

it('KbNode usa BelongsToBusinessTrait', function () {
    $traits = class_uses_recursive(KbNode::class);
    expect($traits)->toHaveKey(\Modules\KB\Entities\Concerns\BelongsToBusinessTrait::class);
});

// ------------------------------------------------------------------
// Contract: kb_* tables têm business_id NOT NULL (defesa schema-level)
// ------------------------------------------------------------------

it('kb_nodes tem coluna business_id (Tier 0 schema-level)', function () {
    expect(\Schema::hasColumn('kb_nodes', 'business_id'))->toBeTrue();
});

it('kb_comments tem coluna business_id (Tier 0 schema-level)', function () {
    expect(\Schema::hasColumn('kb_comments', 'business_id'))->toBeTrue();
});

it('kb_favorites tem coluna business_id (Tier 0 schema-level)', function () {
    expect(\Schema::hasColumn('kb_favorites', 'business_id'))->toBeTrue();
});

it('kb_edges tem coluna business_id (Tier 0 schema-level)', function () {
    expect(\Schema::hasColumn('kb_edges', 'business_id'))->toBeTrue();
});
