<?php

declare(strict_types=1);

use Modules\KB\Entities\KbNode;

/**
 * Unit specs do Model KbNode.
 *
 * Contrato:
 *   - memory/requisitos/KB/SCHEMA-DB-V1.md §3 (DDL)
 *   - ADR 0093 multi-tenant Tier 0 IRREVOGÁVEL
 *   - ADR 0061 zero auto-mem privada / ADRs append-only via bridge
 *   - ADR 0101 tests biz=1 (NUNCA biz=4 ROTA LIVRE)
 *
 * Invariante crítica (R1 da ADR 0149):
 *   is_editable=false ⇒ body_blocks IS NULL
 *
 *   Enforcement esperado em KbNodeObserver (saving event) — TODO[CL]:
 *   Agent A precisa criar Modules\KB\Observers\KbNodeObserver. Esta suite
 *   ESPERA que tentativa de save com is_editable=false + body_blocks!=null
 *   lance Exception (assertThrows + mensagem contendo "is_editable").
 */

beforeEach(function () {
    kbBootstrapSchema();
});

afterEach(function () {
    kbTeardownSchema();
});

// ─── Multi-tenant Tier 0 (R5 da ADR 0149) ──────────────────────────────────

it('scopes by business_id (biz=1 NAO ve biz=99)', function () {
    // Cria nodes em 2 businesses
    kbCreateBusinessRow(1);
    kbCreateBusinessRow(99);

    \DB::table('kb_nodes')->insert([
        'business_id' => 1,
        'type'        => 'article',
        'slug'        => 'biz1-node',
        'title'       => 'Biz 1 node',
        'is_editable' => true,
        'status'      => 'ok',
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);
    \DB::table('kb_nodes')->insert([
        'business_id' => 99,
        'type'        => 'article',
        'slug'        => 'biz99-node',
        'title'       => 'Biz 99 node',
        'is_editable' => true,
        'status'      => 'ok',
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    // Autentica como user de biz=1 (session('user.business_id')=1)
    kbActAsUser(bizId: 1, userId: 42);

    // KbNode::all() respeita global scope BelongsToBusinessTrait
    $rows = KbNode::all();
    expect($rows)->toHaveCount(1)
        ->and($rows->first()->slug)->toBe('biz1-node');
});

// ─── Invariante crítica R1: is_editable=false ⇒ body_blocks IS NULL ────────

it('rejects body_blocks when is_editable=false (bridge node invariante)', function () {
    kbActAsUser(bizId: 1);

    $node = new KbNode();
    $node->business_id = 1;
    $node->type        = 'adr';
    $node->slug        = 'invalid-bridge';
    $node->title       = 'Bridge com body invalido';
    $node->is_editable = false;
    $node->body_blocks = [['kind' => 'para', 'text' => 'NAO DEVE SER ACEITO']];

    expect(fn () => $node->save())
        ->toThrow(\Throwable::class);
    // TODO[CL]: confirmar com Agent A o tipo exato da Exception
    // (LogicException, DomainException, ou InvalidArgumentException).
    // Mensagem deve mencionar "is_editable" ou "body_blocks".
});

it('allows body_blocks when is_editable=true (artigo operacional)', function () {
    kbActAsUser(bizId: 1);

    $node = new KbNode();
    $node->business_id = 1;
    $node->type        = 'article';
    $node->slug        = 'valid-article';
    $node->title       = 'Article com body';
    $node->is_editable = true;
    $node->body_blocks = [
        ['kind' => 'h2',   'text' => 'Heading'],
        ['kind' => 'para', 'text' => 'Conteúdo.'],
    ];

    $node->save();

    expect($node->exists)->toBeTrue()
        ->and($node->fresh()->body_blocks)->toBeArray()
        ->and($node->fresh()->body_blocks)->toHaveCount(2);
});

it('allows NULL body_blocks when is_editable=false (bridge canon valido)', function () {
    kbActAsUser(bizId: 1);
    $mcpDocId = kbCreateMcpDoc(1, 'adr');

    $node = new KbNode();
    $node->business_id   = 1;
    $node->type          = 'adr';
    $node->slug          = 'valid-bridge';
    $node->title         = 'ADR 0094 bridge';
    $node->is_editable   = false;
    $node->body_blocks   = null;
    $node->source_doc_id = $mcpDocId;

    $node->save();

    expect($node->exists)->toBeTrue()
        ->and($node->fresh()->body_blocks)->toBeNull()
        ->and($node->fresh()->source_doc_id)->toBe($mcpDocId);
});

// ─── Soft delete ────────────────────────────────────────────────────────────

it('soft deletes with deleted_at populated', function () {
    kbActAsUser(bizId: 1);

    $node = new KbNode();
    $node->business_id = 1;
    $node->type        = 'article';
    $node->slug        = 'to-delete';
    $node->title       = 'Será soft-deletado';
    $node->is_editable = true;
    $node->body_blocks = [['kind' => 'para', 'text' => 'x']];
    $node->save();

    $node->delete();

    expect($node->fresh())->toBeNull(); // global scope esconde deleted_at IS NOT NULL
    // TODO[CL]: a partir do Agent A, KbNode deve usar SoftDeletes trait.
    // Confirma também via raw DB:
    $raw = \DB::table('kb_nodes')->where('slug', 'to-delete')->first();
    expect($raw)->not->toBeNull()
        ->and($raw->deleted_at)->not->toBeNull();
});

// ─── Relações (FKs) ─────────────────────────────────────────────────────────

it('belongs to business', function () {
    kbActAsUser(bizId: 1);

    $node = new KbNode();
    $node->business_id = 1;
    $node->type        = 'article';
    $node->slug        = 'has-rel';
    $node->title       = 'rel test';
    $node->is_editable = true;
    $node->body_blocks = [['kind' => 'para', 'text' => 'x']];
    $node->save();

    // Espera-se que KbNode tenha relação business() (BelongsTo App\Business)
    expect(method_exists($node, 'business'))->toBeTrue();
    // TODO[CL]: assertar instância e id depois que App\Business model existir
    // bootstrappable em test mode (precisa de Schema completo UltimatePOS).
});

it('belongs to author user', function () {
    kbActAsUser(bizId: 1);

    $node = new KbNode();
    $node->business_id    = 1;
    $node->type           = 'article';
    $node->slug           = 'author-rel';
    $node->title          = 'author test';
    $node->is_editable    = true;
    $node->body_blocks    = [['kind' => 'para', 'text' => 'x']];
    $node->author_user_id = 42;
    $node->save();

    expect(method_exists($node, 'author'))->toBeTrue();
    // TODO[CL]: confirmar nome da relação com Agent A (author vs authorUser).
});

it('belongs to category and subcategory', function () {
    kbActAsUser(bizId: 1);

    expect(method_exists(KbNode::class, 'category'))->toBeTrue()
        ->and(method_exists(KbNode::class, 'subcategory'))->toBeTrue();
});

it('belongs to source_doc (mcp_memory_documents bridge)', function () {
    kbActAsUser(bizId: 1);

    expect(method_exists(KbNode::class, 'sourceDoc'))->toBeTrue();
    // TODO[CL]: confirmar nome — sourceDoc vs source_doc vs document.
});

// ─── Casts ──────────────────────────────────────────────────────────────────

it('casts body_blocks and tags as JSON arrays', function () {
    kbActAsUser(bizId: 1);

    $node = new KbNode();
    $node->business_id = 1;
    $node->type        = 'article';
    $node->slug        = 'json-casts';
    $node->title       = 'JSON casts';
    $node->is_editable = true;
    $node->body_blocks = [['kind' => 'para', 'text' => 'A']];
    $node->tags        = ['tag1', 'tag2'];
    $node->save();

    $fresh = KbNode::where('slug', 'json-casts')->first();
    expect($fresh->body_blocks)->toBeArray()
        ->and($fresh->tags)->toBe(['tag1', 'tag2']);
});

// ─── Unique constraint (business_id, slug) ──────────────────────────────────

it('blocks duplicate slug within same business', function () {
    kbActAsUser(bizId: 1);

    $a = new KbNode();
    $a->business_id = 1; $a->type = 'article'; $a->slug = 'dup-slug';
    $a->title = 'A'; $a->is_editable = true; $a->body_blocks = [['kind' => 'para', 'text' => 'x']];
    $a->save();

    expect(function () {
        $b = new KbNode();
        $b->business_id = 1; $b->type = 'article'; $b->slug = 'dup-slug';
        $b->title = 'B'; $b->is_editable = true; $b->body_blocks = [['kind' => 'para', 'text' => 'y']];
        $b->save();
    })->toThrow(\Throwable::class);
});

it('allows same slug across different businesses', function () {
    kbCreateBusinessRow(1);
    kbCreateBusinessRow(99);
    // Insert raw pra escapar global scope na criação cross-tenant
    \DB::table('kb_nodes')->insert([
        'business_id' => 1, 'type' => 'article', 'slug' => 'same-slug',
        'title' => 'biz1', 'is_editable' => true,
        'body_blocks' => json_encode([['kind' => 'para', 'text' => 'a']]),
        'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
    ]);
    \DB::table('kb_nodes')->insert([
        'business_id' => 99, 'type' => 'article', 'slug' => 'same-slug',
        'title' => 'biz99', 'is_editable' => true,
        'body_blocks' => json_encode([['kind' => 'para', 'text' => 'b']]),
        'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(\DB::table('kb_nodes')->where('slug', 'same-slug')->count())->toBe(2);
});
