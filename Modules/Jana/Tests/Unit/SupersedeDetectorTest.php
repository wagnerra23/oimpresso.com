<?php

use Modules\Jana\Services\Memoria\SupersedeDetector;

/**
 * Teste de LÓGICA PURA (sem DB, sem app boot) da DECISÃO de supersede event-time
 * (ADR 0295, T4 slice 3). Mocka o seam do LLM (perguntarAoLlm) e exercita
 * SupersedeDetector::decidir() — validação de threshold, guard multi-tenant
 * (id ∈ candidatos) e FAILSAFE. Roda no CI (jana-bitemporal-pest.yml).
 *
 * A consolidação no banco (event-time + supersedes_id) é MySQL-only e vive em
 * Tests/Feature/Memoria/SupersedeConsolidacaoTest.php (lane jana-pest.yml).
 *
 * biz=1 (ADR 0101 — convenção de seed/test).
 */
$BIZ = 1;
$USER = 1;
$MIN = 70; // threshold de confiança explícito (pure — sem config)

/**
 * Detector com o seam do LLM mockado pra devolver um payload fixo.
 *
 * @param  array<string, mixed>|null  $resposta
 */
function detectorComLlm(?array $resposta): SupersedeDetector
{
    $detector = Mockery::mock(SupersedeDetector::class)->makePartial();
    $detector->shouldAllowMockingProtectedMethods();
    $detector->shouldReceive('perguntarAoLlm')->andReturn($resposta);

    return $detector;
}

afterEach(function () {
    Mockery::close();
});

it('aplica supersede quando id ∈ candidatos e confiança ≥ min', function () use ($BIZ, $USER, $MIN) {
    $candidatos = [10 => 'A meta de faturamento é R$ 50 mil/mês'];

    $decisao = detectorComLlm([
        'supersede' => true,
        'supersedes_id' => 10,
        'confianca' => 90,
        'motivo' => 'atualiza o valor da meta',
    ])->decidir($BIZ, $USER, 'A meta agora é R$ 80 mil/mês', $candidatos, $MIN);

    expect($decisao)->toBeArray()
        ->and($decisao['supersedes_id'])->toBe(10)
        ->and($decisao['confianca'])->toBe(90)
        ->and($decisao['motivo'])->toBe('atualiza o valor da meta');
});

it('NÃO aplica quando confiança < min (só apêndice)', function () use ($BIZ, $USER, $MIN) {
    $candidatos = [10 => 'A meta é R$ 50 mil/mês'];

    $decisao = detectorComLlm([
        'supersede' => true,
        'supersedes_id' => 10,
        'confianca' => 50, // abaixo do threshold 70
        'motivo' => 'talvez',
    ])->decidir($BIZ, $USER, 'A meta agora é R$ 80 mil/mês', $candidatos, $MIN);

    expect($decisao)->toBeNull();
});

it('GUARD multi-tenant: id fora do conjunto de candidatos → null', function () use ($BIZ, $USER, $MIN) {
    $candidatos = [10 => 'A meta é R$ 50 mil/mês'];

    // LLM "alucina" / aponta um id que não está na janela tenant-scoped (ex.: 999
    // de outro business/user). O detector DEVE rejeitar.
    $decisao = detectorComLlm([
        'supersede' => true,
        'supersedes_id' => 999,
        'confianca' => 95,
        'motivo' => 'id inexistente',
    ])->decidir($BIZ, $USER, 'A meta agora é R$ 80 mil/mês', $candidatos, $MIN);

    expect($decisao)->toBeNull();
});

it('supersede=false do LLM → null', function () use ($BIZ, $USER, $MIN) {
    $candidatos = [10 => 'Prefere relatórios por e-mail'];

    $decisao = detectorComLlm([
        'supersede' => false,
        'supersedes_id' => 0,
        'confianca' => 0,
        'motivo' => 'fato novo, sem antecessor',
    ])->decidir($BIZ, $USER, 'A meta é R$ 80 mil/mês', $candidatos, $MIN);

    expect($decisao)->toBeNull();
});

it('candidatos vazios → null e NEM chama o LLM', function () use ($BIZ, $USER, $MIN) {
    $detector = Mockery::mock(SupersedeDetector::class)->makePartial();
    $detector->shouldAllowMockingProtectedMethods();
    $detector->shouldNotReceive('perguntarAoLlm');

    expect($detector->decidir($BIZ, $USER, 'A meta é R$ 80 mil/mês', [], $MIN))->toBeNull();
});

it('novoFato vazio → null e NEM chama o LLM', function () use ($BIZ, $USER, $MIN) {
    $detector = Mockery::mock(SupersedeDetector::class)->makePartial();
    $detector->shouldAllowMockingProtectedMethods();
    $detector->shouldNotReceive('perguntarAoLlm');

    expect($detector->decidir($BIZ, $USER, '   ', [10 => 'algo'], $MIN))->toBeNull();
});

it('FAILSAFE: LLM indisponível (null) → null, sem lançar', function () use ($BIZ, $USER, $MIN) {
    $decisao = detectorComLlm(null)
        ->decidir($BIZ, $USER, 'A meta agora é R$ 80 mil/mês', [10 => 'A meta é R$ 50 mil/mês'], $MIN);

    expect($decisao)->toBeNull();
});

it('shape inválido do LLM (sem supersedes_id) → null', function () use ($BIZ, $USER, $MIN) {
    $decisao = detectorComLlm([
        'supersede' => true,
        'confianca' => 90,
        // supersedes_id ausente → cast pra 0 → 0 não está nos candidatos → null
        'motivo' => 'faltou id',
    ])->decidir($BIZ, $USER, 'A meta agora é R$ 80 mil/mês', [10 => 'A meta é R$ 50 mil/mês'], $MIN);

    expect($decisao)->toBeNull();
});
