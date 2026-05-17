<?php

declare(strict_types=1);

/**
 * KbDriftDetectorCommandTest — Wave 23 KB §G4 — testes do kb:drift-detector.
 *
 * Cobre:
 *   - --business-id obrigatório (Tier 0 ADR 0093)
 *   - biz=4 bloqueado (ROTA LIVRE prod — ADR 0101)
 *   - Mock mode sem drift → exit 0
 *   - Mock mode COM artigo referenciando path deletado → exit 1
 *   - Schema ausente → graceful exit 0
 *
 * Multi-tenant: usa biz=1 + biz=99 (cross-tenant proof).
 */

// TestCase é aplicado via tests/Pest.php uses(TestCase::class)->in(KbFeatureDir).

beforeEach(function () {
    kbBootstrapSchema();
    kbCreateBusinessRow(1);
    kbCreateBusinessRow(99);
});

afterEach(function () {
    kbTeardownSchema();
});

it('drift-detector exige --business-id', function () {
    $this->artisan('kb:drift-detector')
        ->assertExitCode(1);
});

it('drift-detector bloqueia biz=4 (ROTA LIVRE prod — ADR 0101)', function () {
    $this->artisan('kb:drift-detector', ['--business-id' => 4])
        ->assertExitCode(1);
});

it('drift-detector mock mode sem artigo referenciando paths → exit 0', function () {
    // Schema bootstrap mas sem kb_nodes — drift vazio
    $this->artisan('kb:drift-detector', [
        '--business-id' => 1,
        '--mock' => true,
    ])->assertExitCode(0);
});

it('drift-detector mock mode com artigo referenciando path deletado → exit 1', function () {
    // Cria artigo biz=1 que cita path deletado conhecido (do mockDeletedPaths)
    \DB::table('kb_nodes')->insert([
        'business_id' => 1,
        'type' => 'article',
        'slug' => 'artigo-com-drift',
        'title' => 'Artigo com referência drift',
        'is_editable' => true,
        'body_blocks' => json_encode([
            ['kind' => 'para', 'text' => 'Ver memory/decisions/0000-deleted-test.md pra detalhes.'],
        ]),
        'status' => 'ok',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('kb:drift-detector', [
        '--business-id' => 1,
        '--mock' => true,
    ])->assertExitCode(1); // drift detectado
});

it('drift-detector multi-tenant: artigo biz=99 NÃO afeta biz=1', function () {
    // Cria artigo COM drift em biz=99
    \DB::table('kb_nodes')->insert([
        'business_id' => 99,
        'type' => 'article',
        'slug' => 'artigo-drift-biz99',
        'title' => 'Drift biz 99',
        'is_editable' => true,
        'body_blocks' => json_encode([
            ['kind' => 'para', 'text' => 'cita memory/decisions/0000-deleted-test.md aqui'],
        ]),
        'status' => 'ok',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Rodar pra biz=1 — não vê drift do biz=99
    $this->artisan('kb:drift-detector', [
        '--business-id' => 1,
        '--mock' => true,
    ])->assertExitCode(0);

    // Rodar pra biz=99 — vê drift
    $this->artisan('kb:drift-detector', [
        '--business-id' => 99,
        '--mock' => true,
    ])->assertExitCode(1);
});

it('drift-detector --detail emite tabela', function () {
    \DB::table('kb_nodes')->insert([
        'business_id' => 1,
        'type' => 'article',
        'slug' => 'detail-drift',
        'title' => 'Detail test',
        'is_editable' => true,
        'body_blocks' => json_encode([
            ['kind' => 'para', 'text' => 'Modules/Removed/Service.php mencionado'],
        ]),
        'status' => 'ok',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('kb:drift-detector', [
        '--business-id' => 1,
        '--mock' => true,
        '--detail' => true,
    ])
        ->expectsOutputToContain('Path')
        ->assertExitCode(1);
});
