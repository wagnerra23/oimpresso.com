<?php

declare(strict_types=1);

// @covers-us US-NFSE-006
// Cancelamento pelo evento e101101 assinado (decisão B2 do [W], 2026-11-18), no lugar do
// `DELETE /nfse/{numero}` que o adapter mandava. Fontes oficiais (gov.br/nfse):
//  - Manual dos Contribuintes — API do Emissor Público Nacional v1.2, item 1.5: POST /nfse/{chaveAcesso}/eventos;
//  - pedRegEvento_v1.01.xsd + tiposEventos_v1.01.xsd (pacote nfse-esquemas_xsd-v1-01-20260209);
//  - Anexo II LeiautesRN_Eventos: Id = "PRE" + chave (50) + código do evento (6), assinatura obrigatória.
// Não chama a Sefin: o envio é exercitado com Http::fake. Sem banco — nenhum tenant real.

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\NFSe\Adapters\SnNfseAdapter;
use Modules\NFSe\Exceptions\NfseException;
use Modules\NFSe\Exceptions\ProviderTimeoutException;
use Modules\NfeBrasil\Services\Nfse\DpsXml;
use NFePHP\Common\Signer;

const EVCANC_CHAVE = '42187072112223330001810000000009000012610123456789';
const EVCANC_OUTRA_CHAVE = '42187072112223330001810000000009000022610123456780';
const EVCANC_CNPJ = '11222333000181'; // pii-allowlist (CNPJ sintético de fixture)
const EVCANC_MOTIVO = 'Servico cancelado a pedido do tomador';

function evcancPfx(): string
{
    static $pfx = null;
    if ($pfx === null) {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $csr = openssl_csr_new(['commonName' => 'EMPRESA TESTE', 'countryName' => 'BR'], $key, ['digest_alg' => 'sha256']);
        $x509 = openssl_csr_sign($csr, null, $key, 365, ['digest_alg' => 'sha256']);
        openssl_pkcs12_export($x509, $pfx, $key, 'senha-teste');
    }

    return $pfx;
}

function evcancAssinado(string $ambiente = 'homologacao', string $motivo = EVCANC_MOTIVO): string
{
    $pedido = (new SnNfseAdapter())->buildPedRegEventoCancelamento(EVCANC_CHAVE, $motivo, $ambiente, EVCANC_CNPJ);

    return DpsXml::assinarPedRegEvento(DpsXml::serializarPedRegEvento($pedido), evcancPfx(), 'senha-teste');
}

/** Valida contra o pedRegEvento_v1.01.xsd versionado (mesma correção do TSSerieDPS do README). */
function evcancErrosXsd(string $xml): array
{
    $origem = base_path('Modules/NfeBrasil/Resources/schemas/nfse/v1.01');
    $dir = sys_get_temp_dir() . '/evt-xsd-' . uniqid();
    mkdir($dir);
    foreach (glob($origem . '/*.xsd') as $f) {
        copy($f, $dir . '/' . basename($f));
    }
    $simples = $dir . '/tiposSimples_v1.01.xsd';
    file_put_contents($simples, str_replace('value="^0{0,4}\d{1,5}$"', 'value="0{0,4}\d{1,5}"', (string) file_get_contents($simples)));

    $doc = new DOMDocument();
    $doc->loadXML($xml);
    libxml_use_internal_errors(true);
    $doc->schemaValidate($dir . '/pedRegEvento_v1.01.xsd');
    $erros = array_map(fn ($e) => trim($e->message), libxml_get_errors());
    libxml_clear_errors();
    array_map('unlink', glob($dir . '/*.xsd'));
    rmdir($dir);

    return $erros;
}

/** Resposta da Sefin com o evento gerado: envolve o pedido que chegou, como a Sefin faz. */
function evcancRespostaComEvento(Request $request, ?string $chaveNoEvento = null): array
{
    $pedido = DpsXml::desempacotar((string) $request->data()['pedidoRegistroEventoXmlGZipB64']);
    $pedido = preg_replace('/^<\?xml[^>]*>/', '', $pedido);
    if ($chaveNoEvento !== null) {
        $pedido = str_replace(EVCANC_CHAVE, $chaveNoEvento, $pedido);
    }
    $evento = '<evento xmlns="' . DpsXml::NAMESPACE . '" versao="1.01"><infEvento Id="EVT' . EVCANC_CHAVE . '101101001">'
        . $pedido . '</infEvento></evento>';

    return ['eventoXmlGZipB64' => DpsXml::empacotar($evento)];
}

function evcancCancelar(string $ambiente = 'homologacao', string $chave = EVCANC_CHAVE): bool
{
    return (new SnNfseAdapter())->cancelarNoAmbiente(
        $chave,
        EVCANC_MOTIVO,
        $ambiente,
        base64_encode(evcancPfx()),
        'senha-teste',
        EVCANC_CNPJ,
    );
}

it('o pedido de cancelamento assinado é válido no pedRegEvento_v1.01.xsd oficial', function (string $ambiente) {
    $xml = evcancAssinado($ambiente);

    expect(evcancErrosXsd($xml))->toBe([]);
    expect(Signer::isSigned($xml, 'infPedReg'))->toBeTrue();
    expect($xml)->toContain('<pedRegEvento xmlns="http://www.sped.fazenda.gov.br/nfse" versao="1.01">');
})->with(['homologação' => ['homologacao'], 'produção' => ['producao']]);

it('controle negativo: o validador reprova um pedido com motivo abaixo de 15 caracteres', function () {
    $ruim = str_replace('<xMotivo>' . EVCANC_MOTIVO . '</xMotivo>', '<xMotivo>curto</xMotivo>', evcancAssinado());

    expect($ruim)->toContain('<xMotivo>curto</xMotivo>');
    expect(evcancErrosXsd($ruim))->not->toBe([]);
});

it('Id = PRE + chave de acesso (50) + 101101, tpAmb pelo ambiente da empresa, e101101 com cMotivo 9', function () {
    $a = new SnNfseAdapter();
    $hom = $a->buildPedRegEventoCancelamento(EVCANC_CHAVE, EVCANC_MOTIVO, 'homologacao', '11.222.333/0001-81'); // pii-allowlist (CNPJ sintético com máscara)
    $prod = $a->buildPedRegEventoCancelamento(EVCANC_CHAVE, EVCANC_MOTIVO, 'producao', EVCANC_CNPJ);

    expect($hom['infPedReg']['@Id'])->toBe('PRE' . EVCANC_CHAVE . '101101');
    expect(strlen($hom['infPedReg']['@Id']))->toBe(59);
    expect($hom['infPedReg']['tpAmb'])->toBe('2');
    expect($prod['infPedReg']['tpAmb'])->toBe('1');
    expect($hom['infPedReg']['CNPJAutor'])->toBe(EVCANC_CNPJ);
    expect($hom['infPedReg']['chNFSe'])->toBe(EVCANC_CHAVE);
    expect($hom['infPedReg']['e101101'])->toBe([
        'xDesc'   => 'Cancelamento de NFS-e',
        'cMotivo' => '9',
        'xMotivo' => EVCANC_MOTIVO,
    ]);
});

it('confirma quando a Sefin devolve o evento desta chave — POST assinado no endpoint de eventos do ambiente', function (string $ambiente, string $host) {
    Http::fake(fn (Request $request) => Http::response(evcancRespostaComEvento($request), 201));

    expect(evcancCancelar($ambiente))->toBeTrue();

    Http::assertSentCount(1);
    Http::assertSent(function (Request $request) use ($host) {
        $xml = DpsXml::desempacotar((string) ($request->data()['pedidoRegistroEventoXmlGZipB64'] ?? ''));

        return $request->method() === 'POST'
            && $request->url() === $host . '/nfse/' . EVCANC_CHAVE . '/eventos'
            && Signer::isSigned($xml, 'infPedReg')
            && evcancErrosXsd($xml) === [];
    });
})->with([
    'homologação' => ['homologacao', 'https://sefin.producaorestrita.nfse.gov.br/SefinNacional'],
    'produção'    => ['producao', 'https://sefin.nfse.gov.br/SefinNacional'],
]);

it('não confirma resposta 2xx sem o evento gerado', function () {
    Http::fake(['*' => Http::response([], 200)]);

    expect(evcancCancelar())->toBeFalse();
});

it('não confirma quando o evento devolvido é de outra nota', function () {
    Http::fake(fn (Request $request) => Http::response(evcancRespostaComEvento($request, EVCANC_OUTRA_CHAVE), 201));

    expect(evcancCancelar())->toBeFalse();
});

it('recusa da Sefin vira exceção com o motivo dela — não devolve true', function () {
    Http::fake(['*' => Http::response(['erros' => [[
        'Codigo'    => 'E1235',
        'Descricao' => 'Prazo para cancelamento expirado.',
    ]]], 400)]);

    expect(fn () => evcancCancelar())
        ->toThrow(NfseException::class, 'A Sefin recusou o cancelamento: Prazo para cancelamento expirado.');
});

it('falha de rede vira ProviderTimeoutException', function () {
    Http::fake(fn () => throw new ConnectionException('Connection timed out'));

    expect(fn () => evcancCancelar())->toThrow(ProviderTimeoutException::class);
});

it('sem a chave de acesso de 50 dígitos não envia nada', function (string $chave) {
    Http::fake();

    expect(fn () => evcancCancelar('homologacao', $chave))
        ->toThrow(NfseException::class, 'chave de acesso');

    Http::assertNothingSent();
})->with(['vazia' => [''], 'número da nota' => ['900001']]);

it('o caminho DELETE /nfse/{numero} não existe mais', function () {
    Http::fake(fn (Request $request) => Http::response(evcancRespostaComEvento($request), 201));

    evcancCancelar();

    Http::assertNotSent(fn (Request $request) => $request->method() === 'DELETE');
});
