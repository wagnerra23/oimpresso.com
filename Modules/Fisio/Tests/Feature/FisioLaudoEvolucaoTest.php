<?php

declare(strict_types=1);
// @covers-us US-FISIO-005
// Cobre UC-FLD-01..UC-FLD-03 (Fisio/Pacientes/Show.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Fisio\Entities\Questionnaire;
use Modules\Fisio\Entities\Record;
use Modules\Fisio\Services\LaudoEvolucao;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Laudo de evolução: síntese de uma página montada SÓ do que está assinado no prontuário e dos
 * questionários respondidos. Mesmo desenho do FisioProntuarioPdfTest: o conteúdo pelo serviço e
 * pelo HTML da view; a rota pelo binário, 403/404 e uma linha no activity_log sem PII.
 * Tenant 98 contra 99. ⚠️ SKIP em SQLite (LC-13).
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip(['activity_log'])) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->u98 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    $this->u99 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededSupportClientTenant());
    F::pacote([$this->b98, $this->b99]);
    $this->p = F::paciente($this->b98, $this->u98->id);
    F::como($this, $this->u98);

    Carbon::setTestNow('2026-10-01 09:00:00');
    $this->postJson("/fisio/pacientes/{$this->p->id}/registros", ['kind' => 'avaliacao', 'crefito' => F::CREFITO, 'body' => [
        'queixa_principal' => 'Dor lombar', 'historia_doenca' => 'Há 3 meses', 'exame_fisico' => 'Flexão limitada',
        'diagnostico_cinesiologico' => 'Disfunção do controle motor lombopélvico', 'objetivos' => ['reduzir dor'],
        'plano_tratamento' => 'Cinesioterapia 2x/semana por 6 semanas',
    ]])->assertCreated();
    Carbon::setTestNow('2026-10-08 10:00:00');
    $this->postJson("/fisio/pacientes/{$this->p->id}/registros", ['kind' => 'evolucao', 'crefito' => F::CREFITO,
        'body' => ['conduta' => 'Ponte glútea 3x10', 'resposta_paciente' => 'Sem dor']])->assertCreated();
    // Rascunho não assinado e questionários: estado inicial por fixture.
    // SUPERADMIN: fixture de estado inicial; o escopo é provado nos asserts.
    Record::withoutGlobalScopes()->create(['business_id' => $this->b98, 'patient_id' => $this->p->id, 'kind' => Record::KIND_EVOLUCAO,
        'body' => ['conduta' => 'RASCUNHO-NAO-ASSINADO', 'resposta_paciente' => '-']]);
    foreach ([['2026-10-01', '14'], ['2026-10-15', '6']] as [$dia, $score]) {
        Questionnaire::withoutGlobalScopes()->create(['business_id' => $this->b98, 'patient_id' => $this->p->id,
            'instrument' => 'rmdq', 'answers' => [], 'score' => $score, 'answered_at' => "{$dia} 10:00:00"]);
    }
    Carbon::setTestNow('2026-10-21 14:00:00');
});

afterEach(fn () => Carbon::setTestNow());

it('UC-FLD-01 · o laudo sai do que está assinado: diagnóstico e plano da avaliação, conduta da evolução, medidas inicial e atual', function () {
    $d = app(LaudoEvolucao::class)->montar($this->p->fresh(), $this->b98, $this->u98->id);

    expect($d['diagnostico'])->toBe('Disfunção do controle motor lombopélvico')
        ->and($d['plano'])->toBe('Cinesioterapia 2x/semana por 6 semanas')
        ->and($d['conduta'])->toBe('Ponte glútea 3x10')
        ->and($d['periodo'])->toBe('01/10/2026 a 21/10/2026')
        ->and($d['contagem'])->toBe(['avaliacoes' => 1, 'evolucoes' => 1, 'telemonitoramentos' => 0])
        ->and($d['medidas'][0]['instrumento'])->toBe('RMDQ')
        ->and((float) $d['medidas'][0]['primeira'])->toBe(14.0)
        ->and((float) $d['medidas'][0]['atual'])->toBe(6.0)
        ->and($d['crefito'])->toBe(F::CREFITO);

    $html = view('fisio::pdf.laudo-evolucao', $d)->render();
    expect($html)->toContain('Laudo de evolução fisioterapêutica')
        ->and($html)->toContain('Ponte glútea 3x10')
        ->and($html)->toContain('Res. COFFITO 414/2012');
    expect(str_contains($html, 'RASCUNHO-NAO-ASSINADO'))->toBeFalse();
});

it('UC-FLD-02 · a rota baixa o PDF e grava uma linha no activity_log sem PII', function () {
    $contato = DB::table('contacts')->where('id', $this->p->contact_id)->first();

    $r = $this->get("/fisio/pacientes/{$this->p->id}/laudo.pdf")->assertOk();

    expect($r->headers->get('Content-Type'))->toContain('application/pdf')
        ->and(substr((string) $r->getContent(), 0, 5))->toBe('%PDF-');
    $log = DB::table('activity_log')->where('log_name', 'fisio')->where('description', 'laudo_exportado')->where('subject_id', $this->p->id)->sole();
    expect((int) $log->causer_id)->toBe($this->u98->id)
        ->and(str_contains((string) $log->properties, (string) $contato->name))->toBeFalse()
        ->and(str_contains((string) $log->properties, 'Disfunção'))->toBeFalse();
});

it('UC-FLD-03 · outra clínica recebe 404 e sem fisio.access 403; nada é registrado', function () {
    F::como($this, $this->u99);
    $this->get("/fisio/pacientes/{$this->p->id}/laudo.pdf")->assertNotFound();

    F::como($this, $this->usuarioComPermissoes([], $this->seededTenant()));
    $this->get("/fisio/pacientes/{$this->p->id}/laudo.pdf")->assertForbidden();

    expect(DB::table('activity_log')->where('description', 'laudo_exportado')->where('subject_id', $this->p->id)->count())->toBe(0);

    // Controle: a 99 tira o laudo do próprio paciente (o 404 de cima é do escopo, não da rota).
    F::como($this, $this->u99);
    $this->get('/fisio/pacientes/'.F::paciente($this->b99, $this->u99->id)->id.'/laudo.pdf')->assertOk();
});
