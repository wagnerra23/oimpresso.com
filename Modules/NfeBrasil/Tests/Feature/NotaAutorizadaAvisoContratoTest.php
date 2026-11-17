<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\NfeBrasil\Events\NFCeAutorizada;
use Modules\NfeBrasil\Events\NFeAutorizada;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Services\CertificadoService;
use Modules\NfeBrasil\Services\DanfeService;
use Modules\NfeBrasil\Services\NfeService;
use NFePHP\NFe\Tools;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Lote 1.1 da auditoria do NfeBrasil (N4-09) · CU-NFE-01.
 *
 * Toda nota que vira autorizada avisa o resto do sistema (DANFE por e-mail e WhatsApp, cache do
 * cockpit Fiscal) — não só a da cobrança recorrente. O aviso sai do NfeService, no ponto em que a
 * nota passa a autorizada, uma vez só. Tenant 98 (ADR 0358).
 */
const AVISO_CHAVE_55 = '35261112345678000199550010000088881000088881';
const AVISO_CHAVE_65 = '35261112345678000199650010000088881000088881';

beforeEach(function () {
    if (! Schema::hasTable('nfe_emissoes') || ! Schema::hasColumn('nfe_emissoes', 'retry_count')) {
        $this->markTestSkipped('Schema do NfeBrasil ausente.');
    }
    Storage::fake('nfe_fiscal');
    Storage::fake('local');
    app()->instance(DanfeService::class, Mockery::mock(DanfeService::class)->shouldReceive('salvar')->andReturn(null)->getMock());
});

function avisoXml(string $chave): string
{
    return '<NFe xmlns="http://www.portalfiscal.inf.br/nfe"><infNFe Id="NFe' . $chave . '" versao="4.00"><ide><nNF>8888</nNF></ide></infNFe>'
        . '<Signature xmlns="http://www.w3.org/2000/09/xmldsig#"><SignedInfo><Reference><DigestValue>abc=</DigestValue></Reference></SignedInfo></Signature></NFe>';
}

function avisoRetorno(string $chave, string $cstat): string
{
    return '<retEnviNFe xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00"><tpAmb>2</tpAmb><verAplic>T</verAplic>'
        . '<cStat>104</cStat><xMotivo>Lote processado</xMotivo><cUF>35</cUF><dhRecbto>2026-11-10T10:00:00-03:00</dhRecbto>'
        . '<protNFe versao="4.00"><infProt><tpAmb>2</tpAmb><verAplic>T</verAplic><chNFe>' . $chave . '</chNFe>'
        . '<dhRecbto>2026-11-10T10:00:00-03:00</dhRecbto><nProt>135000000008888</nProt><digVal>abc=</digVal>'
        . '<cStat>' . $cstat . '</cStat><xMotivo>Retorno</xMotivo></infProt></protNFe></retEnviNFe>';
}

function avisoConsulta(string $chave): string
{
    return '<retConsSitNFe xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00"><tpAmb>2</tpAmb><verAplic>T</verAplic>'
        . '<cStat>100</cStat><xMotivo>Consulta</xMotivo><cUF>35</cUF><dhRecbto>2026-11-10T10:00:00-03:00</dhRecbto>'
        . '<chNFe>' . $chave . '</chNFe><protNFe versao="4.00"><infProt><tpAmb>2</tpAmb><verAplic>T</verAplic><chNFe>' . $chave . '</chNFe>'
        . '<dhRecbto>2026-11-10T09:00:00-03:00</dhRecbto><nProt>135000000008888</nProt><digVal>abc=</digVal>'
        . '<cStat>100</cStat><xMotivo>Autorizado</xMotivo></infProt></protNFe></retConsSitNFe>';
}

function avisoServico(?string $envio, ?string $consulta = null): NfeService
{
    $cert = Mockery::mock(CertificadoService::class);
    $cert->shouldReceive('carregarParaSefaz')->andReturn([
        'pfx_binary' => 'x', 'senha' => 'x', 'valido_ate' => now()->addYear(), 'source' => 'teste',
    ]);

    $tools = Mockery::mock(Tools::class);
    if ($envio !== null) {
        $tools->shouldReceive('sefazEnviaLote')->andReturn($envio);
    }
    if ($consulta !== null) {
        $tools->shouldReceive('sefazConsultaChave')->andReturn($consulta);
    }

    return new NfeService($cert, fn () => $tools);
}

function avisoEmissao(string $status, int $modelo, ?int $tx = null): NfeEmissao
{
    $biz = (int) test()->seededTenant()->id;
    $chave = $modelo === 65 ? AVISO_CHAVE_65 : AVISO_CHAVE_55;
    Storage::disk('nfe_fiscal')->put("nfe-brasil/{$biz}/notas/1-8888.xml", avisoXml($chave));

    return NfeEmissao::create([
        // `modelo` é enum('55','65','67'): inteiro vira índice do enum no MySQL, por isso texto.
        'business_id' => $biz, 'transaction_id' => $tx, 'modelo' => (string) $modelo, 'serie' => '1', 'numero' => 8888,
        'status' => $status, 'valor_total' => 10, 'tp_emis' => 1,
        'xml_path' => "nfe-brasil/{$biz}/notas/1-8888.xml",
    ]);
}

it('CU-NFE-01 · NF-e autorizada fora da cobrança recorrente dispara NFeAutorizada uma vez', function () {
    Event::fake([NFeAutorizada::class, NFCeAutorizada::class]);
    $emissao = avisoEmissao('contingencia', 55);

    $volta = avisoServico(avisoRetorno(AVISO_CHAVE_55, '100'))->transmitirContingencia($emissao->business_id, $emissao->id);

    expect($volta->status)->toBe('autorizada');
    Event::assertDispatchedTimes(NFeAutorizada::class, 1);
    Event::assertDispatched(NFeAutorizada::class, fn ($e) => $e->emissao->id === $emissao->id);
    Event::assertNotDispatched(NFCeAutorizada::class);
});

it('CU-NFE-01 · NFC-e autorizada dispara NFCeAutorizada, não NFeAutorizada', function () {
    Event::fake([NFeAutorizada::class, NFCeAutorizada::class]);
    $emissao = avisoEmissao('contingencia', 65);

    avisoServico(avisoRetorno(AVISO_CHAVE_65, '100'))->transmitirContingencia($emissao->business_id, $emissao->id);

    Event::assertDispatchedTimes(NFCeAutorizada::class, 1);
    Event::assertNotDispatched(NFeAutorizada::class);
});

it('CU-NFE-01 · nota rejeitada não dispara aviso de autorização', function () {
    Event::fake([NFeAutorizada::class, NFCeAutorizada::class]);
    $emissao = avisoEmissao('contingencia', 55);

    $volta = avisoServico(avisoRetorno(AVISO_CHAVE_55, '225'))->transmitirContingencia($emissao->business_id, $emissao->id);

    expect($volta->status)->toBe('rejeitada');
    Event::assertNotDispatched(NFeAutorizada::class);
});

it('CU-NFE-01 · nota que a reconciliação acha autorizada na SEFAZ também avisa', function () {
    Event::fake([NFeAutorizada::class, NFCeAutorizada::class]);
    $emissao = avisoEmissao('erro_envio', 55, 998877002);

    $metodo = new ReflectionMethod(NfeService::class, 'emitirInterno');
    try {
        $metodo->invoke(avisoServico(null, avisoConsulta(AVISO_CHAVE_55)), (int) $emissao->business_id, ['valor_total' => 10, 'modelo' => '55'], '55', 998877002);
    } catch (Throwable) {
        // sem dados de nota no teste o fluxo para ao montar um XML novo; a reconciliação já rodou
    }

    expect($emissao->refresh()->status)->toBe('autorizada');
    Event::assertDispatchedTimes(NFeAutorizada::class, 1);
});

it('CU-NFE-01 · ouvinte que falha não desfaz a nota autorizada', function () {
    Event::listen(NFeAutorizada::class, function () {
        throw new RuntimeException('ouvinte quebrado');
    });
    $emissao = avisoEmissao('contingencia', 55);

    $volta = avisoServico(avisoRetorno(AVISO_CHAVE_55, '100'))->transmitirContingencia($emissao->business_id, $emissao->id);

    expect($volta->status)->toBe('autorizada');
    expect($emissao->refresh()->status)->toBe('autorizada');
});
