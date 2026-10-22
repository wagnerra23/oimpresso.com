<?php
// @covers-us US-COMVIS-020

declare(strict_types=1);

use App\Media;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\ComunicacaoVisual\Entities\Orcamento;
use Modules\ComunicacaoVisual\Entities\Os;
use Modules\ComunicacaoVisual\Http\Controllers\OrcamentoArquivoController;
use Modules\ComunicacaoVisual\Http\Controllers\OrcamentoController;
use Modules\ComunicacaoVisual\Services\OrcamentoCalculator;
use Modules\ComunicacaoVisual\Services\OrcamentoGravador;
use Modules\ComunicacaoVisual\Services\OrcamentoViraOs;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\WithSeededTenant;

/**
 * Upload de arquivo no orçamento (UC-FLX-01 · US-COMVIS-020, aceite "Upload de arquivo no orçamento").
 *
 * Âncora: SPEC US-COMVIS-020 — "os arquivos anexados ao orçamento passam a apontar também para a
 * OS". Até aqui nada gravava `media` no orçamento: a cópia existia e não havia o que copiar.
 * Contrato: Index.casos.md UC-CVARQ-01..06. Tenant 98 contra 99 (ADR 0358). Disco falso.
 * ⚠️ SKIP em SQLite (lane sem as tabelas comvis_*): leia assertions, não "0 failed" (LC-13).
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('comvis_os_itens')) {
        $this->markTestSkipped('Requer MySQL com as tabelas comvis_* migradas.');
    }
    try {
        $this->biz = $this->seededTenant();
        $this->outra = $this->seededSupportClientTenant();
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente: '.$e->getMessage());
    }
    Storage::fake(config('filesystems.default'));

    $payload = ['data_emissao' => '2026-10-21',
        'itens' => [['descricao' => 'Fachada ACM', 'largura_m' => 4, 'altura_m' => 1, 'quantidade' => 1, 'preco_unitario_m2' => 180]]];
    $this->orc = app(OrcamentoGravador::class)->gravar($this->biz->id, app(OrcamentoCalculator::class)->calcular($payload), 'enviado');
});

function cvarqChamar($test, string $acao, array $parametros, array $permissoes = ['comvis.orcamento.create'], $biz = null)
{
    $biz = $biz ?? $test->biz;
    $user = $test->usuarioComPermissoes($permissoes, $biz);
    $test->actingAs($user);
    session(['user.business_id' => $biz->id, 'business.id' => $biz->id, 'user.id' => $user->id]);

    return app()->call([app(OrcamentoArquivoController::class), $acao], $parametros);
}

function cvarqEnvio(array $arquivos): Request
{
    return Request::create('/comunicacao-visual/api/orcamentos/1/arquivos', 'POST', [], [], ['arquivos' => $arquivos]);
}

function cvarqDoOrcamento(int $orcamentoId)
{
    return DB::table('media')->where('model_type', Orcamento::class)->where('model_id', $orcamentoId)->orderBy('id')->get();
}

it('UC-CVARQ-01 · anexar grava o arquivo no orçamento da empresa, e aprovar o leva para a OS', function () {
    $resp = cvarqChamar($this, 'store', ['request' => cvarqEnvio([
        UploadedFile::fake()->create('arte-fachada.pdf', 120, 'application/pdf'),
        UploadedFile::fake()->image('foto-local.jpg'),
    ]), 'id' => $this->orc->id]);

    $gravados = cvarqDoOrcamento($this->orc->id);
    expect($resp->getStatusCode())->toBe(201)
        ->and($gravados)->toHaveCount(2)
        ->and($gravados->pluck('business_id')->map(fn ($v) => (int) $v)->unique()->all())->toBe([$this->biz->id])
        ->and(collect($resp->getData(true))->pluck('nome')->all())->toBe(['arte-fachada.pdf', 'foto-local.jpg'])
        ->and(collect(OrcamentoController::paraTela($this->biz->id, $this->orc->id)['arquivos'])->pluck('nome')->all())
        ->toBe(['arte-fachada.pdf', 'foto-local.jpg']);

    [$os] = app(OrcamentoViraOs::class)->aprovar($this->biz->id, $this->orc->id);
    expect(DB::table('media')->where('model_type', Os::class)->where('model_id', $os->id)->orderBy('id')->pluck('file_name')->all())
        ->toBe($gravados->pluck('file_name')->all());
});

it('UC-CVARQ-02 · formato que a gráfica não usa é recusado e nada é gravado', function () {
    expect(fn () => cvarqChamar($this, 'store', ['request' => cvarqEnvio([
        UploadedFile::fake()->create('programa.exe', 10),
    ]), 'id' => $this->orc->id]))->toThrow(ValidationException::class);

    expect(cvarqDoOrcamento($this->orc->id))->toHaveCount(0);
});

it('UC-CVARQ-03 · depois de virar OS o orçamento não recebe nem perde arquivo', function () {
    Media::create(['business_id' => $this->biz->id, 'file_name' => '1_1_arte.pdf', 'model_type' => Orcamento::class, 'model_id' => $this->orc->id]);
    app(OrcamentoViraOs::class)->aprovar($this->biz->id, $this->orc->id);
    $mediaId = cvarqDoOrcamento($this->orc->id)->first()->id;

    $envio = cvarqChamar($this, 'store', ['request' => cvarqEnvio([UploadedFile::fake()->create('nova.pdf', 10, 'application/pdf')]), 'id' => $this->orc->id]);
    $remocao = cvarqChamar($this, 'destroy', ['id' => $this->orc->id, 'media' => $mediaId]);

    expect($envio->getStatusCode())->toBe(422)
        ->and($remocao->getStatusCode())->toBe(422)
        ->and(cvarqDoOrcamento($this->orc->id)->pluck('file_name')->all())->toBe(['1_1_arte.pdf']);
});

it('UC-CVARQ-04 · a empresa 99 não anexa nem remove no orçamento da 98', function () {
    Media::create(['business_id' => $this->biz->id, 'file_name' => '1_1_arte.pdf', 'model_type' => Orcamento::class, 'model_id' => $this->orc->id]);
    $mediaId = cvarqDoOrcamento($this->orc->id)->first()->id;

    expect(fn () => cvarqChamar($this, 'store', ['request' => cvarqEnvio([UploadedFile::fake()->create('alheio.pdf', 10, 'application/pdf')]), 'id' => $this->orc->id], ['comvis.orcamento.create'], $this->outra))
        ->toThrow(ModelNotFoundException::class);
    expect(fn () => cvarqChamar($this, 'destroy', ['id' => $this->orc->id, 'media' => $mediaId], ['comvis.orcamento.create'], $this->outra))
        ->toThrow(ModelNotFoundException::class);

    expect(cvarqDoOrcamento($this->orc->id)->pluck('file_name')->all())->toBe(['1_1_arte.pdf']);
});

it('UC-CVARQ-05 · sem permissão de criar orçamento não anexa nem remove', function () {
    Media::create(['business_id' => $this->biz->id, 'file_name' => '1_1_arte.pdf', 'model_type' => Orcamento::class, 'model_id' => $this->orc->id]);
    $mediaId = cvarqDoOrcamento($this->orc->id)->first()->id;

    foreach ([['store', ['request' => cvarqEnvio([UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]), 'id' => $this->orc->id]],
        ['destroy', ['id' => $this->orc->id, 'media' => $mediaId]]] as [$acao, $parametros]) {
        try {
            cvarqChamar($this, $acao, $parametros, ['comvis.orcamento.view']);
            $this->fail("Sem comvis.orcamento.create, {$acao} deveria ser recusado.");
        } catch (HttpException $e) {
            expect($e->getStatusCode())->toBe(403);
        }
    }
    expect(cvarqDoOrcamento($this->orc->id))->toHaveCount(1);
});

it('UC-CVARQ-06 · remover tira o arquivo do orçamento e ele não vai para a OS', function () {
    cvarqChamar($this, 'store', ['request' => cvarqEnvio([
        UploadedFile::fake()->create('errada.pdf', 10, 'application/pdf'),
        UploadedFile::fake()->create('certa.pdf', 10, 'application/pdf'),
    ]), 'id' => $this->orc->id]);
    $errada = cvarqDoOrcamento($this->orc->id)->first();

    $resp = cvarqChamar($this, 'destroy', ['id' => $this->orc->id, 'media' => $errada->id]);
    [$os] = app(OrcamentoViraOs::class)->aprovar($this->biz->id, $this->orc->id);

    expect($resp->getStatusCode())->toBe(200)
        ->and(collect($resp->getData(true))->pluck('nome')->all())->toBe(['certa.pdf'])
        ->and(DB::table('media')->where('model_type', Os::class)->where('model_id', $os->id)->count())->toBe(1);
});
