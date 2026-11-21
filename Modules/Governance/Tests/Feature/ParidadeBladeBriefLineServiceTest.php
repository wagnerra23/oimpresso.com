<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Process;
use Modules\Governance\Services\ParidadeBladeBriefLineService;

uses(Tests\TestCase::class);

/**
 * Linha "Blade por migrar" do Daily Brief — o serviço não tinha teste nenhum.
 *
 * Contrato medido aqui (auditoria Governance L2.2, 2026-11-19): a sonda Node falhando
 * não pode fazer a linha sumir calada — o brief diz "não medido (motivo)". E o caminho
 * de sucesso continua formatando a contagem a partir do JSON da sonda.
 * Shell-out fakeado (Process::fake), sem DB.
 */
it('sonda com JSON → linha com total e pior módulo (core não conta como pior)', function () {
    Process::fake(['*' => Process::result(output: json_encode([
        'total_blade' => 12,
        'por_escopo' => ['core' => ['blade' => 30], 'ModuloFicticioXyz' => ['blade' => 9]],
    ]))]);

    expect((new ParidadeBladeBriefLineService())->line())
        ->toStartWith('🟠 Blade por migrar: 12 endpoints — pior módulo: ModuloFicticioXyz (9)');
});

it('node ausente (exit 127, sem saída) → "não medido", nunca some calada', function () {
    Process::fake(['*' => Process::result(output: '', exitCode: 127)]);

    expect((new ParidadeBladeBriefLineService())->line())
        ->toBe('⚪ Blade por migrar: não medido (node ausente (exit 127))');
});

it('sonda sem saída (exit 1) → "não medido" com o exit no motivo', function () {
    Process::fake(['*' => Process::result(output: '', exitCode: 1)]);

    expect((new ParidadeBladeBriefLineService())->line())
        ->toBe('⚪ Blade por migrar: não medido (sonda sem saída (exit 1))');
});

it('censo com 0 endpoints → null (nada a reportar, mediu e não há o que dizer)', function () {
    Process::fake(['*' => Process::result(output: json_encode(['total_blade' => 0, 'por_escopo' => []]))]);

    expect((new ParidadeBladeBriefLineService())->line())->toBeNull();
});
