<?php

declare(strict_types=1);

// @covers-us US-NFE-071 — contingência: SVC-AN/SVC-RS (tpEmis 6/7) e evento EPEC 110140 (UC-FLX-32).
// Contrato: memory/requisitos/NfeBrasil/SPEC.md US-NFE-071 · resources/js/Pages/Fiscal/Nfe.casos.md
// UC-FNFE-19, 20, 30, 31 · ADR TECH-0002 (tabela dos modos oficiais, tpEmis 4/6/7/9).

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\NfeBrasil\Exceptions\ContingenciaException;
use Modules\NfeBrasil\Models\NfeBusinessConfig;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Models\NfeEvento;
use Modules\NfeBrasil\Services\CertificadoService;
use Modules\NfeBrasil\Services\ContingenciaService;
use Modules\NfeBrasil\Services\ModoContingencia;
use Modules\NfeBrasil\Services\NfeService;
use NFePHP\NFe\Tools;

uses(Tests\TestCase::class);

/**
 * Tenants: A = canônico de teste (98, ADR 0358) · B = 2 (segundo tenant do seed do CI).
 * Helpers com prefixo `modo` — Pest declara funções globais e os vizinhos usam `cont`/`agnd`/`retr`.
 * Série 995 para não consumir número da série real do tenant.
 */
const MODO_SERIE = '995';
const MODO_VALOR = 71.71;
const MODO_BIZ_B = 2;

const MODO_RET_AUTORIZADA = '<retEnviNFe xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00"><tpAmb>2</tpAmb>'
    . '<cUF>35</cUF><cStat>104</cStat><xMotivo>Lote processado</xMotivo><protNFe versao="4.00"><infProt>'
    . '<cStat>100</cStat><xMotivo>Autorizado o uso da NF-e</xMotivo>'
    . '<chNFe>35210112345678000199550010000000011000000019</chNFe><nProt>135260000000001</nProt>'
    . '<dhRecbto>2026-10-21T00:00:00-03:00</dhRecbto></infProt></protNFe></retEnviNFe>';

function modoRetEpec(string $cstat): string
{
    return '<retEnvEvento xmlns="http://www.portalfiscal.inf.br/nfe" versao="1.00"><idLote>1</idLote>'
        . '<tpAmb>2</tpAmb><verAplic>AN_1.0</verAplic><cOrgao>91</cOrgao><cStat>128</cStat>'
        . '<xMotivo>Lote de Evento Processado</xMotivo><retEvento versao="1.00"><infEvento><tpAmb>2</tpAmb>'
        . '<verAplic>AN_1.0</verAplic><cOrgao>91</cOrgao><cStat>' . $cstat . '</cStat>'
        . '<xMotivo>Evento registrado, mas nao vinculado a NF-e</xMotivo>'
        . '<chNFe>35210112345678000199550010000000011000000019</chNFe><tpEvento>110140</tpEvento>'
        . '<nSeqEvento>1</nSeqEvento><dhRegEvento>2026-10-21T10:00:00-03:00</dhRegEvento>'
        . '<nProt>891260000000001</nProt></infEvento></retEvento></retEnvEvento>';
}

function modoTenants(): array
{
    if (! Schema::hasColumn('nfe_business_configs', 'contingencia_modo')) {
        test()->markTestSkipped('Coluna contingencia_modo ausente — rode as migrations do NfeBrasil.');
    }
    $a = (int) test()->seededTenant()->id;
    if ($a === MODO_BIZ_B || ! DB::table('business')->where('id', MODO_BIZ_B)->exists()) {
        test()->markTestSkipped('Precisa de dois tenants distintos (98 e 2) — rode pest-mysql-setup.');
    }

    return [$a, MODO_BIZ_B];
}

function modoContingencia(int $businessId, bool $ligada, ?string $modo = null): void
{
    NfeBusinessConfig::withoutGlobalScopes()->updateOrCreate(
        ['business_id' => $businessId],
        [
            'regime' => 'simples',
            'tributacao_default' => ['cfop' => '5102'],
            'em_contingencia' => $ligada,
            'contingencia_motivo' => $ligada ? 'SEFAZ-SP fora do ar desde 14h' : null,
            'contingencia_ativada_em' => $ligada ? now()->setTime(14, 5, 0) : null,
            'contingencia_modo' => $ligada ? $modo : null,
        ],
    );
}

/**
 * Emite com Tools falso. `$sondas` recebe o que a lib recebeu: o XML assinado, o modo armado
 * em `Tools::$contingency` no envio do lote e no EPEC, e quantas vezes cada um foi chamado.
 */
function modoEmitir(int $businessId, string $modelo, array &$sondas, ?\Closure $epec = null): NfeEmissao
{
    $sondas = ['xml' => null, 'lote' => 0, 'tipo_lote' => null, 'epec' => 0, 'tipo_epec' => null];

    $cert = \Mockery::mock(CertificadoService::class);
    $cert->shouldReceive('carregarParaSefaz')->andReturn([
        'pfx_binary' => 'fake', 'senha' => 'fake', 'valido_ate' => now()->addYear(), 'source' => 'test',
    ]);

    $tools = function () use (&$sondas, $epec): Tools {
        $mock = \Mockery::mock(Tools::class);
        $mock->shouldReceive('model')->andReturnNull();
        $mock->shouldReceive('signNFe')->andReturnUsing(function (string $x) use (&$sondas) {
            $sondas['xml'] = $x;

            return $x;
        });
        $mock->shouldReceive('sefazEnviaLote')->andReturnUsing(function () use ($mock, &$sondas) {
            $sondas['lote']++;
            $sondas['tipo_lote'] = $mock->contingency?->type;

            return MODO_RET_AUTORIZADA;
        });
        $mock->shouldReceive('sefazEPEC')->andReturnUsing(function () use ($mock, &$sondas, $epec) {
            $sondas['epec']++;
            $sondas['tipo_epec'] = $mock->contingency?->type;

            return $epec ? $epec() : modoRetEpec('136');
        });

        return $mock;
    };

    $v = MODO_VALOR;
    $dados = [
        'transaction_id' => null, 'modelo' => $modelo, 'serie' => MODO_SERIE, 'nat_op' => 'Venda de produto',
        'emit' => [
            'cnpj' => '12345678000199', 'razao_social' => 'EMPRESA TESTE LTDA', 'ie' => '123456789', 'crt' => 1,
            'uf' => 'SP', 'ambiente' => 2, 'logradouro' => 'Rua Teste', 'numero_end' => '100', 'bairro' => 'Centro',
            'municipio' => 'Sao Paulo', 'cod_municipio' => '3550308', 'cep' => '01001000',
        ],
        'dest' => [
            'nome' => 'CLIENTE TESTE LTDA', 'cnpj' => '98765432000111', 'ind_ie_dest' => '1', 'ie' => '987654321',
            'logradouro' => 'Rua Dest', 'numero' => '200', 'bairro' => 'Bairro', 'municipio' => 'Sao Paulo',
            'cod_municipio' => '3550308', 'uf' => 'SP', 'cep' => '01310100',
        ],
        'dets' => [[
            'cprod' => '001', 'xprod' => 'Produto Teste', 'ncm' => '49019900', 'cfop' => '5102', 'ucm' => 'UN',
            'qcom' => 1.0, 'vuncom' => $v, 'vprod' => $v, 'utrib' => 'UN', 'qtrib' => 1.0, 'vuntrib' => $v,
            'ind_tot' => 1, 'icms' => ['cst_csosn' => '102', 'orig' => 0],
            'pis' => ['cst' => '07', 'vbc' => 0, 'ppis' => 0, 'vpis' => 0],
            'cofins' => ['cst' => '07', 'vbc' => 0, 'pcofins' => 0, 'vcofins' => 0],
        ]],
        'total' => ['v_prod' => $v, 'v_bc_icms' => 0, 'v_icms' => 0, 'v_pis' => 0, 'v_cofins' => 0, 'v_nf' => $v, 'v_desc' => 0, 'v_frete' => 0],
        'pag' => [['tpag' => '01', 'vpag' => $v]],
        'valor_total' => $v,
    ];

    return (new NfeService($cert, $tools))->emitir($businessId, $dados);
}

function modoLimpar(): void
{
    try {
        // nfe_eventos cai junto (FK cascadeOnDelete).
        NfeEmissao::withoutGlobalScopes()->where('valor_total', MODO_VALOR)->forceDelete();
    } catch (\Throwable) {
    }
}

beforeEach(function () {
    Storage::fake('local');
    modoLimpar();
});

afterEach(function () {
    modoLimpar();
    try {
        foreach ([(int) test()->seededTenant()->id, MODO_BIZ_B] as $b) {
            modoContingencia($b, false);
        }
    } catch (\Throwable) {
    }
    \Mockery::close();
});

describe('US-NFE-071 · UC-FLX-32 · modos SVC-AN/SVC-RS e evento EPEC', function () {

    it('UC-FNFE-19 · SVC-AN: NF-e sai com tpEmis 6 e é transmitida NA HORA pela SEFAZ Virtual do AN', function () {
        [$a] = modoTenants();
        modoContingencia($a, true, ModoContingencia::SVC_AN);

        $s = [];
        $emissao = modoEmitir($a, '55', $s);

        expect((int) $emissao->tp_emis)->toBe(NfeEmissao::TP_EMIS_SVC_AN)
            ->and($s['xml'])->toContain('<tpEmis>6</tpEmis>')
            ->and($s['xml'])->toContain('<xJust>SEFAZ-SP fora do ar desde 14h</xJust>')
            // o lote foi para a SVC — é o `type` que faz a lib trocar o webservice
            ->and($s['lote'])->toBe(1)
            ->and($s['tipo_lote'])->toBe('SVCAN')
            ->and($s['epec'])->toBe(0)
            ->and($emissao->status)->toBe('autorizada');
    });

    it('UC-FNFE-19 · SVC-RS: NF-e sai com tpEmis 7 e o lote vai para a SVC do RS', function () {
        [$a] = modoTenants();
        modoContingencia($a, true, ModoContingencia::SVC_RS);

        $s = [];
        $emissao = modoEmitir($a, '55', $s);

        expect((int) $emissao->tp_emis)->toBe(NfeEmissao::TP_EMIS_SVC_RS)
            ->and($s['xml'])->toContain('<tpEmis>7</tpEmis>')
            ->and($s['tipo_lote'])->toBe('SVCRS')
            ->and($emissao->status)->toBe('autorizada');
    });

    it('UC-FNFE-19 · NFC-e não tem SEFAZ Virtual: com SVC ligado ela segue off-line (tpEmis 9), sem envio', function () {
        [$a] = modoTenants();
        modoContingencia($a, true, ModoContingencia::SVC_AN);

        $s = [];
        $emissao = modoEmitir($a, '65', $s);

        expect((int) $emissao->tp_emis)->toBe(NfeEmissao::TP_EMIS_OFFLINE_NFCE)
            ->and($emissao->status)->toBe('contingencia')
            ->and($s['lote'])->toBe(0)
            ->and($s['epec'])->toBe(0);
    });

    it('UC-FNFE-20 · EPEC: o evento 110140 é enviado e registrado, a nota fica guardada e NÃO vai à SEFAZ da UF', function () {
        [$a] = modoTenants();
        modoContingencia($a, true, ModoContingencia::EPEC);

        $s = [];
        $emissao = modoEmitir($a, '55', $s);

        $evento = NfeEvento::withoutGlobalScopes()->where('emissao_id', $emissao->id)->where('tipo', '110140')->first();

        expect((int) $emissao->tp_emis)->toBe(NfeEmissao::TP_EMIS_EPEC)
            ->and($emissao->status)->toBe('contingencia')
            ->and($s['lote'])->toBe(0)
            ->and($s['epec'])->toBe(1)
            ->and($s['tipo_epec'])->toBe('EPEC')
            ->and($evento)->not->toBeNull()
            ->and($evento->status)->toBe('autorizado')
            ->and($evento->cstat_evento)->toBe('136')
            ->and($emissao->metadata['epec_nprot'] ?? null)->toBe('891260000000001');
        Storage::assertExists($emissao->xml_path);
    });

    it('UC-FNFE-20 · tenant que ativou antes da escolha (modo nulo) segue em EPEC', function () {
        [$a] = modoTenants();
        modoContingencia($a, true, null);

        $s = [];
        $emissao = modoEmitir($a, '55', $s);

        expect((int) $emissao->tp_emis)->toBe(NfeEmissao::TP_EMIS_EPEC)
            ->and($s['epec'])->toBe(1);
    });

    it('UC-FNFE-30 · EPEC recusado pelo AN: a nota segue guardada, o evento fica rejeitado e o motivo diz que o DANFE não vale', function () {
        [$a] = modoTenants();
        modoContingencia($a, true, ModoContingencia::EPEC);

        $s = [];
        $emissao = modoEmitir($a, '55', $s, fn () => modoRetEpec('467'));

        $evento = NfeEvento::withoutGlobalScopes()->where('emissao_id', $emissao->id)->where('tipo', '110140')->first();

        expect($emissao->status)->toBe('contingencia')
            ->and($evento->status)->toBe('rejeitado')
            ->and($evento->cstat_evento)->toBe('467')
            ->and($emissao->motivo)->toContain('O DANFE só vale com o EPEC registrado');
        Storage::assertExists($emissao->xml_path);
    });

    it('UC-FNFE-30 · AN fora do ar (exceção no envio): a emissão não cai e o evento registra o erro', function () {
        [$a] = modoTenants();
        modoContingencia($a, true, ModoContingencia::EPEC);

        $s = [];
        $emissao = modoEmitir($a, '55', $s, fn () => throw new \RuntimeException('Connection reset by peer'));

        $evento = NfeEvento::withoutGlobalScopes()->where('emissao_id', $emissao->id)->where('tipo', '110140')->first();

        expect($emissao->status)->toBe('contingencia')
            ->and($evento->status)->toBe('rejeitado')
            ->and($evento->payload_json['erro'] ?? '')->toContain('Connection reset');
    });

    it('UC-FNFE-31 · multi-tenant: o EPEC do tenant A grava evento só em A; o B não ganha evento nem nota', function () {
        [$a, $b] = modoTenants();
        modoContingencia($a, true, ModoContingencia::EPEC);
        $antesB = NfeEvento::withoutGlobalScopes()->where('business_id', $b)->where('tipo', '110140')->count();

        $s = [];
        $emissao = modoEmitir($a, '55', $s);

        $eventos = NfeEvento::withoutGlobalScopes()->where('emissao_id', $emissao->id)->get();

        expect($eventos)->toHaveCount(1)
            ->and((int) $eventos->first()->business_id)->toBe($a)
            ->and((int) $emissao->business_id)->toBe($a)
            ->and(NfeEvento::withoutGlobalScopes()->where('business_id', $b)->where('tipo', '110140')->count())->toBe($antesB);
    });
});

describe('US-NFE-071 · ativação com modo (ContingenciaService)', function () {

    it('UC-FNFE-19 · grava o modo escolhido e desativar limpa', function () {
        [$a] = modoTenants();
        modoContingencia($a, false);

        $cfg = app(ContingenciaService::class)->ativar($a, 'SEFAZ fora do ar — teste', null, ModoContingencia::EPEC);
        expect($cfg->contingencia_modo)->toBe('epec');

        $cfg = app(ContingenciaService::class)->desativar($a);
        expect($cfg->contingencia_modo)->toBeNull();
    });

    it('UC-FNFE-19 · recusa a SEFAZ Virtual que não atende a UF do emitente', function () {
        [$a] = modoTenants();
        modoContingencia($a, false);

        $uf = DB::table('business_locations')->where('business_id', $a)->orderBy('id')->value('state');
        $daUf = ModoContingencia::svcDaUf(is_string($uf) ? $uf : null);
        // sem UF conhecida, as duas SVC são recusadas; com UF, a outra é recusada
        $errada = $daUf === ModoContingencia::SVC_AN ? ModoContingencia::SVC_RS : ModoContingencia::SVC_AN;

        expect(fn () => app(ContingenciaService::class)->ativar($a, 'SEFAZ fora do ar — teste', null, $errada))
            ->toThrow(ContingenciaException::class);

        expect(NfeBusinessConfig::withoutGlobalScopes()->where('business_id', $a)->value('em_contingencia'))
            ->toBeFalsy();

        if ($daUf !== null) {
            $cfg = app(ContingenciaService::class)->ativar($a, 'SEFAZ fora do ar — teste', null, $daUf);
            expect($cfg->contingencia_modo)->toBe($daUf);
        }
    });

    it('UC-FNFE-19 · modo desconhecido é recusado', function () {
        [$a] = modoTenants();
        modoContingencia($a, false);

        expect(fn () => app(ContingenciaService::class)->ativar($a, 'SEFAZ fora do ar — teste', null, 'fsda'))
            ->toThrow(ContingenciaException::class);
    });

    it('UC-FNFE-19 · a tabela UF → SVC é a da sped-nfe, nas 27 UFs', function () {
        $ufs = ['AC', 'AL', 'AM', 'AP', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MG', 'MS', 'MT', 'PA',
            'PB', 'PE', 'PI', 'PR', 'RJ', 'RN', 'RO', 'RR', 'RS', 'SC', 'SE', 'SP', 'TO'];
        $tipoNosso = ['svc-an' => 'SVCAN', 'svc-rs' => 'SVCRS'];

        foreach ($ufs as $uf) {
            // segundo caminho: a lib que de fato roteia o webservice
            $lib = new \NFePHP\NFe\Factories\Contingency();
            $lib->activate($uf, 'SEFAZ fora do ar — conferência da tabela');

            expect($tipoNosso[ModoContingencia::svcDaUf($uf)] ?? null)->toBe($lib->type, "UF {$uf}");
        }
        expect(ModoContingencia::svcDaUf('XX'))->toBeNull();
    });
});
