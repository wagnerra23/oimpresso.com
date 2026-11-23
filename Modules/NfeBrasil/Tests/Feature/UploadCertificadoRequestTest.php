<?php

declare(strict_types=1);

// @covers-us US-NFE-041 — upload do certificado A1 (UploadCertificadoRequest).

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Modules\NfeBrasil\Http\Requests\UploadCertificadoRequest;

uses(Tests\TestCase::class);

/**
 * Trava o defeito de 2026-11-23: a regra `mimes:pfx,p12` deduzia a extensão pelo
 * CONTEÚDO; o libmagic lê PKCS#12 como `application/octet-stream` (extensão `bin`),
 * então todo .pfx válido voltava "O certificado deve ser .pfx ou .p12.".
 *
 * Os casos usam um PKCS#12 REAL gerado pelo openssl — com bytes inventados o teste
 * não reproduziria a detecção de MIME que causou o defeito.
 */
function pfxReal(): string
{
    $chave = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    $csr = openssl_csr_new(['commonName' => 'TESTE LTDA:12345678000199'], $chave);
    $cert = openssl_csr_sign($csr, null, $chave, 1);
    openssl_pkcs12_export($cert, $pfx, $chave, 'senha-teste');

    return $pfx;
}

function validarUpload(UploadedFile $arquivo): \Illuminate\Validation\Validator
{
    $request = new UploadCertificadoRequest();

    return Validator::make(
        ['certificado' => $arquivo, 'senha' => 'senha-teste'],
        $request->rules(),
        $request->messages(),
    );
}

it('aceita um .pfx real (o conteúdo é lido como octet-stream)', function () {
    $v = validarUpload(UploadedFile::fake()->createWithContent('WR2 - wscrct.01.pfx', pfxReal()));

    expect($v->errors()->get('certificado'))->toBe([]);
});

it('aceita o mesmo PKCS#12 com extensão .p12 e em maiúsculas', function () {
    $v = validarUpload(UploadedFile::fake()->createWithContent('CERT.P12', pfxReal()));

    expect($v->errors()->get('certificado'))->toBe([]);
});

it('recusa arquivo que não é .pfx/.p12 com a mensagem em PT-BR', function () {
    $v = validarUpload(UploadedFile::fake()->createWithContent('certificado.txt', 'nao sou um certificado'));

    expect($v->errors()->first('certificado'))->toBe('O certificado deve ser .pfx ou .p12.');
});

it('recusa .pfx acima de 100 KB (A3 ou arquivo errado)', function () {
    $v = validarUpload(UploadedFile::fake()->create('grande.pfx', 101));

    expect($v->errors()->first('certificado'))->toStartWith('Certificado muito grande');
});

it('não corta espaços da senha do certificado (TrimStrings)', function () {
    $request = \Illuminate\Http\Request::create('/nfe-brasil/configuracao/certificado', 'POST', [
        'senha' => ' senha com espaço ',
        'outro' => ' campo comum ',
    ]);

    $visto = null;
    (new \App\Http\Middleware\TrimStrings())->handle($request, function ($r) use (&$visto) {
        $visto = $r;

        return response('ok');
    });

    expect($visto->input('senha'))->toBe(' senha com espaço ')
        ->and($visto->input('outro'))->toBe('campo comum'); // controle: o resto segue cortado
});
