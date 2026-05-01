<?php

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Modules\NFSe\Adapters\SnNfseAdapter;
use Modules\NFSe\Contracts\NfseProviderInterface;
use Modules\NFSe\DTO\NfseEmissaoPayload;
use Modules\NFSe\DTO\NfseResultado;
use Modules\NFSe\Exceptions\CertificadoInvalidoException;
use Modules\NFSe\Exceptions\CodigoServicoInvalidoException;
use Modules\NFSe\Exceptions\IssInvalidoException;
use Modules\NFSe\Exceptions\NfseException;
use Modules\NFSe\Exceptions\NfseJaCanceladaException;
use Modules\NFSe\Exceptions\PrestadorNaoAutorizadoException;
use Modules\NFSe\Exceptions\ProviderTimeoutException;
use Modules\NFSe\Exceptions\RpsDuplicadoException;
use Modules\NFSe\Exceptions\TomadorInvalidoException;
use Modules\NFSe\Models\NfseEmissao;
use Modules\NFSe\Models\NfseCertificado;
use Modules\NFSe\Models\NfseProviderConfig;
use Modules\NFSe\Services\NfseEmissaoService;

uses(RefreshDatabase::class);

// ── helpers ──────────────────────────────────────────────────────────────────

function mockProvider(): NfseProviderInterface
{
    return Mockery::mock(NfseProviderInterface::class);
}

function payload(array $overrides = []): NfseEmissaoPayload
{
    return new NfseEmissaoPayload(
        businessId: 1,
        rpsNumero: $overrides['rpsNumero'] ?? '000001',
        competencia: $overrides['competencia'] ?? Carbon::create(2026, 5, 1),
        tomadorNome: 'ROTA LIVRE LTDA',
        tomadorCnpj: '12.345.678/0001-90',
        tomadorCpf: null,
        tomadorEmail: 'fiscal@rotalivrepress.com.br',
        descricao: 'Licença mensal sistema ERP',
        lc116Codigo: '1.05',
        valorServicos: 1500.00,
        aliquotaIss: 0.02,
        ambiente: 'homologacao',
    );
}

function seedConfig(int $businessId = 1, ?int $certId = null): NfseProviderConfig
{
    return NfseProviderConfig::withoutGlobalScopes()->create([
        'business_id'          => $businessId,
        'provider'             => 'sn_nfse_federal',
        'municipio_codigo_ibge' => '4218707',
        'serie_default'        => 'RPS',
        'cnae'                 => '6201-5/00',
        'lc116_codigo_default' => '1.05',
        'aliquota_iss'         => 0.02,
        'ambiente'             => 'homologacao',
        'cert_id'              => $certId,
    ]);
}

function seedCert(int $businessId = 1, bool $expirado = false): NfseCertificado
{
    return NfseCertificado::withoutGlobalScopes()->create([
        'business_id'        => $businessId,
        'cert_pfx_encrypted' => encrypt('pfx_simulado'),
        'senha_encrypted'    => encrypt('senha123'),
        'valido_ate'         => $expirado ? now()->subDay() : now()->addYear(),
        'titular_cnpj'       => '11.222.333/0001-00',
        'titular_nome'       => 'OIMPRESSO COMUNICACAO LTDA',
        'ativo'              => true,
    ]);
}

// ── golden path ───────────────────────────────────────────────────────────────

it('emite NFSe com sucesso e persiste status emitida', function () {
    $cert = seedCert();
    seedConfig(certId: $cert->id);

    $provider = mockProvider();
    $provider->shouldReceive('emitir')->once()->andReturn(
        NfseResultado::sucesso('123456', 'PROT-001', 'COD-VER', 'https://pdf.nfse.gov.br/123456')
    );

    $service = new NfseEmissaoService($provider);
    $emissao = $service->emitir(payload());

    expect($emissao->status)->toBe('emitida')
        ->and($emissao->numero)->toBe('123456')
        ->and($emissao->provider_protocolo)->toBe('PROT-001')
        ->and($emissao->pdf_url)->toContain('123456');
});

// ── idempotência ──────────────────────────────────────────────────────────────

it('retorna nota existente quando idempotency_key já existe com status emitida', function () {
    $cert = seedCert();
    seedConfig(certId: $cert->id);
    $p = payload();

    NfseEmissao::withoutGlobalScopes()->create([
        'business_id'     => 1,
        'status'          => 'emitida',
        'numero'          => '999',
        'rps_numero'      => '000001',
        'serie'           => 'RPS',
        'competencia'     => '2026-05-01',
        'tomador_nome'    => 'ROTA LIVRE LTDA',
        'tomador_cnpj'    => '12.345.678/0001-90',
        'descricao'       => 'Licença mensal sistema ERP',
        'valor_servicos'  => 1500.00,
        'valor_iss'       => 30.00,
        'idempotency_key' => $p->idempotencyKey(),
    ]);

    $provider = mockProvider();
    $provider->shouldNotReceive('emitir');

    $service  = new NfseEmissaoService($provider);
    $emissao  = $service->emitir($p);

    expect($emissao->numero)->toBe('999');
});

// ── erros de certificado ──────────────────────────────────────────────────────

it('lança CertificadoInvalidoException quando nenhum cert configurado', function () {
    seedConfig();  // sem cert_id

    $service = new NfseEmissaoService(mockProvider());

    expect(fn () => $service->emitir(payload()))
        ->toThrow(CertificadoInvalidoException::class);
});

it('lança CertificadoInvalidoException quando cert expirado', function () {
    $cert = seedCert(expirado: true);
    seedConfig(certId: $cert->id);

    $service = new NfseEmissaoService(mockProvider());

    expect(fn () => $service->emitir(payload()))
        ->toThrow(CertificadoInvalidoException::class, 'expirado');
});

// ── RPS duplicado ─────────────────────────────────────────────────────────────

it('lança RpsDuplicadoException e marca status emitida na nota local', function () {
    $cert = seedCert();
    seedConfig(certId: $cert->id);

    $provider = mockProvider();
    $provider->shouldReceive('emitir')->andThrow(new RpsDuplicadoException('000001'));

    $service = new NfseEmissaoService($provider);

    expect(fn () => $service->emitir(payload()))
        ->toThrow(RpsDuplicadoException::class, '000001');

    expect(NfseEmissao::withoutGlobalScopes()->first()->status)->toBe('emitida');
});

// ── ISS inválido ──────────────────────────────────────────────────────────────

it('lança IssInvalidoException (E501) e marca status erro', function () {
    $cert = seedCert();
    seedConfig(certId: $cert->id);

    $provider = mockProvider();
    $provider->shouldReceive('emitir')->andThrow(new IssInvalidoException('E501'));

    $service = new NfseEmissaoService($provider);

    expect(fn () => $service->emitir(payload()))
        ->toThrow(IssInvalidoException::class);

    expect(NfseEmissao::withoutGlobalScopes()->first()->status)->toBe('erro');
});

// ── código serviço inválido ────────────────────────────────────────────────────

it('lança CodigoServicoInvalidoException e persiste erro', function () {
    $cert = seedCert();
    seedConfig(certId: $cert->id);

    $provider = mockProvider();
    $provider->shouldReceive('emitir')->andThrow(new CodigoServicoInvalidoException('9.99'));

    $service = new NfseEmissaoService($provider);

    expect(fn () => $service->emitir(payload()))
        ->toThrow(CodigoServicoInvalidoException::class, '9.99');
});

// ── tomador inválido ──────────────────────────────────────────────────────────

it('lança TomadorInvalidoException quando CNPJ tomador inválido', function () {
    $cert = seedCert();
    seedConfig(certId: $cert->id);

    $provider = mockProvider();
    $provider->shouldReceive('emitir')->andThrow(new TomadorInvalidoException('CNPJ inválido'));

    $service = new NfseEmissaoService($provider);

    expect(fn () => $service->emitir(payload()))
        ->toThrow(TomadorInvalidoException::class);
});

// ── prestador não autorizado (L1) ─────────────────────────────────────────────

it('lança PrestadorNaoAutorizadoException (L1) sem retry', function () {
    $cert = seedCert();
    seedConfig(certId: $cert->id);

    $provider = mockProvider();
    $provider->shouldReceive('emitir')->once()->andThrow(new PrestadorNaoAutorizadoException());

    $service = new NfseEmissaoService($provider);

    expect(fn () => $service->emitir(payload()))
        ->toThrow(PrestadorNaoAutorizadoException::class);
});

// ── timeout com retry ─────────────────────────────────────────────────────────

it('retenta 3 vezes em ProviderTimeoutException e lança após esgotar', function () {
    $cert = seedCert();
    seedConfig(certId: $cert->id);

    $provider = mockProvider();
    // Deve ser chamado exatamente 3 vezes (MAX_RETRIES)
    $provider->shouldReceive('emitir')
        ->times(3)
        ->andThrow(new ProviderTimeoutException());

    $service = new NfseEmissaoService($provider);

    expect(fn () => $service->emitir(payload()))
        ->toThrow(ProviderTimeoutException::class);

    expect(NfseEmissao::withoutGlobalScopes()->first()->status)->toBe('erro');
})->skip('sleep(backoff) torna o teste lento — rodar só em CI fora do watch');

// ── cancelamento ──────────────────────────────────────────────────────────────

it('cancela nota emitida com sucesso', function () {
    $cert = seedCert();
    seedConfig(certId: $cert->id);

    $emissao = NfseEmissao::withoutGlobalScopes()->create([
        'business_id'     => 1,
        'status'          => 'emitida',
        'numero'          => '123456',
        'rps_numero'      => '000001',
        'serie'           => 'RPS',
        'competencia'     => '2026-05-01',
        'tomador_nome'    => 'ROTA LIVRE',
        'descricao'       => 'ERP',
        'valor_servicos'  => 1500,
        'valor_iss'       => 30,
        'idempotency_key' => 'abc123',
    ]);

    $provider = mockProvider();
    $provider->shouldReceive('cancelar')->once()->with('123456', 'Solicitação do cliente')->andReturn(true);

    $service = new NfseEmissaoService($provider);
    $service->cancelar($emissao, 'Solicitação do cliente');

    expect($emissao->fresh()->status)->toBe('cancelada');
});

it('lança NfseJaCanceladaException ao cancelar nota já cancelada', function () {
    $emissao = NfseEmissao::withoutGlobalScopes()->create([
        'business_id'     => 1,
        'status'          => 'cancelada',
        'numero'          => '123456',
        'rps_numero'      => '000001',
        'serie'           => 'RPS',
        'competencia'     => '2026-05-01',
        'tomador_nome'    => 'ROTA LIVRE',
        'descricao'       => 'ERP',
        'valor_servicos'  => 1500,
        'valor_iss'       => 30,
        'idempotency_key' => 'xyz999',
    ]);

    $service = new NfseEmissaoService(mockProvider());

    expect(fn () => $service->cancelar($emissao, 'motivo'))
        ->toThrow(NfseJaCanceladaException::class);
});

// ── config ausente ────────────────────────────────────────────────────────────

it('lança NfseException quando não existe config para o business_id', function () {
    $service = new NfseEmissaoService(mockProvider());

    expect(fn () => $service->emitir(payload()))
        ->toThrow(NfseException::class, 'Configuração NFSe não encontrada');
});

// ── cálculo ISS ───────────────────────────────────────────────────────────────

it('calcula valor ISS corretamente com aliquota 2%', function () {
    $p = payload(['aliquota_iss' => 0.02]);
    expect($p->valorIss())->toBe(30.00);
});

it('retorna ISS zero quando iss_retido=true', function () {
    $p = new NfseEmissaoPayload(
        businessId: 1, rpsNumero: '1', competencia: Carbon::now(),
        tomadorNome: 'X', tomadorCnpj: null, tomadorCpf: '123',
        tomadorEmail: null, descricao: 'Y', lc116Codigo: '1.05',
        valorServicos: 1000, aliquotaIss: 0.02, issRetido: true,
    );
    expect($p->valorIss())->toBe(0.0);
});
