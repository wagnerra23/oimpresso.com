<?php

declare(strict_types=1);
// @covers-us US-FISIO-010
// Cobre UC-FPS-17 (Fisio/Pacientes/Show.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Aba Evolução da ficha (US-FISIO-010): a série de dor e de dias com exercício do paciente,
 * na visão do PROFISSIONAL (ADR 0437 §5 — o gráfico de dor não vai ao portal). A prop é
 * deferida e lida como o browser lê (partial reload).
 *
 * Hoje = 2026-10-14. Janela de 30 dias = 15/09..14/10. Tenant 98 contra o 99.
 * Escrito junto com a tela (não fazia parte da suíte TDD da F1). ⚠️ SKIP em SQLite (LC-13).
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip()) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    Carbon::setTestNow('2026-10-14 12:00:00');
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->u98 = $this->usuarioComPermissoes([F::PERM_ACESSO], $this->seededTenant());
    $this->u99 = $this->usuarioComPermissoes([F::PERM_ACESSO], $this->seededSupportClientTenant());
    F::pacote([$this->b98, $this->b99]);
    $this->ex = F::exercicio(null);
});

afterEach(fn () => Carbon::setTestNow());

it('UC-FPS-17 · evolução traz os registros dos últimos 30 dias, em ordem, com dor, feitos e sinal de alerta', function () {
    $p = F::paciente($this->b98, $this->u98->id, ['sessions_per_week' => 2]);
    $prog = F::programa($p, [$this->ex], $this->u98->id, ['starts_at' => '2026-09-01']);
    $item = F::itens($prog);
    F::checkin($p, $prog, '2026-09-10', $item, 9);              // fora da janela
    F::checkin($p, $prog, '2026-10-13', [], 6, 'adequado', ['febre']);
    F::checkin($p, $prog, '2026-10-12', $item, null);
    F::checkin($p, $prog, '2026-10-01', $item, 4);

    $ev = F::props($this, $this->u98, "/fisio/pacientes/{$p->id}", 'Fisio/Pacientes/Show', 'evolucao');

    expect($ev['dias'])->toBe(30)
        ->and($ev['sessions_per_week'])->toBe(2)
        ->and($ev['adesao_7d'])->toBe(50)                          // 1 dia com exercício ÷ 2
        ->and(array_column($ev['pontos'], 'date'))->toBe(['2026-10-01', '2026-10-12', '2026-10-13'])
        ->and(array_column($ev['pontos'], 'pain'))->toBe([4, null, 6])
        ->and(array_column($ev['pontos'], 'feitos'))->toBe([1, 1, 0])
        ->and($ev['pontos'][2]['red_flags'])->toBe(['febre'])
        ->and($ev)->not->toHaveKey('serie_dor');

    // A série de dor (prop própria) traz só o que tem dor informada, em ordem de data.
    $serie = F::props($this, $this->u98, "/fisio/pacientes/{$p->id}", 'Fisio/Pacientes/Show', 'serie_dor');
    expect($serie)->toBe([['date' => '2026-10-01', 'pain' => 4], ['date' => '2026-10-13', 'pain' => 6]]);
});

it('UC-FPS-17 · sem programa ativo a adesão é nula e sem registro a série é vazia', function () {
    $p = F::paciente($this->b98, $this->u98->id);

    $ev = F::props($this, $this->u98, "/fisio/pacientes/{$p->id}", 'Fisio/Pacientes/Show', 'evolucao');

    expect($ev['adesao_7d'])->toBeNull()
        ->and($ev['pontos'])->toBe([]);
});

it('UC-FPS-17 · evolução de paciente de outra clínica não abre e não vaza check-in', function () {
    $alheio = F::paciente($this->b99, $this->u99->id);
    $prog = F::programa($alheio, [$this->ex], $this->u99->id);
    F::checkin($alheio, $prog, '2026-10-13', F::itens($prog), 8);

    F::como($this, $this->u98);
    $this->get("/fisio/pacientes/{$alheio->id}")->assertNotFound();

    // Controle positivo: a própria 99 enxerga o registro.
    $ev = F::props($this, $this->u99, "/fisio/pacientes/{$alheio->id}", 'Fisio/Pacientes/Show', 'evolucao');
    expect(array_column($ev['pontos'], 'pain'))->toBe([8]);
});
