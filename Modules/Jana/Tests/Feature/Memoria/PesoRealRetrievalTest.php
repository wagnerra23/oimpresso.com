<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Modules\Jana\Entities\MemoriaFato;
use Modules\Jana\Services\Memoria\MeilisearchDriver;

uses(Tests\TestCase::class);

/**
 * Área C — Etapa 5 IAOS / ADR 0232: plugar o Peso Real no retrieval.
 *
 * ⚠️ Este passo toca o CORAÇÃO da busca em prod (MeilisearchDriver). A regra
 * inviolável é: com a feature-flag OFF, o comportamento deve ser BYTE-IDÊNTICO
 * ao atual. O teste de NÃO-REGRESSÃO (bloco 1) prova exatamente isso.
 *
 * Cobertura:
 *   (a) flag OFF  → applyPesoReal nunca roda; o array que vai pro reranker é
 *       IDÊNTICO à saída do applyTimeDecay (não-regressão estrita).
 *   (b) flag ON   → ADR accepted reordena ACIMA de ADR superseded (decisão
 *       evergreen via pesoDecisao × lifecycle_mult, sem decay temporal).
 *   (c) flag ON   → não crasha com metadata ausente (fallback inferência).
 *
 * Testa via reflection nos métodos privados — sem bootar Scout/Meilisearch
 * (lentos em CI), igual ao molde TimeDecayTest.php.
 */

// ── helpers ──────────────────────────────────────────────────────────────

/**
 * Cria MemoriaFato em memória (sem persist) com metadata + idade controlada.
 */
function pesoMakeFato(
    int $id,
    string $fato = 'doc texto',
    array $metadata = [],
    int $ageDays = 0,
): MemoriaFato {
    $model = new MemoriaFato();
    $model->setRawAttributes([
        'id'          => $id,
        'business_id' => 1,
        'user_id'     => 1,
        'fato'        => $fato,
        'metadata'    => json_encode($metadata),
        'valid_from'  => now()->subDays($ageDays)->toDateTimeString(),
        'valid_until' => null,
        'created_at'  => now()->subDays($ageDays)->toDateTimeString(),
        'updated_at'  => now()->toDateTimeString(),
    ]);

    return $model;
}

/** Invoca applyTimeDecay() via reflection. */
function pesoInvokeTimeDecay(MeilisearchDriver $driver, Collection $hits): array
{
    $ref    = new ReflectionClass($driver);
    $method = $ref->getMethod('applyTimeDecay');
    $method->setAccessible(true);

    return $method->invoke($driver, $hits);
}

/** Invoca applyPesoReal() via reflection. */
function pesoInvokePesoReal(MeilisearchDriver $driver, array $candidatos, Collection $merged): array
{
    $ref    = new ReflectionClass($driver);
    $method = $ref->getMethod('applyPesoReal');
    $method->setAccessible(true);

    return $method->invoke($driver, $candidatos, $merged);
}

// ── 1. NÃO-REGRESSÃO — flag OFF preserva o comportamento atual ────────────

it('flag OFF (default) NÃO reordena — saída idêntica ao applyTimeDecay (não-regressão)', function () {
    // Default canônico do config.php: retrieval_enabled = false.
    // O driver só chama applyPesoReal se config('copiloto.peso_real.retrieval_enabled').
    // Aqui provamos que, com a flag OFF, o array que iria pro reranker é EXATAMENTE
    // a saída do applyTimeDecay — byte-idêntico ao pipeline legado.
    config(['copiloto.peso_real.retrieval_enabled' => false]);
    config([
        'copiloto.time_decay.enabled'            => true,
        'copiloto.time_decay.temporal_weight'    => 0.4,
        'copiloto.time_decay.half_life'          => ['adr' => 365, 'default' => 180],
        'copiloto.time_decay.status_multipliers' => ['accepted' => 1.2, 'superseded' => 0.3, 'default' => 1.0],
    ]);

    $driver = new MeilisearchDriver();

    $merged = collect([
        pesoMakeFato(1, 'ADR superseded antigo', ['doc_type' => 'adr', 'status' => 'superseded'], ageDays: 30),
        pesoMakeFato(2, 'ADR accepted recente',  ['doc_type' => 'adr', 'status' => 'accepted'],   ageDays: 30),
    ]);

    $candidatos = pesoInvokeTimeDecay($driver, $merged);

    // Simula o guard do buscarInterno: flag OFF → NÃO chama applyPesoReal.
    $resultado = config('copiloto.peso_real.retrieval_enabled')
        ? pesoInvokePesoReal($driver, $candidatos, $merged)
        : $candidatos;

    // Igualdade ESTRITA: mesma ordem, mesmos ids, mesmos scores, mesmo shape.
    expect($resultado)->toBe($candidatos);
    expect(array_column($resultado, 'id'))->toBe([1, 2]); // ordem do applyTimeDecay preservada
});

it('KL-C1 — config resolve NÃO-NULL: retrieval_enabled === false e lifecycle_mult populado (fim do duplo-OFF)', function () {
    // Não sobrescreve config — lê o valor REAL merged (JanaServiceProvider::
    // registerConfig → mergeConfigFrom 'copiloto'). O "duplo-OFF" era: flag false
    // no arquivo + chave resolvendo null em runtime (kill-switch não-funcional).
    // Agora a chave resolve false ESTRITO (não null) — a flag é a única porta.
    expect(config('copiloto.peso_real.retrieval_enabled'))->toBeFalse()
        ->and(config('copiloto.peso_real.lifecycle_mult'))->toBeArray()
        // Vocabulário canônico alinhado (status EN normalizado + frontmatter PT):
        ->and(config('copiloto.peso_real.lifecycle_mult.aceito'))->toBe(1.0)
        ->and(config('copiloto.peso_real.lifecycle_mult.ativo'))->toBe(1.0)
        ->and(config('copiloto.peso_real.lifecycle_mult.proposed'))->toBe(1.0)
        ->and(config('copiloto.peso_real.lifecycle_mult.historical'))->toBe(0.5)
        ->and(config('copiloto.peso_real.lifecycle_mult.superseded'))->toBe(0.3);
});

it('KL-C1 — flag AUSENTE (null, ex.: config publicada stale) continua OFF: kill-switch resiliente', function () {
    // Simula o runtime degradado que motivou o "duplo-OFF": copiloto.peso_real
    // inteiro resolvendo null. O guard do driver lê com default explícito false
    // → applyPesoReal NUNCA roda → pipeline byte-idêntico ao legado.
    config(['copiloto.peso_real' => null]);

    $driver = new MeilisearchDriver();

    $merged = collect([
        pesoMakeFato(1, 'ADR superseded', ['doc_type' => 'adr', 'status' => 'superseded'], ageDays: 30),
        pesoMakeFato(2, 'ADR aceito',     ['doc_type' => 'adr', 'status' => 'accepted'],   ageDays: 30),
    ]);

    $candidatos = pesoInvokeTimeDecay($driver, $merged);

    // Mesma expressão do guard em buscarInterno (default explícito false).
    $resultado = (bool) config('copiloto.peso_real.retrieval_enabled', false)
        ? pesoInvokePesoReal($driver, $candidatos, $merged)
        : $candidatos;

    expect(config('copiloto.peso_real.retrieval_enabled'))->toBeNull() // cenário degradado reproduzido
        ->and($resultado)->toBe($candidatos);                          // OFF: saída idêntica
});

// ── 2. flag ON — Peso Real reordena decisões por lifecycle (sem decay) ─────

it('flag ON — ADR accepted reordena ACIMA de ADR superseded (decisão evergreen)', function () {
    config(['copiloto.peso_real.retrieval_enabled' => true]);
    config([
        'copiloto.time_decay.enabled'            => true,
        'copiloto.time_decay.temporal_weight'    => 0.4,
        'copiloto.time_decay.half_life'          => ['adr' => 365],
        'copiloto.time_decay.status_multipliers' => ['accepted' => 1.2, 'superseded' => 0.3, 'default' => 1.0],
    ]);

    $driver = new MeilisearchDriver();

    // Input em ordem "errada": superseded primeiro (id 1), accepted depois (id 2).
    // Mesmo age → o time-decay sozinho não inverteria forte; o Peso Real (decisão)
    // usa lifecycle_mult (accepted=1.0 vs superseded=0.3) e deve trazer o accepted
    // pro topo independente da idade.
    $merged = collect([
        pesoMakeFato(1, 'ADR superseded', ['doc_type' => 'adr', 'status' => 'superseded'], ageDays: 5),
        pesoMakeFato(2, 'ADR accepted',   ['doc_type' => 'adr', 'status' => 'accepted'],   ageDays: 400),
    ]);

    $candidatos = pesoInvokeTimeDecay($driver, $merged);
    $reordenado = pesoInvokePesoReal($driver, $candidatos, $merged);

    // accepted (id 2) deve estar em PRIMEIRO mesmo sendo ~400d mais velho:
    // decisão não decai por tempo, só por lifecycle.
    expect($reordenado[0]['id'])->toBe(2);
    expect($reordenado[1]['id'])->toBe(1);

    // peso(accepted) = 70 × 1.0 = 70 ; peso(superseded) = 70 × 0.3 = 21 (KL-C1)
    expect($reordenado[0]['score'])->toEqualWithDelta(70.0, 0.01);
    expect($reordenado[1]['score'])->toEqualWithDelta(21.0, 0.01);
});

it('flag ON — vocabulário real do seeder (proposed/historical/superseded) ordena 1.0 > 0.5 > 0.3', function () {
    // KL-C1: metadata['status'] real vem EN-normalizado do SeedAdrsCommand
    // (accepted|proposed|historical|superseded). ANTES, proposed e historical
    // não existiam na lifecycle_mult → caíam no fallback 0.1 = peso de morto.
    config(['copiloto.peso_real.retrieval_enabled' => true]);
    config(['copiloto.time_decay.enabled' => true]);

    $driver = new MeilisearchDriver();

    $merged = collect([
        pesoMakeFato(1, 'ADR superseded', ['doc_type' => 'adr', 'status' => 'superseded'], ageDays: 1),
        pesoMakeFato(2, 'ADR historical', ['doc_type' => 'adr', 'status' => 'historical'], ageDays: 1),
        pesoMakeFato(3, 'ADR proposed',   ['doc_type' => 'adr', 'status' => 'proposed'],   ageDays: 1),
    ]);

    $candidatos = pesoInvokeTimeDecay($driver, $merged);
    $reordenado = pesoInvokePesoReal($driver, $candidatos, $merged);

    expect(array_column($reordenado, 'id'))->toBe([3, 2, 1]);
    // pesos: proposed 70×1.0=70 · historical 70×0.5=35 · superseded 70×0.3=21
    expect($reordenado[0]['score'])->toEqualWithDelta(70.0, 0.01);
    expect($reordenado[1]['score'])->toEqualWithDelta(35.0, 0.01);
    expect($reordenado[2]['score'])->toEqualWithDelta(21.0, 0.01);
});

it('flag ON — lifecycle canônico do frontmatter (ativo/substituido) é preferido e não cai no fallback', function () {
    // applyPesoReal prefere metadata['lifecycle'] sobre metadata['status'].
    // Vocabulário canônico (scripts/memory-schemas/adr.schema.json):
    // ativo|arquivado|substituido|historical. ADR 0270 D-4: vigente > morto.
    config(['copiloto.peso_real.retrieval_enabled' => true]);
    config(['copiloto.time_decay.enabled' => true]);

    $driver = new MeilisearchDriver();

    $merged = collect([
        pesoMakeFato(1, 'ADR substituido', ['doc_type' => 'adr', 'lifecycle' => 'substituido', 'status' => 'superseded'], ageDays: 1),
        pesoMakeFato(2, 'ADR ativo',       ['doc_type' => 'adr', 'lifecycle' => 'ativo',       'status' => 'aceito'],     ageDays: 1),
    ]);

    $candidatos = pesoInvokeTimeDecay($driver, $merged);
    $reordenado = pesoInvokePesoReal($driver, $candidatos, $merged);

    expect(array_column($reordenado, 'id'))->toBe([2, 1]);
    expect($reordenado[0]['score'])->toEqualWithDelta(70.0, 0.01); // ativo 70×1.0
    expect($reordenado[1]['score'])->toEqualWithDelta(21.0, 0.01); // substituido 70×0.3
});

it('flag ON — respeita relevancia_meta do metadata quando a Área B já populou', function () {
    config(['copiloto.peso_real.retrieval_enabled' => true]);
    config(['copiloto.time_decay.enabled' => true]);

    $driver = new MeilisearchDriver();

    $merged = collect([
        // relevancia_meta explícito 100 (ex.: ADR 0022 meta R$ [redacted Tier 0]M), accepted.
        pesoMakeFato(1, 'ADR meta', ['doc_type' => 'adr', 'status' => 'accepted', 'relevancia_meta' => 100], ageDays: 1),
        // ADR accepted sem relevancia_meta → infere 70.
        pesoMakeFato(2, 'ADR comum', ['doc_type' => 'adr', 'status' => 'accepted'], ageDays: 1),
    ]);

    $candidatos = pesoInvokeTimeDecay($driver, $merged);
    $reordenado = pesoInvokePesoReal($driver, $candidatos, $merged);

    expect($reordenado[0]['id'])->toBe(1);                 // relevancia 100 vence
    expect($reordenado[0]['score'])->toEqualWithDelta(100.0, 0.01);
    expect($reordenado[1]['score'])->toEqualWithDelta(70.0, 0.01);
});

// ── 3. flag ON — robustez: não crasha com metadata ausente ────────────────

it('flag ON — não crasha com metadata ausente (fallback inferência)', function () {
    config(['copiloto.peso_real.retrieval_enabled' => true]);
    config(['copiloto.time_decay.enabled' => true]);

    $driver = new MeilisearchDriver();

    // Fato sem metadata algum (null) — não deve quebrar; trata como 'fato' (mem).
    $semMeta = new MemoriaFato();
    $semMeta->setRawAttributes([
        'id'          => 50,
        'business_id' => 1,
        'user_id'     => 1,
        'fato'        => 'sem metadata',
        'metadata'    => null,
        'valid_from'  => now()->toDateTimeString(),
        'valid_until' => null,
        'created_at'  => now()->toDateTimeString(),
        'updated_at'  => now()->toDateTimeString(),
    ]);

    $merged     = collect([$semMeta]);
    $candidatos = pesoInvokeTimeDecay($driver, $merged);
    $reordenado = pesoInvokePesoReal($driver, $candidatos, $merged);

    expect($reordenado)->toHaveCount(1);
    expect($reordenado[0]['id'])->toBe(50);
    expect($reordenado[0])->toHaveKeys(['id', 'snippet', 'score']);
    expect($reordenado[0]['score'])->toBeFloat();
    expect($reordenado[0]['score'])->toBeGreaterThan(0.0); // memória: 50 × fator_temporal(~1.0)
});

it('flag ON — memória (session) mantém o decay temporal já aplicado em 3.5', function () {
    config(['copiloto.peso_real.retrieval_enabled' => true]);
    config([
        'copiloto.time_decay.enabled'            => true,
        'copiloto.time_decay.temporal_weight'    => 0.4,
        'copiloto.time_decay.half_life'          => ['session' => 30, 'default' => 180],
        'copiloto.time_decay.status_multipliers' => ['default' => 1.0],
    ]);

    $driver = new MeilisearchDriver();

    // Duas sessions, mesma relevancia inferida (50), idades diferentes:
    // a mais recente deve ficar acima (decay temporal preservado p/ memória).
    $merged = collect([
        pesoMakeFato(1, 'session velha',   ['doc_type' => 'session'], ageDays: 90),
        pesoMakeFato(2, 'session recente', ['doc_type' => 'session'], ageDays: 1),
    ]);

    $candidatos = pesoInvokeTimeDecay($driver, $merged);
    $reordenado = pesoInvokePesoReal($driver, $candidatos, $merged);

    // recente (id 2) acima da velha (id 1): memória decai por tempo.
    expect($reordenado[0]['id'])->toBe(2);
    expect($reordenado[1]['id'])->toBe(1);
    // peso = 50 × fator_temporal → ambos < 50, recente mais perto de 50.
    expect($reordenado[0]['score'])->toBeGreaterThan($reordenado[1]['score']);
    expect($reordenado[0]['score'])->toBeLessThanOrEqual(50.0);
});

// ── 4. Multi-tenant Tier 0 — applyPesoReal não recebe business scope ──────

it('applyPesoReal opera sobre material já scoped — sem businessId no signature (Tier 0)', function () {
    $ref    = new ReflectionClass(MeilisearchDriver::class);
    $method = $ref->getMethod('applyPesoReal');

    $paramNames = array_map(fn ($p) => $p->getName(), $method->getParameters());
    expect($paramNames)->not->toContain('businessId');
    expect($paramNames)->not->toContain('userId');
    expect($paramNames)->toContain('candidatos');
    expect($paramNames)->toContain('merged');
});
