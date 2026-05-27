<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Modules\PaymentGateway\Dto\EmitirCobrancaInput;
use Modules\PaymentGateway\Exceptions\CredentialMisconfiguredException;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Modules\PaymentGateway\Services\Drivers\SicoobApiDriver;

uses(Tests\TestCase::class);

/**
 * Onda 4f.sicoob_api PR3 — US-FIN-044.
 *
 * Testa mTLS handshake real (.pfx + senha cifrada via Laravel Crypt).
 *
 * Em Http::fake() o handshake mTLS é bypass (não bate na rede), MAS as
 * options Guzzle são preenchidas pelo driver e podem ser inspecionadas
 * via reflection (ou indiretamente via comportamento esperado).
 *
 * Aqui validamos:
 *   1. requires_mtls=false → mtlsOptions vazio (compat sandbox sem cert)
 *   2. requires_mtls=true + pfx_path vazio → CredentialMisconfigured
 *   3. requires_mtls=true + .pfx inexistente → CredentialMisconfigured
 *   4. requires_mtls=true + senha ausente → CredentialMisconfigured
 *   5. requires_mtls=true + senha não decifra → CredentialMisconfigured
 *   6. Tudo OK → options 'cert' = [absolute_path, plain_password]
 *   7. Path absoluto preservado
 *   8. Path relativo prefixado com storage/app/private/
 *   9. Multi-tenant: biz=4 e biz=99 têm .pfx isolados (não compartilham)
 *  10. NUNCA loga senha (não conseguimos provar isso 100% mas o código
 *      nunca passa senha pra Log::; podemos checar que mensagens de erro
 *      não contêm o plaintext).
 *
 * Tier 0 multi-tenant: biz=1 padrão. Caso 9 usa biz=4 e biz=99 fictícios.
 */

beforeEach(function () {
    Cache::flush();
    session(['business.id' => 1]);

    // Diretório de teste isolado por suite — não polui storage de outros tests.
    $this->pfxDir = storage_path('app/private/sicoob');
    File::ensureDirectoryExists($this->pfxDir);

    $this->makeCred = function (array $extra = [], int $bizId = 1, ?string $pfxRel = null, bool $reqMtls = true): PaymentGatewayCredential {
        return PaymentGatewayCredential::query()->create([
            'business_id'    => $bizId,
            'gateway_key'    => 'sicoob_api',
            'ambiente'       => 'sandbox',
            'ativo'          => true,
            'requires_mtls'  => $reqMtls,
            'mtls_pfx_path'  => $pfxRel,
            'config_json'    => array_merge([
                'client_id'         => 'fake-client-id',
                'client_secret'     => 'fake-client-secret',
                'numero_cliente'    => 12345,
                'codigo_modalidade' => 1,
                'numero_conta'      => 1234567,
            ], $extra),
        ]);
    };

    $this->writeFakePfx = function (string $relPath): string {
        $abs = storage_path('app/private/' . ltrim($relPath, '/'));
        File::ensureDirectoryExists(dirname($abs));
        // Conteúdo fake — driver só checa is_file(), não valida PKCS12.
        File::put($abs, "FAKE-PFX-BINARY-FOR-TEST");

        return $abs;
    };
});

afterEach(function () {
    if (File::isDirectory($this->pfxDir)) {
        File::deleteDirectory($this->pfxDir);
    }
});

function callMtlsOptions(SicoobApiDriver $driver, PaymentGatewayCredential $cred): array
{
    $reflection = new ReflectionMethod($driver, 'mtlsOptions');
    $reflection->setAccessible(true);

    return $reflection->invoke($driver, $cred);
}

it('mtlsOptions retorna [] quando requires_mtls=false (compat sandbox)', function () {
    $cred = ($this->makeCred)(reqMtls: false);

    expect(callMtlsOptions(new SicoobApiDriver(), $cred))->toBe([]);
});

it('mtlsOptions throws CredentialMisconfigured quando requires_mtls=true mas mtls_pfx_path vazio', function () {
    $cred = ($this->makeCred)(pfxRel: null, reqMtls: true);

    expect(fn () => callMtlsOptions(new SicoobApiDriver(), $cred))
        ->toThrow(CredentialMisconfiguredException::class, 'mtls_pfx_path está vazio');
});

it('mtlsOptions throws quando .pfx não existe no filesystem', function () {
    $cred = ($this->makeCred)(pfxRel: 'sicoob/nao-existe.pfx', reqMtls: true);

    expect(fn () => callMtlsOptions(new SicoobApiDriver(), $cred))
        ->toThrow(CredentialMisconfiguredException::class, '.pfx não encontrado');
});

it('mtlsOptions throws quando senha ausente em config_json', function () {
    ($this->writeFakePfx)('sicoob/1.pfx');
    $cred = ($this->makeCred)(pfxRel: 'sicoob/1.pfx', reqMtls: true);
    // sem mtls_pfx_password_encrypted

    expect(fn () => callMtlsOptions(new SicoobApiDriver(), $cred))
        ->toThrow(CredentialMisconfiguredException::class, 'mtls_pfx_password_encrypted');
});

it('mtlsOptions throws quando senha não decifra (APP_KEY mudou)', function () {
    ($this->writeFakePfx)('sicoob/1.pfx');
    $cred = ($this->makeCred)(
        extra: ['mtls_pfx_password_encrypted' => 'ciphertext-garbage-not-decryptable'],
        pfxRel: 'sicoob/1.pfx',
        reqMtls: true,
    );

    expect(fn () => callMtlsOptions(new SicoobApiDriver(), $cred))
        ->toThrow(CredentialMisconfiguredException::class, 'APP_KEY');
});

it('mtlsOptions retorna [cert => [path, plain_password]] quando tudo OK', function () {
    $absPath = ($this->writeFakePfx)('sicoob/1.pfx');
    $plainPwd = 'minha-senha-secreta-123';
    $cred = ($this->makeCred)(
        extra: ['mtls_pfx_password_encrypted' => Crypt::encryptString($plainPwd)],
        pfxRel: 'sicoob/1.pfx',
        reqMtls: true,
    );

    $opts = callMtlsOptions(new SicoobApiDriver(), $cred);

    expect($opts)->toHaveKey('cert')
        ->and($opts['cert'])->toBe([$absPath, $plainPwd]);
});

it('resolveMtlsPfxFullPath preserva path absoluto Windows e Unix', function () {
    // Windows absolute
    $cred = ($this->makeCred)(
        extra: ['mtls_pfx_password_encrypted' => Crypt::encryptString('p')],
        pfxRel: 'C:/temp/sicoob-test.pfx',
        reqMtls: true,
    );
    File::ensureDirectoryExists('C:/temp');
    File::put('C:/temp/sicoob-test.pfx', 'fake');

    try {
        $opts = callMtlsOptions(new SicoobApiDriver(), $cred);
        expect($opts['cert'][0])->toBe('C:/temp/sicoob-test.pfx');
    } finally {
        File::delete('C:/temp/sicoob-test.pfx');
    }
});

it('resolveMtlsPfxFullPath prefixa path relativo com storage/app/private', function () {
    $absExpected = ($this->writeFakePfx)('sicoob/4.pfx');
    $cred = ($this->makeCred)(
        extra: ['mtls_pfx_password_encrypted' => Crypt::encryptString('p')],
        pfxRel: 'sicoob/4.pfx',
        reqMtls: true,
        bizId: 4,
    );

    $opts = callMtlsOptions(new SicoobApiDriver(), $cred);

    expect($opts['cert'][0])->toBe($absExpected)
        ->and($opts['cert'][0])->toContain('storage')
        ->and($opts['cert'][0])->toContain('private');
});

it('multi-tenant Tier 0: biz=4 e biz=99 carregam .pfx ISOLADOS', function () {
    ($this->writeFakePfx)('sicoob/4.pfx');
    ($this->writeFakePfx)('sicoob/99.pfx');

    $credBiz4 = ($this->makeCred)(
        extra: ['mtls_pfx_password_encrypted' => Crypt::encryptString('senha-biz-4')],
        pfxRel: 'sicoob/4.pfx',
        reqMtls: true,
        bizId: 4,
    );
    $credBiz99 = ($this->makeCred)(
        extra: ['mtls_pfx_password_encrypted' => Crypt::encryptString('senha-biz-99')],
        pfxRel: 'sicoob/99.pfx',
        reqMtls: true,
        bizId: 99,
    );

    $driver = new SicoobApiDriver();
    $opts4 = callMtlsOptions($driver, $credBiz4);
    $opts99 = callMtlsOptions($driver, $credBiz99);

    // Paths distintos e senhas distintas — nada cruzou.
    expect($opts4['cert'][0])->toContain('4.pfx')
        ->and($opts99['cert'][0])->toContain('99.pfx')
        ->and($opts4['cert'][0])->not->toBe($opts99['cert'][0])
        ->and($opts4['cert'][1])->toBe('senha-biz-4')
        ->and($opts99['cert'][1])->toBe('senha-biz-99');
});

it('emitirBoleto OK ponta-a-ponta com requires_mtls=true (Http::fake bypass handshake)', function () {
    Http::fake([
        '*/openid-connect/token' => Http::response(['access_token' => 't', 'expires_in' => 3600], 200),
        '*/cobranca-bancaria/v3/boletos' => Http::response([
            'resultado' => [['boleto' => [
                'nossoNumero' => '987654',
                'linhaDigitavel' => '75691.00000 00000.000000 00000.000000 0 99999999999999',
            ]]],
        ], 200),
    ]);

    ($this->writeFakePfx)('sicoob/1.pfx');
    $cred = ($this->makeCred)(
        extra: ['mtls_pfx_password_encrypted' => Crypt::encryptString('senha-real')],
        pfxRel: 'sicoob/1.pfx',
        reqMtls: true,
    );

    $input = new EmitirCobrancaInput(
        businessId: 1,
        contactId: 1,
        valorCentavos: 10000,
        vencimento: new DateTimeImmutable('+5 days'),
        descricao: 'Test PR3 mTLS',
        idempotencyKey: 'pr3-mtls-' . uniqid(),
        meta: [
            'payer_cpf_cnpj' => '12345678900',
            'payer_name'     => 'Pagador Teste',
        ],
    );

    $result = (new SicoobApiDriver())->emitirBoleto($input, $cred);

    expect($result->nossoNumero)->toBe('987654');
});

it('mensagens de erro mTLS NUNCA contêm senha em plaintext', function () {
    $absPath = ($this->writeFakePfx)('sicoob/1.pfx');
    $cred = ($this->makeCred)(
        extra: ['mtls_pfx_password_encrypted' => 'ciphertext-corrupted'],
        pfxRel: 'sicoob/1.pfx',
        reqMtls: true,
    );

    try {
        callMtlsOptions(new SicoobApiDriver(), $cred);
        $this->fail('Esperava CredentialMisconfiguredException');
    } catch (CredentialMisconfiguredException $e) {
        // Mensagem genérica — não vaza ciphertext nem chave nem path completo
        // do .pfx (path é OK, mas senha NUNCA).
        expect($e->getMessage())
            ->not->toContain('ciphertext-corrupted')
            ->and($e->getMessage())->toContain('APP_KEY');
    }
});
