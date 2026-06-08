<?php

declare(strict_types=1);

/**
 * Feature specs — favoritos (SCHEMA-DB-V1 §11):
 *   POST /kb/nodes/{slug}/favorite   → toggle (idempotent)
 *   GET  /kb?favorites=1              → filtra só favoritos do user atual
 *
 * Permission: kb.favorite
 */

beforeEach(function () {
    kbBootstrapSchema();
});

afterEach(function () {
    kbTeardownSchema();
});

it('POST /kb/nodes/{slug}/favorite toggles ON when not favorited yet', function () {
    kbActAsUser(bizId: 1, userId: 42, permissions: ['kb.view', 'kb.favorite']);

    $nodeId = \DB::table('kb_nodes')->insertGetId([
        'business_id' => 1, 'type' => 'article', 'slug' => 'fav-me',
        'title' => 'favoritable', 'is_editable' => true,
        'body_blocks' => json_encode([['kind' => 'para', 'text' => 'x']]),
        'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $response = $this->postJson('/kb/nodes/fav-me/favorite');

    $response->assertOk();
    expect(\DB::table('kb_favorites')->where('user_id', 42)->where('node_id', $nodeId)->count())->toBe(1);
});

it('POST /kb/nodes/{slug}/favorite toggles OFF on second call', function () {
    kbActAsUser(bizId: 1, userId: 42, permissions: ['kb.view', 'kb.favorite']);

    $nodeId = \DB::table('kb_nodes')->insertGetId([
        'business_id' => 1, 'type' => 'article', 'slug' => 'fav-toggle',
        'title' => 'x', 'is_editable' => true,
        'body_blocks' => json_encode([['kind' => 'para', 'text' => 'x']]),
        'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->postJson('/kb/nodes/fav-toggle/favorite');
    expect(\DB::table('kb_favorites')->where('node_id', $nodeId)->count())->toBe(1);

    $this->postJson('/kb/nodes/fav-toggle/favorite');
    expect(\DB::table('kb_favorites')->where('node_id', $nodeId)->count())->toBe(0);
});

it('GET /kb?favorites=1 returns only favorited nodes of current user', function () {
    kbActAsUser(bizId: 1, userId: 42, permissions: ['kb.view', 'kb.favorite']);

    $favId = \DB::table('kb_nodes')->insertGetId([
        'business_id' => 1, 'type' => 'article', 'slug' => 'fav',
        'title' => 'favorited', 'is_editable' => true,
        'body_blocks' => json_encode([['kind' => 'para', 'text' => 'x']]),
        'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
    ]);
    \DB::table('kb_nodes')->insertGetId([
        'business_id' => 1, 'type' => 'article', 'slug' => 'not-fav',
        'title' => 'not favorited', 'is_editable' => true,
        'body_blocks' => json_encode([['kind' => 'para', 'text' => 'y']]),
        'status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
    ]);

    \DB::table('kb_favorites')->insert([
        'business_id' => 1, 'user_id' => 42, 'node_id' => $favId,
        'created_at' => now(),
    ]);
    // Favorito de user 99 (NÃO deve aparecer)
    \DB::table('kb_favorites')->insert([
        'business_id' => 1, 'user_id' => 99,
        'node_id' => \DB::table('kb_nodes')->where('slug', 'not-fav')->value('id'),
        'created_at' => now(),
    ]);

    $response = $this->getJson('/kb/nodes?favorites=1');
    $response->assertOk();

    $items = $response->json('data') ?? $response->json('nodes') ?? $response->json();
    expect(collect($items)->pluck('slug')->all())
        ->toContain('fav')
        ->and(collect($items)->pluck('slug')->all())->not->toContain('not-fav');
});
