<?php

declare(strict_types=1);
// @covers-us US-FISIO-063
// Cobre UC-FER-08..UC-FER-11 (memory/requisitos/Fisio/_telas/exames-e-receitas.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\Fisio\Entities\MedicamentoPaciente;
use Modules\Fisio\Exceptions\DadoClinicoImutavelException;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Medicamentos em uso, estruturados (US-FISIO-063, parte 2). Convive com o texto livre da
 * avaliação, que não muda. Tenant 98 contra 99 (ADR 0358). ⚠️ SKIP em SQLite (LC-13).
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip(['fisio_patient_medications', 'fisio_patient_documents'])) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    $this->b98 = (int) $this->seededTenant()->id;
    $this->u98 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    $this->leitor98 = $this->usuarioComPermissoes([F::PERM_ACESSO], $this->seededTenant());
    $this->u99 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededSupportClientTenant());
    F::pacote([$this->b98, (int) $this->seededSupportClientTenant()->id]);
    $this->p = F::paciente($this->b98, $this->u98->id);
    F::como($this, $this->u98);
});

function fmedIncluir($t, int $pacienteId, array $dados = [])
{
    return $t->postJson("/fisio/pacientes/{$pacienteId}/medicamentos", array_merge(
        ['nome' => 'Pregabalina', 'dose' => '75 mg', 'frequencia' => '1 cápsula à noite', 'desde' => '2026-11-03'], $dados));
}

it('UC-FER-08 · incluir grava nome, dose, frequência e datas na empresa do paciente; nome obrigatório e fim não antes do início', function () {
    $id = (int) fmedIncluir($this, $this->p->id)->assertCreated()->json('id');
    $m = MedicamentoPaciente::query()->findOrFail($id);
    expect($m->business_id)->toBe($this->b98)
        ->and($m->name)->toBe('Pregabalina')
        ->and($m->dose)->toBe('75 mg')
        ->and($m->frequency)->toBe('1 cápsula à noite')
        ->and($m->started_on?->toDateString())->toBe('2026-11-03')
        ->and($m->ended_on)->toBeNull()
        ->and($m->source)->toBe('manual')
        ->and($m->created_by)->toBe($this->u98->id);

    $antes = MedicamentoPaciente::query()->count();
    fmedIncluir($this, $this->p->id, ['nome' => ''])->assertUnprocessable()->assertJsonValidationErrors('nome');
    fmedIncluir($this, $this->p->id, ['ate' => '2026-11-01'])->assertUnprocessable()->assertJsonValidationErrors('ate');
    expect(MedicamentoPaciente::query()->count())->toBe($antes);
});

it('UC-FER-09 · alterar muda só o que veio e grava o antes e o depois; encerrar antes do início é recusado', function () {
    $id = (int) fmedIncluir($this, $this->p->id)->assertCreated()->json('id');

    $this->putJson("/fisio/medicamentos/{$id}", ['dose' => '150 mg'])->assertOk();
    $m = MedicamentoPaciente::query()->findOrFail($id);
    expect($m->dose)->toBe('150 mg')->and($m->frequency)->toBe('1 cápsula à noite')->and($m->name)->toBe('Pregabalina');

    $log = DB::table('activity_log')->where('subject_type', MedicamentoPaciente::class)->where('subject_id', $id)
        ->where('description', 'fisio.medicamento.alterado')->sole();
    $props = json_decode((string) $log->properties, true);
    expect($props['antes'])->toBe(['dose' => '75 mg'])->and($props['depois'])->toBe(['dose' => '150 mg']);

    $this->putJson("/fisio/medicamentos/{$id}", ['ate' => '2026-11-01'])->assertUnprocessable()->assertJsonValidationErrors('ate');
    $this->putJson("/fisio/medicamentos/{$id}", ['ate' => '2026-11-10'])->assertOk();
    expect(MedicamentoPaciente::query()->findOrFail($id)->ended_on?->toDateString())->toBe('2026-11-10');
});

it('UC-FER-10 · tirar da lista exige motivo, não apaga e trava a alteração; o banco recusa DELETE', function () {
    $id = (int) fmedIncluir($this, $this->p->id)->assertCreated()->json('id');

    $this->postJson("/fisio/medicamentos/{$id}/tirar", ['motivo' => ''])->assertUnprocessable()->assertJsonValidationErrors('motivo');
    $this->postJson("/fisio/medicamentos/{$id}/tirar", ['motivo' => 'digitado no paciente errado'])->assertOk()->assertJson(['tirado' => true]);
    $m = MedicamentoPaciente::query()->findOrFail($id);
    expect($m->removed_by)->toBe($this->u98->id)->and($m->removed_reason)->toBe('digitado no paciente errado');

    $this->putJson("/fisio/medicamentos/{$id}", ['dose' => '1 g'])->assertUnprocessable();
    expect(fn () => $m->delete())->toThrow(DadoClinicoImutavelException::class);
    expect(fn () => DB::table('fisio_patient_medications')->where('id', $id)->delete())->toThrow(\Illuminate\Database\QueryException::class);

    $lista = F::props($this, $this->u98, "/fisio/pacientes/{$this->p->id}", 'Fisio/Pacientes/Show', 'medicamentos');
    expect($lista)->toBe([]);
});

it('UC-FER-11 · a ficha recebe os medicamentos deferidos, em uso primeiro; sem fisio.editar 403; a 99 recebe 404', function () {
    $encerrado = (int) fmedIncluir($this, $this->p->id, ['nome' => 'Dipirona', 'desde' => '2026-10-01', 'ate' => '2026-10-05'])->assertCreated()->json('id');
    $emUso = (int) fmedIncluir($this, $this->p->id)->assertCreated()->json('id');

    $lista = F::props($this, $this->u98, "/fisio/pacientes/{$this->p->id}", 'Fisio/Pacientes/Show', 'medicamentos');
    expect(array_column($lista, 'id'))->toBe([$emUso, $encerrado])
        ->and($lista[0]['em_uso'])->toBeTrue()
        ->and($lista[1]['em_uso'])->toBeFalse()
        ->and($lista[0]['origem'])->toBe('manual');

    F::como($this, $this->leitor98);
    fmedIncluir($this, $this->p->id)->assertForbidden();
    $this->putJson("/fisio/medicamentos/{$emUso}", ['dose' => '1 g'])->assertForbidden();

    F::como($this, $this->u99);
    fmedIncluir($this, $this->p->id)->assertNotFound();
    $this->putJson("/fisio/medicamentos/{$emUso}", ['dose' => '1 g'])->assertNotFound();
    $this->postJson("/fisio/medicamentos/{$emUso}/tirar", ['motivo' => 'qualquer'])->assertNotFound();
    expect(MedicamentoPaciente::query()->withoutGlobalScopes()->findOrFail($emUso)->dose)->toBe('75 mg'); // SUPERADMIN: conferência cruzada.
});
