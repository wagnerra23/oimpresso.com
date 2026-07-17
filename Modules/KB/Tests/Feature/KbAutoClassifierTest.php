<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Modules\KB\Services\KbAutoClassifierService;

/**
 * KbAutoClassifierService — o classificador que preenche category_id/subcategory_id via auto_match.
 *
 * Cobre: Modules/KB/Services/KbAutoClassifierService.php + KbClassifyCommand.
 * Contexto: 1.412/1.415 nós com category_id NULL (medido 2026-07-17) → a tela renderiza vazia.
 * Este serviço aplica as regras auto_match seedadas. Testa: classifica por type · dry-run não grava ·
 * type sem regra fica NULL (dívida honesta) · Tier 0 cross-tenant (biz=1 vs biz=99) · idempotência.
 *
 * biz=1 canônico (ADR 0101) · biz=99 fictício cross-tenant · NUNCA biz=4 (ROTA LIVRE prod).
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite: rodar no CT 100 (oimpresso-staging MySQL, biz=1). ADR 0101 / 0062.');
    }
    kbBootstrapSchema();
});

afterEach(fn () => kbTeardownSchema());

/** Seed uma categoria + subcategoria com regra {field:type} num business. Retorna [catId, subId]. */
function seedTypeRule(int $biz, string $catSlug, string $subSlug, string $type): array
{
    kbCreateBusinessRow($biz);
    $catId = DB::table('kb_categories')->insertGetId([
        'business_id' => $biz, 'slug' => $catSlug, 'label' => ucfirst($catSlug),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $subId = DB::table('kb_subcategories')->insertGetId([
        'business_id' => $biz, 'category_id' => $catId, 'slug' => $subSlug, 'label' => ucfirst($subSlug),
        'auto_match' => json_encode(['field' => 'type', 'op' => '=', 'value' => $type]),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return [$catId, $subId];
}

/** Seed um nó SEM categoria. */
function seedNode(int $biz, string $type, string $slug): int
{
    return DB::table('kb_nodes')->insertGetId([
        'business_id' => $biz, 'type' => $type, 'slug' => $slug, 'title' => "T {$slug}",
        'is_editable' => false, 'status' => 'ok', 'category_id' => null, 'subcategory_id' => null,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('classifica um nó pelo type (--apply grava category_id + subcategory_id)', function () {
    [$catId, $subId] = seedTypeRule(1, 'governanca', 'adr', 'adr');
    $nodeId = seedNode(1, 'adr', 'adr-0001');

    $r = app(KbAutoClassifierService::class)->classify(1, apply: true);

    expect($r['classified'])->toBe(1)->and($r['homeless'])->toBe(0);
    $node = DB::table('kb_nodes')->where('id', $nodeId)->first();
    expect((int) $node->category_id)->toBe($catId)
        ->and((int) $node->subcategory_id)->toBe($subId);
});

it('DRY-RUN (apply=false) conta mas NÃO grava', function () {
    seedTypeRule(1, 'governanca', 'adr', 'adr');
    $nodeId = seedNode(1, 'adr', 'adr-0002');

    $r = app(KbAutoClassifierService::class)->classify(1, apply: false);

    expect($r['classified'])->toBe(1)->and($r['applied'])->toBeFalse();
    // o banco segue intocado
    expect(DB::table('kb_nodes')->where('id', $nodeId)->value('category_id'))->toBeNull();
});

it('type SEM regra fica NULL (dívida honesta, não invenção)', function () {
    seedTypeRule(1, 'governanca', 'adr', 'adr'); // só regra pra adr
    $ref = seedNode(1, 'reference', 'ref-0001');  // reference não tem casa

    $r = app(KbAutoClassifierService::class)->classify(1, apply: true);

    expect($r['classified'])->toBe(0)
        ->and($r['homeless'])->toBe(1)
        ->and($r['homeless_by_type'])->toHaveKey('reference');
    expect(DB::table('kb_nodes')->where('id', $ref)->value('category_id'))->toBeNull();
});

it('Tier 0: regra de biz=1 NUNCA classifica nó de biz=99 (cross-tenant)', function () {
    // Regra + nó em biz=1; e um nó type=adr em biz=99 (cliente fictício).
    seedTypeRule(1, 'governanca', 'adr', 'adr');
    $n1  = seedNode(1, 'adr', 'adr-biz1');
    kbCreateBusinessRow(99);
    $n99 = seedNode(99, 'adr', 'adr-biz99');

    // Classifica APENAS biz=1.
    $r = app(KbAutoClassifierService::class)->classify(1, apply: true);

    expect($r['classified'])->toBe(1); // só o de biz=1
    expect(DB::table('kb_nodes')->where('id', $n1)->value('category_id'))->not->toBeNull();
    // O de biz=99 fica INTOCADO — a regra de biz=1 não cruza tenant.
    expect(DB::table('kb_nodes')->where('id', $n99)->value('category_id'))->toBeNull();

    // E classificar biz=99 (que não tem regra) não classifica nada — a regra de biz=1 não o alcança.
    $r99 = app(KbAutoClassifierService::class)->classify(99, apply: true);
    expect($r99['classified'])->toBe(0)->and($r99['homeless'])->toBe(1);
    expect(DB::table('kb_nodes')->where('id', $n99)->value('category_id'))->toBeNull();
});

it('idempotente: 2ª rodada não reclassifica (só toca category_id NULL)', function () {
    seedTypeRule(1, 'governanca', 'adr', 'adr');
    seedNode(1, 'adr', 'adr-idem');

    $svc = app(KbAutoClassifierService::class);
    expect($svc->classify(1, apply: true)['classified'])->toBe(1);
    // 2ª rodada: o nó já tem categoria → não é candidato → 0.
    expect($svc->classify(1, apply: true)['classified'])->toBe(0);
});

it('comando kb:classify exige --business (Tier 0 — session não resolve em CLI)', function () {
    $exit = $this->artisan('kb:classify')->run();
    expect($exit)->toBe(1);
});

it('comando kb:classify --business=1 roda em DRY-RUN por default (não grava)', function () {
    seedTypeRule(1, 'governanca', 'adr', 'adr');
    $nodeId = seedNode(1, 'adr', 'adr-cmd');

    $this->artisan('kb:classify --business=1')->assertExitCode(0);

    // default é dry-run → NÃO gravou
    expect(DB::table('kb_nodes')->where('id', $nodeId)->value('category_id'))->toBeNull();
});
