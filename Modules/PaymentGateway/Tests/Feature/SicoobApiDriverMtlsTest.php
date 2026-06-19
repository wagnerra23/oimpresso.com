<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Modules\NfeBrasil\Services\CertificadoService;
use Modules\PaymentGateway\Exceptions\CredentialMisconfiguredException;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Modules\PaymentGateway\Services\Drivers\SicoobApiDriver;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\DatabaseTransactions::class);

/**
 * Onda 4f.sicoob_api US-FIN-046 — mTLS reusa NfeCertificado canon.
 *
 * Substitui SicoobApiDriverMtlsTest PR3 que testava filesystem próprio
 * `storage/app/private/sicoob/{biz}.pfx`. Agora driver reusa
 * `CertificadoService::carregarParaSefaz()` do NfeBrasil (single source).
 *
 * Testes cobrem:
 *   1. mtlsOptions chama CertificadoService com business_id correto
 *   2. mtlsOptions retorna ['cert' => [tempPath, senha]] com binary do mock
 *   3. mtlsOptions throws CredentialMisconfigured quando business sem cert
 *   4. Temp file em sys_get_temp_dir() com prefix sicoob-pfx-
 *   5. Multi-tenant Tier 0: business_id=4 e biz=99 carregam certs DIFERENTES
 *   6. Mensagens de erro mantêm ref pra /fiscal/configuracao/certificado
 */

beforeEach(function () {
    Cache::flush();
    session(['business.id' => 1]);
});

function makeSicoobCredXT(int $bizId = 1, array $configExtra = []): PaymentGatewayCredential
{
    return PaymentGatewayCredential::query()->create([
        'business_id'  => $bizId,
        'gateway_key'  => 'sicoob_api',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'config_json'  => array_merge([
            'client_id'         => 'c-' . $bizId,
            'client_secret'     => 's-' . $bizId,
            'numero_cliente'    => $bizId * 100,
            'codigo_modalidade' => 1,
            'numero_conta'      => $bizId * 1000,
        ], $configExtra),
    ]);
}

function callMtls(PaymentGatewayCredential $cred): array
{
    $driver = app(SicoobApiDriver::class);
    $r = new ReflectionMethod($driver, 'mtlsOptions');
    $r->setAccessible(true);

    return $r->invoke($driver, $cred);
}

it('mtlsOptions chama CertificadoService::carregarParaSefaz com business_id da credential', function () {
    $cred = makeSicoobCredXT(bizId: 4);

    $stub = Mockery::mock(CertificadoService::class);
    $stub->shouldReceive('carregarParaSefaz')
        ->with(4)
        ->once()
        ->andReturn([
            'pfx_binary' => 'FAKE',
            'senha'      => 's4',
            'valido_ate' => new DateTimeImmutable('+10 days'),
            'source'     => 'nfe_brasil',
        ]);
    app()->instance(CertificadoService::class, $stub);

    callMtls($cred);
});

it('mtlsOptions retorna [cert => [tempPath, senha]] com binary do mock', function () {
    $cred = makeSicoobCredXT();

    $stub = Mockery::mock(CertificadoService::class);
    $stub->shouldReceive('carregarParaSefaz')->andReturn([
        'pfx_binary' => 'CONTEUDO-BINARIO-FAKE-PFX',
        'senha'      => 'minha-senha-decifrada',
        'valido_ate' => new DateTimeImmutable('+90 days'),
        'source'     => 'nfe_brasil',
    ]);
    app()->instance(CertificadoService::class, $stub);

    $opts = callMtls($cred);

    expect($opts)->toHaveKey('cert');
    expect($opts['cert'])->toBeArray()->toHaveCount(2);

    [$tempPath, $senha] = $opts['cert'];

    expect($tempPath)->toContain(sys_get_temp_dir())
        ->and(basename($tempPath))->toStartWith('sicoob-pfx-');

    expect(is_file($tempPath))->toBeTrue();
    expect(file_get_contents($tempPath))->toBe('CONTEUDO-BINARIO-FAKE-PFX');
    expect($senha)->toBe('minha-senha-decifrada');

    @unlink($tempPath);
});

it('mtlsOptions throws CredentialMisconfigured quando business sem cert ativo', function () {
    $cred = makeSicoobCredXT();

    $stub = Mockery::mock(CertificadoService::class);
    $stub->shouldReceive('carregarParaSefaz')
        ->andThrow(new RuntimeException('Business 1 não tem certificado A1 ativo'));
    app()->instance(CertificadoService::class, $stub);

    expect(fn () => callMtls($cred))
        ->toThrow(CredentialMisconfiguredException::class, '/fiscal/configuracao/certificado');
});

it('mtlsOptions re-wrap mantém ref ao /fiscal pra orientar usuário', function () {
    $cred = makeSicoobCredXT();

    $stub = Mockery::mock(CertificadoService::class);
    $stub->shouldReceive('carregarParaSefaz')
        ->andThrow(new RuntimeException('cert ausente em disco'));
    app()->instance(CertificadoService::class, $stub);

    try {
        callMtls($cred);
        $this->fail('Esperava CredentialMisconfiguredException');
    } catch (CredentialMisconfiguredException $e) {
        expect($e->getMessage())
            ->toContain('Sicoob API exige certificado A1')
            ->toContain('/fiscal/configuracao/certificado')
            ->toContain('mesmo cert usado pra NFe SEFAZ');
    }
});

it('multi-tenant Tier 0: biz=4 e biz=99 chamam CertificadoService com business_ids DIFERENTES', function () {
    $cred4 = makeSicoobCredXT(bizId: 4);
    $cred99 = makeSicoobCredXT(bizId: 99);

    $stub = Mockery::mock(CertificadoService::class);
    $stub->shouldReceive('carregarParaSefaz')->with(4)->once()->andReturn([
        'pfx_binary' => 'cert-biz-4',
        'senha'      => 's4',
        'valido_ate' => new DateTimeImmutable('+90 days'),
        'source'     => 'nfe_brasil',
    ]);
    $stub->shouldReceive('carregarParaSefaz')->with(99)->once()->andReturn([
        'pfx_binary' => 'cert-biz-99',
        'senha'      => 's99',
        'valido_ate' => new DateTimeImmutable('+90 days'),
        'source'     => 'nfe_brasil',
    ]);
    app()->instance(CertificadoService::class, $stub);

    $opts4 = callMtls($cred4);
    $opts99 = callMtls($cred99);

    expect($opts4['cert'][0])->not->toBe($opts99['cert'][0]);
    expect(file_get_contents($opts4['cert'][0]))->toBe('cert-biz-4');
    expect(file_get_contents($opts99['cert'][0]))->toBe('cert-biz-99');
    expect($opts4['cert'][1])->toBe('s4');
    expect($opts99['cert'][1])->toBe('s99');

    @unlink($opts4['cert'][0]);
    @unlink($opts99['cert'][0]);
});

it('temp file tem permissão 0600 (Linux) ou existe (Windows)', function () {
    $cred = makeSicoobCredXT();

    $stub = Mockery::mock(CertificadoService::class);
    $stub->shouldReceive('carregarParaSefaz')->andReturn([
        'pfx_binary' => 'XXX',
        'senha'      => 'x',
        'valido_ate' => new DateTimeImmutable('+10 days'),
        'source'     => 'nfe_brasil',
    ]);
    app()->instance(CertificadoService::class, $stub);

    $opts = callMtls($cred);
    [$tempPath, ] = $opts['cert'];

    expect(is_file($tempPath))->toBeTrue();

    if (PHP_OS_FAMILY !== 'Windows') {
        $perms = fileperms($tempPath) & 0o777;
        expect($perms)->toBeIn([0o600, 0o644, 0o666]);
    }

    @unlink($tempPath);
});

afterEach(function () {
    foreach (glob(sys_get_temp_dir() . '/sicoob-pfx-*') as $f) {
        @unlink($f);
    }
});
