<?php

declare(strict_types=1);

// @covers-us US-NFE-076 — a NF-e do fornecedor é lida com o que o de-para e o fiscal precisam, por NSU ou por upload.

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\NfeBrasil\Models\NfeDfeItem;
use Modules\NfeBrasil\Models\NfeDfeRecebido;
use Modules\NfeBrasil\Services\Entrada\EntradaXmlRecusadaException;
use Modules\NfeBrasil\Services\Entrada\ImportarXmlEntradaService;
use Modules\NfeBrasil\Services\Entrada\LeitorNfeEntrada;

uses(Tests\TestCase::class);

/**
 * Casos derivados da ADR `proposals/2026-10-06-entrada-xml-compra.md` (D2, D3, fase 2) e do
 * UC-TRB-18 do protótipo `fiscal-tributacao.casos.md`. Tenant 98, vizinho 99 (ADR 0358).
 * CNPJs e chaves são fictícios.
 */

const ENT_CNPJ_FORN = '11222333000181'; # pii-allowlist: CNPJ fictício de fixture
const ENT_CNPJ_EMPRESA = '44555666000172'; # pii-allowlist: CNPJ fictício de fixture

function entradaXml(string $chave, string $cnpjDest, bool $comProtocolo = true): string
{
    $forn = ENT_CNPJ_FORN;
    $nfe = <<<XML
    <NFe xmlns="http://www.portalfiscal.inf.br/nfe"><infNFe Id="NFe{$chave}" versao="4.00">
      <ide><dhEmi>2026-10-20T10:00:00-03:00</dhEmi></ide>
      <emit><CNPJ>{$forn}</CNPJ><xNome>Tintas Sul Ltda</xNome></emit>
      <dest><CNPJ>{$cnpjDest}</CNPJ></dest>
      <det nItem="1"><prod><cProd>TS-001</cProd><cEAN>7891234567895</cEAN><xProd>Tinta solvente 1L</xProd>
        <NCM>32089010</NCM><CFOP>5102</CFOP><uCom>UN</uCom><qCom>10.0000</qCom><vUnCom>25.5000</vUnCom><vProd>255.00</vProd></prod>
        <imposto><ICMS><ICMS00><orig>0</orig><CST>00</CST><vBC>255.00</vBC><pICMS>18.00</pICMS><vICMS>45.90</vICMS></ICMS00></ICMS></imposto></det>
      <det nItem="2"><prod><cProd>TS-777</cProd><cEAN>SEM GTIN</cEAN><xProd>Diluente 5L</xProd>
        <NCM>38140000</NCM><CFOP>5405</CFOP><uCom>GL</uCom><qCom>2.0000</qCom><vUnCom>60.0000</vUnCom><vProd>120.00</vProd></prod>
        <imposto><ICMS><ICMS60><orig>0</orig><CST>60</CST></ICMS60></ICMS></imposto></det>
      <total><ICMSTot><vNF>375.00</vNF></ICMSTot></total>
    </infNFe></NFe>
    XML;

    return $comProtocolo
        ? '<nfeProc xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00">' . $nfe
            . '<protNFe><infProt><nProt>135260000012345</nProt></infProt></protNFe></nfeProc>'
        : $nfe;
}


const ENT_CHAVE = '42261011222333000181550010000012341000012345';

it('UC-NFENT-01 · o leitor tira de cada item o código do fornecedor, EAN, unidade, CST com origem e ICMS destacado', function () {
    $nota = LeitorNfeEntrada::ler(entradaXml(ENT_CHAVE, ENT_CNPJ_EMPRESA));

    expect($nota['chave_44'])->toBe(ENT_CHAVE);
    expect($nota['cnpj_destinatario'])->toBe(ENT_CNPJ_EMPRESA);
    expect($nota['num_protocolo'])->toBe('135260000012345');
    expect($nota['valor_total'])->toBe(375.0);
    expect($nota['itens'])->toHaveCount(2);
    expect($nota['itens'][0])->toMatchArray([
        'codigo_fornecedor' => 'TS-001', 'ean' => '7891234567895', 'unidade' => 'UN',
        'cfop' => '5102', 'cst_csosn' => '000', 'quantidade' => 10.0, 'valor_total' => 255.0, 'icms_valor' => 45.9,
    ]);
    // "SEM GTIN" não é código de barras; CST 60 com origem 0 vira "060" (ST retida, D4).
    expect($nota['itens'][1])->toMatchArray(['ean' => null, 'cst_csosn' => '060', 'icms_valor' => null]);
});

it('UC-NFENT-01 · aceita a NF-e sem protocolo (arquivo NFe puro) e recusa o que não é NF-e', function () {
    expect(LeitorNfeEntrada::ler(entradaXml(ENT_CHAVE, ENT_CNPJ_EMPRESA, false))['num_protocolo'])->toBe('');
    expect(fn () => LeitorNfeEntrada::ler('<pedido><x/></pedido>'))->toThrow(InvalidArgumentException::class);
    expect(fn () => LeitorNfeEntrada::ler('não é xml'))->toThrow(InvalidArgumentException::class);
});

describe('upload (MySQL)', function () {
    beforeEach(function () {
        if (DB::getDriverName() !== 'mysql' || ! Schema::hasColumn('nfe_dfe_recebidos', 'origem')) {
            $this->markTestSkipped('precisa do schema MySQL com a fase 1 da entrada por XML');
        }
        Storage::fake(config('nfebrasil.dfes_recebidos_disk', 'local'));
        config(['nfebrasil.entrada_xml_enabled' => true]);
        $this->a = $this->seededTenant()->id;
        $this->b = $this->seededSupportClientTenant()->id;
        $this->antes = DB::table('business')->whereIn('id', [$this->a, $this->b])->get(['id', 'cnpj', 'tax_number_1'])->keyBy('id');
        DB::table('business')->where('id', $this->a)->update(['cnpj' => ENT_CNPJ_EMPRESA, 'tax_number_1' => null]);
        DB::table('business')->where('id', $this->b)->update(['cnpj' => '77888999000150', 'tax_number_1' => null]); # pii-allowlist: CNPJ fictício
        if (Schema::hasTable('nfe_certificados') && DB::table('nfe_certificados')->whereIn('business_id', [$this->a, $this->b])->where('ativo', true)->whereNotNull('cnpj_titular')->exists()) {
            $this->markTestSkipped('tenant de teste com certificado ativo neste banco');
        }
        $this->limpar = fn () => DB::table('nfe_dfe_recebidos')->whereIn('business_id', [$this->a, $this->b])->where('chave_44', ENT_CHAVE)->get(['id'])
            ->each(function ($r) { DB::table('nfe_dfe_itens')->where('dfe_recebido_id', $r->id)->delete(); DB::table('nfe_dfe_recebidos')->where('id', $r->id)->delete(); });
        ($this->limpar)();
    });

    afterEach(function () {
        if (! isset($this->antes)) {
            return;
        }
        ($this->limpar)();
        foreach ($this->antes as $id => $b) {
            DB::table('business')->where('id', $id)->update(['cnpj' => $b->cnpj, 'tax_number_1' => $b->tax_number_1]);
        }
    });

    it('UC-NFENT-02 · grava a nota de upload com origem, sem NSU, e os itens com as colunas novas', function () {
        $r = app(ImportarXmlEntradaService::class)->importar($this->a, entradaXml(ENT_CHAVE, ENT_CNPJ_EMPRESA));

        expect($r->origem)->toBe(NfeDfeRecebido::ORIGEM_UPLOAD);
        expect($r->nsu)->toBeNull();
        expect((float) $r->valor_total)->toBe(375.0);
        $itens = NfeDfeItem::where('dfe_recebido_id', $r->id)->orderBy('id')->get();
        expect($itens->pluck('codigo_fornecedor')->all())->toBe(['TS-001', 'TS-777']);
        expect($itens->pluck('cst_csosn')->all())->toBe(['000', '060']);
        expect($itens->every(fn ($i) => (int) $i->business_id === $this->a))->toBeTrue();
        Storage::disk(config('nfebrasil.dfes_recebidos_disk', 'local'))->assertExists($r->xml_path);
    });

    it('UC-NFENT-03 · nota emitida para outro CNPJ é recusada e nada é gravado (Tier 0)', function () {
        $svc = app(ImportarXmlEntradaService::class);
        expect(fn () => $svc->importar($this->b, entradaXml(ENT_CHAVE, ENT_CNPJ_EMPRESA)))
            ->toThrow(EntradaXmlRecusadaException::class, 'emitida para outro CNPJ');
        expect(DB::table('nfe_dfe_recebidos')->where('chave_44', ENT_CHAVE)->whereIn('business_id', [$this->a, $this->b])->count())->toBe(0);
    });

    it('UC-NFENT-04 · a mesma nota não entra duas vezes', function () {
        $svc = app(ImportarXmlEntradaService::class);
        $svc->importar($this->a, entradaXml(ENT_CHAVE, ENT_CNPJ_EMPRESA));
        expect(fn () => $svc->importar($this->a, entradaXml(ENT_CHAVE, ENT_CNPJ_EMPRESA)))
            ->toThrow(EntradaXmlRecusadaException::class, 'já foi importada');
        expect(DB::table('nfe_dfe_recebidos')->where('business_id', $this->a)->where('chave_44', ENT_CHAVE)->count())->toBe(1);
    });

    it('UC-NFENT-05 · com a flag desligada o upload é recusado', function () {
        config(['nfebrasil.entrada_xml_enabled' => false]);
        expect(fn () => app(ImportarXmlEntradaService::class)->importar($this->a, entradaXml(ENT_CHAVE, ENT_CNPJ_EMPRESA)))
            ->toThrow(EntradaXmlRecusadaException::class, 'desligada');
        expect(DB::table('nfe_dfe_recebidos')->where('business_id', $this->a)->where('chave_44', ENT_CHAVE)->count())->toBe(0);
    });
});
