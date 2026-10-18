<?php

declare(strict_types=1);
// Cobre UC-FBI-05..UC-FBI-07 (Fisio/Biblioteca/Index.casos.md).
// @covers-us US-FISIO-004

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\Arquivos\Entities\Arquivo;
use Modules\Fisio\Entities\Exercise;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Foto ou vídeo curto no exercício PRÓPRIO da clínica, guardado pelo backbone `Modules/Arquivos`
 * (ADR 0123): arquivo sob `biz-{id}/`, vínculo polimórfico com o exercício, servido só por rota
 * que confere a empresa. Global não aceita mídia de clínica (403); o próprio de outra empresa é
 * 404 em toda rota — ler, anexar e remover.
 *
 * LGPD (ADR 0437): mídia do EXERCÍCIO, nunca do paciente — o envio exige a declaração.
 *
 * Tenant 98 contra 99, ambos com pacote. ⚠️ SKIP em SQLite (LC-13): leia assertions.
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip(['arquivos', 'arquivos_audit_log', 'arquivos_dedupe'])) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    config(['fisio.midia.foto_max_kb' => 10240, 'fisio.midia.video_max_kb' => 51200]);
    Storage::fake(config('arquivos.disk_default', 'local'));

    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->u98 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    $this->u99 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededSupportClientTenant());
    F::pacote([$this->b98, $this->b99]);
    $this->global = F::exercicio(null, 'Global ponte');
    $this->de98 = F::exercicio($this->b98, 'Próprio da 98');
    $this->de99 = F::exercicio($this->b99, 'Próprio da 99');
});

function fbmFoto(string $nome = 'demo.jpg'): UploadedFile
{
    return UploadedFile::fake()->image($nome, 64, 64);
}

function fbmVideo(string $nome = 'demo.mp4'): UploadedFile
{
    // Cabeçalho `ftyp` real: o servidor confere o CONTEÚDO (finfo), não a extensão. Sufixo único
    // por chamada porque a dedupe do backbone é por md5 dentro da empresa.
    $mp4 = "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom\x00\x00\x00\x08free".uniqid('', true);

    return UploadedFile::fake()->createWithContent($nome, $mp4);
}

/** @return \Illuminate\Support\Collection<int, Arquivo> */
function fbmArquivos(Exercise $e, bool $comExcluidos = false)
{
    // SUPERADMIN: verificação cruza as duas empresas; o escopo é provado pelas rotas. Tira SÓ o
    // escopo de empresa — `withoutGlobalScopes()` tiraria também o de exclusão reversível.
    $q = Arquivo::withoutGlobalScope('business_id')->where('arquivable_type', Exercise::class)->where('arquivable_id', $e->id);

    return ($comExcluidos ? $q->withTrashed() : $q)->get();
}

function fbmAnexar($t, Exercise $e, UploadedFile $arquivo, bool $declaro = true)
{
    return $t->postJson("/fisio/biblioteca/exercicios/{$e->id}/midia", array_filter([
        'midia' => $arquivo,
        'declaro_sem_paciente' => $declaro ? '1' : null,
    ]));
}

it('UC-FBI-05 · foto no próprio fica sob biz-98, ligada ao exercício, e só a 98 lê', function () {
    F::como($this, $this->u98);
    fbmAnexar($this, $this->de98, fbmFoto())->assertCreated()->assertJsonPath('midia.tipo', 'foto');

    $arquivos = fbmArquivos($this->de98);
    expect($arquivos)->toHaveCount(1);
    $a = $arquivos->first();
    expect((int) $a->business_id)->toBe($this->b98)
        ->and((string) $a->storage_path)->toStartWith('biz-'.$this->b98.'/')
        ->and((string) $a->mime_type)->toBe('image/jpeg');
    Storage::disk((string) $a->disk)->assertExists((string) $a->storage_path);

    F::como($this, $this->u98);
    $r = $this->get("/fisio/biblioteca/exercicios/{$this->de98->id}/midia");
    $r->assertOk();
    expect((string) $r->headers->get('Content-Type'))->toStartWith('image/jpeg');

    F::como($this, $this->u99);
    $this->get("/fisio/biblioteca/exercicios/{$this->de98->id}/midia")->assertNotFound();
});

it('UC-FBI-05 · vídeo mp4 aparece na grade da dona; a outra clínica não recebe a mídia', function () {
    F::como($this, $this->u98);
    fbmAnexar($this, $this->de98, fbmVideo())->assertCreated()->assertJsonPath('midia.tipo', 'video');

    $grade98 = collect(F::props($this, $this->u98, '/fisio/biblioteca', 'Fisio/Biblioteca/Index', 'exercicios'))->keyBy('id');
    expect($grade98[$this->de98->id]['midia']['tipo'] ?? null)->toBe('video')
        ->and((string) ($grade98[$this->de98->id]['midia']['url'] ?? ''))->toStartWith("/fisio/biblioteca/exercicios/{$this->de98->id}/midia")
        ->and(array_key_exists('midia', $grade98[$this->global->id]))->toBeTrue()
        ->and($grade98[$this->global->id]['midia'])->toBeNull();

    $grade99 = collect(F::props($this, $this->u99, '/fisio/biblioteca', 'Fisio/Biblioteca/Index', 'exercicios'))->keyBy('id');
    expect($grade99->has($this->de98->id))->toBeFalse()
        ->and($grade99[$this->de99->id]['midia'])->toBeNull();
});

it('UC-FBI-05 · vídeo é servido como video/mp4 com faixa de bytes (player do Safari)', function () {
    F::como($this, $this->u98);
    fbmAnexar($this, $this->de98, fbmVideo())->assertCreated();

    F::como($this, $this->u98);
    $r = $this->get("/fisio/biblioteca/exercicios/{$this->de98->id}/midia", ['Range' => 'bytes=0-3']);
    $r->assertStatus(206);
    expect((string) $r->headers->get('Content-Type'))->toBe('video/mp4');
});

it('UC-FBI-05 · global responde 403 e próprio de outra clínica 404; nada é gravado', function () {
    F::como($this, $this->u98);
    fbmAnexar($this, $this->global, fbmFoto())->assertForbidden();
    fbmAnexar($this, $this->de99, fbmFoto())->assertNotFound();

    expect(fbmArquivos($this->global, true))->toHaveCount(0)
        ->and(fbmArquivos($this->de99, true))->toHaveCount(0);
});

it('UC-FBI-05 · anexar de novo troca a mídia: só uma fica vigente', function () {
    F::como($this, $this->u98);
    fbmAnexar($this, $this->de98, fbmFoto('primeira.jpg'))->assertCreated();
    F::como($this, $this->u98);
    fbmAnexar($this, $this->de98, fbmVideo('segunda.mp4'))->assertCreated();

    $vivos = fbmArquivos($this->de98);
    expect($vivos)->toHaveCount(1)
        ->and(\Modules\Fisio\Services\MidiaExercicio::tipo((string) $vivos->first()->mime_type))->toBe('video')
        ->and(fbmArquivos($this->de98, true))->toHaveCount(2);
});

it('UC-FBI-06 · tipo, tamanho e declaração validados no servidor, com mensagem em português', function () {
    F::como($this, $this->u98);

    $gif = fbmAnexar($this, $this->de98, UploadedFile::fake()->create('anim.gif', 10, 'image/gif'));
    $gif->assertStatus(422)->assertJsonValidationErrors(['midia']);
    expect((string) $gif->json('errors.midia.0'))->toContain('MP4');

    $pdf = fbmAnexar($this, $this->de98, UploadedFile::fake()->create('ficha.pdf', 10, 'application/pdf'));
    $pdf->assertStatus(422)->assertJsonValidationErrors(['midia']);

    $fotoGrande = fbmAnexar($this, $this->de98, UploadedFile::fake()->create('grande.jpg', 10241, 'image/jpeg'));
    $fotoGrande->assertStatus(422)->assertJsonValidationErrors(['midia']);
    expect((string) $fotoGrande->json('errors.midia.0'))->toContain('A foto passa de 10 MB');

    $videoGrande = fbmAnexar($this, $this->de98, UploadedFile::fake()->create('longo.mp4', 51201, 'video/mp4'));
    $videoGrande->assertStatus(422)->assertJsonValidationErrors(['midia']);
    expect((string) $videoGrande->json('errors.midia.0'))->toContain('O vídeo passa de 50 MB');

    $semDeclaracao = fbmAnexar($this, $this->de98, fbmFoto(), false);
    $semDeclaracao->assertStatus(422)->assertJsonValidationErrors(['declaro_sem_paciente']);
    expect((string) $semDeclaracao->json('errors.declaro_sem_paciente.0'))->toContain('não mostra paciente');

    expect(fbmArquivos($this->de98, true))->toHaveCount(0);
});

it('UC-FBI-06 · sem fisio.editar não anexa nem remove (403)', function () {
    $leitor = $this->usuarioComPermissoes([F::PERM_ACESSO], $this->seededTenant());
    F::como($this, $leitor);
    fbmAnexar($this, $this->de98, fbmFoto())->assertForbidden();
    $this->deleteJson("/fisio/biblioteca/exercicios/{$this->de98->id}/midia")->assertForbidden();

    expect(fbmArquivos($this->de98, true))->toHaveCount(0);
});

it('UC-FBI-07 · a dona remove: o vínculo sai e a rota da mídia passa a 404', function () {
    F::como($this, $this->u98);
    fbmAnexar($this, $this->de98, fbmFoto())->assertCreated();

    F::como($this, $this->u98);
    $this->deleteJson("/fisio/biblioteca/exercicios/{$this->de98->id}/midia")->assertOk()->assertJsonPath('removidas', 1);

    expect(fbmArquivos($this->de98))->toHaveCount(0)
        ->and(fbmArquivos($this->de98, true))->toHaveCount(1);
    F::como($this, $this->u98);
    $this->get("/fisio/biblioteca/exercicios/{$this->de98->id}/midia")->assertNotFound();
});

it('UC-FBI-07 · a outra clínica não remove a mídia da dona (404) e nada muda', function () {
    F::como($this, $this->u98);
    fbmAnexar($this, $this->de98, fbmFoto())->assertCreated();

    F::como($this, $this->u99);
    $this->deleteJson("/fisio/biblioteca/exercicios/{$this->de98->id}/midia")->assertNotFound();

    expect(fbmArquivos($this->de98))->toHaveCount(1);
    F::como($this, $this->u98);
    $this->get("/fisio/biblioteca/exercicios/{$this->de98->id}/midia")->assertOk();
});

it('UC-FBI-05 · a mídia não tem vínculo com paciente (LGPD, ADR 0437)', function () {
    expect(Schema::hasColumn('arquivos', 'patient_id'))->toBeFalse();

    F::como($this, $this->u98);
    fbmAnexar($this, $this->de98, fbmFoto())->assertCreated();
    expect(fbmArquivos($this->de98)->pluck('arquivable_type')->unique()->values()->all())->toBe([Exercise::class]);
});
