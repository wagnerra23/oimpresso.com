<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Modules\PaymentGateway\Dto\CobrancaStatus;
use Modules\PaymentGateway\Exceptions\CredentialMisconfiguredException;
use Modules\PaymentGateway\Exceptions\GatewayUnavailableException;
use Modules\PaymentGateway\Exceptions\InvalidPayerException;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Modules\PaymentGateway\Services\Drivers\InterDriver;

uses(Tests\TestCase::class);

/**
 * InterDriver::consultarPixCob() — consulta de status PIX usada pelo polling
 * de reconciliação (InterReconcilePixCommand). Cobre GET /pix/v2/cob|cobv/{txid}
 * + mapPixStatus + mTLS com certificado configurado.
 *
 * DB-FREE: credencial não-salva (new + forceFill) + stub de cobrança + Http::fake.
 * Roda em qualquer ambiente (inclusive CI SQLite sem migrations).
 *
 * Multi-tenant Tier 0: business_id=1 (ADR 0101 — nunca cliente real).
 */

/** Credencial Inter NÃO-salva (não toca DB). */
function interCredStub(array $config = []): PaymentGatewayCredential
{
    $cred = new PaymentGatewayCredential();
    $cred->forceFill([
        'business_id'  => 1,
        'gateway_key'  => 'inter',
        'ambiente'     => 'sandbox',
        'ativo'        => true,
        'nome_display' => 'Inter Driver Test',
        'config_json'  => array_merge(['client_id' => 'cli', 'client_secret' => 'sec'], $config),
    ]);
    $cred->id = 1;

    return $cred;
}

/** Stub de cobrança (driver só lê gateway_external_id + tipo). */
function cobrancaStub(string $txid, string $tipo = 'pix_cob'): object
{
    return (object) ['gateway_external_id' => $txid, 'tipo' => $tipo];
}

function fakeOAuth(): array
{
    return ['*/oauth/v2/token' => Http::response(['access_token' => 'tk', 'expires_in' => 3600], 200)];
}

it('pix_cob CONCLUIDA → status paga + valor + pagaEm (GET /pix/v2/cob/{txid})', function () {
    Http::fake(array_merge(fakeOAuth(), [
        '*/pix/v2/cob/*' => Http::response([
            'status' => 'CONCLUIDA',
            'valor'  => ['original' => '150.00'],
            'pix'    => [[
                'endToEndId' => 'E000202606011000',
                'txid'       => 'tx-cob-1',
                'valor'      => '150.00',
                'horario'    => '2026-06-01T10:00:00Z',
            ]],
        ], 200),
    ]));

    $status = (new InterDriver())->consultarPixCob(cobrancaStub('tx-cob-1'), interCredStub());

    expect($status)->toBeInstanceOf(CobrancaStatus::class);
    expect($status->status)->toBe('paga');
    expect($status->valorPagoCentavos)->toBe(15000);
    expect($status->formaPagamento)->toBe('pix');
    expect($status->pagaEm)->not->toBeNull();

    Http::assertSent(fn ($req) => str_contains($req->url(), '/pix/v2/cob/tx-cob-1'));
});

it('pix_cobv usa endpoint /pix/v2/cobv/{txid}', function () {
    Http::fake(array_merge(fakeOAuth(), [
        '*/pix/v2/cobv/*' => Http::response(['status' => 'ATIVA'], 200),
    ]));

    (new InterDriver())->consultarPixCob(cobrancaStub('tx-cobv-1', 'pix_cobv'), interCredStub());

    Http::assertSent(fn ($req) => str_contains($req->url(), '/pix/v2/cobv/tx-cobv-1'));
});

it('ATIVA → emitida (sem valor pago)', function () {
    Http::fake(array_merge(fakeOAuth(), [
        '*/pix/v2/cob/*' => Http::response(['status' => 'ATIVA', 'valor' => ['original' => '99.00']], 200),
    ]));

    $status = (new InterDriver())->consultarPixCob(cobrancaStub('tx-ativa'), interCredStub());

    expect($status->status)->toBe('emitida');
    expect($status->valorPagoCentavos)->toBeNull();
    expect($status->formaPagamento)->toBeNull();
});

it('REMOVIDA_PELO_USUARIO_RECEBEDOR → cancelada', function () {
    Http::fake(array_merge(fakeOAuth(), [
        '*/pix/v2/cob/*' => Http::response(['status' => 'REMOVIDA_PELO_USUARIO_RECEBEDOR'], 200),
    ]));

    $status = (new InterDriver())->consultarPixCob(cobrancaStub('tx-rem-1'), interCredStub());
    expect($status->status)->toBe('cancelada');
});

it('REMOVIDA_PELO_PSP → cancelada', function () {
    Http::fake(array_merge(fakeOAuth(), [
        '*/pix/v2/cob/*' => Http::response(['status' => 'REMOVIDA_PELO_PSP'], 200),
    ]));

    $status = (new InterDriver())->consultarPixCob(cobrancaStub('tx-rem-2'), interCredStub());
    expect($status->status)->toBe('cancelada');
});

it('status desconhecido → pending', function () {
    Http::fake(array_merge(fakeOAuth(), [
        '*/pix/v2/cob/*' => Http::response(['status' => 'ALGO_NOVO'], 200),
    ]));

    $status = (new InterDriver())->consultarPixCob(cobrancaStub('tx-x'), interCredStub());
    expect($status->status)->toBe('pending');
});

it('soma múltiplos PIX recebidos no valor pago', function () {
    Http::fake(array_merge(fakeOAuth(), [
        '*/pix/v2/cob/*' => Http::response([
            'status' => 'CONCLUIDA',
            'pix'    => [
                ['txid' => 't', 'valor' => '100.00', 'horario' => '2026-06-01T10:00:00Z'],
                ['txid' => 't', 'valor' => '50.50',  'horario' => '2026-06-01T10:05:00Z'],
            ],
        ], 200),
    ]));

    $status = (new InterDriver())->consultarPixCob(cobrancaStub('tx-multi'), interCredStub());
    expect($status->valorPagoCentavos)->toBe(15050);
});

it('cobrança sem gateway_external_id (txid) → InvalidPayerException', function () {
    Http::fake(fakeOAuth());

    expect(fn () => (new InterDriver())->consultarPixCob(cobrancaStub(''), interCredStub()))
        ->toThrow(InvalidPayerException::class);
});

it('Inter responde erro (HTTP 500) → GatewayUnavailableException', function () {
    Http::fake(array_merge(fakeOAuth(), [
        '*/pix/v2/cob/*' => Http::response(['erro' => 'interno'], 500),
    ]));

    expect(fn () => (new InterDriver())->consultarPixCob(cobrancaStub('tx-err'), interCredStub()))
        ->toThrow(GatewayUnavailableException::class);
});

it('credencial sem client_id/secret → CredentialMisconfiguredException', function () {
    Http::fake(fakeOAuth());

    $credSemConfig = new PaymentGatewayCredential();
    $credSemConfig->forceFill([
        'business_id' => 1,
        'gateway_key' => 'inter',
        'ambiente'    => 'sandbox',
        'ativo'       => true,
        'config_json' => [], // sem client_id/secret
    ]);

    expect(fn () => (new InterDriver())->consultarPixCob(cobrancaStub('tx'), $credSemConfig))
        ->toThrow(CredentialMisconfiguredException::class);
});

it('certificado mTLS configurado: mtlsOptions inclui cert + ssl_key e a consulta funciona', function () {
    // Cria arquivos de certificado temporários (is_file precisa existir)
    $crt = tempnam(sys_get_temp_dir(), 'inter_crt_');
    $key = tempnam(sys_get_temp_dir(), 'inter_key_');
    file_put_contents($crt, "-----BEGIN CERTIFICATE-----\nFAKE\n-----END CERTIFICATE-----");
    file_put_contents($key, "-----BEGIN PRIVATE KEY-----\nFAKE\n-----END PRIVATE KEY-----");

    try {
        $cred = interCredStub(['certificado_crt' => $crt, 'certificado_key' => $key]);
        $driver = new InterDriver();

        // (a) mtlsOptions (private) monta cert + ssl_key a partir dos arquivos
        $ref = new ReflectionMethod(InterDriver::class, 'mtlsOptions');
        $ref->setAccessible(true);
        $opts = $ref->invoke($driver, $cred->config_json);

        expect($opts['cert'] ?? null)->toBe($crt);
        expect($opts['ssl_key'] ?? null)->toBe($key);

        // (b) com cert configurado, a consulta continua funcionando end-to-end
        Http::fake(array_merge(fakeOAuth(), [
            '*/pix/v2/cob/*' => Http::response([
                'status' => 'CONCLUIDA',
                'pix'    => [['txid' => 'tx-cert', 'valor' => '10.00', 'horario' => '2026-06-01T10:00:00Z']],
            ], 200),
        ]));

        $status = $driver->consultarPixCob(cobrancaStub('tx-cert'), $cred);
        expect($status->status)->toBe('paga');
    } finally {
        @unlink($crt);
        @unlink($key);
    }
});

it('SEM certificado configurado: mtlsOptions vazio (não quebra)', function () {
    $driver = new InterDriver();
    $ref = new ReflectionMethod(InterDriver::class, 'mtlsOptions');
    $ref->setAccessible(true);

    $opts = $ref->invoke($driver, ['client_id' => 'x', 'client_secret' => 'y']);
    expect($opts)->toBe([]);
});

it('certificado via base64 inline (crt_b64/key_b64) materializa arquivos temp com o PEM', function () {
    $crtPem = "-----BEGIN CERTIFICATE-----\nMIIBcrtFake\n-----END CERTIFICATE-----";
    $keyPem = "-----BEGIN PRIVATE KEY-----\nMIIBkeyFake\n-----END PRIVATE KEY-----";

    $driver = new InterDriver();
    $ref = new ReflectionMethod(InterDriver::class, 'mtlsOptions');
    $ref->setAccessible(true);

    $opts = $ref->invoke($driver, [
        'client_id'           => 'x',
        'client_secret'       => 'y',
        'certificado_crt_b64' => base64_encode($crtPem),
        'certificado_key_b64' => base64_encode($keyPem),
    ]);

    expect($opts['cert'] ?? null)->not->toBeNull();
    expect($opts['ssl_key'] ?? null)->not->toBeNull();
    expect(is_file($opts['cert']))->toBeTrue();
    expect(is_file($opts['ssl_key']))->toBeTrue();
    expect(file_get_contents($opts['cert']))->toBe($crtPem);
    expect(file_get_contents($opts['ssl_key']))->toBe($keyPem);
});

it('certificado_key_b64 cifrado por-campo (Crypt, como install-biz.py grava) é decifrado e materializado', function () {
    $keyPem = "-----BEGIN PRIVATE KEY-----\nMIIBkeyCrypt\n-----END PRIVATE KEY-----";

    $driver = new InterDriver();
    $ref = new ReflectionMethod(InterDriver::class, 'mtlsOptions');
    $ref->setAccessible(true);

    $opts = $ref->invoke($driver, [
        'client_id'           => 'x',
        'client_secret'       => 'y',
        'certificado_key_b64' => Crypt::encryptString(base64_encode($keyPem)),
    ]);

    expect($opts['ssl_key'] ?? null)->not->toBeNull();
    expect(file_get_contents($opts['ssl_key']))->toBe($keyPem);
});

it('caminho de arquivo tem prioridade sobre base64 quando ambos presentes', function () {
    $pathPem = "-----BEGIN CERTIFICATE-----\nDoArquivo\n-----END CERTIFICATE-----";
    $crtFile = tempnam(sys_get_temp_dir(), 'prio_crt_');
    file_put_contents($crtFile, $pathPem);

    try {
        $driver = new InterDriver();
        $ref = new ReflectionMethod(InterDriver::class, 'mtlsOptions');
        $ref->setAccessible(true);

        $opts = $ref->invoke($driver, [
            'certificado_crt'     => $crtFile,
            'certificado_crt_b64' => base64_encode("-----BEGIN CERTIFICATE-----\nDoB64\n-----END CERTIFICATE-----"),
        ]);

        expect($opts['cert'])->toBe($crtFile);
    } finally {
        @unlink($crtFile);
    }
});

it('decodePem aceita PEM cru, base64(PEM) e Crypt(base64(PEM)); rejeita lixo', function () {
    $pem = "-----BEGIN CERTIFICATE-----\nABC\n-----END CERTIFICATE-----";
    $driver = new InterDriver();
    $ref = new ReflectionMethod(InterDriver::class, 'decodePem');
    $ref->setAccessible(true);

    expect($ref->invoke($driver, $pem))->toBe($pem);
    expect($ref->invoke($driver, base64_encode($pem)))->toBe($pem);
    expect($ref->invoke($driver, Crypt::encryptString(base64_encode($pem))))->toBe($pem);
    expect($ref->invoke($driver, 'lixo-que-nao-eh-pem'))->toBeNull();
});

it('maybeDecrypt decifra Crypt e passa valor plano direto', function () {
    $driver = new InterDriver();
    $ref = new ReflectionMethod(InterDriver::class, 'maybeDecrypt');
    $ref->setAccessible(true);

    expect($ref->invoke($driver, Crypt::encryptString('segredo')))->toBe('segredo');
    expect($ref->invoke($driver, 'ja-plano'))->toBe('ja-plano');
    expect($ref->invoke($driver, ''))->toBe('');
});
