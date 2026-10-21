<?php

declare(strict_types=1);
// @covers-us US-FISIO-010
// Cobre UC-FPN-02, UC-FPN-03, UC-FPN-04, UC-FPN-05 (Fisio/Painel.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Painel (T-10) — os números batem com os check-ins de teste. A regra pura está nos Unit
 * (AdesaoCalculatorTest, AtencaoDetectorTest); aqui se prova que o painel lê o banco certo,
 * no tenant certo, e entrega as mesmas contas.
 *
 * Hoje = 2026-10-14. Janela de 7 dias = 08/10..14/10 (hoje incluso).
 * Tenant 98. ⚠️ SKIP em SQLite (LC-13).
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip()) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    Carbon::setTestNow('2026-10-14 12:00:00');
    $this->b98 = (int) $this->seededTenant()->id;
    $this->u98 = $this->usuarioComPermissoes([F::PERM_ACESSO], $this->seededTenant());
    F::pacote([$this->b98]);
    $this->ex = F::exercicio(null);
});

afterEach(fn () => Carbon::setTestNow());

function fpnLinha($t, int $patientId): array
{
    $linha = collect(F::props($t, $t->u98, '/fisio', 'Fisio/Painel', 'pacientes'))->firstWhere('id', $patientId);
    expect($linha)->not->toBeNull();

    return $linha;
}

function fpnAtencao($t): \Illuminate\Support\Collection
{
    return collect(F::props($t, $t->u98, '/fisio', 'Fisio/Painel', 'atencao'))->keyBy('patient_id');
}

it('UC-FPN-02 · a tela abre com KPIs, fila de atenção e tabela', function () {
    F::como($this, $this->u98);
    $this->get('/fisio')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->component('Fisio/Painel', false));
});

it('UC-FPN-02 · adesão 7d = dias com ≥1 exercício feito ÷ sessions_per_week', function () {
    $p = F::paciente($this->b98, $this->u98->id, ['sessions_per_week' => 3]);
    $prog = F::programa($p, [$this->ex], $this->u98->id);
    $item = F::itens($prog);

    F::checkin($p, $prog, '2026-10-09', $item, 2);
    F::checkin($p, $prog, '2026-10-12', $item, 2);
    F::checkin($p, $prog, '2026-10-13', [], 2);        // registrou mas não fez nada: não conta
    F::checkin($p, $prog, '2026-10-07', $item, 2);     // fora da janela: não conta

    expect(fpnLinha($this, $p->id)['adesao_7d'])->toBe(67); // 2/3 → 66,7% → 67
});

it('UC-FPN-02 · mais check-ins que a meta → 100%, nunca acima', function () {
    $p = F::paciente($this->b98, $this->u98->id, ['sessions_per_week' => 2]);
    $prog = F::programa($p, [$this->ex], $this->u98->id);
    foreach (['2026-10-08', '2026-10-09', '2026-10-10', '2026-10-11', '2026-10-12'] as $d) {
        F::checkin($p, $prog, $d, F::itens($prog), 1);
    }

    expect(fpnLinha($this, $p->id)['adesao_7d'])->toBe(100);
});

it('UC-FPN-02 · sem programa ativo, a adesão é nula (não 0%) e o paciente não derruba a média', function () {
    $sem = F::paciente($this->b98, $this->u98->id);
    $com = F::paciente($this->b98, $this->u98->id, ['sessions_per_week' => 1]);
    $prog = F::programa($com, [$this->ex], $this->u98->id);
    F::checkin($com, $prog, '2026-10-13', F::itens($prog), 2);

    expect(fpnLinha($this, $sem->id)['adesao_7d'])->toBeNull()
        ->and(F::props($this, $this->u98, '/fisio', 'Fisio/Painel', 'kpis')['adesao_media_7d'])->toBe(100);
});

it('UC-FPN-02 · KPIs: pacientes ativos não contam bloqueados; dor média usa o último registro de cada um', function () {
    $a = F::paciente($this->b98, $this->u98->id);
    $b = F::paciente($this->b98, $this->u98->id);
    $bloq = F::paciente($this->b98, $this->u98->id, ['blocked_at' => now()]);
    $pa = F::programa($a, [$this->ex], $this->u98->id);
    $pb = F::programa($b, [$this->ex], $this->u98->id);
    F::checkin($a, $pa, '2026-10-10', F::itens($pa), 8);
    F::checkin($a, $pa, '2026-10-13', F::itens($pa), 4);
    F::checkin($b, $pb, '2026-10-13', F::itens($pb), 2);
    F::checkin($bloq, null, '2026-10-13', [], 10);

    $kpis = F::props($this, $this->u98, '/fisio', 'Fisio/Painel', 'kpis');
    expect($kpis['pacientes_ativos'])->toBe(2)
        ->and((float) $kpis['dor_media'])->toBe(3.0);
});

it('UC-FPN-03 · dor subiu ≥2 entre os 2 últimos registros → atenção; subir 1 não entra', function () {
    $subiu = F::paciente($this->b98, $this->u98->id);
    $pouco = F::paciente($this->b98, $this->u98->id);
    $ps = F::programa($subiu, [$this->ex], $this->u98->id);
    $pp = F::programa($pouco, [$this->ex], $this->u98->id);
    F::checkin($subiu, $ps, '2026-10-12', F::itens($ps), 3);
    F::checkin($subiu, $ps, '2026-10-13', F::itens($ps), 5);
    F::checkin($pouco, $pp, '2026-10-12', F::itens($pp), 3);
    F::checkin($pouco, $pp, '2026-10-13', F::itens($pp), 4);

    $fila = fpnAtencao($this);
    expect($fila[$subiu->id]['motivos'])->toContain('dor_subiu')
        ->and($fila->has($pouco->id))->toBeFalse();
});

it('UC-FPN-03 · dor ≥7 no último registro → atenção', function () {
    $p = F::paciente($this->b98, $this->u98->id);
    $prog = F::programa($p, [$this->ex], $this->u98->id);
    F::checkin($p, $prog, '2026-10-13', F::itens($prog), 7);

    expect(fpnAtencao($this)[$p->id]['motivos'])->toContain('dor_alta');
});

it('UC-FPN-04 · sem registro há ≥4 dias com programa ativo → atenção; 3 dias não; sem programa não', function () {
    $sumiu = F::paciente($this->b98, $this->u98->id);
    $recente = F::paciente($this->b98, $this->u98->id);
    $semProg = F::paciente($this->b98, $this->u98->id);
    $p1 = F::programa($sumiu, [$this->ex], $this->u98->id, ['starts_at' => '2026-10-01']);
    $p2 = F::programa($recente, [$this->ex], $this->u98->id, ['starts_at' => '2026-10-01']);
    F::checkin($sumiu, $p1, '2026-10-10', F::itens($p1), 2);    // 4 dias
    F::checkin($recente, $p2, '2026-10-11', F::itens($p2), 2);  // 3 dias
    F::checkin($semProg, null, '2026-10-01', [], 2);

    $fila = fpnAtencao($this);
    expect($fila[$sumiu->id]['motivos'])->toContain('sem_registro')
        ->and($fila->has($recente->id))->toBeFalse()
        ->and($fila->has($semProg->id))->toBeFalse();
});

it('UC-FPN-04 · programa ativo há ≥4 dias e NUNCA registrou → atenção (conta desde starts_at)', function () {
    $nunca = F::paciente($this->b98, $this->u98->id);
    $novo = F::paciente($this->b98, $this->u98->id);
    F::programa($nunca, [$this->ex], $this->u98->id, ['starts_at' => '2026-10-10']);
    F::programa($novo, [$this->ex], $this->u98->id, ['starts_at' => '2026-10-12']);

    $fila = fpnAtencao($this);
    expect($fila[$nunca->id]['motivos'])->toContain('sem_registro')
        ->and($fila->has($novo->id))->toBeFalse();
});

it('UC-FPN-05 · tabela traz nível e sequência derivados, e bloqueado não aparece', function () {
    config(['fisio.jogo.niveis' => [1 => 0, 2 => 100]]);
    $p = F::paciente($this->b98, $this->u98->id, ['sessions_per_week' => 3]);
    $bloq = F::paciente($this->b98, $this->u98->id, ['blocked_at' => now()]);
    $prog = F::programa($p, [$this->ex], $this->u98->id);
    $token = F::portalPronto($this, $p);
    $this->postJson('/fisio/p/'.$token.'/checkin', ['done_item_ids' => F::itens($prog), 'pain' => 2, 'effort' => 'adequado'])->assertCreated();

    $linha = fpnLinha($this, $p->id);
    expect($linha['nivel'])->toBe(1)                      // 90 XP (60 + 30) < 100
        ->and($linha['sequencia'])->toBe(1)
        ->and($linha['ultima_dor'])->toBe(2)
        ->and(collect(F::props($this, $this->u98, '/fisio', 'Fisio/Painel', 'pacientes'))->pluck('id'))->not->toContain($bloq->id);
});
