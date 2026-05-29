<?php

declare(strict_types=1);

/**
 * RelevanciaMetaInfererTest — Área B do Modelo de Peso Real (ADR 0232).
 *
 * Cobre a heurística PURA que infere `relevancia_meta` (0-100). Service não
 * toca DB/business_id/LLM — testes injetam o mapa de config diretamente, então
 * rodam sem boot de framework (independentes de TestCase / MySQL).
 *
 * Invariantes (ADR 0232 + NORTE-ROI):
 *  - override explícito do frontmatter VENCE heurística;
 *  - meta/multi-tenant (Tier 0) → topo (76-100);
 *  - módulo cliente-pagante (Vestuario) → alto;
 *  - governança → médio (habilitador);
 *  - OficinaAuto (sem sinal) → baixo;
 *  - desconhecido → 50 (meio da régua);
 *  - clamp em [0, 100].
 *
 * @group governance-v4
 * @group peso-real
 * @see Modules/Jana/Services/Peso/RelevanciaMetaInferer.php
 */

use Modules\Jana\Services\Peso\RelevanciaMetaInferer;

/**
 * Mapa que espelha Modules/Jana/Config/config.php → peso_real.relevancia.
 * Mantido aqui pra teste hermético (sem boot). Se config mudar, atualizar ambos.
 *
 * @return array<string, mixed>
 */
function relevanciaConfigStub(): array
{
    return [
        'tags' => [
            'tier-0'        => 95,
            'multi-tenant'  => 95,
            'meta'          => 95,
            'seguranca'     => 90,
            'governance'    => 45,
            'feature-wish'  => 25,
        ],
        'modules' => [
            'Financeiro'  => 88,
            'Vestuario'   => 88,
            'Copiloto'    => 65,
            'governance'  => 45,
            'OficinaAuto' => 28,
        ],
        'types' => [
            'adr'       => 55,
            'session'   => 40,
            'reference' => 45,
        ],
    ];
}

function makeInferer(): RelevanciaMetaInferer
{
    return new RelevanciaMetaInferer(relevanciaConfigStub());
}

it('usa override numerico do frontmatter vencendo heuristica', function () {
    $inferer = makeInferer();

    // Módulo OficinaAuto inferiria ~28, mas override explícito vence.
    $score = $inferer->inferir(
        type: 'spec',
        module: 'OficinaAuto',
        tags: ['feature-wish'],
        frontmatter: ['relevancia_meta' => 90],
    );

    expect($score)->toBe(90);
});

it('usa override por rotulo meta_contribution vencendo heuristica', function () {
    $inferer = makeInferer();

    expect($inferer->inferir('adr', 'OficinaAuto', [], ['meta_contribution' => 'alta']))->toBe(90)
        ->and($inferer->inferir('adr', 'Vestuario', [], ['meta_contribution' => 'baixa']))->toBe(30)
        ->and($inferer->inferir('adr', null, [], ['meta_contribution' => 'media']))->toBe(60);
});

it('rotulo meta_contribution invalido nao vence — cai na heuristica', function () {
    $inferer = makeInferer();

    // 'gigante' não é rótulo válido → ignora override, infere por módulo.
    expect($inferer->inferir('adr', 'Vestuario', [], ['meta_contribution' => 'gigante']))->toBe(88);
});

it('classifica meta e multi-tenant Tier 0 no topo da regua', function () {
    $inferer = makeInferer();

    expect($inferer->inferir('adr', 'governance', ['meta']))->toBeGreaterThanOrEqual(76)
        ->and($inferer->inferir('adr', 'Jana', ['multi-tenant']))->toBeGreaterThanOrEqual(76)
        ->and($inferer->inferir('adr', null, ['tier-0']))->toBe(95);
});

it('tag de maior peso vence quando ha multiplas', function () {
    $inferer = makeInferer();

    // governance(45) + multi-tenant(95) → vence o maior.
    expect($inferer->inferir('adr', null, ['governance', 'multi-tenant']))->toBe(95);
});

it('classifica modulo cliente-pagante (Vestuario) como alto', function () {
    $inferer = makeInferer();

    expect($inferer->inferir('spec', 'Vestuario'))->toBe(88)
        ->and($inferer->inferir('spec', 'Vestuario'))->toBeGreaterThanOrEqual(76);
});

it('classifica modulo sem sinal (OficinaAuto) como baixo', function () {
    $inferer = makeInferer();

    expect($inferer->inferir('spec', 'OficinaAuto'))->toBe(28)
        ->and($inferer->inferir('spec', 'OficinaAuto'))->toBeLessThanOrEqual(50);
});

it('classifica governanca como medio (habilitador)', function () {
    $inferer = makeInferer();

    $score = $inferer->inferir('adr', 'governance', ['governance']);

    expect($score)->toBe(45)
        ->and($score)->toBeGreaterThanOrEqual(26)
        ->and($score)->toBeLessThanOrEqual(50);
});

it('match de modulo e case-insensitive', function () {
    $inferer = makeInferer();

    expect($inferer->inferir('spec', 'VESTUARIO'))->toBe(88)
        ->and($inferer->inferir('spec', 'vestuario'))->toBe(88);
});

it('retorna default 50 para desconhecido total', function () {
    $inferer = makeInferer();

    // tipo desconhecido, sem módulo, sem tags, sem frontmatter.
    expect($inferer->inferir('tipo-inexistente', null, [], []))->toBe(50);
});

it('cai pra heuristica de tipo quando modulo e tags nao casam', function () {
    $inferer = makeInferer();

    expect($inferer->inferir('adr', 'ModuloDesconhecido', ['tag-qualquer']))->toBe(55)
        ->and($inferer->inferir('session', null, []))->toBe(40);
});

it('clampa override fora do intervalo em [0, 100]', function () {
    $inferer = makeInferer();

    expect($inferer->inferir('adr', null, [], ['relevancia_meta' => 250]))->toBe(100)
        ->and($inferer->inferir('adr', null, [], ['relevancia_meta' => -40]))->toBe(0);
});

it('hierarquia: tag Tier 0 vence modulo de baixo peso', function () {
    $inferer = makeInferer();

    // OficinaAuto(28) por módulo, mas tag 'meta' (95) tem prioridade maior.
    expect($inferer->inferir('spec', 'OficinaAuto', ['meta']))->toBe(95);
});

it('precedencia: relevancia_meta numerico vence meta_contribution', function () {
    $inferer = makeInferer();

    $fm = ['relevancia_meta' => 77, 'meta_contribution' => 'baixa'];

    expect($inferer->inferir('adr', 'Vestuario', [], $fm))->toBe(77);
});
