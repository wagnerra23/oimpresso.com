<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Services\DanfeService;
use NFePHP\DA\NFe\Danfe;

uses(Tests\TestCase::class);

/**
 * US-NFE-044 · DanfeService — render PDF a partir do XML autorizado.
 *
 * Pattern: cria nfe_emissoes in-memory + Storage::fake. Mocka Danfe via
 * `danfeFactory` closure pra evitar processamento real do XML autorizado
 * (mais robusto pra CI sem fontes/imagens da lib).
 */

beforeEach(function () {
    Schema::dropIfExists('nfe_emissoes');
    Schema::create('nfe_emissoes', function ($t) {
        $t->id();
        $t->unsignedInteger('business_id')->index();
        $t->unsignedInteger('transaction_id')->nullable();
        $t->string('modelo', 2)->default('55');
        $t->string('serie', 3)->default('1');
        $t->unsignedInteger('numero')->nullable();
        $t->string('chave_44', 44)->nullable();
        $t->string('status', 20)->default('pendente');
        $t->string('cstat', 5)->nullable();
        $t->text('motivo')->nullable();
        $t->string('xml_path', 255)->nullable();
        $t->string('danfe_path', 255)->nullable();
        $t->decimal('valor_total', 15, 2)->default(0);
        $t->timestamp('emitido_em')->nullable();
        $t->json('metadata')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });
    Storage::fake('local');
});

afterEach(function () {
    Schema::dropIfExists('nfe_emissoes');
});

// ── helpers ───────────────────────────────────────────────────────────────

function emissaoFake(array $overrides = []): NfeEmissao
{
    return NfeEmissao::create(array_merge([
        'business_id'  => 4,
        'numero'       => 1,
        'chave_44'     => '35210112345678000199550010000000011000000019',
        'status'       => 'autorizada',
        'cstat'        => '100',
        'xml_path'     => 'nfe-brasil/4/notas/1-1.xml',
        'valor_total'  => 100.00,
        'emitido_em'   => now(),
    ], $overrides));
}

function fakeDanfeFactory(string $pdfBytes = 'PDF-BYTES-FAKE-12345'): Closure
{
    return function (string $xml) use ($pdfBytes) {
        $mock = \Mockery::mock(Danfe::class);
        $mock->shouldReceive('render')->andReturn($pdfBytes);
        return $mock;
    };
}

// ── tests ─────────────────────────────────────────────────────────────────

it('renderizar() retorna PDF bytes a partir do XML em storage', function () {
    $emissao = emissaoFake();
    Storage::put($emissao->xml_path, '<nfeProc>fake-xml</nfeProc>');

    $svc = new DanfeService(fakeDanfeFactory('PDF-CONTENT-X'));

    $bytes = $svc->renderizar($emissao);

    expect($bytes)->toBe('PDF-CONTENT-X');
});

it('renderizar() lança RuntimeException quando emissão sem xml_path', function () {
    $emissao = emissaoFake(['xml_path' => null]);
    $svc = new DanfeService(fakeDanfeFactory());

    expect(fn () => $svc->renderizar($emissao))
        ->toThrow(\RuntimeException::class, 'sem xml_path');
});

it('renderizar() lança RuntimeException quando XML ausente em storage', function () {
    $emissao = emissaoFake(['xml_path' => 'nfe-brasil/4/notas/missing.xml']);
    // NÃO faz Storage::put — arquivo não existe
    $svc = new DanfeService(fakeDanfeFactory());

    expect(fn () => $svc->renderizar($emissao))
        ->toThrow(\RuntimeException::class, 'XML não encontrado em storage');
});

it('salvar() persiste DANFE em storage + atualiza danfe_path no model', function () {
    $emissao = emissaoFake();
    Storage::put($emissao->xml_path, '<nfeProc>fake-xml</nfeProc>');

    $svc = new DanfeService(fakeDanfeFactory('PDF-PERSISTED'));

    $path = $svc->salvar($emissao);

    expect($path)->toBe('nfe-brasil/4/danfe/35210112345678000199550010000000011000000019.pdf');
    expect(Storage::disk('local')->exists($path))->toBeTrue();
    expect(Storage::disk('local')->get($path))->toBe('PDF-PERSISTED');
    expect($emissao->fresh()->danfe_path)->toBe($path);
});

it('salvar() pula emissão não-autorizada (retorna null)', function () {
    $emissao = emissaoFake(['status' => 'rejeitada', 'chave_44' => null]);
    $svc = new DanfeService(fakeDanfeFactory());

    $path = $svc->salvar($emissao);

    expect($path)->toBeNull();
    expect($emissao->fresh()->danfe_path)->toBeNull();
});

it('salvar() pula emissão sem chave_44 (caso edge)', function () {
    $emissao = emissaoFake(['chave_44' => null]);
    Storage::put($emissao->xml_path, '<nfeProc>fake-xml</nfeProc>');
    $svc = new DanfeService(fakeDanfeFactory());

    expect($svc->salvar($emissao))->toBeNull();
});

it('salvar() defensivo: render lança Throwable → log warning + retorna null sem alterar status', function () {
    $emissao = emissaoFake();
    Storage::put($emissao->xml_path, '<nfeProc>fake-xml</nfeProc>');

    $factory = function () { throw new \RuntimeException('font.ttf não encontrada'); };
    $svc = new DanfeService($factory);

    $path = $svc->salvar($emissao);

    expect($path)->toBeNull();
    expect($emissao->fresh()->status)->toBe('autorizada'); // preservado
    expect($emissao->fresh()->danfe_path)->toBeNull();
});

it('lerOuGerar() retorna bytes do storage quando danfe_path existe', function () {
    $emissao = emissaoFake(['danfe_path' => 'nfe-brasil/4/danfe/cached.pdf']);
    Storage::put($emissao->danfe_path, 'CACHED-PDF-BYTES');

    $svc = new DanfeService(fakeDanfeFactory('NEW-RENDER')); // factory NÃO deve ser chamada

    $bytes = $svc->lerOuGerar($emissao);

    expect($bytes)->toBe('CACHED-PDF-BYTES');
});

it('lerOuGerar() chama salvar() lazy quando danfe_path ausente', function () {
    $emissao = emissaoFake(['danfe_path' => null]);
    Storage::put($emissao->xml_path, '<nfeProc>fake-xml</nfeProc>');

    $svc = new DanfeService(fakeDanfeFactory('LAZY-RENDERED'));

    $bytes = $svc->lerOuGerar($emissao);

    expect($bytes)->toBe('LAZY-RENDERED');
    expect($emissao->fresh()->danfe_path)->not()->toBeNull();
});

it('multi-tenant: DANFE de business 4 fica em path separado de business 5', function () {
    $emissao4 = emissaoFake(['business_id' => 4, 'chave_44' => '35210000000000000000550010000000011000000019']);
    $emissao5 = emissaoFake(['business_id' => 5, 'chave_44' => '35210000000000000000550010000000021000000026']);
    Storage::put($emissao4->xml_path, '<xml>biz-4</xml>');
    Storage::put($emissao5->xml_path, '<xml>biz-5</xml>');

    $svc = new DanfeService(fakeDanfeFactory('PDF'));

    $svc->salvar($emissao4);
    $svc->salvar($emissao5);

    expect($emissao4->fresh()->danfe_path)->toContain('nfe-brasil/4/danfe/');
    expect($emissao5->fresh()->danfe_path)->toContain('nfe-brasil/5/danfe/');
    expect($emissao4->fresh()->danfe_path)->not()->toBe($emissao5->fresh()->danfe_path);
});
