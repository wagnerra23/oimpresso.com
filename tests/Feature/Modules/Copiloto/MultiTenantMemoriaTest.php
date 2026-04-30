<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\Copiloto\Entities\CopilotoMemoriaFato;
use Modules\Copiloto\Entities\Mcp\McpMemoryDocument;
use Modules\Copiloto\Services\Memoria\HitTrackerService;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

/**
 * MEM-MULTI-1 — Isolamento multi-tenant: business_id em copiloto_memoria_facts
 * e mcp_memory_documents.
 *
 * Garante que dados de uma empresa NUNCA vazam pra outra empresa.
 * Também valida que docs com business_id=NULL são compartilhados (legado global).
 *
 * Roda contra MySQL dev real. DatabaseTransactions faz rollback após cada teste.
 */

beforeEach(function () {
    try {
        DB::table('copiloto_memoria_facts')->count();
    } catch (\Throwable $e) {
        test()->markTestSkipped('copiloto_memoria_facts indisponível: ' . $e->getMessage());
    }
    config(['scout.driver' => 'null']);
});

// ── CopilotoMemoriaFato — isolamento por business_id ─────────────────────────

it('MULTI: scopeDoUser biz=1 não retorna fatos de biz=4', function () {
    CopilotoMemoriaFato::create(['business_id' => 1, 'user_id' => 77777, 'fato' => 'Fato oimpresso']);
    CopilotoMemoriaFato::create(['business_id' => 4, 'user_id' => 77777, 'fato' => 'Fato ROTA LIVRE']);

    $resultado = CopilotoMemoriaFato::doUser(1, 77777)->get();
    expect($resultado)->toHaveCount(1)
        ->and($resultado->first()->fato)->toBe('Fato oimpresso');
});

it('MULTI: scopeDoUser biz=4 não retorna fatos de biz=1', function () {
    CopilotoMemoriaFato::create(['business_id' => 1, 'user_id' => 77777, 'fato' => 'Fato oimpresso']);
    CopilotoMemoriaFato::create(['business_id' => 4, 'user_id' => 77777, 'fato' => 'Fato ROTA LIVRE']);

    $resultado = CopilotoMemoriaFato::doUser(4, 77777)->get();
    expect($resultado)->toHaveCount(1)
        ->and($resultado->first()->fato)->toBe('Fato ROTA LIVRE');
});

it('MULTI: mesmo user_id em empresas diferentes é isolado corretamente', function () {
    CopilotoMemoriaFato::create(['business_id' => 1, 'user_id' => 77777, 'fato' => 'User em oimpresso']);
    CopilotoMemoriaFato::create(['business_id' => 4, 'user_id' => 77777, 'fato' => 'User em ROTA LIVRE']);

    expect(CopilotoMemoriaFato::doUser(1, 77777)->count())->toBe(1)
        ->and(CopilotoMemoriaFato::doUser(4, 77777)->count())->toBe(1);
});

// ── McpMemoryDocument — scopeDoBusiness ──────────────────────────────────────

it('MULTI: scopeDoBusiness biz=1 retorna docs biz=1 e NULL', function () {
    try {
        DB::table('mcp_memory_documents')->count();
    } catch (\Throwable $e) {
        test()->markTestSkipped('mcp_memory_documents indisponível: ' . $e->getMessage());
    }

    $slug1     = 'test-multi-biz1-' . uniqid();
    $slug4     = 'test-multi-biz4-' . uniqid();
    $slugGlob  = 'test-multi-glob-' . uniqid();

    DB::table('mcp_memory_documents')->insert([
        ['slug' => $slug1,    'type' => 'adr', 'title' => 'ADR biz=1',    'content_md' => 'c', 'git_path' => $slug1,    'business_id' => 1,    'created_at' => now(), 'updated_at' => now()],
        ['slug' => $slug4,    'type' => 'adr', 'title' => 'ADR biz=4',    'content_md' => 'c', 'git_path' => $slug4,    'business_id' => 4,    'created_at' => now(), 'updated_at' => now()],
        ['slug' => $slugGlob, 'type' => 'adr', 'title' => 'ADR global',   'content_md' => 'c', 'git_path' => $slugGlob, 'business_id' => null, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $visivel = McpMemoryDocument::doBusiness(1)->whereIn('slug', [$slug1, $slug4, $slugGlob])->pluck('slug')->all();

    expect($visivel)->toContain($slug1)
        ->and($visivel)->toContain($slugGlob)
        ->and($visivel)->not->toContain($slug4)
        ->and(count($visivel))->toBe(2);
});

it('MULTI: scopeDoBusiness biz=4 não retorna docs biz=1', function () {
    try {
        DB::table('mcp_memory_documents')->count();
    } catch (\Throwable $e) {
        test()->markTestSkipped('mcp_memory_documents indisponível: ' . $e->getMessage());
    }

    $slug1    = 'test-multi2-biz1-' . uniqid();
    $slug4    = 'test-multi2-biz4-' . uniqid();
    $slugGlob = 'test-multi2-glob-' . uniqid();

    DB::table('mcp_memory_documents')->insert([
        ['slug' => $slug1,    'type' => 'adr', 'title' => 'ADR biz=1',  'content_md' => 'c', 'git_path' => $slug1,    'business_id' => 1,    'created_at' => now(), 'updated_at' => now()],
        ['slug' => $slug4,    'type' => 'adr', 'title' => 'ADR biz=4',  'content_md' => 'c', 'git_path' => $slug4,    'business_id' => 4,    'created_at' => now(), 'updated_at' => now()],
        ['slug' => $slugGlob, 'type' => 'adr', 'title' => 'ADR global', 'content_md' => 'c', 'git_path' => $slugGlob, 'business_id' => null, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $slugs = McpMemoryDocument::doBusiness(4)->whereIn('slug', [$slug1, $slug4, $slugGlob])->pluck('slug')->all();

    expect($slugs)->toContain($slug4)
        ->and($slugs)->toContain($slugGlob)
        ->and($slugs)->not->toContain($slug1);
});

// ── HitTracker: business_id no serviço impede contaminação ───────────────────

it('MULTI: HitTrackerService com businessId=1 NÃO incrementa fatos de biz=4', function () {
    // Fato pertence à biz=4
    $idBiz4 = DB::table('copiloto_memoria_facts')->insertGetId([
        'business_id' => 4, 'user_id' => 77777, 'fato' => 'Fato Larissa',
        'metadata' => '{}', 'valid_from' => now(), 'hits_count' => 0,
        'core_memory' => false, 'created_at' => now(), 'updated_at' => now(),
    ]);

    // App de biz=1 tenta registrar hit com o ID de biz=4 — deve ser bloqueado
    app(HitTrackerService::class)->registrarUso([$idBiz4], businessId: 1);

    expect(DB::table('copiloto_memoria_facts')->where('id', $idBiz4)->value('hits_count'))->toBe(0);
});

it('MULTI: HitTrackerService com businessId correto só incrementa fatos da empresa', function () {
    $idBiz1 = DB::table('copiloto_memoria_facts')->insertGetId([
        'business_id' => 1, 'user_id' => 77777, 'fato' => 'Fato oimpresso',
        'metadata' => '{}', 'valid_from' => now(), 'hits_count' => 0,
        'core_memory' => false, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $idBiz4 = DB::table('copiloto_memoria_facts')->insertGetId([
        'business_id' => 4, 'user_id' => 77777, 'fato' => 'Fato ROTA LIVRE',
        'metadata' => '{}', 'valid_from' => now(), 'hits_count' => 0,
        'core_memory' => false, 'created_at' => now(), 'updated_at' => now(),
    ]);

    // Passa ambos IDs mas com businessId=1 — só biz=1 deve ser incrementado
    app(HitTrackerService::class)->registrarUso([$idBiz1, $idBiz4], businessId: 1);

    expect(DB::table('copiloto_memoria_facts')->where('id', $idBiz1)->value('hits_count'))->toBe(1)
        ->and(DB::table('copiloto_memoria_facts')->where('id', $idBiz4)->value('hits_count'))->toBe(0);
});

// ── Cleanup isola por business ────────────────────────────────────────────────

it('MULTI: copiloto:cleanup-memoria --business=1 não toca fatos de biz=4', function () {
    DB::table('copiloto_memoria_facts')->insert([
        ['business_id' => 1, 'user_id' => 77777, 'fato' => 'Bloat biz=1', 'metadata' => '{}', 'valid_from' => now(), 'hits_count' => 0, 'core_memory' => false, 'created_at' => now()->subDays(40), 'updated_at' => now(), 'deleted_at' => null],
        ['business_id' => 4, 'user_id' => 77777, 'fato' => 'Bloat biz=4', 'metadata' => '{}', 'valid_from' => now(), 'hits_count' => 0, 'core_memory' => false, 'created_at' => now()->subDays(40), 'updated_at' => now(), 'deleted_at' => null],
    ]);

    $this->artisan('copiloto:cleanup-memoria --business=1')->assertSuccessful();

    expect(DB::table('copiloto_memoria_facts')->where('user_id', 77777)->where('business_id', 4)->whereNull('deleted_at')->count())->toBe(1);
    expect(DB::table('copiloto_memoria_facts')->where('user_id', 77777)->where('business_id', 1)->whereNotNull('deleted_at')->count())->toBe(1);
});
