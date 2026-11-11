<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Services\CertificadoService;
use Modules\NfeBrasil\Services\NfeCartaCorrecaoService;
use NFePHP\NFe\Tools;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Lote 1.4 da auditoria do NfeBrasil (N4-14) · carta de correção.
 *
 * A CC-e montava a configuração da SEFAZ por conta própria: ambiente com `env()` fora do config,
 * UF de `business.state` (coluna que não existe, então sempre SP) e CNPJ de `business.cnpj`
 * aceitando o padrão só-zeros. Agora lê das mesmas fontes da emissão, da manifestação e do DF-e
 * (`AmbienteDoEmitente`, `CnpjDoEmitente`, `ResolveUfEmitente`). Tenant 98 (ADR 0358); o business
 * e a location são alterados dentro da transação do teste.
 */
const CCE_CHAVE = '43261112345678000199550010000099991000099991';

beforeEach(function () {
    if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('nfe_emissoes') || ! Schema::hasTable('nfe_eventos')) {
        $this->markTestSkipped('precisa do schema MySQL do NfeBrasil');
    }
    $this->biz = (int) $this->seededTenant()->id;
    if (DB::table('nfe_certificados')->where('business_id', $this->biz)->where('ativo', true)->whereNotNull('cnpj_titular')->exists()) {
        $this->markTestSkipped('o tenant 98 tem certificado ativo com titular neste banco');
    }
    if (! DB::table('business_locations')->where('business_id', $this->biz)->exists()) {
        $this->markTestSkipped('o tenant 98 não tem location neste banco');
    }
    DB::table('business')->where('id', $this->biz)->update([
        'ambiente'     => 1,
        'cnpj'         => '00.000.000/0000-00', # pii-allowlist: valor padrão da coluna business.cnpj
        'tax_number_1' => '11.222.333/0001-81', # pii-allowlist: CNPJ fictício de fixture
    ]);
    $primeira = DB::table('business_locations')->where('business_id', $this->biz)->orderBy('id')->value('id');
    DB::table('business_locations')->where('id', $primeira)->update(['state' => 'RS']);
});

function cceRetorno(): string
{
    return '<retEnvEvento xmlns="http://www.portalfiscal.inf.br/nfe" versao="1.00"><idLote>1</idLote><tpAmb>1</tpAmb><verAplic>T</verAplic>'
        . '<cOrgao>43</cOrgao><cStat>128</cStat><xMotivo>Lote de evento processado</xMotivo><retEvento versao="1.00"><infEvento>'
        . '<tpAmb>1</tpAmb><verAplic>T</verAplic><cOrgao>43</cOrgao><cStat>135</cStat><xMotivo>Evento registrado</xMotivo>'
        . '<chNFe>' . CCE_CHAVE . '</chNFe><tpEvento>110110</tpEvento><nSeqEvento>1</nSeqEvento>'
        . '<dhRegEvento>2026-11-10T10:00:00-03:00</dhRegEvento><nProt>143000000009999</nProt></infEvento></retEvento></retEnvEvento>';
}

it('CU-NFE · a CC-e fala com a SEFAZ no ambiente, UF e CNPJ da empresa', function () {
    $emissao = NfeEmissao::create([
        'business_id' => $this->biz, 'modelo' => 55, 'serie' => '1', 'numero' => 9999,
        'status' => 'autorizada', 'chave_44' => CCE_CHAVE, 'valor_total' => 10, 'tp_emis' => 1,
        'emitido_em' => now(),
    ]);

    $cert = Mockery::mock(CertificadoService::class);
    $cert->shouldReceive('carregarParaSefaz')->andReturn(['pfx_binary' => 'x', 'senha' => 'x', 'valido_ate' => null, 'source' => 'teste']);

    $config = null;
    $servico = new NfeCartaCorrecaoService($cert, function (string $configJson) use (&$config): Tools {
        $config = json_decode($configJson, true);
        $tools = Mockery::mock(Tools::class);
        $tools->shouldReceive('sefazCCe')->andReturn(cceRetorno());

        return $tools;
    });

    $evento = $servico->aplicar($this->biz, $emissao->id, 'Correção do endereço de entrega do destinatário', 1);

    expect($evento->status)->toBe('autorizado');
    expect($config['tpAmb'])->toBe(1);
    expect($config['siglaUF'])->toBe('RS');
    expect($config['cnpj'])->toBe('11222333000181');
});
