<?php

declare(strict_types=1);

// @covers-us R-NFSE-005 — a DPS da NFS-e vai em XML assinado, válido nos XSDs oficiais v1.01, gzip+base64 em POST /nfse.

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Modules\NFSe\Adapters\SnNfseAdapter;
use Modules\NFSe\DTO\NfseEmissaoPayload;
use Modules\NFSe\Exceptions\NfseException;
use Modules\NfeBrasil\Services\Nfse\DpsXml;
use NFePHP\Common\Signer;

uses(Tests\TestCase::class);

/**
 * R-NFSE-005. O adapter mandava JSON, que a Sefin Nacional não reconhece. Aqui a DPS é
 * gerada, assinada, validada contra os XSDs oficiais versionados em
 * `Modules/NfeBrasil/Resources/schemas/nfse/v1.01/` e empacotada como a API pede.
 * Não chama a Sefin: o envio é exercitado com Http::fake.
 */
function dpsxPfx(): string
{
    static $pfx = null;
    if ($pfx === null) {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $csr = openssl_csr_new(['commonName' => 'EMPRESA TESTE:11222333000181', 'countryName' => 'BR'], $key, ['digest_alg' => 'sha256']);
        $x509 = openssl_csr_sign($csr, null, $key, 365, ['digest_alg' => 'sha256']);
        openssl_pkcs12_export($x509, $pfx, $key, 'senha-teste');
    }

    return $pfx;
}

function dpsxPayload(array $extra = []): NfseEmissaoPayload
{
    return new NfseEmissaoPayload(...array_merge([
        'businessId' => 99, 'rpsNumero' => '123', 'competencia' => Carbon::create(2026, 10, 1),
        'tomadorNome' => 'Tomador Teste', 'tomadorCnpj' => '11.222.333/0001-81', 'tomadorCpf' => null,
        'tomadorEmail' => 'tomador@example.com', 'descricao' => 'Manutenção predial', 'lc116Codigo' => '7.10',
        'valorServicos' => 1500.0, 'aliquotaIss' => 0.05, 'certPfxBase64' => base64_encode(dpsxPfx()),
        'certSenha' => 'senha-teste', 'ambiente' => 'homologacao', 'municipioIbge' => '4218707',
        'prestadorCnpj' => '11222333000181', 'prestadorIm' => '12345', 'regimePrestador' => 'lucro_presumido',
    ], $extra));
}

function dpsxAssinada(array $extra = []): string
{
    $xml = DpsXml::serializar((new SnNfseAdapter())->buildDps(dpsxPayload($extra)));

    return DpsXml::assinar($xml, dpsxPfx(), 'senha-teste');
}

/** Valida contra os XSDs versionados; a única mudança é tirar o ^/$ literal do TSSerieDPS (README). */
function dpsxErrosXsd(string $xml): array
{
    $origem = base_path('Modules/NfeBrasil/Resources/schemas/nfse/v1.01');
    $dir = sys_get_temp_dir() . '/dps-xsd-' . uniqid();
    mkdir($dir);
    foreach (glob($origem . '/*.xsd') as $f) {
        copy($f, $dir . '/' . basename($f));
    }
    $simples = $dir . '/tiposSimples_v1.01.xsd';
    file_put_contents($simples, str_replace('value="^0{0,4}\d{1,5}$"', 'value="0{0,4}\d{1,5}"', (string) file_get_contents($simples)));

    $doc = new DOMDocument();
    $doc->loadXML($xml);
    libxml_use_internal_errors(true);
    $doc->schemaValidate($dir . '/DPS_v1.01.xsd');
    $erros = array_map(fn ($e) => trim($e->message), libxml_get_errors());
    libxml_clear_errors();
    array_map('unlink', glob($dir . '/*.xsd'));
    rmdir($dir);

    return $erros;
}

it('R-NFSE-005 a DPS assinada é válida nos XSDs oficiais v1.01 e a assinatura confere', function (array $extra) {
    $xml = dpsxAssinada($extra);

    expect(dpsxErrosXsd($xml))->toBe([]);
    expect(Signer::isSigned($xml, 'infDPS'))->toBeTrue();
    expect($xml)->toContain('<DPS xmlns="http://www.sped.fazenda.gov.br/nfse" versao="1.01">');
})->with([
    'presumido, tomador CNPJ' => [[]],
    'retenção federal (R-NFSE-003)' => [['retencaoFederalSujeito' => true, 'retPisCentavos' => 975, 'retCofinsCentavos' => 4500, 'retCsllCentavos' => 1500]],
    'Simples, tomador CPF, ISS retido' => [['regimePrestador' => 'simples', 'tomadorCnpj' => null, 'tomadorCpf' => '529.982.247-25', 'issRetido' => true, 'prestadorIm' => null]],
    'tomador sem documento' => [['tomadorCnpj' => null, 'tomadorCpf' => null, 'tomadorEmail' => null]],
]);

it('R-NFSE-005 controle negativo: o validador reprova uma DPS quebrada', function () {
    $ruim = str_replace('<cTribNac>071001</cTribNac>', '<cTribNac>07100</cTribNac>', dpsxAssinada());

    expect(dpsxErrosXsd($ruim))->not->toBe([]);
});

it('R-NFSE-005 Id = município (7) + tipo de inscrição (1) + CNPJ (14) + série (5) + nDPS (15)', function () {
    $dps = (new SnNfseAdapter())->buildDps(dpsxPayload(['rpsNumero' => '987', 'serieDps' => '2']));

    expect($dps['infDPS']['@Id'])->toBe('DPS' . '4218707' . '2' . '11222333000181' . '00002' . '000000000000987');
    expect(strlen($dps['infDPS']['@Id']))->toBe(45);
    expect($dps['infDPS']['nDPS'])->toBe('987');
});

it('R-NFSE-005 regime → opSimpNac, LC 116 → cTribNac, ISS retido → tpRetISSQN', function () {
    $a = new SnNfseAdapter();

    expect($a->buildDps(dpsxPayload())['infDPS']['prest']['regTrib']['opSimpNac'])->toBe('1');
    expect($a->buildDps(dpsxPayload(['regimePrestador' => 'simples']))['infDPS']['prest']['regTrib']['opSimpNac'])->toBe('3');
    expect($a->buildDps(dpsxPayload(['regimePrestador' => 'mei']))['infDPS']['prest']['regTrib']['opSimpNac'])->toBe('2');
    expect($a->buildDps(dpsxPayload(['lc116Codigo' => '1.05']))['infDPS']['serv']['cServ']['cTribNac'])->toBe('010501');
    expect($a->buildDps(dpsxPayload(['issRetido' => true]))['infDPS']['valores']['trib']['tribMun']['tpRetISSQN'])->toBe('2');
    expect($a->buildDps(dpsxPayload())['infDPS']['valores']['trib']['tribMun']['tpRetISSQN'])->toBe('1');
});

it('R-NFSE-005 falha visível sem regime, sem CNPJ do prestador ou com nDPS inválido', function () {
    $a = new SnNfseAdapter();

    expect(fn () => $a->buildDps(dpsxPayload(['regimePrestador' => null])))->toThrow(NfseException::class);
    expect(fn () => $a->buildDps(dpsxPayload(['prestadorCnpj' => null])))->toThrow(NfseException::class);
    expect(fn () => $a->buildDps(dpsxPayload(['rpsNumero' => '20261013101010000001'])))->toThrow(NfseException::class);
});

it('R-NFSE-005 comNumeroDps troca só o número da DPS', function () {
    $p = dpsxPayload();
    $q = $p->comNumeroDps('4567');

    expect($q->rpsNumero)->toBe('4567');
    expect($q->idempotencyKey())->toBe($p->idempotencyKey());
    expect($q->tomadorNome)->toBe($p->tomadorNome);
});

it('R-NFSE-005 emitir() envia dpsXmlGZipB64 assinado ao /nfse do ambiente do tenant e lê chave e número', function (string $ambiente, string $host, string $tpAmb) {
    $nfseRetorno = '<NFSe xmlns="http://www.sped.fazenda.gov.br/nfse"><infNFSe><nNFSe>42</nNFSe></infNFSe></NFSe>';
    Http::fake(['*' => Http::response([
        'chaveAcesso'    => str_repeat('1', 50),
        'nfseXmlGZipB64' => DpsXml::empacotar($nfseRetorno),
    ], 201)]);

    $r = (new SnNfseAdapter('producao'))->emitir(dpsxPayload(['ambiente' => $ambiente]));

    expect($r->numero)->toBe('42');
    expect($r->protocolo)->toBe(str_repeat('1', 50));
    Http::assertSent(function ($request) use ($host, $tpAmb) {
        $xml = DpsXml::desempacotar((string) $request['dpsXmlGZipB64']);

        return $request->url() === "{$host}/SefinNacional/nfse"
            && str_contains($xml, "<tpAmb>{$tpAmb}</tpAmb>")
            && Signer::isSigned($xml, 'infDPS');
    });
})->with([
    'homologação mesmo com bind global em produção' => ['homologacao', 'https://sefin.producaorestrita.nfse.gov.br', '2'],
    'produção' => ['producao', 'https://sefin.nfse.gov.br', '1'],
]);

it('R-NFSE-005 erro da Sefin no formato erros[{Codigo, Descricao}] vira NfseException com o código', function () {
    Http::fake(['*' => Http::response(['erros' => [['Codigo' => 'E0714', 'Descricao' => 'Arquivo enviado com erro na assinatura']]], 400)]);

    try {
        (new SnNfseAdapter())->emitir(dpsxPayload());
        $this->fail('esperava NfseException');
    } catch (NfseException $e) {
        expect($e->codigo)->toBe('E0714');
        expect($e->getMessage())->toBe('Arquivo enviado com erro na assinatura');
    }
});
