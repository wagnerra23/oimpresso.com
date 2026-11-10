<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Services\CertificadoService;
use Modules\NfeBrasil\Services\DanfeService;
use Modules\NfeBrasil\Services\NfeService;
use NFePHP\NFe\Tools;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * CU-NFE-04 (o número fiscal nunca é reaproveitado nem apagado) e CU-NFE-01 (emitir sem emitir
 * duas vezes) · lote 0.3.
 *
 * Quando a SEFAZ responde duplicidade ou o envio cai no meio, a nota pode já estar autorizada lá.
 * Antes de dar o número por livre, o sistema pergunta pela chave. Tenant 98 (ADR 0358).
 */
const DUP_CHAVE = '35261112345678000199550010000077771000077771';

beforeEach(function () {
    if (! Schema::hasTable('nfe_emissoes') || ! Schema::hasColumn('nfe_emissoes', 'retry_count')) {
        $this->markTestSkipped('Schema do NfeBrasil ausente.');
    }
    Storage::fake('nfe_fiscal');
    Storage::fake('local');
    app()->instance(DanfeService::class, Mockery::mock(DanfeService::class)->shouldReceive('salvar')->andReturn(null)->getMock());
});

function dupXmlAssinado(): string
{
    return '<NFe xmlns="http://www.portalfiscal.inf.br/nfe"><infNFe Id="NFe' . DUP_CHAVE . '" versao="4.00"><ide><nNF>7777</nNF></ide></infNFe>'
        . '<Signature xmlns="http://www.w3.org/2000/09/xmldsig#"><SignedInfo><Reference><DigestValue>abc=</DigestValue></Reference></SignedInfo></Signature></NFe>';
}

function dupRetornoEnvio(string $cstat): string
{
    return '<retEnviNFe xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00"><tpAmb>2</tpAmb><verAplic>T</verAplic>'
        . '<cStat>104</cStat><xMotivo>Lote processado</xMotivo><cUF>35</cUF><dhRecbto>2026-11-10T10:00:00-03:00</dhRecbto>'
        . '<protNFe versao="4.00"><infProt><tpAmb>2</tpAmb><verAplic>T</verAplic><chNFe>' . DUP_CHAVE . '</chNFe>'
        . '<dhRecbto>2026-11-10T10:00:00-03:00</dhRecbto><cStat>' . $cstat . '</cStat><xMotivo>Rejeicao: Duplicidade de NF-e</xMotivo>'
        . '</infProt></protNFe></retEnviNFe>';
}

function dupConsulta(string $cstat): string
{
    $prot = in_array($cstat, ['100', '150', '101'], true)
        ? '<protNFe versao="4.00"><infProt><tpAmb>2</tpAmb><verAplic>T</verAplic><chNFe>' . DUP_CHAVE . '</chNFe>'
            . '<dhRecbto>2026-11-10T09:00:00-03:00</dhRecbto><nProt>135000000007777</nProt><digVal>abc=</digVal>'
            . '<cStat>100</cStat><xMotivo>Autorizado</xMotivo></infProt></protNFe>'
        : '';

    return '<retConsSitNFe xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00"><tpAmb>2</tpAmb><verAplic>T</verAplic>'
        . '<cStat>' . $cstat . '</cStat><xMotivo>Consulta</xMotivo><cUF>35</cUF><dhRecbto>2026-11-10T10:00:00-03:00</dhRecbto>'
        . '<chNFe>' . DUP_CHAVE . '</chNFe>' . $prot . '</retConsSitNFe>';
}

/** @param string|Throwable|null $consulta */
function dupServico(?string $envio, $consulta): NfeService
{
    $cert = Mockery::mock(CertificadoService::class);
    $cert->shouldReceive('carregarParaSefaz')->andReturn([
        'pfx_binary' => 'x', 'senha' => 'x', 'valido_ate' => now()->addYear(), 'source' => 'teste',
    ]);

    $tools = Mockery::mock(Tools::class);
    if ($envio !== null) {
        $tools->shouldReceive('sefazEnviaLote')->andReturn($envio);
    }
    $expect = $tools->shouldReceive('sefazConsultaChave');
    $consulta instanceof Throwable ? $expect->andThrow($consulta) : $expect->andReturn((string) $consulta);

    return new NfeService($cert, fn () => $tools);
}

function dupEmissao(string $status, ?int $tx = 998877001): NfeEmissao
{
    $biz = (int) test()->seededTenant()->id;
    Storage::disk('nfe_fiscal')->put("nfe-brasil/{$biz}/notas/1-7777.xml", dupXmlAssinado());

    return NfeEmissao::create([
        'business_id' => $biz, 'transaction_id' => $tx, 'modelo' => 55, 'serie' => '1', 'numero' => 7777,
        'status' => $status, 'valor_total' => 10, 'tp_emis' => 1,
        'xml_path' => "nfe-brasil/{$biz}/notas/1-7777.xml",
    ]);
}

function dupReemitir(NfeService $servico, NfeEmissao $existente): ?NfeEmissao
{
    $metodo = new ReflectionMethod($servico, 'emitirInterno');
    try {
        return $metodo->invoke($servico, (int) $existente->business_id, ['valor_total' => 10, 'modelo' => '55'], '55', (int) $existente->transaction_id);
    } catch (Throwable) {
        return null; // sem dados de nota no teste: o fluxo para ao montar o XML novo
    }
}

it('CU-NFE-01 · duplicidade (204) com a nota autorizada na SEFAZ grava autorizada, com protocolo', function () {
    $emissao = dupEmissao('contingencia', null);

    $volta = dupServico(dupRetornoEnvio('204'), dupConsulta('100'))->transmitirContingencia($emissao->business_id, $emissao->id);

    expect($volta->status)->toBe('autorizada');
    expect($volta->chave_44)->toBe(DUP_CHAVE);
    expect($volta->numero)->toBe(7777);
    expect(Storage::disk('nfe_fiscal')->get($volta->xml_path))->toContain('<protNFe');
});

it('CU-NFE-01 · duplicidade com chave diferente (539): a nota desta chave não consta, segue rejeitada', function () {
    $emissao = dupEmissao('contingencia', null);

    $volta = dupServico(dupRetornoEnvio('539'), dupConsulta('217'))->transmitirContingencia($emissao->business_id, $emissao->id);

    expect($volta->status)->toBe('rejeitada');
    expect($volta->cstat)->toBe('539');
});

it('CU-NFE-01 · duplicidade sem resposta da consulta fica em erro_envio, para a retentativa perguntar de novo', function () {
    $emissao = dupEmissao('contingencia', null);

    $volta = dupServico(dupRetornoEnvio('204'), new RuntimeException('timeout'))->transmitirContingencia($emissao->business_id, $emissao->id);

    expect($volta->status)->toBe('erro_envio');
});

it('CU-NFE-04 · retentativa de erro_envio autorizado na SEFAZ não troca o número', function () {
    $emissao = dupEmissao('erro_envio');

    $volta = dupReemitir(dupServico(null, dupConsulta('100')), $emissao);

    expect($volta?->id)->toBe($emissao->id);
    expect($emissao->refresh()->status)->toBe('autorizada');
    expect((int) $emissao->transaction_id)->toBe(998877001);
    expect(NfeEmissao::where('business_id', $emissao->business_id)->where('numero', '!=', 7777)->where('valor_total', 10)->where('created_at', '>=', now()->subMinute())->count())->toBe(0);
});

it('CU-NFE-04 · sem resposta da SEFAZ a retentativa para e não libera o número', function () {
    $emissao = dupEmissao('erro_envio');

    dupReemitir(dupServico(null, new RuntimeException('timeout')), $emissao);

    expect($emissao->refresh()->status)->toBe('erro_envio');
    expect((int) $emissao->transaction_id)->toBe(998877001);
});

it('CU-NFE-04 · nota que a SEFAZ não conhece (217) segue o caminho antigo: inutilizada localmente', function () {
    $emissao = dupEmissao('erro_envio');

    dupReemitir(dupServico(null, dupConsulta('217')), $emissao);

    expect($emissao->refresh()->status)->toBe('inutilizada');
    expect($emissao->transaction_id)->toBeNull();
});

it('CU-NFE-04 · consulta que acha a nota cancelada grava cancelada e recusa reemitir', function () {
    $emissao = dupEmissao('erro_envio');

    dupReemitir(dupServico(null, dupConsulta('101')), $emissao);

    expect($emissao->refresh()->status)->toBe('cancelada');
});

it('CU-NFE-04 · denegada continua denegada na retentativa: o número não é liberado', function () {
    $emissao = dupEmissao('denegada');

    dupReemitir(dupServico(null, dupConsulta('217')), $emissao);

    expect($emissao->refresh()->status)->toBe('denegada');
    expect($emissao->numero)->toBe(7777);
});

it('CU-NFE-04 · nota em contingência não é tocada pela retentativa', function () {
    $emissao = dupEmissao('contingencia');

    $volta = dupReemitir(dupServico(null, dupConsulta('217')), $emissao);

    expect($volta?->id)->toBe($emissao->id);
    expect($emissao->refresh()->status)->toBe('contingencia');
});
