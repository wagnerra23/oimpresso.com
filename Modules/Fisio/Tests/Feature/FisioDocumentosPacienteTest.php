<?php

declare(strict_types=1);
// @covers-us US-FISIO-063
// Cobre UC-FER-01..UC-FER-07 (memory/requisitos/Fisio/_telas/exames-e-receitas.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Arquivos\Entities\Arquivo;
use Modules\Fisio\Entities\DocumentoPaciente;
use Modules\Fisio\Exceptions\DadoClinicoImutavelException;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Exames e receitas do paciente (US-FISIO-063, parte 1): anexar foto ou PDF ao prontuário pelo
 * backbone `Modules/Arquivos` (ADR 0123), abrir pela rota que confere a empresa, tirar da lista
 * sem apagar. Tenant 98 contra 99 (ADR 0358), inclusive no download. ⚠️ SKIP em SQLite (LC-13).
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip(['fisio_patient_documents', 'arquivos', 'arquivos_audit_log', 'arquivos_dedupe'])) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    config(['fisio.documentos.max_kb' => 10240]);
    // O Curador pode mandar o arquivo para o cofre cifrado: os dois discos ficam falsos.
    Storage::fake(config('arquivos.disk_default', 'local'));
    Storage::fake(config('arquivos.disk_vault', 'vault'));

    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->u98 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    $this->leitor98 = $this->usuarioComPermissoes([F::PERM_ACESSO], $this->seededTenant());
    $this->u99 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededSupportClientTenant());
    F::pacote([$this->b98, $this->b99]);
    $this->p = F::paciente($this->b98, $this->u98->id);
    F::como($this, $this->u98);
});

function ferFoto(): UploadedFile
{
    // Sufixo único: a dedupe do backbone é por md5 dentro da empresa.
    return UploadedFile::fake()->image('receita-'.uniqid().'.jpg', 64, 64);
}

function ferPdf(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('exame.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF ".uniqid('', true));
}

/** @return \Illuminate\Support\Collection<int, Arquivo> */
function ferArquivos(int $docId)
{
    // SUPERADMIN: verificação cruza as duas empresas. Tira só o escopo de empresa, não o de exclusão.
    return Arquivo::withoutGlobalScope('business_id')->withTrashed()
        ->where('arquivable_type', DocumentoPaciente::class)->where('arquivable_id', $docId)->get();
}

function ferAnexar($t, int $pacienteId, array $extra = [])
{
    return $t->postJson("/fisio/pacientes/{$pacienteId}/documentos", array_merge([
        'arquivo' => ferFoto(), 'tipo' => 'receita', 'data_documento' => '2026-11-03', 'observacao' => 'trouxe na 2ª sessão',
    ], $extra));
}

it('UC-FER-01 · anexar grava o documento na empresa do paciente e o arquivo sob biz-98, ligado ao documento', function () {
    $id = (int) ferAnexar($this, $this->p->id)->assertCreated()->json('id');
    $id2 = (int) ferAnexar($this, $this->p->id, ['arquivo' => ferPdf(), 'tipo' => 'exame_imagem', 'data_documento' => null, 'observacao' => null])->assertCreated()->json('id');

    $d = DocumentoPaciente::query()->findOrFail($id);
    expect($d->business_id)->toBe($this->b98)
        ->and($d->patient_id)->toBe($this->p->id)
        ->and($d->kind)->toBe('receita')
        ->and($d->document_date?->toDateString())->toBe('2026-11-03')
        ->and($d->note)->toBe('trouxe na 2ª sessão')
        ->and($d->origin)->toBe('clinica')
        ->and($d->status)->toBe('prontuario')
        ->and($d->created_by)->toBe($this->u98->id);

    $a = ferArquivos($id);
    expect($a)->toHaveCount(1)
        ->and((int) $a[0]->business_id)->toBe($this->b98)
        ->and($a[0]->storage_path)->toStartWith("biz-{$this->b98}/")
        ->and($a[0]->deleted_at)->toBeNull()
        ->and(ferArquivos($id2)->first()?->mime_type)->toBe('application/pdf');
});

it('UC-FER-02 · sem arquivo, tipo fora da lista, formato errado ou data futura: 422 e nada gravado', function () {
    $antes = DocumentoPaciente::query()->count();
    $this->postJson("/fisio/pacientes/{$this->p->id}/documentos", ['tipo' => 'receita'])->assertUnprocessable()->assertJsonValidationErrors('arquivo');
    ferAnexar($this, $this->p->id, ['tipo' => 'raio_x'])->assertUnprocessable()->assertJsonValidationErrors('tipo');
    ferAnexar($this, $this->p->id, ['arquivo' => UploadedFile::fake()->createWithContent('nota.txt', 'texto '.uniqid())])
        ->assertUnprocessable()->assertJsonValidationErrors('arquivo');
    ferAnexar($this, $this->p->id, ['data_documento' => now()->addDays(2)->toDateString()])->assertUnprocessable()->assertJsonValidationErrors('data_documento');
    ferAnexar($this, $this->p->id, ['observacao' => str_repeat('a', 501)])->assertUnprocessable()->assertJsonValidationErrors('observacao');

    expect(DocumentoPaciente::query()->count())->toBe($antes);
});

it('UC-FER-03 · abrir: a 98 recebe o arquivo com nosniff e a abertura vai ao activity_log; a 99 recebe 404 e nada é logado', function () {
    $id = (int) ferAnexar($this, $this->p->id)->assertCreated()->json('id');

    F::como($this, $this->leitor98);
    $r = $this->get("/fisio/documentos/{$id}/arquivo")->assertOk();
    expect($r->headers->get('Content-Type'))->toStartWith('image/jpeg')
        ->and($r->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and((string) $r->headers->get('Cache-Control'))->toContain('private')
        ->and(strlen((string) $r->getContent()))->toBeGreaterThan(0);

    $logs = fn () => DB::table('activity_log')->where('subject_type', DocumentoPaciente::class)->where('subject_id', $id)
        ->where('description', 'fisio.documento.aberto')->count();
    expect($logs())->toBe(1);

    F::como($this, $this->u99);
    $this->get("/fisio/documentos/{$id}/arquivo")->assertNotFound();
    expect($logs())->toBe(1);
});

it('UC-FER-04 · tirar da lista exige motivo, grava quem e quando, mantém documento e arquivo; repetir não muda nada', function () {
    $id = (int) ferAnexar($this, $this->p->id)->assertCreated()->json('id');

    $this->postJson("/fisio/documentos/{$id}/tirar", ['motivo' => ''])->assertUnprocessable()->assertJsonValidationErrors('motivo');
    $this->postJson("/fisio/documentos/{$id}/tirar", ['motivo' => 'anexado no paciente errado'])->assertOk()->assertJson(['tirado' => true]);

    $d = DocumentoPaciente::query()->findOrFail($id);
    $quando = $d->removed_at;
    expect($quando)->not->toBeNull()
        ->and($d->removed_by)->toBe($this->u98->id)
        ->and($d->removed_reason)->toBe('anexado no paciente errado')
        ->and(ferArquivos($id)->first()?->deleted_at)->toBeNull();
    expect(DB::table('activity_log')->where('subject_type', DocumentoPaciente::class)->where('subject_id', $id)
        ->where('description', 'fisio.documento.tirado_da_lista')->count())->toBe(1);

    $outro = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    F::como($this, $outro);
    $this->postJson("/fisio/documentos/{$id}/tirar", ['motivo' => 'de novo'])->assertOk()->assertJson(['tirado' => false]);
    $d->refresh();
    expect($d->removed_by)->toBe($this->u98->id)->and($d->removed_at?->equalTo($quando))->toBeTrue();

    // Continua no prontuário: o arquivo ainda abre.
    $this->get("/fisio/documentos/{$id}/arquivo")->assertOk();
});

it('UC-FER-05 · documento do prontuário não se apaga, nem pelo model nem pelo banco', function () {
    $id = (int) ferAnexar($this, $this->p->id)->assertCreated()->json('id');

    expect(fn () => DocumentoPaciente::query()->findOrFail($id)->delete())->toThrow(DadoClinicoImutavelException::class);
    expect(fn () => DB::table('fisio_patient_documents')->where('id', $id)->delete())->toThrow(\Illuminate\Database\QueryException::class);
    expect(DB::table('fisio_patient_documents')->where('id', $id)->exists())->toBeTrue();
});

it('UC-FER-06 · sem fisio.editar 403; documento e paciente de outra empresa 404; paciente bloqueado não recebe documento', function () {
    $id = (int) ferAnexar($this, $this->p->id)->assertCreated()->json('id');

    F::como($this, $this->leitor98);
    ferAnexar($this, $this->p->id)->assertForbidden();
    $this->postJson("/fisio/documentos/{$id}/tirar", ['motivo' => 'qualquer'])->assertForbidden();

    F::como($this, $this->u99);
    ferAnexar($this, $this->p->id)->assertNotFound();
    $this->postJson("/fisio/documentos/{$id}/tirar", ['motivo' => 'qualquer'])->assertNotFound();
    expect(DocumentoPaciente::query()->withoutGlobalScopes()->where('patient_id', $this->p->id)->count())->toBe(1); // SUPERADMIN: conferência cruzada.

    F::como($this, $this->u98);
    $bloqueado = F::paciente($this->b98, $this->u98->id, ['blocked_at' => now()]);
    ferAnexar($this, $bloqueado->id)->assertUnprocessable()->assertJsonValidationErrors('arquivo');
});

it('UC-FER-07 · a ficha recebe os tipos junto e os documentos deferidos, sem caminho de disco e sem os tirados', function () {
    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $r = $this->get("/fisio/pacientes/{$this->p->id}", ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'X-Inertia-Version' => (string) $versao])->assertOk();
    expect($r->json('props'))->not->toHaveKey('documentos')
        ->and($r->json('deferredProps.documentos'))->toContain('documentos')
        ->and(array_keys((array) $r->json('props.documento_tipos')))->toBe(['exame_imagem', 'exame_laboratorial', 'laudo_medico', 'receita', 'outro']);

    $fica = (int) ferAnexar($this, $this->p->id)->assertCreated()->json('id');
    $sai = (int) ferAnexar($this, $this->p->id, ['arquivo' => ferPdf(), 'tipo' => 'laudo_medico'])->assertCreated()->json('id');
    $this->postJson("/fisio/documentos/{$sai}/tirar", ['motivo' => 'duplicado'])->assertOk();

    $docs = F::props($this, $this->u98, "/fisio/pacientes/{$this->p->id}", 'Fisio/Pacientes/Show', 'documentos');
    expect(array_column($docs, 'id'))->toBe([$fica])
        ->and($docs[0]['url'])->toBe("/fisio/documentos/{$fica}/arquivo")
        ->and($docs[0]['formato'])->toBe('foto')
        ->and($docs[0])->not->toHaveKey('storage_path')
        ->and($docs[0])->not->toHaveKey('original_name')
        ->and(json_encode($docs))->not->toContain('biz-');
});
