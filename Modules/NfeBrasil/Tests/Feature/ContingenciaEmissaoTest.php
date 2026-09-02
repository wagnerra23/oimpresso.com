<?php

declare(strict_types=1);

// @covers-us US-NFE-006 — EMITIR em contingência (tpEmis 4/9, XML persistido, SEFAZ não chamada).
// Contrato: memory/requisitos/NfeBrasil/adr/tech/0002-contingencia-epec-fsda-retentativa-ordenada.md
// §"Persistência: XML em contingência tem prioridade" — "XML é gravado ANTES de qualquer call SEFAZ".

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\NfeBrasil\Models\NfeBusinessConfig;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Services\CertificadoService;
use Modules\NfeBrasil\Services\NfeService;
use NFePHP\NFe\Tools;

uses(Tests\TestCase::class);

/**
 * US-NFE-006 fase 4 — a emissão em contingência.
 *
 * O QUE ESTE ARQUIVO DEFENDE (em ordem de gravidade)
 * --------------------------------------------------
 * 1. NÃO-REGRESSÃO: business fora de contingência emite EXATAMENTE como antes,
 *    com tpEmis=1. Este é o caso de 100% dos tenants hoje, e é o primeiro teste
 *    de propósito — se ele cair, o PR quebrou emissão fiscal de cliente real.
 * 2. Em contingência a SEFAZ NÃO é chamada (`shouldNotReceive`), e mesmo assim
 *    o XML é PERSISTIDO — que é a exigência literal da ADR.
 * 3. Nada de dado fiscal fabricado: sem chave_44, sem cStat, sem emitido_em.
 *    Quem emite esses campos é a SEFAZ, e ela não respondeu.
 *
 * Helpers com prefixo `cont` de propósito: NfeServiceTest declara funções GLOBAIS
 * com nomes parecidos (Pest não escopa por arquivo) e redeclarar dá fatal.
 */
function contBootstrapBiz(): object
{
    if (! Schema::hasTable('nfe_emissoes') || ! Schema::hasColumn('nfe_emissoes', 'tp_emis')) {
        test()->markTestSkipped('Schema da contingência ausente — rode as migrations do NfeBrasil.');
    }

    try {
        $business = \App\Business::first();
    } catch (\Throwable $e) {
        test()->markTestSkipped('Tabela business indisponível: ' . $e->getMessage());
    }

    if (! $business) {
        test()->markTestSkipped('Sem business no banco — rode o seeder antes.');
    }

    return $business;
}

/** Cert falso — não precisa de .pfx real porque o toolsFactory devolve o mock direto. */
function contCertSvc(): CertificadoService
{
    $mock = \Mockery::mock(CertificadoService::class);
    $mock->shouldReceive('carregarParaSefaz')->andReturn([
        'pfx_binary' => 'fake-binary',
        'senha' => 'senha-fake',
        'valido_ate' => now()->addYear(),
        'source' => 'test',
    ]);

    return $mock;
}

/**
 * Tools que EXPLODE se `sefazEnviaLote` for chamado.
 *
 * É a asserção central do arquivo: em contingência, chamar a SEFAZ é o defeito.
 * Um `expect(...)` depois do fato não pegaria isso — só o mock estrito pega.
 */
function contToolsQueProibeSefaz(): \Closure
{
    return function (string $configJson, array $certData): Tools {
        $mock = \Mockery::mock(Tools::class);
        $mock->shouldReceive('signNFe')->andReturnArg(0);
        $mock->shouldReceive('model')->andReturnNull();
        $mock->shouldNotReceive('sefazEnviaLote');

        return $mock;
    };
}

/** Tools cuja chamada à SEFAZ FALHA — simula SEFAZ fora do ar. */
function contToolsSefazForaDoAr(): \Closure
{
    return function (string $configJson, array $certData): Tools {
        $mock = \Mockery::mock(Tools::class);
        $mock->shouldReceive('signNFe')->andReturnArg(0);
        $mock->shouldReceive('model')->andReturnNull();
        $mock->shouldReceive('sefazEnviaLote')->andThrow(new \RuntimeException('Connection reset by peer (SOAP)'));

        return $mock;
    };
}

/** Tools que AUTORIZA — controle positivo (fluxo normal intocado). */
function contToolsAutoriza(): \Closure
{
    return function (string $configJson, array $certData): Tools {
        $xml = '<retEnviNFe xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00">'
            . '<tpAmb>2</tpAmb><cUF>35</cUF><cStat>104</cStat><xMotivo>Lote processado</xMotivo>'
            . '<protNFe versao="4.00"><infProt><cStat>100</cStat>'
            . '<xMotivo>Autorizado o uso da NF-e</xMotivo>'
            . '<chNFe>35210112345678000199550010000000011000000019</chNFe>'
            . '<nProt>135260000000001</nProt><dhRecbto>2026-05-06T00:00:00-03:00</dhRecbto>'
            . '</infProt></protNFe></retEnviNFe>';

        $mock = \Mockery::mock(Tools::class);
        $mock->shouldReceive('signNFe')->andReturnArg(0);
        $mock->shouldReceive('sefazEnviaLote')->andReturn($xml);
        $mock->shouldReceive('model')->andReturnNull();

        return $mock;
    };
}

/** Payload fiscal mínimo. */
function contDados(float $valor = 100.00): array
{
    return [
        'transaction_id' => null,
        'nat_op' => 'Venda de produto',
        'emit' => [
            'cnpj' => '12345678000199', 'razao_social' => 'EMPRESA TESTE LTDA',
            'ie' => '123456789', 'crt' => 1, 'uf' => 'SP', 'ambiente' => 2,
            'logradouro' => 'Rua Teste', 'numero_end' => '100', 'bairro' => 'Centro',
            'municipio' => 'Sao Paulo', 'cod_municipio' => '3550308', 'cep' => '01001000',
        ],
        'dest' => [
            'nome' => 'CLIENTE TESTE LTDA', 'cnpj' => '98765432000111',
            'ind_ie_dest' => '1', 'ie' => '987654321', 'logradouro' => 'Rua Dest',
            'numero' => '200', 'bairro' => 'Bairro', 'municipio' => 'Sao Paulo',
            'cod_municipio' => '3550308', 'uf' => 'SP', 'cep' => '01310100',
        ],
        'dets' => [[
            'cprod' => '001', 'xprod' => 'Produto Teste', 'ncm' => '49019900',
            'cfop' => '5102', 'ucm' => 'UN', 'qcom' => 1.0, 'vuncom' => $valor,
            'vprod' => $valor, 'utrib' => 'UN', 'qtrib' => 1.0, 'vuntrib' => $valor,
            'ind_tot' => 1, 'icms' => ['cst_csosn' => '102', 'orig' => 0],
            'pis' => ['cst' => '07', 'vbc' => 0, 'ppis' => 0, 'vpis' => 0],
            'cofins' => ['cst' => '07', 'vbc' => 0, 'pcofins' => 0, 'vcofins' => 0],
        ]],
        'total' => [
            'v_prod' => $valor, 'v_bc_icms' => 0, 'v_icms' => 0, 'v_pis' => 0,
            'v_cofins' => 0, 'v_nf' => $valor, 'v_desc' => 0, 'v_frete' => 0,
        ],
        'pag' => [['tpag' => '01', 'vpag' => $valor]],
        'valor_total' => $valor,
    ];
}

function contLigarContingencia(int $businessId, bool $ligada): void
{
    NfeBusinessConfig::withoutGlobalScopes()->updateOrCreate(
        ['business_id' => $businessId],
        [
            'regime' => 'simples',
            'tributacao_default' => ['cfop' => '5102'],
            'em_contingencia' => $ligada,
            'contingencia_motivo' => $ligada ? 'SEFAZ fora do ar — teste automatizado' : null,
            'contingencia_ativada_em' => $ligada ? now() : null,
        ],
    );
}

const CONT_VALOR = 100.00;

beforeEach(function () {
    if (! Schema::hasTable('nfe_emissoes') || ! Schema::hasColumn('nfe_emissoes', 'tp_emis')) {
        test()->markTestSkipped('Schema da contingência ausente — rode as migrations do NfeBrasil.');
    }
    Storage::fake('local');
});

afterEach(function () {
    try {
        NfeEmissao::withoutGlobalScopes()
            ->where('valor_total', CONT_VALOR)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->forceDelete();
        $biz = \App\Business::first();
        if ($biz) {
            contLigarContingencia((int) $biz->id, false);
        }
    } catch (\Throwable) {
    }
    \Mockery::close();
});

describe('US-NFE-006 · emitir em contingência (ADR TECH-0002)', function () {

    it('NÃO-REGRESSÃO · fora de contingência emite normal, com tpEmis=1', function () {
        $biz = contBootstrapBiz();
        contLigarContingencia((int) $biz->id, false);

        $emissao = (new NfeService(contCertSvc(), contToolsAutoriza()))
            ->emitir($biz->id, contDados(CONT_VALOR));

        // Se ISTO cair, o PR quebrou emissão fiscal de cliente real — é o caso de
        // 100% dos tenants hoje, porque contingência é opt-in e nasce desligada.
        expect($emissao->status)->toBe('autorizada');
        expect((int) $emissao->tp_emis)->toBe(NfeEmissao::TP_EMIS_NORMAL);
        expect($emissao->cstat)->toBe('100');
    });

    it('UC-CONT-30 · em contingência a SEFAZ NÃO é chamada e o XML É persistido', function () {
        $biz = contBootstrapBiz();
        contLigarContingencia((int) $biz->id, true);

        // contToolsQueProibeSefaz() usa shouldNotReceive: se o fluxo chamar a SEFAZ,
        // o Mockery derruba o teste. É a asserção central — um expect() posterior
        // não distinguiria "não chamou" de "chamou e ignorou a resposta".
        $emissao = (new NfeService(contCertSvc(), contToolsQueProibeSefaz()))
            ->emitir($biz->id, contDados(CONT_VALOR));

        expect($emissao->status)->toBe('contingencia');
        expect($emissao->xml_path)->not->toBeNull();
        Storage::assertExists($emissao->xml_path);
    });

    it('UC-CONT-32 · o XML sobrevive à SEFAZ FORA DO AR (exigência literal da ADR)', function () {
        $biz = contBootstrapBiz();
        contLigarContingencia((int) $biz->id, true);

        // Tools cuja chamada à SEFAZ explode. Em contingência nem chegamos lá — e é
        // exatamente esse o ponto: "XML gravado ANTES de qualquer call SEFAZ".
        $emissao = (new NfeService(contCertSvc(), contToolsSefazForaDoAr()))
            ->emitir($biz->id, contDados(CONT_VALOR));

        expect($emissao->status)->toBe('contingencia');
        Storage::assertExists($emissao->xml_path);
        expect(Storage::get($emissao->xml_path))->not->toBeEmpty();
    });

    it('UC-CONT-33 · contingência NÃO fabrica dado fiscal (sem chave, cStat ou protocolo)', function () {
        $biz = contBootstrapBiz();
        contLigarContingencia((int) $biz->id, true);

        $emissao = (new NfeService(contCertSvc(), contToolsQueProibeSefaz()))
            ->emitir($biz->id, contDados(CONT_VALOR));

        // Quem emite chave/cStat/protocolo é a SEFAZ, e ela não respondeu. Preencher
        // com placeholder seria inventar documento fiscal.
        expect($emissao->chave_44)->toBeNull();
        expect($emissao->cstat)->toBeNull();
        expect($emissao->emitido_em)->toBeNull();
    });

    it('UC-CONT-34 · a nota em contingência CONSOME número real da série', function () {
        $biz = contBootstrapBiz();
        contLigarContingencia((int) $biz->id, true);

        $emissao = (new NfeService(contCertSvc(), contToolsQueProibeSefaz()))
            ->emitir($biz->id, contDados(CONT_VALOR));

        // Não é detalhe: é a razão de a retransmissão (fase 5) ser obrigatória.
        // Número consumido sem protocolo é buraco na sequência pro auditor.
        expect((int) $emissao->numero)->toBeGreaterThan(0);
        expect($emissao->serie)->not->toBeNull();
    });

    it('UC-CONT-31a · NF-e modelo 55 em contingência grava tp_emis=4 (EPEC)', function () {
        $biz = contBootstrapBiz();
        contLigarContingencia((int) $biz->id, true);

        $emissao = (new NfeService(contCertSvc(), contToolsQueProibeSefaz()))
            ->emitir($biz->id, array_merge(contDados(CONT_VALOR), ['modelo' => '55']));

        // O modo NÃO é escolha livre: depende do modelo (Manual SEFAZ / ADR TECH-0002).
        expect((int) $emissao->tp_emis)->toBe(NfeEmissao::TP_EMIS_EPEC);
        expect($emissao->modelo)->toBe('55');
    });

    it('UC-CONT-31b · NFC-e modelo 65 em contingência grava tp_emis=9 (off-line)', function () {
        $biz = contBootstrapBiz();
        contLigarContingencia((int) $biz->id, true);

        $emissao = (new NfeService(contCertSvc(), contToolsQueProibeSefaz()))
            ->emitir($biz->id, array_merge(contDados(CONT_VALOR), ['modelo' => '65']));

        // Par com o UC-CONT-31a: se os dois modelos gravassem o MESMO tpEmis, um dos
        // dois estaria fiscalmente errado e nenhum teste isolado pegaria.
        expect((int) $emissao->tp_emis)->toBe(NfeEmissao::TP_EMIS_OFFLINE_NFCE);
        expect($emissao->modelo)->toBe('65');
    });
});
