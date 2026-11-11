<?php

declare(strict_types=1);
// @covers-us US-FISIO-060
// Cobre UC-FBP-10..UC-FBP-12 (memory/requisitos/Fisio/_telas/laudo-biopsicossocial.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Modules\Fisio\Entities\Checkin;
use Modules\Fisio\Entities\Questionnaire;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Apoio da fisioterapia ao laudo biopsicossocial: só o que o Fisio mede, com período
 * e fonte; sem dado suficiente, aviso no lugar do número; nenhuma conclusão. Os números
 * esperados abaixo foram feitos à mão a partir das fixtures (conta no comentário de cada um).
 * Tenant 98 contra 99 (ADR 0358). ⚠️ SKIP em SQLite (LC-13).
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip(['fisio_laudos_bps', 'fisio_laudos_bps_equipe', 'fisio_questionnaires'])) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    $this->b98 = (int) $this->seededTenant()->id;
    $this->u98 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    $this->u99 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededSupportClientTenant());
    F::pacote([$this->b98, (int) $this->seededSupportClientTenant()->id]);
    Carbon::setTestNow('2026-11-11 12:00:00');
    $this->p = F::paciente($this->b98, $this->u98->id, ['sessions_per_week' => 3]);
    F::como($this, $this->u98);
    $this->laudoId = (int) $this->postJson("/fisio/pacientes/{$this->p->id}/laudos-bps")->assertCreated()->json('id');
});

afterEach(fn () => Carbon::setTestNow());

it('UC-FBP-10 · dor de 30 e 90 dias, crises, adesão e questionários com período e fonte', function () {
    $prog = F::programa($this->p, [F::exercicio(null)], $this->u98->id);
    $item = F::itens($prog)[0];
    // Últimos 30 dias (13/10 a 11/11): 8 dias, dor 2,3,4,5,6,7,8,5 (soma 40), exercício em 6, crise em 2.
    foreach ([['2026-10-15', 2, true, false], ['2026-10-18', 3, true, false], ['2026-10-22', 4, true, true], ['2026-10-25', 5, true, false],
        ['2026-10-29', 6, true, false], ['2026-11-02', 7, true, true], ['2026-11-05', 8, false, false], ['2026-11-09', 5, false, false]] as [$dia, $dor, $fez, $crise]) {
        $c = F::checkin($this->p, $prog, $dia, $fez ? [$item] : [], $dor);
        Checkin::withoutGlobalScopes()->whereKey($c->id)->update(['crisis' => $crise]);
    }
    // 31 a 90 dias atrás: 4 dias, dor 9,1,5,5 (soma 20), sem exercício, 1 crise. Um dia fora da janela de 90 não entra.
    foreach ([['2026-08-20', 9, true], ['2026-09-01', 1, false], ['2026-09-15', 5, false], ['2026-10-01', 5, false], ['2026-08-01', 0, false]] as [$dia, $dor, $crise]) {
        $c = F::checkin($this->p, $prog, $dia, [], $dor);
        Checkin::withoutGlobalScopes()->whereKey($c->id)->update(['crisis' => $crise]);
    }
    // SUPERADMIN: fixture de estado inicial na 98.
    Questionnaire::withoutGlobalScopes()->create(['business_id' => $this->b98, 'patient_id' => $this->p->id,
        'instrument' => 'rmdq', 'answers' => [], 'score' => '14', 'answered_at' => '2026-10-20 10:00:00']);

    $d = $this->getJson("/fisio/laudos-bps/{$this->laudoId}/apoio-fisio")->assertOk()->json();

    // 30 dias: 40 ÷ 8 = 5,0; mínima 2; máxima 8; 2 crises.
    expect($d['janelas'][0])->toMatchArray(['dias' => 30, 'de' => '2026-10-13', 'ate' => '2026-11-11', 'registros_dor' => 8,
        'media' => 5.0, 'minima' => 2, 'maxima' => 8, 'crises' => 2]);
    // 90 dias (14/08 a 11/11): (40 + 20) ÷ 12 = 5,0; mínima 1; máxima 9; 3 crises.
    expect($d['janelas'][1])->toMatchArray(['dias' => 90, 'de' => '2026-08-14', 'registros_dor' => 12,
        'media' => 5.0, 'minima' => 1, 'maxima' => 9, 'crises' => 3]);
    // Adesão: 6 dias com exercício ÷ (3 × 30 ÷ 7 = 12,857) = 46,7% → 47.
    expect($d['adesao'])->toMatchArray(['dias_com_exercicio' => 6, 'meta_semanal' => 3, 'percentual' => 47]);
    expect($d['questionarios'][0])->toMatchArray(['instrumento' => 'RMDQ', 'em' => '20/10/2026'])
        ->and($d['avisos'])->toBe([]);

    expect($d['texto'])->toContain('13/10/2026 a 11/11/2026: média 5,0, mínima 2 e máxima 8, em 8 dias com registro. Dias de crise registrados: 2.')
        ->and($d['texto'])->toContain('14/08/2026 a 11/11/2026: média 5,0, mínima 1 e máxima 9, em 12 dias com registro. Dias de crise registrados: 3.')
        ->and($d['texto'])->toContain('47% (6 dias com exercício feito, para a meta de 3 sessões por semana)')
        ->and($d['texto'])->toContain('em 20/10/2026.')
        ->and($d['texto'])->toContain('Fonte: registros do paciente no Fisio');
});

it('UC-FBP-11 · sem dado suficiente: nenhum número, aviso no lugar', function () {
    // 6 dias com dor (um a menos que o mínimo de 7) e nenhum programa ativo.
    foreach (['2026-11-01', '2026-11-02', '2026-11-03', '2026-11-04', '2026-11-05', '2026-11-06'] as $dia) {
        F::checkin($this->p, null, $dia, [], 6);
    }

    $d = $this->getJson("/fisio/laudos-bps/{$this->laudoId}/apoio-fisio")->assertOk()->json();

    expect($d['janelas'][0]['media'])->toBeNull()->and($d['janelas'][1]['media'])->toBeNull()
        ->and($d['adesao'])->toBeNull()->and($d['questionarios'])->toBe([])
        ->and($d['avisos'])->toBe([
            'Dor dos últimos 30 dias: 6 registro(s), menos que os 7 necessários. Não calculada.',
            'Dor dos últimos 90 dias: 6 registro(s), menos que os 7 necessários. Não calculada.',
            'Adesão: sem programa ativo, não calculada.',
            'Questionários: nenhum respondido.',
        ]);
    expect($d['texto'])->not->toContain('média')->and($d['texto'])->not->toContain('Adesão');
});

it('UC-FBP-12 · o texto não conclui; quem só lê vê o apoio; outra empresa 404', function () {
    foreach (range(1, 8) as $n) {
        F::checkin($this->p, null, sprintf('2026-11-%02d', $n), [], 9);
    }
    $d = $this->getJson("/fisio/laudos-bps/{$this->laudoId}/apoio-fisio")->assertOk()->json();
    expect(preg_match('/defici|incapa|grave|severa|invalidez/iu', $d['texto']))->toBe(0);

    F::como($this, $this->usuarioComPermissoes([F::PERM_ACESSO], $this->seededTenant()));
    $this->getJson("/fisio/laudos-bps/{$this->laudoId}/apoio-fisio")->assertOk();

    F::como($this, $this->u99);
    $this->getJson("/fisio/laudos-bps/{$this->laudoId}/apoio-fisio")->assertNotFound();
});
