<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\NfeBrasil\Models\NfeDfeNsuState;
use Modules\NfeBrasil\Models\NfeDfeRecebido;
use Modules\NfeBrasil\Services\CertificadoService;
use Modules\NfeBrasil\Services\Manifestacao\DistribuicaoDfeService;
use NFePHP\NFe\Tools;

uses(Tests\TestCase::class);

/**
 * US-NFE-051 — DistribuicaoDfeService.
 */

function distDfeBootstrap(): array
{
    if (! Schema::hasTable('nfe_dfe_recebidos') || ! Schema::hasTable('nfe_dfe_nsu_state')) {
        test()->markTestSkipped('Tabelas nfe_dfe_* ausentes.');
    }

    try {
        $business = \App\Business::first();
    } catch (\Throwable) {
        test()->markTestSkipped('Tabela business indisponível.');
    }

    if (! $business) {
        test()->markTestSkipped('Sem business no banco.');
    }

    return [$business];
}

function fakeDistDFeXmlVazio(int $ultNSU = 100, int $maxNSU = 100): string
{
    return <<<XML
    <retDistDFeInt versao="1.01" xmlns="http://www.portalfiscal.inf.br/nfe">
      <tpAmb>2</tpAmb><verAplic>AN-1.0</verAplic>
      <cStat>137</cStat>
      <xMotivo>Nenhum documento localizado</xMotivo>
      <dhResp>2026-05-09T12:00:00-03:00</dhResp>
      <ultNSU>{$ultNSU}</ultNSU>
      <maxNSU>{$maxNSU}</maxNSU>
    </retDistDFeInt>
    XML;
}

function fakeDistDFeXmlComResNFe(int $ultNSU, int $maxNSU, string $resNFeXml): string
{
    $base64 = base64_encode(gzencode($resNFeXml));
    return <<<XML
    <retDistDFeInt versao="1.01" xmlns="http://www.portalfiscal.inf.br/nfe">
      <tpAmb>2</tpAmb><verAplic>AN-1.0</verAplic>
      <cStat>138</cStat>
      <xMotivo>Documento(s) localizado(s)</xMotivo>
      <ultNSU>{$ultNSU}</ultNSU>
      <maxNSU>{$maxNSU}</maxNSU>
      <loteDistDFeInt>
        <docZip NSU="{$ultNSU}" schema="resNFe_v1.01.xsd">{$base64}</docZip>
      </loteDistDFeInt>
    </retDistDFeInt>
    XML;
}

function fakeResNFeXml(string $chave = '35210112345678000199550010000000011000000019'): string
{
    return <<<XML
    <resNFe xmlns="http://www.portalfiscal.inf.br/nfe" versao="1.01">
      <chNFe>{$chave}</chNFe>
      <CNPJ>12345678000199</CNPJ>
      <xNome>FORNECEDOR TESTE LTDA</xNome>
      <IE>123456789</IE>
      <dhEmi>2026-05-08T10:00:00-03:00</dhEmi>
      <tpNF>1</tpNF>
      <vNF>2500.50</vNF>
      <digVal>abc123</digVal>
      <dhRecbto>2026-05-08T10:01:00-03:00</dhRecbto>
      <nProt>135260000000099</nProt>
      <cSitNFe>1</cSitNFe>
    </resNFe>
    XML;
}

function buildDistDfeService(string $responseXml): DistribuicaoDfeService
{
    $certServiceMock = \Mockery::mock(CertificadoService::class);
    $certServiceMock->shouldReceive('carregarParaSefaz')->andReturn([
        'pfx_binary' => 'fake-binary',
        'senha'      => 'fake-pass',
        'valido_ate' => null,
        'source'     => 'test',
    ]);

    $factory = function (string $configJson, array $certData) use ($responseXml): Tools {
        $mock = \Mockery::mock(Tools::class);
        $mock->shouldReceive('model')->andReturnNull();
        $mock->shouldReceive('setEnvironment')->andReturnNull();
        $mock->shouldReceive('sefazDistDFe')->andReturn($responseXml);
        return $mock;
    };

    return new DistribuicaoDfeService($certServiceMock, $factory);
}

afterEach(function () {
    \Mockery::close();
});

it('lote vazio (cStat 137) processa 0 documentos', function () {
    [$business] = distDfeBootstrap();

    NfeDfeNsuState::where('business_id', $business->id)->delete();

    $service = buildDistDfeService(fakeDistDFeXmlVazio(100, 100));
    $resultado = $service->puxarLote((int) $business->id);

    expect($resultado['processados'])->toBe(0);

    NfeDfeNsuState::where('business_id', $business->id)->delete();
});

it('processa lote com 1 resNFe e persiste em nfe_dfe_recebidos', function () {
    [$business] = distDfeBootstrap();
    $chave = '35210112345678000199550010000000011000000111';

    NfeDfeNsuState::where('business_id', $business->id)->delete();
    NfeDfeRecebido::where('business_id', $business->id)->where('chave_44', $chave)->delete();

    Storage::fake('local');

    $resNFe = fakeResNFeXml($chave);
    $loteXml = fakeDistDFeXmlComResNFe(101, 101, $resNFe);

    $service = buildDistDfeService($loteXml);
    $resultado = $service->puxarLote((int) $business->id, loopLimit: 1);

    expect($resultado['processados'])->toBe(1);

    $dfe = NfeDfeRecebido::where('business_id', $business->id)
        ->where('chave_44', $chave)->first();
    expect($dfe)->not->toBeNull();
    expect($dfe->cnpj_emitente)->toBe('12345678000199');
    expect((float) $dfe->valor_total)->toBe(2500.50);

    $dfe->delete();
    NfeDfeNsuState::where('business_id', $business->id)->delete();
});

it('respeita throttle de 5min — 2ª chamada retorna skipped_throttle', function () {
    [$business] = distDfeBootstrap();

    NfeDfeNsuState::updateOrCreate(
        ['business_id' => (int) $business->id],
        ['last_nsu' => 50, 'ultimo_check_em' => now()->subMinutes(2)],
    );

    $service = buildDistDfeService(fakeDistDFeXmlVazio(50, 50));
    $resultado = $service->puxarLote((int) $business->id);

    expect($resultado['skipped_throttle'] ?? false)->toBeTrue();

    NfeDfeNsuState::where('business_id', $business->id)->delete();
});

it('idempotência — 2ª chamada com mesma chave não duplica', function () {
    [$business] = distDfeBootstrap();
    $chave = '35210112345678000199550010000000011000000222';

    NfeDfeNsuState::where('business_id', $business->id)->delete();
    NfeDfeRecebido::where('business_id', $business->id)->where('chave_44', $chave)->delete();

    Storage::fake('local');

    $resNFe = fakeResNFeXml($chave);
    $loteXml = fakeDistDFeXmlComResNFe(202, 202, $resNFe);

    $service1 = buildDistDfeService($loteXml);
    $r1 = $service1->puxarLote((int) $business->id, loopLimit: 1);

    NfeDfeNsuState::where('business_id', $business->id)->update(['ultimo_check_em' => now()->subMinutes(10)]);

    $service2 = buildDistDfeService($loteXml);
    $r2 = $service2->puxarLote((int) $business->id, loopLimit: 1);

    expect($r1['processados'])->toBe(1);
    expect($r2['processados'])->toBe(0); // idempotência

    NfeDfeRecebido::where('business_id', $business->id)->where('chave_44', $chave)->delete();
    NfeDfeNsuState::where('business_id', $business->id)->delete();
});
