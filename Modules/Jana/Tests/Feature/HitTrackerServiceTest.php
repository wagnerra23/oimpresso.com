<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Services\Memoria\HitTrackerService;

uses(Tests\TestCase::class);

/**
 * MEM-FASE6 + defesa multi-tenant 2026-05-06.
 *
 * `registrarUso(int $businessId, array $fatoIds)` deve incrementar `hits_count`
 * APENAS quando (id IN $fatoIds AND business_id = $businessId). IDs vazando
 * entre tenants via bug em outro lugar NÃO podem causar cross-tenant counter
 * increment.
 *
 * Pattern: cria `jana_memoria_facts` in-memory pra rodar isolated em SQLite.
 */

beforeEach(function () {
    // era-sqlite: cria schema mcp_*/jana_* manual (sqlite-friendly). No MySQL persistente
    // do nightly isso corrompe os testes irmãos (lever do floor SDD). Cobertura real é
    // na lane sqlite (per-PR); pula no MySQL.
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('era-sqlite: corruptor de schema compartilhado no MySQL — sqlite-only no burn-down do floor SDD.');
    }

    Schema::dropIfExists('jana_memoria_facts');
    Schema::create('jana_memoria_facts', function ($t) {
        $t->id();
        $t->unsignedInteger('business_id')->index();
        $t->string('content');
        $t->unsignedInteger('hits_count')->default(0);
        $t->timestamp('ultimo_hit_em')->nullable();
        $t->boolean('core_memory')->default(false);
        $t->timestamps();
        $t->softDeletes();
    });
});

afterEach(function () {
    if (config('database.default') !== 'sqlite') {
        return;
    }

    Schema::dropIfExists('jana_memoria_facts');
});

// ── helpers ──────────────────────────────────────────────────────────────

function fato(int $businessId, string $content = 'fato', int $hits = 0, bool $core = false): int
{
    return DB::table('jana_memoria_facts')->insertGetId([
        'business_id'   => $businessId,
        'content'       => $content,
        'hits_count'    => $hits,
        'core_memory'   => $core,
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);
}

function hitsDe(int $factId): int
{
    return (int) DB::table('jana_memoria_facts')->where('id', $factId)->value('hits_count');
}

function isCoreMemory(int $factId): bool
{
    return (bool) DB::table('jana_memoria_facts')->where('id', $factId)->value('core_memory');
}

// ── tests ────────────────────────────────────────────────────────────────

it('incrementa hits_count dos fatos do business correto', function () {
    $f1 = fato(4, 'fato A');
    $f2 = fato(4, 'fato B');

    (new HitTrackerService())->registrarUso(4, [$f1, $f2]);

    expect(hitsDe($f1))->toBe(1)
        ->and(hitsDe($f2))->toBe(1);
});

it('atualiza ultimo_hit_em quando incrementa', function () {
    $f1 = fato(4);

    expect(DB::table('jana_memoria_facts')->where('id', $f1)->value('ultimo_hit_em'))->toBeNull();

    (new HitTrackerService())->registrarUso(4, [$f1]);

    expect(DB::table('jana_memoria_facts')->where('id', $f1)->value('ultimo_hit_em'))->not()->toBeNull();
});

it('🔒 multi-tenant: biz=1 NÃO incrementa fato de biz=4 mesmo passando o id', function () {
    $factoBiz4 = fato(4, 'fato secret biz 4');

    // biz=1 chama com id de biz=4 (cenário de vazamento)
    (new HitTrackerService())->registrarUso(1, [$factoBiz4]);

    // Defesa: hits permanece 0
    expect(hitsDe($factoBiz4))->toBe(0);
});

it('🔒 multi-tenant: chamada parcial — só fatos do business correto incrementam', function () {
    $factoBiz1 = fato(1, 'fato biz 1');
    $factoBiz4 = fato(4, 'fato biz 4');

    // biz=1 chama com IDs misturados
    (new HitTrackerService())->registrarUso(1, [$factoBiz1, $factoBiz4]);

    expect(hitsDe($factoBiz1))->toBe(1)   // do biz 1: ok incrementa
        ->and(hitsDe($factoBiz4))->toBe(0); // do biz 4: defesa bloqueia
});

it('promove core_memory quando hits_count >= threshold (5 default)', function () {
    $f1 = fato(4, 'fato quase no limite', hits: 4);

    expect(isCoreMemory($f1))->toBeFalse();

    (new HitTrackerService())->registrarUso(4, [$f1]);

    expect(hitsDe($f1))->toBe(5)
        ->and(isCoreMemory($f1))->toBeTrue();
});

it('🔒 multi-tenant: promoção a core_memory também respeita business_id', function () {
    $factoBiz4 = fato(4, 'biz 4 quase no limite', hits: 4);

    // biz=1 tenta promover fato de biz=4
    (new HitTrackerService())->registrarUso(1, [$factoBiz4]);

    expect(hitsDe($factoBiz4))->toBe(4)         // não incrementou
        ->and(isCoreMemory($factoBiz4))->toBeFalse(); // não promoveu
});

it('soft-deleted facts não são afetados', function () {
    $f1 = fato(4);
    DB::table('jana_memoria_facts')->where('id', $f1)->update(['deleted_at' => now()]);

    (new HitTrackerService())->registrarUso(4, [$f1]);

    expect(hitsDe($f1))->toBe(0);
});

it('lista vazia: no-op silencioso (sem query)', function () {
    $svc = new HitTrackerService();

    expect(fn () => $svc->registrarUso(4, []))->not()->toThrow(\Throwable::class);
});

it('falha do DB é silente (tracking nunca quebra o chat)', function () {
    Schema::dropIfExists('jana_memoria_facts'); // tabela não existe

    $svc = new HitTrackerService();

    // Não deve explodir mesmo com tabela ausente
    expect(fn () => $svc->registrarUso(4, [1, 2, 3]))->not()->toThrow(\Throwable::class);
});
