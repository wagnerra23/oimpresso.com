<?php

declare(strict_types=1);

/**
 * Feature specs — comments inline (SCHEMA-DB-V1 §11):
 *   POST   /kb/nodes/{slug}/comments  — cria comment com block_idx
 *   DELETE /kb/comments/{id}          — delete (autor OR admin)
 *
 * Permission: kb.comment
 */

beforeEach(function () {
    kbBootstrapSchema();
});

afterEach(function () {
    kbTeardownSchema();
});

it('POST /kb/nodes/{slug}/comments creates inline comment with block_idx', function () {
    kbActAsUser(bizId: 1, userId: 42, permissions: ['kb.view', 'kb.comment']);

    $nodeId = \DB::table('kb_nodes')->insertGetId([
        'business_id' => 1, 'type' => 'article', 'slug' => 'c-me',
        'title' => 'commentable', 'is_editable' => true,
        'body_blocks' => json_encode([
            ['kind' => 'h2', 'text' => 'Heading'],
            ['kind' => 'para', 'text' => 'Bloco que será comentado.'],
        ]),
        'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $response = $this->postJson('/kb/nodes/c-me/comments', [
        'block_idx' => 1,
        'text'      => 'Pequeno typo: ponto e vírgula faltando.',
    ]);

    $response->assertCreated();
    $row = \DB::table('kb_comments')->where('node_id', $nodeId)->first();
    expect($row)->not->toBeNull()
        ->and((int) $row->block_idx)->toBe(1)
        ->and((int) $row->author_user_id)->toBe(42);
});

it('POST /kb/nodes/{slug}/comments without kb.comment returns 403', function () {
    kbActAsUser(bizId: 1, permissions: ['kb.view']);  // SEM kb.comment

    \DB::table('kb_nodes')->insertGetId([
        'business_id' => 1, 'type' => 'article', 'slug' => 'no-perm',
        'title' => 'x', 'is_editable' => true,
        'body_blocks' => json_encode([['kind' => 'para', 'text' => 'x']]),
        'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $response = $this->postJson('/kb/nodes/no-perm/comments', [
        'block_idx' => 0, 'text' => 'try',
    ]);

    $response->assertForbidden();
});

it('DELETE /kb/comments/{id} allows author to delete own comment', function () {
    kbActAsUser(bizId: 1, userId: 42, permissions: ['kb.view', 'kb.comment']);

    $nodeId = \DB::table('kb_nodes')->insertGetId([
        'business_id' => 1, 'type' => 'article', 'slug' => 'del-comm',
        'title' => 'x', 'is_editable' => true,
        'body_blocks' => json_encode([['kind' => 'para', 'text' => 'x']]),
        'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $commentId = \DB::table('kb_comments')->insertGetId([
        'business_id' => 1, 'node_id' => $nodeId, 'block_idx' => 0,
        'text' => 'meu comment', 'author_user_id' => 42,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $response = $this->deleteJson("/kb/comments/{$commentId}");

    $response->assertOk();
    $row = \DB::table('kb_comments')->where('id', $commentId)->first();
    expect($row->deleted_at)->not->toBeNull();
});

it('DELETE /kb/comments/{id} blocks non-author non-admin from deleting', function () {
    // user 42 cria comment; user 99 tenta deletar (sem ser admin)
    kbCreateBusinessRow(1);
    \DB::table('kb_nodes')->insertGetId([
        'business_id' => 1, 'type' => 'article', 'slug' => 'block-del',
        'title' => 'x', 'is_editable' => true,
        'body_blocks' => json_encode([['kind' => 'para', 'text' => 'x']]),
        'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $commentId = \DB::table('kb_comments')->insertGetId([
        'business_id' => 1, 'node_id' => \DB::table('kb_nodes')->where('slug', 'block-del')->value('id'),
        'block_idx' => 0, 'text' => 'do user 42', 'author_user_id' => 42,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    // Acting as user 99 (não-autor, não-admin)
    kbActAsUser(bizId: 1, userId: 99, permissions: ['kb.view', 'kb.comment']);

    $response = $this->deleteJson("/kb/comments/{$commentId}");

    $response->assertForbidden();
    $row = \DB::table('kb_comments')->where('id', $commentId)->first();
    expect($row->deleted_at)->toBeNull();  // não foi deletado
});
