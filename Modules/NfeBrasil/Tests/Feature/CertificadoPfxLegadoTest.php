<?php

declare(strict_types=1);

// @covers-us US-NFE-001 — upload do certificado A1 aceita .pfx no formato antigo (RC2-40) com a senha certa.

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\NfeBrasil\Models\NfeCertificado;
use Modules\NfeBrasil\Services\CertificadoService;

uses(Tests\TestCase::class);

/**
 * Upload de certificado A1 no formato antigo.
 *
 * Os certificados são gerados aqui, com CNPJ sintético (11222333000181), em
 * dois formatos:
 *   - atual: openssl_pkcs12_export do PHP (AES);
 *   - antigo: `openssl pkcs12 -export -legacy -certpbe PBE-SHA1-RC2-40`, o
 *     formato comum de A1 emitido no Brasil, que o OpenSSL 3 sem o provider
 *     legado recusa com "unsupported" mesmo com a senha certa.
 *
 * Nenhuma chamada usa o leitor injetável: o caminho exercitado é o real
 * (openssl_pkcs12_read + conversão por `openssl pkcs12 -legacy`).
 */

const PFX_LEGADO_CNPJ = '11222333000181';
const PFX_LEGADO_SENHA = 'S3nh@ Teste "legado" $1';

function pfxLegadoGerarChaveECert(): array
{
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $csr = openssl_csr_new(['commonName' => 'EMPRESA TESTE LTDA:'.PFX_LEGADO_CNPJ], $key, ['digest_alg' => 'sha256']);
    $cert = openssl_csr_sign($csr, null, $key, 365, ['digest_alg' => 'sha256']);
    openssl_x509_export($cert, $certPem);
    openssl_pkey_export($key, $keyPem);

    return [$certPem, $keyPem, $key];
}

function pfxLegadoModerno(string $senha): string
{
    [$certPem, , $key] = pfxLegadoGerarChaveECert();
    $out = '';
    openssl_pkcs12_export($certPem, $out, $key, $senha);

    return $out;
}

function pfxLegadoRc2(string $senha): string
{
    [$certPem, $keyPem] = pfxLegadoGerarChaveECert();
    // `-export` lê o -in duas vezes (chave e certificados), então stdin não
    // serve: o PEM sintético vai num arquivo temporário apagado logo em seguida.
    $pemPath = tempnam(sys_get_temp_dir(), 'pfxleg');
    file_put_contents($pemPath, $certPem.$keyPem);
    $cmd = ['openssl', 'pkcs12', '-export', '-legacy', '-in', $pemPath,
        '-certpbe', 'PBE-SHA1-RC2-40', '-keypbe', 'PBE-SHA1-3DES', '-macalg', 'sha1',
        '-passout', 'env:PFX_TESTE_SENHA'];
    $env = ['PFX_TESTE_SENHA' => $senha, 'PATH' => (string) getenv('PATH'), 'HOME' => (string) (getenv('HOME') ?: sys_get_temp_dir())];
    try {
        $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
        fclose($pipes[0]);
        $pfx = (string) stream_get_contents($pipes[1]);
        $err = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $rc = proc_close($proc);
    } finally {
        unlink($pemPath);
    }
    expect($rc)->toBe(0, "openssl -export -legacy falhou: {$err}");
    expect(strlen($pfx))->toBeGreaterThan(0);

    return $pfx;
}

beforeEach(function () {
    Storage::fake('nfe_certs');
});

it('aceita .pfx no formato atual com a senha certa', function () {
    $meta = (new CertificadoService)->validar(base64_encode(pfxLegadoModerno(PFX_LEGADO_SENHA)), PFX_LEGADO_SENHA);

    expect($meta['cnpj_titular'])->toBe(PFX_LEGADO_CNPJ);
    expect($meta)->not->toHaveKey('pfx_binario');
});

it('aceita .pfx no formato antigo (RC2-40) com a senha certa', function () {
    $pfx = pfxLegadoRc2(PFX_LEGADO_SENHA);

    // Pré-condição que torna o caso discriminante: o leitor nativo deste runtime
    // recusa o arquivo por formato, não por senha. Se um dia o PHP passar a ler
    // RC2-40 sozinho, este caso para de provar a conversão — e diz isso.
    $info = [];
    $nativo = openssl_pkcs12_read($pfx, $info, PFX_LEGADO_SENHA);
    $erros = [];
    while (($e = openssl_error_string()) !== false) {
        $erros[] = $e;
    }
    if ($nativo) {
        $this->markTestSkipped('Este runtime lê RC2-40 sem conversão; o caso não discrimina aqui.');
    }
    expect(CertificadoService::ehFormatoNaoSuportado(implode(' | ', $erros)))->toBeTrue(implode(' | ', $erros));

    $meta = (new CertificadoService)->validar(base64_encode($pfx), PFX_LEGADO_SENHA);

    expect($meta['cnpj_titular'])->toBe(PFX_LEGADO_CNPJ);
});

it('recusa .pfx antigo com senha errada dizendo que a senha está errada', function () {
    $pfx = pfxLegadoRc2(PFX_LEGADO_SENHA);

    expect(fn () => (new CertificadoService)->validar(base64_encode($pfx), 'senha-errada'))
        ->toThrow(InvalidArgumentException::class, CertificadoService::MSG_SENHA_ERRADA);
});

it('recusa .pfx atual com senha errada dizendo que a senha está errada', function () {
    $pfx = pfxLegadoModerno(PFX_LEGADO_SENHA);

    expect(fn () => (new CertificadoService)->validar(base64_encode($pfx), 'senha-errada'))
        ->toThrow(InvalidArgumentException::class, CertificadoService::MSG_SENHA_ERRADA);
});

it('recusa arquivo que não é .pfx como arquivo inválido, não como senha errada', function () {
    expect(fn () => (new CertificadoService)->validar(base64_encode('isto nao e um pfx'), PFX_LEGADO_SENHA))
        ->toThrow(InvalidArgumentException::class, CertificadoService::MSG_ARQUIVO_INVALIDO);
});

it('salvar grava o .pfx antigo já convertido, legível pelo leitor nativo', function () {
    if (! Schema::hasTable('nfe_certificados') || ! DB::table('business')->where('id', 98)->exists()) {
        $this->markTestSkipped('Requer a lane MySQL com o tenant 98 semeado.');
    }

    $cert = (new CertificadoService)->salvar(98, base64_encode(pfxLegadoRc2(PFX_LEGADO_SENHA)), PFX_LEGADO_SENHA);

    try {
        $gravado = Crypt::decrypt(Storage::disk('nfe_certs')->get(sprintf('98/cert/%s.pfx.enc', $cert->uuid)));
        $info = [];
        expect(openssl_pkcs12_read($gravado, $info, PFX_LEGADO_SENHA))->toBeTrue();
        expect($info)->toHaveKey('pkey');
        expect($cert->cnpj_titular)->toBe(PFX_LEGADO_CNPJ);
    } finally {
        NfeCertificado::withoutGlobalScopes()->whereKey($cert->id)->forceDelete(); // SUPERADMIN: limpeza do teste, tenant 98
    }
});
