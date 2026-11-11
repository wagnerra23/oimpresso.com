<?php

declare(strict_types=1);
// @covers-us US-FISIO-063
// Cobre UC-FER-12..UC-FER-16 (memory/requisitos/Fisio/_telas/exames-e-receitas.casos.md).

use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Prompts\AgentPrompt;
use Modules\Fisio\Ai\Agents\TranscricaoDocumentoAgent;
use Modules\Fisio\Entities\DocumentoPaciente;
use Modules\Fisio\Entities\MedicamentoPaciente;
use Modules\Fisio\Entities\TranscricaoDocumento;
use Modules\Fisio\Services\TranscricaoDocumentos;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Transcrição da foto pela IA, conferida pelo profissional (US-FISIO-063, parte 3). O modelo é
 * falso (Agent::fake): mede-se o portão (desligada por padrão, provedor sem treino), que a
 * sugestão nasce pendente e só com os campos do schema, e que nada vai ao prontuário sem
 * alguém confirmar. Tenant 98 contra 99. ⚠️ SKIP em SQLite (LC-13).
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip(['fisio_document_transcriptions', 'fisio_patient_medications', 'fisio_patient_documents', 'fisio_settings', 'arquivos'])) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    config(['fisio.documentos.transcricao.provider' => 'anthropic', 'fisio.documentos.transcricao.model' => 'modelo-teste',
        'fisio.documentos.transcricao.provedores_sem_treino' => ['anthropic', 'openai']]);
    Storage::fake(config('arquivos.disk_default', 'local'));
    Storage::fake(config('arquivos.disk_vault', 'vault'));

    $this->b98 = (int) $this->seededTenant()->id;
    $this->u98 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    $this->leitor98 = $this->usuarioComPermissoes([F::PERM_ACESSO], $this->seededTenant());
    $this->u99 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededSupportClientTenant());
    F::pacote([$this->b98, (int) $this->seededSupportClientTenant()->id]);
    $this->p = F::paciente($this->b98, $this->u98->id);
    F::como($this, $this->u98);
});

function ftrDoc($t, string $tipo = 'receita', ?string $data = null): int
{
    return (int) $t->postJson("/fisio/pacientes/{$t->p->id}/documentos", array_filter([
        'arquivo' => UploadedFile::fake()->image('doc-'.uniqid().'.jpg', 64, 64), 'tipo' => $tipo, 'data_documento' => $data,
    ]))->assertCreated()->json('id');
}

function ftrLigar($t): void
{
    app(TranscricaoDocumentos::class)->ligar($t->b98, true, $t->u98);
}

$receita = [[
    'data' => '2026-11-03', 'medico' => 'Dra. Fixture', 'crm' => 'CRM-SC 00000',
    'medicamentos' => [['nome' => 'Pregabalina', 'dose' => '75 mg', 'frequencia' => '1 cápsula à noite']],
    'tipo_exame' => null, 'conclusao' => 'não devia vir na receita',
    'interpretacao' => 'resultado alterado, procurar médico', // fora do schema: não passa
]];

it('UC-FER-12 · desligada por padrão: o modelo nem é chamado; só quem edita liga, e ligar vai ao activity_log', function () {
    TranscricaoDocumentoAgent::fake([])->preventStrayPrompts();
    $doc = ftrDoc($this);
    $this->postJson("/fisio/documentos/{$doc}/transcricao")->assertUnprocessable()->assertJsonValidationErrors('transcricao');
    TranscricaoDocumentoAgent::assertNeverPrompted();

    F::como($this, $this->leitor98);
    $this->postJson('/fisio/documentos/transcricao-ia', ['ligado' => true])->assertForbidden();
    F::como($this, $this->u98);
    $this->postJson('/fisio/documentos/transcricao-ia', ['ligado' => true])->assertOk()->assertJson(['ligado' => true]);
    expect(app(TranscricaoDocumentos::class)->ligada($this->b98))->toBeTrue()
        ->and(DB::table('activity_log')->where('description', 'fisio.transcricao_ia.configurada')->where('causer_id', $this->u98->id)->exists())->toBeTrue();
});

it('UC-FER-13 · a sugestão nasce pendente, só com os campos do schema, e nada vai ao prontuário antes de conferir', function () use ($receita) {
    TranscricaoDocumentoAgent::fake($receita);
    ftrLigar($this);
    $doc = ftrDoc($this);

    $id = (int) $this->postJson("/fisio/documentos/{$doc}/transcricao")->assertCreated()->json('id');
    TranscricaoDocumentoAgent::assertPrompted(fn (AgentPrompt $p) => $p->attachments->count() === 1);

    $t = TranscricaoDocumento::query()->findOrFail($id);
    expect($t->status)->toBe('pendente')
        ->and(array_keys($t->suggested))->toBe(['data', 'medico', 'crm', 'medicamentos'])
        ->and($t->suggested['medicamentos'][0])->toBe(['nome' => 'Pregabalina', 'dose' => '75 mg', 'frequencia' => '1 cápsula à noite'])
        ->and(json_encode($t->suggested))->not->toContain('alterado')
        ->and($t->model)->toBe('modelo-teste')
        ->and($t->requested_by)->toBe($this->u98->id);
    expect(MedicamentoPaciente::query()->where('patient_id', $this->p->id)->count())->toBe(0)
        ->and(DocumentoPaciente::query()->findOrFail($doc)->document_date)->toBeNull();

    $docs = F::props($this, $this->u98, "/fisio/pacientes/{$this->p->id}", 'Fisio/Pacientes/Show', 'documentos');
    expect($docs[0]['transcricao']['status'])->toBe('pendente')->and($docs[0]['transcrevivel'])->toBeTrue();
});

it('UC-FER-14 · confirmar receita grava quem conferiu e os campos corrigidos; os medicamentos entram com a receita como origem', function () use ($receita) {
    TranscricaoDocumentoAgent::fake($receita);
    ftrLigar($this);
    $doc = ftrDoc($this);
    $id = (int) $this->postJson("/fisio/documentos/{$doc}/transcricao")->assertCreated()->json('id');

    $this->postJson("/fisio/transcricoes/{$id}/confirmar", [
        'data' => '2026-11-03', 'medico' => 'Dra. Fixture', 'crm' => 'CRM-SC 00000',
        'medicamentos' => [
            ['nome' => 'Pregabalina', 'dose' => '150 mg', 'frequencia' => '1 cápsula à noite'], // dose corrigida
            ['nome' => 'Amitriptilina', 'dose' => '25 mg', 'frequencia' => null],                 // incluída à mão
        ],
    ])->assertOk();

    $t = TranscricaoDocumento::query()->findOrFail($id);
    expect($t->status)->toBe('conferida')
        ->and($t->decided_by)->toBe($this->u98->id)
        ->and($t->decided_at)->not->toBeNull()
        ->and($t->corrected)->toBe(['medicamentos'])
        ->and($t->confirmed['medicamentos'][0]['dose'])->toBe('150 mg');

    $meds = MedicamentoPaciente::query()->where('patient_id', $this->p->id)->orderBy('id')->get();
    expect($meds)->toHaveCount(2)
        ->and($meds[0]->name)->toBe('Pregabalina')->and($meds[0]->dose)->toBe('150 mg')
        ->and($meds[0]->source)->toBe('transcricao')
        ->and($meds[0]->document_id)->toBe($doc)
        ->and($meds[0]->confirmed_by)->toBe($this->u98->id)
        ->and($meds[0]->started_on?->toDateString())->toBe('2026-11-03');
    expect(DocumentoPaciente::query()->findOrFail($doc)->document_date?->toDateString())->toBe('2026-11-03');

    $this->postJson("/fisio/transcricoes/{$id}/confirmar", ['data' => null, 'medicamentos' => []])->assertUnprocessable()->assertJsonValidationErrors('transcricao');
    $this->postJson("/fisio/transcricoes/{$id}/descartar")->assertUnprocessable();
    expect(MedicamentoPaciente::query()->where('patient_id', $this->p->id)->count())->toBe(2);
});

it('UC-FER-15 · exame: a conclusão é guardada como foi escrita; confirmar sem mudar nada registra zero correções e nenhum medicamento', function () {
    $conclusao = 'Discreta protrusão discal em L4-L5, sem compressão radicular.';
    $exame = ['data' => '2026-10-20', 'medico' => null, 'crm' => null, 'medicamentos' => [],
        'tipo_exame' => 'Ressonância magnética da coluna lombar', 'conclusao' => $conclusao];
    TranscricaoDocumentoAgent::fake([$exame, $exame]);
    ftrLigar($this);
    $doc = ftrDoc($this, 'exame_imagem', '2026-10-21');
    $id = (int) $this->postJson("/fisio/documentos/{$doc}/transcricao")->assertCreated()->json('id');

    $t = TranscricaoDocumento::query()->findOrFail($id);
    expect(array_keys($t->suggested))->toBe(['data', 'tipo_exame', 'conclusao'])->and($t->suggested['conclusao'])->toBe($conclusao);

    $this->postJson("/fisio/transcricoes/{$id}/confirmar", ['data' => '2026-10-20', 'tipo_exame' => 'Ressonância magnética da coluna lombar', 'conclusao' => $conclusao])->assertOk();
    $t->refresh();
    expect($t->corrected)->toBe([])->and($t->confirmed['conclusao'])->toBe($conclusao)
        ->and(MedicamentoPaciente::query()->where('patient_id', $this->p->id)->count())->toBe(0)
        // A data que já estava no documento não é trocada pela da transcrição.
        ->and(DocumentoPaciente::query()->findOrFail($doc)->document_date?->toDateString())->toBe('2026-10-21');

    $outro = (int) $this->postJson("/fisio/documentos/{$doc}/transcricao")->assertCreated()->json('id');
    $this->postJson("/fisio/transcricoes/{$outro}/descartar")->assertOk();
    expect(TranscricaoDocumento::query()->findOrFail($outro)->status)->toBe('descartada');
});

it('UC-FER-16 · provedor fora da lista, tipo sem campos, sem fisio.editar e outra empresa são recusados; o agente não tem campo de interpretação', function () use ($receita) {
    TranscricaoDocumentoAgent::fake($receita);
    ftrLigar($this);
    $doc = ftrDoc($this);

    config(['fisio.documentos.transcricao.provider' => 'gemini']);
    $this->postJson("/fisio/documentos/{$doc}/transcricao")->assertUnprocessable()->assertJsonValidationErrors('transcricao');
    TranscricaoDocumentoAgent::assertNeverPrompted();
    config(['fisio.documentos.transcricao.provider' => 'anthropic']);

    $outro = ftrDoc($this, 'outro');
    $this->postJson("/fisio/documentos/{$outro}/transcricao")->assertUnprocessable();

    $id = (int) $this->postJson("/fisio/documentos/{$doc}/transcricao")->assertCreated()->json('id');
    F::como($this, $this->leitor98);
    $this->postJson("/fisio/documentos/{$doc}/transcricao")->assertForbidden();
    $this->postJson("/fisio/transcricoes/{$id}/confirmar", ['medicamentos' => []])->assertForbidden();

    F::como($this, $this->u99);
    $this->postJson("/fisio/documentos/{$doc}/transcricao")->assertNotFound();
    $this->postJson("/fisio/transcricoes/{$id}/confirmar", ['medicamentos' => []])->assertNotFound();
    $this->postJson("/fisio/transcricoes/{$id}/descartar")->assertNotFound();
    expect(TranscricaoDocumento::query()->withoutGlobalScopes()->findOrFail($id)->status)->toBe('pendente'); // SUPERADMIN: conferência cruzada.

    $schema = (new TranscricaoDocumentoAgent('receita'))->schema(new JsonSchemaTypeFactory);
    expect(array_keys($schema))->toBe(TranscricaoDocumentoAgent::CAMPOS)
        ->and((string) (new TranscricaoDocumentoAgent('exame'))->instructions())->toContain('Nunca interprete');
});
