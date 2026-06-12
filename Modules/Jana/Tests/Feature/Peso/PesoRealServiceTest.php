<?php

declare(strict_types=1);

use Modules\Jana\Services\Peso\PesoRealService;

uses(Tests\TestCase::class);

/**
 * Área A / Etapa 5 IAOS — PesoRealService (ADR 0232).
 *
 * Função PURA: testes sem DB, sem business_id, sem I/O. Só aritmética
 * determinística. Cobrem as três fórmulas + edges + fallback de config.
 *
 * @see memory/decisions/0232-modelo-peso-real-classificacao-por-meta.md
 */
beforeEach(function () {
    $this->svc = new PesoRealService();
});

/*
|--------------------------------------------------------------------------
| (a) DECISÃO / ADR
|--------------------------------------------------------------------------
*/

it('(a) decisão accepted ranka acima de superseded com relevância igual', function () {
    $accepted   = $this->svc->pesoDecisao(80, 'accepted');
    $superseded = $this->svc->pesoDecisao(80, 'superseded');

    expect($accepted)->toBe(80.0)            // 80 × 1.0
        ->and($superseded)->toBe(24.0)       // 80 × 0.3 (KL-C1 — alinhado ao time_decay aprovado 2026-05-13)
        ->and($accepted)->toBeGreaterThan($superseded);
});

it('(a2) KL-C1 — vocabulário canônico real não cai mais no fallback 0.1', function () {
    // status EN normalizado (SeedAdrsCommand::normalizeStatus) + frontmatter
    // canônico (adr.schema.json): vigente=1.0 > historical=0.5 > superseded=0.3.
    // ANTES deste fix, aceito/proposto/ativo/historical caíam TODOS em 0.1 —
    // vigente pesava igual a morto (violava ADR 0270 D-4).
    expect($this->svc->pesoDecisao(80, 'aceito'))->toBe(80.0)        // 80 × 1.0
        ->and($this->svc->pesoDecisao(80, 'ativo'))->toBe(80.0)      // lifecycle vigente
        ->and($this->svc->pesoDecisao(80, 'proposed'))->toBe(80.0)
        ->and($this->svc->pesoDecisao(80, 'proposto'))->toBe(80.0)
        ->and($this->svc->pesoDecisao(80, 'historical'))->toBe(40.0)  // 80 × 0.5
        ->and($this->svc->pesoDecisao(80, 'substituido'))->toBe(24.0) // 80 × 0.3
        ->and($this->svc->pesoDecisao(80, 'arquivado'))->toBe(24.0);  // 80 × 0.3

    // Ordem D-4: vigente > historical > superseded — o morto não volta com o
    // mesmo peso do vigente.
    expect($this->svc->pesoDecisao(80, 'aceito'))
        ->toBeGreaterThan($this->svc->pesoDecisao(80, 'historical'))
        ->and($this->svc->pesoDecisao(80, 'historical'))
        ->toBeGreaterThan($this->svc->pesoDecisao(80, 'superseded'));
});

it('(b) decisão NÃO muda com o tempo — peso é função só de relevância × lifecycle', function () {
    // pesoDecisao nem aceita "idade": a evergreen-ness é estrutural. Provamos
    // que o mesmo input dá o mesmo peso e que a tabela de lifecycle é monotônica.
    $hoje  = $this->svc->pesoDecisao(90, 'accepted');
    $amanha = $this->svc->pesoDecisao(90, 'accepted');

    expect($hoje)->toBe($amanha)->toBe(90.0);

    // lifecycle degrada o peso (supersede), nunca a idade.
    expect($this->svc->pesoDecisao(90, 'accepted'))
        ->toBeGreaterThan($this->svc->pesoDecisao(90, 'accepted-historical'))
        ->and($this->svc->pesoDecisao(90, 'accepted-historical'))
        ->toBeGreaterThan($this->svc->pesoDecisao(90, 'sunsetting'))
        ->and($this->svc->pesoDecisao(90, 'sunsetting'))
        ->toBeGreaterThan($this->svc->pesoDecisao(90, 'superseded'));
});

it('decisão com lifecycle desconhecido cai no fallback mínimo', function () {
    expect($this->svc->pesoDecisao(100, 'inexistente'))->toBe(10.0); // 100 × 0.1
});

/*
|--------------------------------------------------------------------------
| (b) MEMÓRIA / lição / fato
|--------------------------------------------------------------------------
*/

it('(c) memória decai com os dias', function () {
    $fresca = $this->svc->pesoMemoria(80, diasDesde: 0);
    $velha  = $this->svc->pesoMemoria(80, diasDesde: 120);
    $antiga = $this->svc->pesoMemoria(80, diasDesde: 365);

    expect($fresca)->toBeGreaterThan($velha)
        ->and($velha)->toBeGreaterThan($antiga);

    // dias=0, recorrencia=0 → decay=1, boost=1 → peso == relevância.
    expect($fresca)->toBe(80.0);
});

it('(d) memória crítica não cai abaixo do piso, mesmo muito velha', function () {
    // 10 anos: o decay puro derrubaria pra ~0.
    $rel = 90;
    $semFloor = $this->svc->pesoMemoria($rel, diasDesde: 3650, critica: false);
    $comFloor = $this->svc->pesoMemoria($rel, diasDesde: 3650, critica: true);

    $piso = $rel * 0.5; // fallback piso_critico = 0.5

    expect($semFloor)->toBeLessThan($piso)        // sem floor afundou
        ->and($comFloor)->toBe($piso)             // floor segurou exatamente no piso
        ->and($comFloor)->toBeGreaterThan($semFloor);
});

it('memória crítica nova fica acima do piso (floor não limita pra baixo o que já é alto)', function () {
    $rel = 90;
    $criticaNova = $this->svc->pesoMemoria($rel, diasDesde: 0, critica: true);

    expect($criticaNova)->toBe(90.0)              // decay=1 → peso cheio
        ->and($criticaNova)->toBeGreaterThan($rel * 0.5);
});

it('(e) recorrência aumenta o peso da memória', function () {
    $semRec = $this->svc->pesoMemoria(80, diasDesde: 30, recorrencia: 0);
    $comRec = $this->svc->pesoMemoria(80, diasDesde: 30, recorrencia: 5);

    expect($comRec)->toBeGreaterThan($semRec);
});

it('half_life maior preserva mais peso (decay mais lento)', function () {
    $curto = $this->svc->pesoMemoria(80, diasDesde: 60, halfLife: 30);
    $longo = $this->svc->pesoMemoria(80, diasDesde: 60, halfLife: 120);

    expect($longo)->toBeGreaterThan($curto);
});

/*
|--------------------------------------------------------------------------
| (c) INICIATIVA / módulo
|--------------------------------------------------------------------------
*/

it('(f) iniciativa com sinal 1.0 > iniciativa com sinal 0.2 (resto igual)', function () {
    $paga     = $this->svc->pesoIniciativa(100000, sinalCliente: 1.0, timeCriticality: 1.0, esforco: 10);
    $hipotese = $this->svc->pesoIniciativa(100000, sinalCliente: 0.2, timeCriticality: 1.0, esforco: 10);

    expect($paga)->toBeGreaterThan($hipotese)
        ->and($paga)->toBe(10000.0)    // 100000×1.0×1.0 / 10
        ->and($hipotese)->toBe(2000.0); // 100000×0.2×1.0 / 10
});

it('(g) time_criticality 1.5 eleva a iniciativa (Cost of Delay / WSJF)', function () {
    $normal     = $this->svc->pesoIniciativa(100000, 1.0, timeCriticality: 1.0, esforco: 10);
    $compliance = $this->svc->pesoIniciativa(100000, 1.0, timeCriticality: 1.5, esforco: 10);

    expect($compliance)->toBeGreaterThan($normal)
        ->and($compliance)->toBe(15000.0); // ×1.5
});

it('(h) edge: esforço 0 não divide por zero (divisor protegido por 1.0)', function () {
    $r = $this->svc->pesoIniciativa(50000, 1.0, 1.0, esforco: 0);

    expect($r)->toBe(50000.0)            // numerador puro
        ->and(is_finite($r))->toBeTrue(); // sem INF/NAN
});

it('edge: esforço negativo também protegido', function () {
    $r = $this->svc->pesoIniciativa(50000, 1.0, 1.0, esforco: -5);

    expect($r)->toBe(50000.0)
        ->and(is_finite($r))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Mapeamento de rótulos
|--------------------------------------------------------------------------
*/

it('mapeia rótulos de sinal e time_criticality, com fallback conservador', function () {
    expect($this->svc->sinalCliente('paga_reporta'))->toBe(1.0)
        ->and($this->svc->sinalCliente('qualificado'))->toBe(0.5)
        ->and($this->svc->sinalCliente('hipotese'))->toBe(0.2)
        ->and($this->svc->sinalCliente('desconhecido'))->toBe(0.2)   // fallback hipótese
        ->and($this->svc->timeCriticality('normal'))->toBe(1.0)
        ->and($this->svc->timeCriticality('compliance'))->toBe(1.5)
        ->and($this->svc->timeCriticality('desconhecido'))->toBe(1.0); // fallback normal
});

/*
|--------------------------------------------------------------------------
| Robustez
|--------------------------------------------------------------------------
*/

it('relevancia_meta fora de faixa é clampada para 0-100', function () {
    expect($this->svc->pesoDecisao(150, 'accepted'))->toBe(100.0)  // clamp topo
        ->and($this->svc->pesoDecisao(-30, 'accepted'))->toBe(0.0); // clamp piso
});

it('(i) config ausente usa fallback hardcoded sem quebrar', function () {
    // Zera toda a config copiloto.peso_real — o service deve seguir funcionando
    // com os defaults internos.
    config(['copiloto.peso_real' => null]);

    $svc = new PesoRealService();

    expect($svc->pesoDecisao(80, 'accepted'))->toBe(80.0)           // lifecycle fallback
        ->and($svc->pesoDecisao(80, 'aceito'))->toBe(80.0)          // vocabulário canônico no fallback hardcoded também
        ->and($svc->pesoDecisao(80, 'superseded'))->toBe(24.0)      // 80 × 0.3 (KL-C1)
        ->and($svc->pesoMemoria(90, diasDesde: 3650, critica: true))->toBe(45.0) // piso fallback 0.5
        ->and($svc->sinalCliente('paga_reporta'))->toBe(1.0)        // sinal fallback
        ->and($svc->timeCriticality('compliance'))->toBe(1.5);      // time_crit fallback
});

it('config customizada sobrepõe o fallback', function () {
    config(['copiloto.peso_real.piso_critico' => 0.7]);

    $svc = new PesoRealService();
    // memória crítica velha → piso = 90 × 0.7 = 63 (delta p/ float).
    expect($svc->pesoMemoria(90, diasDesde: 3650, critica: true))->toEqualWithDelta(63.0, 0.0001);
});
