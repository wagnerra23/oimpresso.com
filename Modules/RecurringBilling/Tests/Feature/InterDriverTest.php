<?php

declare(strict_types=1);

use Modules\RecurringBilling\Services\Boleto\Drivers\InterDriver;

uses(Tests\TestCase::class);

/**
 * US-RB-040 · Cobertura Pest do InterDriver — smoke tests.
 *
 * InterDriver depende de Eduardokum\LaravelBoleto\Api\Banco\Inter, que
 * usa cURL próprio com mTLS — não captura via Http::fake() do Laravel.
 * Tests cobrem o que é possível sem mock externo:
 *   - Construtor aceita config sem explodir
 *   - writeTempCert grava arquivo PEM temp com permissão 0600 + idempotência
 *   - Trabalha com config completa pra Inter (não é confundido com Asaas/C6)
 *
 * Tests de roundtrip emitir/cancelar reais ficam pra suite de integração
 * separada (que chama sandbox Inter de verdade quando creds disponíveis).
 */

function interDriverDummyCertContent(): string
{
    return "-----BEGIN CERTIFICATE-----\n" .
        base64_encode(random_bytes(64)) . "\n" .
        "-----END CERTIFICATE-----\n";
}

function interDriverDummyKeyContent(): string
{
    return "-----BEGIN PRIVATE KEY-----\n" .
        base64_encode(random_bytes(64)) . "\n" .
        "-----END PRIVATE KEY-----\n";
}

afterEach(function () {
    // Limpa arquivos temp criados
    foreach (glob(sys_get_temp_dir() . '/inter_crt_*.pem') as $f) @unlink($f);
    foreach (glob(sys_get_temp_dir() . '/inter_key_*.pem') as $f) @unlink($f);
});

it('construtor aceita config completa Inter sem explodir', function () {
    $driver = new InterDriver([
        'client_id'             => 'inter-id',
        'client_secret'         => 'inter-secret',
        'certificado_crt_b64'   => base64_encode(interDriverDummyCertContent()),
        'certificado_key_b64'   => base64_encode(interDriverDummyKeyContent()),
        'conta_corrente'        => '12345678',
        'cnpj_beneficiario'     => '12.345.678/0001-99',
        'nome_beneficiario'     => 'Empresa Teste',
        'cep'                   => '01310-100',
        'logradouro'            => 'Av. Paulista',
        'numero'                => '1000',
        'bairro'                => 'Bela Vista',
        'cidade'                => 'São Paulo',
        'uf'                    => 'SP',
    ]);

    expect($driver)->toBeInstanceOf(InterDriver::class);
});

it('writeTempCert grava arquivo PEM em /tmp com permissão 0600', function () {
    $driver = new InterDriver(['client_id' => 'x']);
    $reflect = (new ReflectionClass($driver))->getMethod('writeTempCert');
    $reflect->setAccessible(true);

    $content = interDriverDummyCertContent();
    $path = $reflect->invoke($driver, 'inter_crt', $content);

    expect(file_exists($path))->toBeTrue()
        ->and(file_get_contents($path))->toBe($content)
        ->and($path)->toContain(sys_get_temp_dir())
        ->and($path)->toEndWith('.pem');

    // Permissão 0600 (em sistemas POSIX). Em Windows, fileperms retorna outra coisa.
    if (PHP_OS_FAMILY !== 'Windows') {
        expect(fileperms($path) & 0777)->toBe(0600);
    }
});

it('writeTempCert é idempotente (mesmo conteúdo → mesmo path, sem reescrever)', function () {
    $driver = new InterDriver(['client_id' => 'x']);
    $reflect = (new ReflectionClass($driver))->getMethod('writeTempCert');
    $reflect->setAccessible(true);

    $content = interDriverDummyCertContent();
    $path1 = $reflect->invoke($driver, 'inter_crt', $content);
    $mtime1 = filemtime($path1);

    sleep(1); // garante mtime diferente se reescrever

    $path2 = $reflect->invoke($driver, 'inter_crt', $content);

    expect($path2)->toBe($path1)             // mesmo path (md5 do conteúdo)
        ->and(filemtime($path2))->toBe($mtime1); // não reescreveu
});

it('writeTempCert gera paths diferentes pra prefixos crt vs key', function () {
    $driver = new InterDriver(['client_id' => 'x']);
    $reflect = (new ReflectionClass($driver))->getMethod('writeTempCert');
    $reflect->setAccessible(true);

    $content = interDriverDummyCertContent();
    $crtPath = $reflect->invoke($driver, 'inter_crt', $content);
    $keyPath = $reflect->invoke($driver, 'inter_key', $content);

    expect($crtPath)->not()->toBe($keyPath)
        ->and($crtPath)->toContain('inter_crt_')
        ->and($keyPath)->toContain('inter_key_');
});

it('writeTempCert trata conteúdo PEM diferente como path diferente', function () {
    $driver = new InterDriver(['client_id' => 'x']);
    $reflect = (new ReflectionClass($driver))->getMethod('writeTempCert');
    $reflect->setAccessible(true);

    $cert1 = interDriverDummyCertContent();
    $cert2 = interDriverDummyCertContent();

    $path1 = $reflect->invoke($driver, 'inter_crt', $cert1);
    $path2 = $reflect->invoke($driver, 'inter_crt', $cert2);

    expect($path1)->not()->toBe($path2);
});
