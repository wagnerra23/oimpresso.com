<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Modules\Jana\Services\Reconcile\ReconcileDrift;
use Modules\Jana\Services\Reconcile\ReconcileResult;
use Modules\Jana\Services\Reconcile\Reconcilers\SettingsReconciler;

uses(Tests\TestCase::class);

/**
 * SettingsReconciler — embedder/filterable vivos do Meilisearch != config-as-code (ADR 0237).
 *
 * Blinda o reconciler que cura o bug recorrente (embedder do `jana_memoria_facts`
 * voltou a `{}` 2× → recall do chat degradou em SILÊNCIO). TUDO sem rede: a leitura
 * dos settings vivos é uma CLOSURE injetada no construtor (fake), e a cura é outra
 * closure injetada (fake que grava chamadas) — a cura NUNCA bate no Meilisearch real
 * no teste. Mesmo contrato testável-sem-I/O de DeployDriftChecker::analisar.
 *
 * Cobertura:
 *   - embedder vivo `{}`  → drift HEALABLE (o bug clássico)
 *   - embedder igual      → synced (zero drift)
 *   - filterable divergente → drift
 *   - source/model/dimensions divergente → drift
 *   - heal=true           → re-aplica settings (idempotente) SÓ contra o fake + healed=true
 *   - dry_run=true        → detecta, NÃO aplica
 *   - observação falha    → drift NÃO-curável (alerta humano, não fallback silencioso)
 *   - núcleo analisar()   → puro, sem rede
 */

// ── fixtures ──────────────────────────────────────────────────────────────

/** Config desejada canônica de UM índice (espelha copiloto.meilisearch_indexes). */
function desiredIndiceFixture(): array
{
    return [
        'embedders' => [
            'qwen3_local' => [
                'source' => 'ollama',
                'model' => 'qwen3-embedding:0.6b',
                'dimensions' => 1024,
                'documentTemplate' => '{{doc.fato}}',
                'documentTemplateMaxBytes' => 400,
                'url' => 'http://ollama-embedder:11434/api/embeddings',
            ],
        ],
        'filterableAttributes' => ['business_id', 'user_id', 'valid_until'],
    ];
}

/** Settings vivos "saudáveis" (sincronizados com a config desejada). */
function vivoSaudavelFixture(): array
{
    return [
        'embedders' => [
            'qwen3_local' => [
                'source' => 'ollama',
                'model' => 'qwen3-embedding:0.6b',
                'dimensions' => 1024,
            ],
        ],
        // ordem trocada de propósito — comparação é por CONJUNTO.
        'filterableAttributes' => ['user_id', 'valid_until', 'business_id'],
    ];
}

/**
 * Monta um reconciler com config injetada (1 índice "idx_teste") + closures fake.
 *
 * @param  array<string, mixed>      $vivo     settings vivos que o fake devolve.
 * @param  array<string, mixed>|null $desired  config desejada (default = fixture).
 * @param  array<int, array{uid: string, payload: array<string, mixed>}> &$aplicacoes  log de cura (out).
 * @param  bool                      $curaOk   o aplicador fake confirma sucesso?
 */
function reconcilerComVivo(
    array $vivo,
    ?array $desired = null,
    ?array &$aplicacoes = null,
    bool $curaOk = true,
): SettingsReconciler {
    config(['copiloto.meilisearch_indexes' => ['idx_teste' => $desired ?? desiredIndiceFixture()]]);

    $aplicacoes ??= [];

    return new SettingsReconciler(
        observarSettings: static fn (string $uid): array => $vivo,
        aplicarSettings: function (string $uid, array $payload) use (&$aplicacoes, $curaOk): bool {
            $aplicacoes[] = ['uid' => $uid, 'payload' => $payload];

            return $curaOk;
        },
    );
}

// ── identidade do reconciler ────────────────────────────────────────────────

it('expõe name()=settings + tags tier_1/retrieval (contrato Reconciler ADR 0237)', function () {
    $r = new SettingsReconciler(
        observarSettings: fn (string $uid): array => [],
        aplicarSettings: fn (string $uid, array $p): bool => true,
    );

    expect($r->name())->toBe('settings');
    expect($r->tags())->toContain('tier_1')->toContain('retrieval');
    expect($r->description())->toBeString()->not->toBe('');
});

// ── O BUG: embedder vivo {} ─────────────────────────────────────────────────

it('embedder vivo {} → drift HEALABLE (o caso que degradou o recall 2×)', function () {
    $aplicacoes = [];
    $r = reconcilerComVivo(
        vivo: ['embedders' => [], 'filterableAttributes' => ['business_id', 'user_id', 'valid_until']],
        aplicacoes: $aplicacoes,
    );

    $res = $r->reconcile(); // sem heal → só detecta

    expect($res)->toBeInstanceOf(ReconcileResult::class);
    expect($res->inSync)->toBeFalse();
    expect($res->driftCount)->toBe(1);
    expect($res->drifts[0])->toBeInstanceOf(ReconcileDrift::class);
    expect($res->drifts[0]->healable)->toBeTrue();
    expect($res->drifts[0]->healed)->toBeFalse(); // sem heal não cura
    expect($res->drifts[0]->target)->toContain('qwen3_local');
    expect($res->drifts[0]->observed)->toContain('{}');
    expect($aplicacoes)->toBe([]); // NÃO disparou cura
});

// ── synced ──────────────────────────────────────────────────────────────────

it('embedder + filterable iguais → synced (zero drift, ordem do filterable irrelevante)', function () {
    $r = reconcilerComVivo(vivo: vivoSaudavelFixture());

    $res = $r->reconcile();

    expect($res->inSync)->toBeTrue();
    expect($res->driftCount)->toBe(0);
    expect($res->drifts)->toBe([]);
});

// ── filterable divergente ────────────────────────────────────────────────────

it('filterableAttributes divergente → drift', function () {
    $vivo = vivoSaudavelFixture();
    $vivo['filterableAttributes'] = ['business_id', 'user_id']; // falta valid_until

    $r = reconcilerComVivo(vivo: $vivo);
    $res = $r->reconcile();

    expect($res->inSync)->toBeFalse();
    expect($res->driftCount)->toBe(1);
    expect($res->drifts[0]->target)->toContain('filterableAttributes');
    expect($res->drifts[0]->healable)->toBeTrue();
    expect($res->drifts[0]->desired)->toContain('valid_until');
});

// ── embedder presente mas divergente ─────────────────────────────────────────

it('embedder presente mas model/dimensions divergente → drift por campo', function () {
    $vivo = [
        'embedders' => [
            'qwen3_local' => [
                'source' => 'ollama',
                'model' => 'nomic-embed-text', // ERRADO (nomic perdeu o eval)
                'dimensions' => 768,            // ERRADO (esperado 1024)
            ],
        ],
        'filterableAttributes' => ['business_id', 'user_id', 'valid_until'],
    ];

    $r = reconcilerComVivo(vivo: $vivo);
    $res = $r->reconcile();

    expect($res->driftCount)->toBe(2); // model + dimensions
    $targets = array_map(fn (ReconcileDrift $d) => $d->target, $res->drifts);
    expect($targets)->toContain('idx_teste.embedders.qwen3_local.model');
    expect($targets)->toContain('idx_teste.embedders.qwen3_local.dimensions');
    foreach ($res->drifts as $d) {
        expect($d->healable)->toBeTrue();
    }
});

it('dimensions int 1024 vs string "1024" NÃO é drift (compara por valor, não tipo)', function () {
    $vivo = vivoSaudavelFixture();
    $vivo['embedders']['qwen3_local']['dimensions'] = '1024'; // string vinda do JSON

    $r = reconcilerComVivo(vivo: $vivo);

    expect($r->reconcile()->inSync)->toBeTrue();
});

// ── HEAL re-aplica (idempotente) só contra o fake ────────────────────────────

it('heal=true re-aplica settings (payload reusa MeilisearchIndexSetupCommand) + marca healed', function () {
    $aplicacoes = [];
    $r = reconcilerComVivo(
        vivo: ['embedders' => [], 'filterableAttributes' => []], // tudo perdido
        aplicacoes: $aplicacoes,
    );

    $res = $r->reconcile(['heal' => true]);

    // curou: aplicou 1× contra o fake (NUNCA no Meilisearch real)
    expect($aplicacoes)->toHaveCount(1);
    expect($aplicacoes[0]['uid'])->toBe('idx_teste');
    // payload tem embedders + filterableAttributes (lógica do command consolidada)
    expect($aplicacoes[0]['payload'])->toHaveKeys(['embedders', 'filterableAttributes']);
    expect($aplicacoes[0]['payload']['embedders'])->toHaveKey('qwen3_local');

    // drifts marcados healed=true + contador
    expect($res->healedCount)->toBeGreaterThan(0);
    foreach ($res->drifts as $d) {
        expect($d->healed)->toBeTrue();
    }
});

it('heal=true em índice já synced é no-op (não chama o aplicador — idempotência)', function () {
    $aplicacoes = [];
    $r = reconcilerComVivo(vivo: vivoSaudavelFixture(), aplicacoes: $aplicacoes);

    $res = $r->reconcile(['heal' => true]);

    expect($res->inSync)->toBeTrue();
    expect($res->healedCount)->toBe(0);
    expect($aplicacoes)->toBe([]); // sem drift → sem cura
});

it('reconcile() é idempotente: 2 runs com heal contra o mesmo fake = mesmo resultado', function () {
    $aplicacoes = [];
    $r = reconcilerComVivo(vivo: ['embedders' => [], 'filterableAttributes' => []], aplicacoes: $aplicacoes);

    $r1 = $r->reconcile(['heal' => true]);
    $r2 = $r->reconcile(['heal' => true]);

    expect($r1->toArray()['drifts'])->toEqual($r2->toArray()['drifts']);
    expect($r1->healedCount)->toBe($r2->healedCount);
});

// ── DRY-RUN detecta, não aplica ──────────────────────────────────────────────

it('dry_run=true com heal → DETECTA mas NÃO aplica (aplicador nunca chamado)', function () {
    $aplicacoes = [];
    $r = reconcilerComVivo(
        vivo: ['embedders' => [], 'filterableAttributes' => []],
        aplicacoes: $aplicacoes,
    );

    $res = $r->reconcile(['heal' => true, 'dry_run' => true]);

    expect($res->inSync)->toBeFalse();
    expect($res->driftCount)->toBeGreaterThan(0);
    expect($res->healedCount)->toBe(0);  // nada curado
    expect($aplicacoes)->toBe([]);       // aplicador NUNCA chamado
});

// ── observação falha → não-curável (alerta humano) ───────────────────────────

it('observação que lança (rede caiu) → drift NÃO-curável, sem cura às cegas', function () {
    config(['copiloto.meilisearch_indexes' => ['idx_teste' => desiredIndiceFixture()]]);

    $aplicacoes = [];
    $r = new SettingsReconciler(
        observarSettings: function (string $uid): array {
            throw new RuntimeException('Connection refused');
        },
        aplicarSettings: function (string $uid, array $payload) use (&$aplicacoes): bool {
            $aplicacoes[] = ['uid' => $uid, 'payload' => $payload];

            return true;
        },
    );

    $res = $r->reconcile(['heal' => true]); // mesmo pedindo heal...

    expect($res->inSync)->toBeFalse();
    expect($res->driftCount)->toBe(1);
    expect($res->drifts[0]->healable)->toBeFalse(); // não dá pra curar o que não li
    expect($res->drifts[0]->detail)->toContain('idx_teste');
    expect($aplicacoes)->toBe([]); // ...NÃO curou às cegas
});

it('aplicador que falha (PATCH não-2xx) → drift fica aberto (healed=false)', function () {
    $aplicacoes = [];
    $r = reconcilerComVivo(
        vivo: ['embedders' => [], 'filterableAttributes' => []],
        aplicacoes: $aplicacoes,
        curaOk: false, // PATCH falhou
    );

    $res = $r->reconcile(['heal' => true]);

    expect($aplicacoes)->toHaveCount(1);  // tentou aplicar
    expect($res->healedCount)->toBe(0);   // mas não confirmou → não healed
    expect($res->drifts[0]->healed)->toBeFalse();
});

// ── núcleo puro analisar() ───────────────────────────────────────────────────

it('analisar() é PURO: compara 2 mapas sem tocar rede (Http nunca chamado)', function () {
    Http::preventStrayRequests();

    $r = new SettingsReconciler(
        observarSettings: fn (string $uid): array => [],
        aplicarSettings: fn (string $uid, array $p): bool => true,
    );

    $drifts = $r->analisar(
        ['jana_memoria_facts' => desiredIndiceFixture()],
        ['embedders' => [], 'filterableAttributes' => []], // vivo zerado
    );

    expect($drifts)->toBeArray();
    // embedder ausente (1) + filterable divergente (1)
    expect($drifts)->toHaveCount(2);
    expect($drifts[0])->toBeInstanceOf(ReconcileDrift::class);
    foreach ($drifts as $d) {
        expect($d->healable)->toBeTrue();
        expect($d->healed)->toBeFalse();
    }
});

it('analisar() com mapas iguais → []', function () {
    $r = new SettingsReconciler(
        observarSettings: fn (string $uid): array => [],
        aplicarSettings: fn (string $uid, array $p): bool => true,
    );

    $drifts = $r->analisar(
        ['idx' => desiredIndiceFixture()],
        vivoSaudavelFixture(),
    );

    expect($drifts)->toBe([]);
});

// ── config vazia → synced com skip ───────────────────────────────────────────

it('config copiloto.meilisearch_indexes vazia → synced (skip), nada a reconciliar', function () {
    config(['copiloto.meilisearch_indexes' => []]);

    $r = new SettingsReconciler(
        observarSettings: fn (string $uid): array => [],
        aplicarSettings: fn (string $uid, array $p): bool => true,
    );

    $res = $r->reconcile();

    expect($res->inSync)->toBeTrue();
    expect($res->driftCount)->toBe(0);
    expect($res->metadata)->toHaveKey('skipped');
});
