<?php

declare(strict_types=1);

// @covers-us US-NFE-008 — manifestar NF-e recebida: ciência/confirmação/desconhecimento, justificativa ≥15 chars, idempotência (ManifestacaoService).

use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Models\NfeDfeEvento;
use Modules\NfeBrasil\Models\NfeDfeRecebido;
use Modules\NfeBrasil\Services\CertificadoService;
use Modules\NfeBrasil\Services\Manifestacao\ManifestacaoService;
use NFePHP\NFe\Tools;

uses(Tests\TestCase::class);

/**
 * US-NFE-050 — ManifestacaoService.
 *
 * Não usa RefreshDatabase — depende de DB dev com `nfe_dfe_*` migradas + business.
 * Skipa se schema ausente.
 */

function manifestacaoBootstrap(): array
{
    if (! Schema::hasTable('nfe_dfe_recebidos') || ! Schema::hasTable('nfe_dfe_eventos')) {
        test()->markTestSkipped('Tabelas nfe_dfe_* ausentes — rode migrations 2026_05_09_100000+ primeiro.');
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

function fakeManifestaXmlAutorizado(string $tpEvento = '210210', string $cStat = '135'): string
{
    return <<<XML
    <retEnvEvento versao="1.00" xmlns="http://www.portalfiscal.inf.br/nfe">
      <idLote>1</idLote><tpAmb>2</tpAmb><cOrgao>91</cOrgao>
      <cStat>128</cStat><xMotivo>Lote processado</xMotivo>
      <retEvento versao="1.00">
        <infEvento>
          <tpAmb>2</tpAmb><cOrgao>91</cOrgao>
          <cStat>{$cStat}</cStat>
          <xMotivo>Evento registrado e vinculado a NF-e</xMotivo>
          <chNFe>35210112345678000199550010000000011000000019</chNFe>
          <tpEvento>{$tpEvento}</tpEvento>
          <xEvento>Manifestacao</xEvento>
          <nSeqEvento>1</nSeqEvento>
          <dhRegEvento>2026-05-09T12:00:00-03:00</dhRegEvento>
          <nProt>135260000000001</nProt>
        </infEvento>
      </retEvento>
    </retEnvEvento>
    XML;
}

function buildManifestacaoService(string $responseXml): ManifestacaoService
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
        $mock->shouldReceive('sefazManifesta')->andReturn($responseXml);
        return $mock;
    };

    return new ManifestacaoService($certServiceMock, $factory);
}

function criarDfeRecebido(int $businessId, string $chave = '35210112345678000199550010000000011000000019'): NfeDfeRecebido
{
    return NfeDfeRecebido::create([
        'business_id'         => $businessId,
        'chave_44'            => $chave,
        'nsu'                 => 12345,
        'cnpj_emitente'       => '12345678000199',
        'nome_emitente'       => 'FORNECEDOR TESTE LTDA',
        'valor_total'         => 1500.00,
        'data_emissao'        => now()->subDay(),
        'status_manifestacao' => NfeDfeRecebido::STATUS_PENDENTE,
        'prazo_confirmacao_em' => now()->addDays(180)->toDateString(),
    ]);
}

afterEach(function () {
    \Mockery::close();
});

it('aplica ciência (210210) e marca status ciencia', function () {
    [$business] = manifestacaoBootstrap();
    $dfe = criarDfeRecebido((int) $business->id);

    $service = buildManifestacaoService(fakeManifestaXmlAutorizado('210210', '135'));
    $evento = $service->cienciar($dfe);

    expect($evento->tipo)->toBe(NfeDfeEvento::TIPO_CIENCIA);
    expect($evento->status)->toBe('autorizado');
    expect($evento->cstat_evento)->toBe('135');
    expect($dfe->fresh()->status_manifestacao)->toBe(NfeDfeRecebido::STATUS_CIENCIA);

    $dfe->fresh()->eventos()->delete();
    $dfe->fresh()->delete();
});

it('aplica confirmação (210200) e marca status confirmada', function () {
    [$business] = manifestacaoBootstrap();
    $dfe = criarDfeRecebido((int) $business->id, '35210112345678000199550010000000022000000020');

    $service = buildManifestacaoService(fakeManifestaXmlAutorizado('210200', '135'));
    $evento = $service->confirmar($dfe);

    expect($evento->tipo)->toBe(NfeDfeEvento::TIPO_CONFIRMACAO);
    expect($evento->status)->toBe('autorizado');
    expect($dfe->fresh()->status_manifestacao)->toBe(NfeDfeRecebido::STATUS_CONFIRMADA);
    expect($dfe->fresh()->manifestado_em)->not->toBeNull();

    $dfe->fresh()->eventos()->delete();
    $dfe->fresh()->delete();
});

it('desconhecimento exige justificativa com pelo menos 15 chars', function () {
    [$business] = manifestacaoBootstrap();
    $dfe = criarDfeRecebido((int) $business->id, '35210112345678000199550010000000033000000021');

    $service = buildManifestacaoService(fakeManifestaXmlAutorizado('210220', '135'));

    expect(fn () => $service->desconhecer($dfe, 'curto'))
        ->toThrow(InvalidArgumentException::class, 'Justificativa exige no mínimo');

    $dfe->delete();
});

it('é idempotente — 2ª chamada do mesmo tipo retorna evento existente', function () {
    [$business] = manifestacaoBootstrap();
    $dfe = criarDfeRecebido((int) $business->id, '35210112345678000199550010000000044000000022');

    $service = buildManifestacaoService(fakeManifestaXmlAutorizado('210210', '135'));
    $evento1 = $service->cienciar($dfe);
    $evento2 = $service->cienciar($dfe);

    expect($evento2->id)->toBe($evento1->id);
    expect(NfeDfeEvento::where('dfe_recebido_id', $dfe->id)->where('tipo', '210210')->count())
        ->toBe(1);

    $dfe->fresh()->eventos()->delete();
    $dfe->fresh()->delete();
});
