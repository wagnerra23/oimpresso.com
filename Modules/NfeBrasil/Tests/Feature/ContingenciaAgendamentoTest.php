<?php

declare(strict_types=1);

// @covers-us US-NFE-070 — contingência: dhCont/xJust no XML + retransmissão agendada (UC-FLX-32).
// Contrato: resources/js/Pages/Fiscal/Nfe.casos.md §"NF-e em contingência quando a SEFAZ cai"
// (UC-FNFE-15..18) · memory/requisitos/NfeBrasil/SPEC.md US-NFE-070.

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\NfeBrasil\Jobs\RetentarContingenciaJob;
use Modules\NfeBrasil\Models\NfeBusinessConfig;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Services\CertificadoService;
use Modules\NfeBrasil\Services\NfeService;
use NFePHP\NFe\Tools;

uses(Tests\TestCase::class);

/**
 * Tenants: A = canônico de teste (98, ADR 0358) · B = 2 (segundo tenant do seed do CI).
 * Helpers com prefixo `agnd` — Pest declara funções globais e os vizinhos já usam `cont`/`retr`.
 */
const AGND_SERIE = '996';
const AGND_VALOR = 66.66;
const AGND_UF = 'RR';
const AGND_BIZ_B = 2;

function agndTenants(): array
{
    if (! Schema::hasTable('nfe_emissoes') || ! Schema::hasColumn('nfe_emissoes', 'tp_emis')) {
        test()->markTestSkipped('Schema da contingência ausente — rode as migrations do NfeBrasil.');
    }
    $a = (int) test()->seededTenant()->id;
    if ($a === AGND_BIZ_B || ! DB::table('business')->where('id', AGND_BIZ_B)->exists()) {
        test()->markTestSkipped('Precisa de dois tenants distintos (98 e 2) — rode pest-mysql-setup.');
    }

    return [$a, AGND_BIZ_B];
}

function agndContingencia(int $businessId, bool $ligada, string $motivo = 'SEFAZ-RR fora do ar desde 14h'): void
{
    NfeBusinessConfig::withoutGlobalScopes()->updateOrCreate(
        ['business_id' => $businessId],
        [
            'regime' => 'simples',
            'tributacao_default' => ['cfop' => '5102'],
            'em_contingencia' => $ligada,
            'contingencia_motivo' => $ligada ? $motivo : null,
            'contingencia_ativada_em' => $ligada ? now()->setTime(14, 5, 0) : null,
        ],
    );
}

function agndNota(int $businessId, int $numero, string $status = 'contingencia'): NfeEmissao
{
    return NfeEmissao::withoutGlobalScopes()->create([
        'business_id' => $businessId, 'transaction_id' => null, 'modelo' => '65',
        'serie' => AGND_SERIE, 'numero' => $numero, 'status' => $status,
        'tp_emis' => NfeEmissao::TP_EMIS_OFFLINE_NFCE, 'valor_total' => AGND_VALOR,
    ]);
}

/** NfeService real com Tools falso que GUARDA o XML montado; autoriza se a SEFAZ for chamada. */
function agndEmitir(int $businessId, string $modelo, ?string &$xml): NfeEmissao
{
    $cert = \Mockery::mock(CertificadoService::class);
    $cert->shouldReceive('carregarParaSefaz')->andReturn([
        'pfx_binary' => 'fake', 'senha' => 'fake', 'valido_ate' => now()->addYear(), 'source' => 'test',
    ]);
    $tools = function () use (&$xml): Tools {
        $mock = \Mockery::mock(Tools::class);
        $mock->shouldReceive('signNFe')->andReturnUsing(function (string $x) use (&$xml) {
            $xml = $x;

            return $x;
        });
        $mock->shouldReceive('model')->andReturnNull();
        $mock->shouldReceive('sefazEnviaLote')->andReturn(
            '<retEnviNFe xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00"><tpAmb>2</tpAmb><cUF>14</cUF>'
            . '<cStat>104</cStat><xMotivo>Lote processado</xMotivo><protNFe versao="4.00"><infProt><cStat>100</cStat>'
            . '<xMotivo>Autorizado o uso da NF-e</xMotivo><chNFe>14210112345678000199550010000000011000000019</chNFe>'
            . '<nProt>114260000000001</nProt><dhRecbto>2026-10-20T00:00:00-03:00</dhRecbto></infProt></protNFe></retEnviNFe>'
        );

        return $mock;
    };

    $v = AGND_VALOR;
    $dados = [
        'transaction_id' => null, 'modelo' => $modelo, 'nat_op' => 'Venda de produto',
        'emit' => [
            'cnpj' => '12345678000199', 'razao_social' => 'EMPRESA TESTE LTDA', 'ie' => '123456789', 'crt' => 1,
            'uf' => AGND_UF, 'ambiente' => 2, 'logradouro' => 'Rua Teste', 'numero_end' => '100', 'bairro' => 'Centro',
            'municipio' => 'Boa Vista', 'cod_municipio' => '1400100', 'cep' => '69301000',
        ],
        'dest' => [
            'nome' => 'CLIENTE TESTE LTDA', 'cnpj' => '98765432000111', 'ind_ie_dest' => '1', 'ie' => '987654321',
            'logradouro' => 'Rua Dest', 'numero' => '200', 'bairro' => 'Bairro', 'municipio' => 'Boa Vista',
            'cod_municipio' => '1400100', 'uf' => AGND_UF, 'cep' => '69301000',
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

/** NfeService falso: responde o ping com o cStat dado e registra quem foi pingado. */
function agndPing(string $cstat, array &$pingados): NfeService
{
    $fake = \Mockery::mock(NfeService::class);
    $fake->shouldReceive('consultarStatusSefaz')->andReturnUsing(function (int $biz) use ($cstat, &$pingados) {
        $pingados[] = $biz;

        return ['ok' => $cstat === '107', 'cstat' => $cstat, 'xMotivo' => 'teste', 'tempoResposta' => 0.2, 'uf' => AGND_UF];
    });

    return $fake;
}

function agndLimpar(): void
{
    try {
        NfeEmissao::withoutGlobalScopes()->where('valor_total', AGND_VALOR)->forceDelete();
        DB::table('nfe_sefaz_status')->where('uf', AGND_UF)->delete();
    } catch (\Throwable) {
    }
}

beforeEach(function () {
    Storage::fake('local');
    agndLimpar();
});

afterEach(function () {
    agndLimpar();
    try {
        foreach ([(int) test()->seededTenant()->id, AGND_BIZ_B] as $b) {
            agndContingencia($b, false);
        }
    } catch (\Throwable) {
    }
    \Mockery::close();
});

describe('US-NFE-070 · UC-FLX-32 · contingência quando a SEFAZ cai', function () {

    it('UC-FNFE-15 · NF-e (tpEmis 4) e NFC-e (tpEmis 9) saem com dhCont e xJust da ativação', function () {
        [$a] = agndTenants();
        agndContingencia($a, true);

        foreach (['55' => NfeEmissao::TP_EMIS_EPEC, '65' => NfeEmissao::TP_EMIS_OFFLINE_NFCE] as $modelo => $tp) {
            $xml = null;
            $emissao = agndEmitir($a, (string) $modelo, $xml);

            expect((int) $emissao->tp_emis)->toBe($tp)
                ->and($xml)->toContain("<tpEmis>{$tp}</tpEmis>")
                ->and($xml)->toContain('<dhCont>' . now()->setTime(14, 5, 0)->format('Y-m-d\TH:i:sP') . '</dhCont>')
                ->and($xml)->toContain('<xJust>SEFAZ-RR fora do ar desde 14h</xJust>');
        }
    });

    it('UC-FNFE-15 · motivo de 10 caracteres ganha prefixo para caber nos 15 mínimos da SEFAZ', function () {
        [$a] = agndTenants();
        agndContingencia($a, true, 'SEFAZ caiu');

        $xml = null;
        agndEmitir($a, '65', $xml);

        expect($xml)->toContain('<xJust>Contingencia: SEFAZ caiu</xJust>');
    });

    it('UC-FNFE-16 · fora de contingência (tpEmis 1) o XML não tem dhCont nem xJust', function () {
        [$a] = agndTenants();
        agndContingencia($a, false);

        $xml = null;
        $emissao = agndEmitir($a, '55', $xml);

        expect((int) $emissao->tp_emis)->toBe(NfeEmissao::TP_EMIS_NORMAL)
            ->and($xml)->toContain('<tpEmis>1</tpEmis>')
            ->and(str_contains((string) $xml, '<dhCont>'))->toBeFalse()
            ->and(str_contains((string) $xml, '<xJust>'))->toBeFalse();
    });

    it('UC-FNFE-17 · o agendamento despacha só para o business com nota em contingência e SEFAZ no ar', function () {
        [$a, $b] = agndTenants();
        Queue::fake();
        agndNota($a, 996101);
        agndNota($b, 996102, 'autorizada'); // B não tem nada pendente

        $pingados = [];
        app()->instance(NfeService::class, agndPing('107', $pingados));

        $this->artisan('nfe:retentar-contingencia')->assertSuccessful();

        // Sem --business-id: o command varre o banco todo. Outros tenants com nota pendente
        // podem existir no CT 100 (banco persiste), então a prova é sobre A e B, não a contagem.
        expect($pingados)->toContain($a)->not->toContain($b);
        Queue::assertPushed(RetentarContingenciaJob::class, fn ($job) => $job->businessId === $a);
        Queue::assertNotPushed(RetentarContingenciaJob::class, fn ($job) => $job->businessId === $b);
        expect(DB::table('nfe_sefaz_status')->where('uf', AGND_UF)->value('status'))->toBe('verde');
    });

    it('UC-FNFE-17 · com a SEFAZ ainda fora (cStat 108) nada é despachado nem gravado', function () {
        [$a] = agndTenants();
        Queue::fake();
        agndNota($a, 996201);

        $pingados = [];
        app()->instance(NfeService::class, agndPing('108', $pingados));

        $this->artisan('nfe:retentar-contingencia', ['--business-id' => $a])->assertSuccessful();

        expect($pingados)->toBe([$a]);
        Queue::assertNothingPushed();
        expect(DB::table('nfe_sefaz_status')->where('uf', AGND_UF)->exists())->toBeFalse();
    });

    it('UC-FNFE-18 · [T0] o job do business A nunca transmite nota do business B', function () {
        [$a, $b] = agndTenants();
        agndNota($a, 996301);
        $notaB = agndNota($b, 996302);

        $vistas = [];
        $fake = \Mockery::mock(NfeService::class);
        $fake->shouldReceive('transmitirContingencia')->andReturnUsing(function (int $biz, int $id) use (&$vistas) {
            $e = NfeEmissao::withoutGlobalScopes()->find($id);
            $vistas[] = [(int) $biz, (int) $e->business_id];
            $e->update(['status' => 'autorizada']);

            return $e->refresh();
        });

        (new RetentarContingenciaJob($a))->handle($fake);

        expect($vistas)->toBe([[$a, $a]])
            ->and(NfeEmissao::withoutGlobalScopes()->find($notaB->id)->status)->toBe('contingencia');
    });
});
