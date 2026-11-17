<?php

declare(strict_types=1);
// @covers-us US-FISIO-063
// Cobre UC-FER-17..UC-FER-21 (memory/requisitos/Fisio/_telas/exames-e-receitas.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Arquivos\Entities\Arquivo;
use Modules\Fisio\Entities\DocumentoPaciente;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Envio de foto de exame ou receita pelo portal do paciente (US-FISIO-063, parte 4): consentimento
 * próprio, chega pendente, nunca direto no prontuário; a clínica aceita ou recusa na ficha.
 * Tenant 98 contra 99 (ADR 0358). ⚠️ SKIP em SQLite (LC-13).
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip(['fisio_patient_documents', 'fisio_document_transcriptions', 'fisio_settings', 'arquivos'])) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    config(['fisio.documentos.portal_max_dia' => 10, 'fisio.consent_versions.documentos' => 'doc-v1']);
    Storage::fake(config('arquivos.disk_default', 'local'));
    Storage::fake(config('arquivos.disk_vault', 'vault'));

    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->u98 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    $this->leitor98 = $this->usuarioComPermissoes([F::PERM_ACESSO], $this->seededTenant());
    $this->u99 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededSupportClientTenant());
    F::pacote([$this->b98, $this->b99]);
    $this->p = F::paciente($this->b98, $this->u98->id);
});

function fpdFoto(): UploadedFile
{
    return UploadedFile::fake()->image('foto-'.uniqid().'.jpg', 64, 64);
}

function fpdEnviar($t, string $token, array $extra = [])
{
    return $t->postJson("/fisio/p/{$token}/documentos", array_merge(['arquivo' => fpdFoto(), 'tipo' => 'exame_imagem', 'observacao' => 'ressonância da coluna'], $extra));
}

/** Portal com PIN, teleatendimento e o consentimento `documentos` dados pelas rotas públicas. */
function fpdPronto($t, $paciente): string
{
    $token = F::portalPronto($t, $paciente);
    $t->postJson("/fisio/p/{$token}/consentimentos", ['purpose' => 'documentos', 'aceito' => true])->assertCreated();

    return $token;
}

it('UC-FER-17 · sem o consentimento próprio o portal recusa; com ele, o envio chega pendente na empresa do paciente', function () {
    $token = F::portalPronto($this, $this->p);
    fpdEnviar($this, $token)->assertForbidden()->assertJson(['erro' => 'consentimento_documentos']);
    expect(DocumentoPaciente::query()->withoutGlobalScopes()->where('patient_id', $this->p->id)->count())->toBe(0); // SUPERADMIN: portal sem sessão.

    $this->postJson("/fisio/p/{$token}/consentimentos", ['purpose' => 'documentos', 'aceito' => true])->assertCreated();
    expect(DB::table('fisio_consents')->where('patient_id', $this->p->id)->where('purpose', 'documentos')->value('version'))->toBe('doc-v1');

    $id = (int) fpdEnviar($this, $token)->assertCreated()->assertJson(['situacao' => 'aguardando'])->json('id');
    $d = DocumentoPaciente::query()->withoutGlobalScopes()->findOrFail($id); // SUPERADMIN: portal sem sessão.
    expect($d->business_id)->toBe($this->b98)
        ->and($d->origin)->toBe('portal')
        ->and($d->status)->toBe('pendente')
        ->and($d->created_by)->toBeNull()
        ->and($d->note)->toBe('ressonância da coluna');
    $a = Arquivo::withoutGlobalScope('business_id')->where('arquivable_type', DocumentoPaciente::class)->where('arquivable_id', $id)->sole(); // SUPERADMIN: conferência.
    expect((int) $a->business_id)->toBe($this->b98)->and($a->storage_path)->toStartWith("biz-{$this->b98}/");

    $props = F::portalProps($this, $token);
    expect($props['envio_documentos']['consentido'])->toBeTrue()
        ->and($props['envio_documentos']['enviados'][0])->toBe(['tipo' => 'exame_imagem', 'enviado_em' => $d->created_at->toIso8601String(), 'situacao' => 'aguardando'])
        ->and(json_encode($props['envio_documentos']))->not->toContain('/fisio/documentos/');
});

it('UC-FER-18 · portões: link inválido 404, sem PIN 403, consentimento revogado 403, teto do dia 429', function () {
    fpdEnviar($this, 'token-que-nao-existe')->assertNotFound();

    $semPin = F::emitirPortal(F::paciente($this->b98, $this->u98->id));
    fpdEnviar($this, $semPin['token'])->assertForbidden()->assertJson(['erro' => 'pin_pendente']);

    $token = fpdPronto($this, $this->p);
    $this->deleteJson("/fisio/p/{$token}/consentimentos/documentos")->assertOk();
    fpdEnviar($this, $token)->assertForbidden()->assertJson(['erro' => 'consentimento_documentos']);

    $this->postJson("/fisio/p/{$token}/consentimentos", ['purpose' => 'documentos', 'aceito' => true])->assertCreated();
    config(['fisio.documentos.portal_max_dia' => 1]);
    fpdEnviar($this, $token)->assertCreated();
    fpdEnviar($this, $token)->assertStatus(429)->assertJson(['erro' => 'limite_do_dia']);
    fpdEnviar($this, $token, ['arquivo' => UploadedFile::fake()->createWithContent('a.txt', 'x')])->assertStatus(429);
});

it('UC-FER-19 · a clínica aceita (pode acertar o tipo) ou recusa com motivo; nada entra sozinho e nada se apaga', function () {
    $token = fpdPronto($this, $this->p);
    $aceitar = (int) fpdEnviar($this, $token)->assertCreated()->json('id');
    $recusar = (int) fpdEnviar($this, $token, ['tipo' => 'outro'])->assertCreated()->json('id');

    F::como($this, $this->u98);
    $docs = F::props($this, $this->u98, "/fisio/pacientes/{$this->p->id}", 'Fisio/Pacientes/Show', 'documentos');
    // Os dois chegam no mesmo dia: a ordem entre eles não é contrato, os pares id → status são.
    expect(collect($docs)->pluck('status', 'id')->all())->toEqual([$aceitar => 'pendente', $recusar => 'pendente']);

    $this->postJson("/fisio/documentos/{$aceitar}/aceitar", ['tipo' => 'exame_laboratorial', 'data_documento' => '2026-11-02'])->assertOk();
    $d = DocumentoPaciente::query()->findOrFail($aceitar);
    expect($d->status)->toBe('prontuario')->and($d->kind)->toBe('exame_laboratorial')
        ->and($d->document_date?->toDateString())->toBe('2026-11-02')
        ->and($d->reviewed_by)->toBe($this->u98->id)->and($d->reviewed_at)->not->toBeNull();
    $this->postJson("/fisio/documentos/{$aceitar}/aceitar")->assertUnprocessable();

    $this->postJson("/fisio/documentos/{$recusar}/recusar", ['motivo' => ''])->assertUnprocessable()->assertJsonValidationErrors('motivo');
    $this->postJson("/fisio/documentos/{$recusar}/recusar", ['motivo' => 'foto de outra pessoa'])->assertOk();
    $r = DocumentoPaciente::query()->findOrFail($recusar);
    expect($r->status)->toBe('recusado')->and($r->removed_reason)->toBe('foto de outra pessoa')->and($r->reviewed_by)->toBe($this->u98->id);
    expect(Arquivo::withoutGlobalScope('business_id')->where('arquivable_id', $recusar)->where('arquivable_type', DocumentoPaciente::class)->whereNull('deleted_at')->count())->toBe(1); // SUPERADMIN: conferência.

    $docs = F::props($this, $this->u98, "/fisio/pacientes/{$this->p->id}", 'Fisio/Pacientes/Show', 'documentos');
    expect(array_column($docs, 'id'))->toBe([$aceitar]);

    $enviados = F::portalProps($this, $token)['envio_documentos']['enviados'];
    expect(array_column($enviados, 'situacao'))->toBe(['nao_aceito', 'no_prontuario']);
});

it('UC-FER-20 · sem fisio.editar 403; outra empresa 404 em aceitar, recusar e abrir; o portal de outro paciente não vê os envios', function () {
    $token = fpdPronto($this, $this->p);
    $id = (int) fpdEnviar($this, $token)->assertCreated()->json('id');

    F::como($this, $this->leitor98);
    $this->postJson("/fisio/documentos/{$id}/aceitar")->assertForbidden();
    $this->postJson("/fisio/documentos/{$id}/recusar", ['motivo' => 'qualquer'])->assertForbidden();

    F::como($this, $this->u99);
    $this->postJson("/fisio/documentos/{$id}/aceitar")->assertNotFound();
    $this->postJson("/fisio/documentos/{$id}/recusar", ['motivo' => 'qualquer'])->assertNotFound();
    $this->get("/fisio/documentos/{$id}/arquivo")->assertNotFound();
    expect(DocumentoPaciente::query()->withoutGlobalScopes()->findOrFail($id)->status)->toBe('pendente'); // SUPERADMIN: conferência cruzada.

    $outro = F::paciente($this->b99, $this->u99->id);
    $token99 = fpdPronto($this, $outro);
    expect(F::portalProps($this, $token99)['envio_documentos']['enviados'])->toBe([]);
});

it('UC-FER-21 · atestado médico é tipo da lista: entra pela ficha e pelo portal, não é transcrito, e o termo novo pede aceite de novo', function () {
    expect(DocumentoPaciente::TIPOS)->toHaveKey('atestado')
        ->and(DocumentoPaciente::TIPOS['atestado'])->toBe('Atestado médico');

    // Portal: com o termo aceito na versão vigente, o atestado chega pendente como os outros tipos.
    $token = fpdPronto($this, $this->p);
    $id = (int) fpdEnviar($this, $token, ['tipo' => 'atestado', 'observacao' => 'atestado de 3 dias'])->assertCreated()->json('id');
    $d = DocumentoPaciente::query()->withoutGlobalScopes()->findOrFail($id); // SUPERADMIN: portal sem sessão.
    expect($d->kind)->toBe('atestado')->and($d->status)->toBe('pendente')->and($d->business_id)->toBe($this->b98);
    expect(F::portalProps($this, $token)['envio_documentos']['tipos'])->toHaveKey('atestado');

    // Ficha: a clínica anexa direto; a lista de tipos vem junto e o atestado não oferece transcrição.
    F::como($this, $this->u98);
    $ficha = (int) $this->postJson("/fisio/pacientes/{$this->p->id}/documentos", [
        'arquivo' => fpdFoto(), 'tipo' => 'atestado', 'data_documento' => '2026-11-10',
    ])->assertCreated()->json('id');
    expect(F::props($this, $this->u98, "/fisio/pacientes/{$this->p->id}", 'Fisio/Pacientes/Show', 'documento_tipos'))->toHaveKey('atestado');
    $docs = collect(F::props($this, $this->u98, "/fisio/pacientes/{$this->p->id}", 'Fisio/Pacientes/Show', 'documentos'))->keyBy('id');
    expect($docs[$ficha]['tipo'])->toBe('atestado')->and($docs[$ficha]['transcrevivel'])->toBeFalse();

    // O texto do termo passou a nomear o atestado: versão nova, e o aceite da anterior não vale mais.
    config(['fisio.consent_versions.documentos' => 'doc-v2']);
    fpdEnviar($this, $token, ['tipo' => 'atestado'])->assertForbidden()->assertJson(['erro' => 'consentimento_documentos']);
    expect(F::portalProps($this, $token)['envio_documentos']['consentido'])->toBeFalse();
});
