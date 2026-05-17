<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Modules\Arquivos\Entities\Arquivo;
use Modules\Arquivos\Services\ArquivosService;
use Modules\NfeBrasil\Http\Controllers\NfeEmissaoController;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Services\DanfeService;
use Modules\NfeBrasil\Services\NfeService;

uses(Tests\TestCase::class);

/**
 * US-ARQ-022b — NfeEmissaoController::serializeEmissao retorna xml_url + danfe_url.
 *
 * Cobertura:
 * - xml_url null quando sem Arquivo relacionado
 * - danfe_url null quando sem Arquivo relacionado
 * - xml_url string signed URL quando arquivo presente (mock ArquivosService)
 * - danfe_url string signed URL quando arquivo presente (mock ArquivosService)
 * - Multi-tenant: arquivo de business 99 NÃO vaza pra session biz 1
 *   (global scope Arquivo filtra por session business_id)
 *
 * @see memory/decisions/0123-modules-arquivos-backbone.md
 */

// ── schema setup ─────────────────────────────────────────────────────────────

beforeEach(function () {
    Schema::dropIfExists('arquivos');
    Schema::dropIfExists('nfe_emissoes');
    Schema::dropIfExists('activity_log');

    // Spatie LogsActivity em NfeEmissao → INSERT em activity_log.
    Schema::create('activity_log', function ($t) {
        $t->bigIncrements('id');
        $t->string('log_name')->nullable();
        $t->text('description')->nullable();
        $t->unsignedBigInteger('subject_id')->nullable();
        $t->string('subject_type')->nullable();
        $t->unsignedBigInteger('causer_id')->nullable();
        $t->string('causer_type')->nullable();
        $t->text('properties')->nullable();
        $t->uuid('batch_uuid')->nullable();
        $t->string('event')->nullable();
        $t->unsignedInteger('business_id')->nullable();
        $t->timestamps();
    });

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

    Schema::create('arquivos', function ($t) {
        $t->bigIncrements('id');
        $t->unsignedInteger('business_id');
        $t->string('arquivable_type', 255)->nullable();
        $t->unsignedBigInteger('arquivable_id')->nullable();
        $t->string('disk', 32)->default('arquivos');
        $t->string('storage_path', 512)->default('nfe-brasil/1/notas/test.xml');
        $t->string('original_name', 255)->default('test.xml');
        $t->string('mime_type', 127)->default('application/xml');
        $t->unsignedBigInteger('size_bytes')->default(1024);
        $t->char('md5', 32)->default(md5('test'));
        $t->enum('bucket', ['sensitive','memory','user','spec','ambiguous','discard','active'])->default('active');
        $t->string('sub_destination', 255)->nullable();
        $t->json('sensitive_flags')->nullable();
        $t->string('classified_by', 64)->nullable();
        $t->timestamp('classified_at')->nullable();
        $t->unsignedBigInteger('uploaded_by_user_id')->nullable();
        $t->enum('visibility', ['private','business','public'])->default('private');
        $t->boolean('encrypted')->default(false);
        $t->unsignedInteger('retention_days')->nullable();
        $t->softDeletes();
        $t->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('arquivos');
    Schema::dropIfExists('nfe_emissoes');
    Schema::dropIfExists('activity_log');
});

// ── helpers ──────────────────────────────────────────────────────────────────

/**
 * Cria NfeEmissao sem arquivos relacionados.
 */
function emissaoSemArquivos(int $businessId = 1): NfeEmissao
{
    return NfeEmissao::withoutGlobalScopes()->create([ // SUPERADMIN: test setup cross-tenant
        'business_id' => $businessId,
        'modelo'      => '65',
        'serie'       => '1',
        'numero'      => 1,
        'chave_44'    => '35210112345678000199650010000000011000000019',
        'status'      => 'autorizada',
        'cstat'       => '100',
        'valor_total' => 150.00,
        'emitido_em'  => now(),
    ]);
}

/**
 * Anexa um Arquivo ao NfeEmissao sem passar pelo global scope (setup de teste).
 */
function anexarArquivo(NfeEmissao $emissao, string $subDestination, int $businessId): Arquivo
{
    return Arquivo::withoutGlobalScopes()->create([ // SUPERADMIN: test setup cross-tenant
        'business_id'     => $businessId,
        'arquivable_type' => NfeEmissao::class,
        'arquivable_id'   => $emissao->id,
        'disk'            => 'arquivos',
        'storage_path'    => "nfe-brasil/{$businessId}/notas/test-{$subDestination}.xml",
        'original_name'   => "test-{$subDestination}.xml",
        'mime_type'       => 'application/xml',
        'size_bytes'      => 1024,
        'md5'             => md5($subDestination),
        'bucket'          => 'active',
        'sub_destination' => $subDestination,
    ]);
}

/**
 * Instancia o controller com ArquivosService mockado.
 * O mock retorna $fakeUrl pra qualquer call signedUrl().
 */
function controllerComMock(string $fakeUrl = 'https://oimpresso.com/arquivos/download/1?sig=fake'): NfeEmissaoController
{
    $mockArquivos = \Mockery::mock(ArquivosService::class);
    $mockArquivos->shouldReceive('signedUrl')
        ->withAnyArgs()
        ->andReturn($fakeUrl);

    return new NfeEmissaoController(
        app(NfeService::class),
        app(DanfeService::class),
        $mockArquivos,
    );
}

/**
 * Invoca serializeEmissao via Reflection (método privado).
 *
 * Nome NÃO pode ser `serialize()` — colide com built-in PHP e gera fatal
 * "Cannot redeclare function serialize()" quando autoloader varre o arquivo.
 */
function invokeSerializeEmissao(NfeEmissaoController $controller, NfeEmissao $emissao): array
{
    $method = (new ReflectionClass($controller))->getMethod('serializeEmissao');
    $method->setAccessible(true);
    return $method->invoke($controller, $emissao);
}

// ── testes ───────────────────────────────────────────────────────────────────

it('serializeEmissao retorna xml_url null quando sem arquivo xml no backbone', function () {
    $emissao = emissaoSemArquivos(1);
    // session vazia — global scope do Arquivo não filtrará nada relevante aqui;
    // o accessor retorna null pois nenhum arquivo está relacionado.

    $result = invokeSerializeEmissao(controllerComMock(), $emissao);

    expect($result)->toHaveKey('xml_url');
    expect($result['xml_url'])->toBeNull();
});

it('serializeEmissao retorna danfe_url null quando sem arquivo danfe no backbone', function () {
    $emissao = emissaoSemArquivos(1);

    $result = invokeSerializeEmissao(controllerComMock(), $emissao);

    expect($result)->toHaveKey('danfe_url');
    expect($result['danfe_url'])->toBeNull();
});

it('serializeEmissao retorna xml_url string quando arquivo xml presente', function () {
    // Simula session business_id=1 para o global scope do Arquivo funcionar
    session(['user.business_id' => 1]);

    $emissao = emissaoSemArquivos(1);
    anexarArquivo($emissao, 'nfe-xml', 1);

    $fakeUrl = 'https://oimpresso.com/arquivos/download/1?sig=fake-xml-token';
    $result  = invokeSerializeEmissao(controllerComMock($fakeUrl), $emissao);

    expect($result['xml_url'])->toBeString()
        ->toBe($fakeUrl);
});

it('serializeEmissao retorna danfe_url string quando arquivo danfe presente', function () {
    session(['user.business_id' => 1]);

    $emissao = emissaoSemArquivos(1);
    anexarArquivo($emissao, 'nfe-danfe', 1);

    $fakeUrl = 'https://oimpresso.com/arquivos/download/2?sig=fake-danfe-token';
    $result  = invokeSerializeEmissao(controllerComMock($fakeUrl), $emissao);

    expect($result['danfe_url'])->toBeString()
        ->toBe($fakeUrl);
});

it('serializeEmissao xml_url null quando arquivo pertence a business 99 e session é biz 1 (global scope bloqueia)', function () {
    // session business_id=1 → global scope de Arquivo filtra WHERE business_id=1
    // O arquivo criado tem business_id=99 → accessor retorna null (isolamento multi-tenant)
    session(['user.business_id' => 1]);

    $emissao = emissaoSemArquivos(1);
    // Arquivo criado com business_id=99 — não deve vazar pra session biz 1
    anexarArquivo($emissao, 'nfe-xml', 99);

    $result = invokeSerializeEmissao(controllerComMock(), $emissao);

    expect($result['xml_url'])->toBeNull();
});

it('serializeEmissao danfe_url null quando arquivo pertence a business 99 e session é biz 1 (global scope bloqueia)', function () {
    session(['user.business_id' => 1]);

    $emissao = emissaoSemArquivos(1);
    anexarArquivo($emissao, 'nfe-danfe', 99);

    $result = invokeSerializeEmissao(controllerComMock(), $emissao);

    expect($result['danfe_url'])->toBeNull();
});

it('serializeEmissao mantém todas as keys fiscais existentes após adição de xml_url/danfe_url', function () {
    $emissao = emissaoSemArquivos(1);

    $result = invokeSerializeEmissao(controllerComMock(), $emissao);

    $keysEsperadas = [
        'id', 'modelo', 'modelo_label', 'serie', 'numero', 'chave_44',
        'status', 'cstat', 'motivo', 'valor_total', 'emitido_em',
        'is_terminal', 'is_cancelavel',
        'xml_url', 'danfe_url',
    ];

    foreach ($keysEsperadas as $key) {
        // Pest toHaveKey($key) — 2º arg é VALUE esperado, não mensagem.
        // Usar expect+toBeTrue com array_key_exists pra mensagem custom.
        expect(array_key_exists($key, $result))
            ->toBeTrue("Key '{$key}' ausente no retorno de serializeEmissao");
    }
});
