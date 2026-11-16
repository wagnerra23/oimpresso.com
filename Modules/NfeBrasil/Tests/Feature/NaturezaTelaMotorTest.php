<?php

declare(strict_types=1);

// @covers-us US-NFE-010 — o motor calcula pela natureza criada na tela (onda 4 · RUNBOOK-tributacao §Naturezas).

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Exceptions\AliquotaInternaNaoCadastradaException;
use Modules\NfeBrasil\Exceptions\TributacaoNaoConfiguradaException;
use Modules\NfeBrasil\Models\NfeFiscalRule;
use Modules\NfeBrasil\Services\MotorTributarioService;
use Modules\NfeBrasil\Services\Tributacao\ProdutoFiscalContext;

uses(Tests\TestCase::class);

/**
 * REGRA MESTRE DE VALOR — cada valor tem dois caminhos: o motor (`calcularComDestino`) e a conta à
 * mão escrita ao lado do assert. Os números de lei vêm do motor com a lei citada (PIS/COFINS do
 * regime, IBS/CBS do ano, 4% da Resolução SF 13/2012); os da UF, da `nfe_icms_uf` semeada aqui.
 * MySQL-only · tenant fictício 98 (ADR 0358). Item de valor 1.000,00 (fictício) em todos os casos.
 */

const NTM_NCM = '77071004';
const NTM_DATA = '2026-11-15';

function ntmBiz(): int
{
    return test()->seededTenant()->id;
}

/** A natureza vazia do protótipo, com mudanças. */
function ntmTela(array $mudar = []): array
{
    return array_replace_recursive([
        'icms' => ['cst' => '00', 'csosn' => '102', 'origem' => 'uf', 'p' => 0, 'modbc' => '3', 'red' => 0, 'pDif' => 0,
            'st' => ['modbcst' => '4', 'mvaOrig' => 'produto', 'mva' => 0, 'redB' => 0], 'difal' => true, 'fcp' => true],
        'ipi' => ['on' => false, 'cst' => '53', 'cEnq' => '999', 'origem' => 'produto', 'p' => 0],
        'pis' => ['cst' => '01', 'cstSN' => '49', 'origem' => 'regime', 'p' => 0],
        'cofins' => ['cst' => '01', 'cstSN' => '49', 'origem' => 'regime', 'p' => 0],
        'ibs' => ['on' => true, 'cst' => '000', 'cClass' => '000001', 'origem' => 'padrao', 'pUF' => 0, 'pMun' => 0, 'pCBS' => 0, 'pRed' => 0],
    ], $mudar);
}

function ntmNatureza(array $tela, array $colunas = []): int
{
    return (int) DB::table('nfe_operacoes_fiscais')->insertGetId(array_merge([
        'business_id' => ntmBiz(), 'slug' => 'tela-ntm-' . uniqid(), 'nome' => 'Venda de teste', 'finalidade' => 1,
        'cfop' => '5102', 'cfop_interestadual' => '6102', 'cfop_exterior' => '7102',
        'config_tela' => json_encode($tela), 'padrao' => false, 'created_at' => now(), 'updated_at' => now(),
    ], $colunas));
}

function ntmCalc(int $op, string $ufDestino, ?int $origem = null, string $data = NTM_DATA): object
{
    return (new MotorTributarioService())->calcularComDestino(
        new ProdutoFiscalContext(ncm: NTM_NCM, valor: 1000.0, origem_mercadoria: $origem),
        ntmBiz(), 'SC', $ufDestino, operacaoId: $op, dataEmissao: $data,
    );
}

function ntmLimpar(): void
{
    DB::table('nfe_operacoes_fiscais')->where('business_id', ntmBiz())->where('slug', 'like', 'tela-ntm-%')->delete();
    DB::table('nfe_icms_uf')->where('business_id', ntmBiz())->where('fonte', 'teste-ntm')->delete();
    DB::table('nfe_fiscal_rules')->where('business_id', ntmBiz())->where('ncm', NTM_NCM)->delete();
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('MySQL-only: nfe_operacoes_fiscais, nfe_icms_uf e config_tela vêm de migrations do módulo.');
    }
    if (! Schema::hasColumn('nfe_operacoes_fiscais', 'config_tela') || ! Schema::hasTable('nfe_icms_uf')) {
        $this->markTestSkipped('Migrations das ondas 2/3 ou da tabela por UF não rodaram.');
    }
    NfeFiscalRule::esquecerVersionamento();
    ntmLimpar();

    $this->configAntes = DB::table('nfe_business_configs')->where('business_id', ntmBiz())->first();
    DB::table('nfe_business_configs')->where('business_id', ntmBiz())->delete();
    DB::table('nfe_business_configs')->insert([
        'business_id' => ntmBiz(), 'regime' => 'lucro_presumido',
        'tributacao_default' => json_encode(['cfop' => '5102', 'cst' => '00', 'aliquota_icms' => 0.17]),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    // SC→SC interna 17%; SC→PR interestadual 12% (Resolução SF 22/1989), sem interna.
    DB::table('nfe_icms_uf')->insert([
        ['business_id' => ntmBiz(), 'uf_origem' => 'SC', 'uf_destino' => 'SC', 'aliquota_interestadual' => null,
            'aliquota_interna' => 0.17, 'fcp' => null, 'fonte' => 'teste-ntm', 'created_at' => now(), 'updated_at' => now()],
        ['business_id' => ntmBiz(), 'uf_origem' => 'SC', 'uf_destino' => 'PR', 'aliquota_interestadual' => 0.12,
            'aliquota_interna' => null, 'fcp' => null, 'fonte' => 'teste-ntm', 'created_at' => now(), 'updated_at' => now()],
    ]);
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasColumn('nfe_operacoes_fiscais', 'config_tela')) {
        return;
    }
    ntmLimpar();
    DB::table('nfe_business_configs')->where('business_id', ntmBiz())->delete();
    if ($this->configAntes) {
        DB::table('nfe_business_configs')->insert((array) $this->configAntes);
    }
});

it('UC-NFTR-53 · dentro do estado, pela UF e pelo regime: ICMS da interna, PIS/COFINS do Presumido, IBS/CBS de 2026', function () {
    $t = ntmCalc(ntmNatureza(ntmTela()), 'SC');

    // À mão: ICMS 1.000 × 17% = 170,00 · PIS 1.000 × 0,65% = 6,50 · COFINS 1.000 × 3% = 30,00
    //        IBS 1.000 × 0,1% = 1,00 · CBS 1.000 × 0,9% = 9,00
    expect($t->cfop)->toBe('5102')
        ->and($t->cst)->toBe('00')
        ->and($t->valor_icms)->toBe(170.0)
        ->and($t->valor_pis)->toBe(6.5)
        ->and($t->valor_cofins)->toBe(30.0)
        ->and($t->valor_ibs)->toBe(1.0)
        ->and($t->valor_cbs)->toBe(9.0)
        ->and($t->cst_pis)->toBe('01')
        ->and($t->c_class_trib)->toBe('000001');
});

it('UC-NFTR-54 · outro estado: 12% da tabela para nacional, 4% para importado (Res. SF 13/2012), 12% para sem similar (origem 6)', function () {
    $op = ntmNatureza(ntmTela());

    // À mão: 1.000 × 12% = 120,00 · 1.000 × 4% = 40,00
    expect(ntmCalc($op, 'PR', 0)->valor_icms)->toBe(120.0)
        ->and(ntmCalc($op, 'PR', 0)->cfop)->toBe('6102')
        ->and(ntmCalc($op, 'PR', 1)->valor_icms)->toBe(40.0)
        ->and(ntmCalc($op, 'PR', 3)->valor_icms)->toBe(40.0)
        ->and(ntmCalc($op, 'PR', 6)->valor_icms)->toBe(120.0)
        // Dentro do estado o 4% não vale: importado continua com a interna (170,00).
        ->and(ntmCalc($op, 'SC', 1)->valor_icms)->toBe(170.0);
});

it('UC-NFTR-55 · alíquota fixa, redução de base (CST 20) e CST sem ICMS próprio', function () {
    // À mão: fixa 18% → 1.000 × 18% = 180,00
    expect(ntmCalc(ntmNatureza(ntmTela(['icms' => ['origem' => 'fixa', 'p' => 18]])), 'SC')->valor_icms)->toBe(180.0);

    // À mão: CST 20 com 40% de redução → base 1.000 × (1 − 0,40) = 600,00 · ICMS 600 × 17% = 102,00
    $red = ntmCalc(ntmNatureza(ntmTela(['icms' => ['cst' => '20', 'red' => 40]])), 'SC');
    expect($red->base_icms)->toBe(600.0)->and($red->valor_icms)->toBe(102.0)->and($red->reducao_base)->toBe(0.4);

    // CST 41 (não tributada): sem ICMS próprio, mesmo "pela UF".
    expect(ntmCalc(ntmNatureza(ntmTela(['icms' => ['cst' => '41']])), 'SC')->valor_icms)->toBe(0.0);
});

it('UC-NFTR-56 · IPI e MVA "da regra do NCM" vêm da regra por NCM; PIS/COFINS no Lucro Real', function () {
    DB::table('nfe_fiscal_rules')->insert([
        'business_id' => ntmBiz(), 'ncm' => NTM_NCM, 'uf_origem' => 'SC', 'uf_destino' => null, 'cfop' => '5102',
        'cst' => '00', 'aliquota_icms' => 0.17, 'aliquota_pis' => 0, 'aliquota_cofins' => 0, 'aliquota_ipi' => 0.05,
        'mva' => 0.4, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('nfe_business_configs')->where('business_id', ntmBiz())->update(['regime' => 'lucro_real']);

    $t = ntmCalc(ntmNatureza(ntmTela(['ipi' => ['on' => true, 'cst' => '50']])), 'SC');
    // À mão: IPI 1.000 × 5% = 50,00 · PIS 1.000 × 1,65% = 16,50 · COFINS 1.000 × 7,6% = 76,00
    expect($t->valor_ipi)->toBe(50.0)->and($t->valor_pis)->toBe(16.5)->and($t->valor_cofins)->toBe(76.0);

    $st = ntmCalc(ntmNatureza(ntmTela(['icms' => ['cst' => '10']])), 'SC');
    // MVA da regra = 40% (o motor só calcula a ST com a interna do destino, que este cálculo não informa).
    expect($st->mva)->toBe(0.4);
});

it('UC-NFTR-57 · faltou a fonte do número: o motor recusa com o motivo, nunca calcula com zero', function () {
    // Interna da UF de origem não cadastrada.
    DB::table('nfe_icms_uf')->where('business_id', ntmBiz())->where('fonte', 'teste-ntm')->where('uf_destino', 'SC')->delete();
    expect(fn () => ntmCalc(ntmNatureza(ntmTela()), 'SC'))->toThrow(AliquotaInternaNaoCadastradaException::class);

    // IPI "da regra do NCM" sem regra para o NCM.
    $semRegra = ntmNatureza(ntmTela(['icms' => ['origem' => 'fixa', 'p' => 17], 'ipi' => ['on' => true, 'cst' => '50']]));
    expect(fn () => ntmCalc($semRegra, 'SC'))->toThrow(TributacaoNaoConfiguradaException::class, 'não tem regra');

    // IBS/CBS pelo padrão do ano num ano sem padrão.
    $fixa = ntmNatureza(ntmTela(['icms' => ['origem' => 'fixa', 'p' => 17]]));
    expect(fn () => ntmCalc($fixa, 'SC', null, '2027-01-10'))->toThrow(TributacaoNaoConfiguradaException::class, '2027');

    // ICMS "da nota de origem" fora da devolução.
    $origem = ntmNatureza(ntmTela(['icms' => ['origem' => 'origem']]));
    expect(fn () => ntmCalc($origem, 'SC'))->toThrow(TributacaoNaoConfiguradaException::class, 'devolução');
});

it('UC-NFTR-58 · operação com regra geral continua pela regra geral, mesmo com config da tela', function () {
    // Regra geral: CFOP 5910, ICMS 0% (a operação do CSV). A config da tela pediria 17%.
    $op = ntmNatureza(ntmTela(), ['regra_geral' => json_encode(['cfop' => '5910', 'cst' => '41', 'aliquota_icms' => 0.0]), 'cfop' => null]);

    $t = ntmCalc($op, 'SC');
    expect($t->cfop)->toBe('5910')->and($t->valor_icms)->toBe(0.0);
});

it('UC-NFTR-59 · calcularNaOperacao não troca a natureza da tela pela venda (nem a recusa)', function () {
    // IBS/CBS desligado: `calcularNaOperacao` usa a data de hoje, e o padrão do ano não deve decidir este caso.
    $op = ntmNatureza(ntmTela(['icms' => ['origem' => 'fixa', 'p' => 12], 'ibs' => ['on' => false]]));
    $motor = new MotorTributarioService();

    $t = $motor->calcularNaOperacao(new ProdutoFiscalContext(ncm: NTM_NCM, valor: 1000.0), ntmBiz(), 'SC', 'SC', $op);
    // À mão: 1.000 × 12% = 120,00 (a venda padrão daria 170,00 pelo default da empresa).
    expect($t->valor_icms)->toBe(120.0);

    $recusa = ntmNatureza(ntmTela(['icms' => ['origem' => 'origem']]));
    expect(fn () => $motor->calcularNaOperacao(new ProdutoFiscalContext(ncm: NTM_NCM, valor: 1000.0), ntmBiz(), 'SC', 'SC', $recusa))
        ->toThrow(TributacaoNaoConfiguradaException::class);
});

it('UC-NFTR-65 · IBS/CBS por ano vêm da configuração: 2027 recusa até a CBS ter alíquota, e calcula quando ela é cadastrada', function () {
    $natureza = ntmNatureza(ntmTela(['icms' => ['origem' => 'fixa', 'p' => 17]]));

    // Como vem no main: 2027 tem o IBS de 0,1% e a CBS null (aguarda a resolução do Senado).
    expect(config('nfebrasil.ibs_cbs_por_ano.2027.ibs'))->toBe(0.001)
        ->and(config('nfebrasil.ibs_cbs_por_ano.2027.cbs'))->toBeNull();
    expect(fn () => ntmCalc($natureza, 'SC', null, '2027-01-10'))
        ->toThrow(TributacaoNaoConfiguradaException::class, 'resolução do Senado');

    // Ano fora da tabela: o motivo diz que o ano não tem padrão.
    expect(fn () => ntmCalc($natureza, 'SC', null, '2029-01-10'))
        ->toThrow(TributacaoNaoConfiguradaException::class, '2029 não tem padrão cadastrado');

    // Cadastrada a CBS (valor de TESTE, não a alíquota real), 2027 calcula pela tabela.
    config(['nfebrasil.ibs_cbs_por_ano.2027.cbs' => 0.05]);
    $t = ntmCalc($natureza, 'SC', null, '2027-01-10');
    // À mão: IBS 1.000 × 0,1% = 1,00 · CBS 1.000 × 5% = 50,00
    expect($t->valor_ibs)->toBe(1.0)
        ->and($t->valor_cbs)->toBe(50.0);

    // 2026 segue igual ao UC-NFTR-53.
    $t26 = ntmCalc($natureza, 'SC');
    expect($t26->valor_ibs)->toBe(1.0)
        ->and($t26->valor_cbs)->toBe(9.0);
});
