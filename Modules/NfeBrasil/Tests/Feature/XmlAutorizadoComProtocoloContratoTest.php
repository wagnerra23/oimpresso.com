<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Services\CertificadoService;
use Modules\NfeBrasil\Services\DanfeService;
use Modules\NfeBrasil\Services\Entrada\LeitorNfeEntrada;
use Modules\NfeBrasil\Services\NfeService;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * R-NFE-010 (guarda do XML) · lote 0.2. O arquivo guardado de uma nota autorizada é o que vai por
 * e-mail ao destinatário e no pacote do contador; sem `protNFe` ele não é o documento fiscal.
 * Tenant 98 (ADR 0358).
 */
const CHAVE_PROTOCOLO = '35261112345678000199550010000012351000012351';

beforeEach(function () {
    if (! Schema::hasTable('nfe_emissoes')) {
        $this->markTestSkipped('nfe_emissoes ausente neste schema.');
    }
    Storage::fake('nfe_fiscal');
    Storage::fake('local');
    app()->instance(DanfeService::class, Mockery::mock(DanfeService::class)->shouldReceive('salvar')->andReturn(null)->getMock());
});

function nfeAssinadaDeTeste(string $digest = 'abc='): string
{
    return '<NFe xmlns="http://www.portalfiscal.inf.br/nfe"><infNFe Id="NFe' . CHAVE_PROTOCOLO . '" versao="4.00"><ide><nNF>1235</nNF></ide></infNFe>'
        . '<Signature xmlns="http://www.w3.org/2000/09/xmldsig#"><SignedInfo><Reference><DigestValue>' . $digest . '</DigestValue></Reference></SignedInfo></Signature></NFe>';
}

function retornoComDigest(string $digest): string
{
    return '<retEnviNFe xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00"><tpAmb>2</tpAmb><verAplic>T</verAplic>'
        . '<cStat>104</cStat><xMotivo>Lote processado</xMotivo><cUF>35</cUF><dhRecbto>2026-11-10T10:00:00-03:00</dhRecbto>'
        . '<protNFe versao="4.00"><infProt><tpAmb>2</tpAmb><verAplic>T</verAplic><chNFe>' . CHAVE_PROTOCOLO . '</chNFe>'
        . '<dhRecbto>2026-11-10T10:00:00-03:00</dhRecbto><nProt>135000000000002</nProt><digVal>' . $digest . '</digVal>'
        . '<cStat>100</cStat><xMotivo>Autorizado</xMotivo></infProt></protNFe></retEnviNFe>';
}

function autorizarDeTeste(string $assinada, string $retorno): NfeEmissao
{
    $biz = (int) test()->seededTenant()->id;
    $emissao = NfeEmissao::create([
        'business_id' => $biz, 'modelo' => 55, 'serie' => '1', 'numero' => 1235,
        'status' => 'enviando', 'valor_total' => 10,
    ]);
    $servico = new NfeService(Mockery::mock(CertificadoService::class));
    (new ReflectionMethod($servico, 'processarRetorno'))->invoke($servico, $emissao, $retorno, $assinada, $biz, '1', 1235);

    return $emissao->refresh();
}

it('R-NFE-010 · o XML guardado da nota autorizada é o nfeProc, com NF-e e protocolo', function () {
    $emissao = autorizarDeTeste(nfeAssinadaDeTeste(), retornoComDigest('abc='));

    $xml = Storage::disk('nfe_fiscal')->get($emissao->xml_path);
    expect($emissao->status)->toBe('autorizada');
    expect($xml)->toContain('<nfeProc');
    expect($xml)->toContain('<protNFe');
    expect($xml)->toContain('<nProt>135000000000002</nProt>');
    expect($xml)->toContain('Id="NFe' . CHAVE_PROTOCOLO . '"');
    expect((array) $emissao->metadata)->not->toHaveKey('xml_sem_protocolo');
});

it('R-NFE-010 · protocolo que não casa com a assinatura não derruba a nota autorizada', function () {
    $emissao = autorizarDeTeste(nfeAssinadaDeTeste('abc='), retornoComDigest('outro='));

    expect($emissao->status)->toBe('autorizada');
    expect(Storage::disk('nfe_fiscal')->get($emissao->xml_path))->toBe(nfeAssinadaDeTeste('abc='));
    expect($emissao->metadata['xml_sem_protocolo'])->toBeTrue();
});

it('R-NFE-010 · o leitor de NF-e aceita o arquivo gravado', function () {
    $emissao = autorizarDeTeste(nfeAssinadaDeTeste(), retornoComDigest('abc='));

    expect(class_exists(LeitorNfeEntrada::class))->toBeTrue();
    expect(simplexml_load_string(Storage::disk('nfe_fiscal')->get($emissao->xml_path))->getName())->toBe('nfeProc');
});
