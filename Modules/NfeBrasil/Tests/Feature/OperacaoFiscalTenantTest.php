<?php

declare(strict_types=1);

// @covers-us US-NFE-010 — operação fiscal + vigência das regras (playbook Fiscal thread 07 · D-OPERACAO).
// @covers R-NFE-020 · R-NFE-020d · R-NFE-019 — migração sem perda, tenant e vínculo de tax_rate.

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Exceptions\TributacaoNaoConfiguradaException;
use Modules\NfeBrasil\Models\NfeFiscalRule;
use Modules\NfeBrasil\Models\NfeOperacaoFiscal;
use Modules\NfeBrasil\Services\MotorTributarioService;
use Modules\NfeBrasil\Services\Tributacao\ProdutoFiscalContext;

uses(Tests\TestCase::class);

/**
 * MySQL-only · tenant fictício 98 (`seededTenant`) e cliente fictício 99 (`seededSupportClientTenant`)
 * — nunca biz=1 nem biz=4 (ADR 0358). Os casos derivam do aceite da thread 07 (R-NFE-019/020/020d),
 * não do código. Valores com a conta à mão ao lado (regra mestre de valor).
 */

const OFT_NCMS = ['77071001', '77071002'];

function oftBiz(): int
{
    return test()->seededTenant()->id;
}

function oftBizOutro(): int
{
    return test()->seededSupportClientTenant()->id;
}

function oftLimpar(): void
{
    $bizs = [oftBiz(), oftBizOutro()];
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    $regras = DB::table('nfe_fiscal_rules')->whereIn('business_id', $bizs)->whereIn('ncm', OFT_NCMS)->pluck('id');
    $taxas  = DB::table('nfe_fiscal_rule_tax_rate_links')->whereIn('fiscal_rule_id', $regras)->pluck('tax_rate_id');
    DB::table('nfe_fiscal_rule_tax_rate_links')->whereIn('fiscal_rule_id', $regras)->delete();
    DB::table('tax_rates')->whereIn('id', $taxas)->delete();
    DB::table('nfe_fiscal_rules')->whereIn('id', $regras)->delete();
    DB::table('nfe_operacoes_fiscais')->whereIn('business_id', $bizs)->where('slug', 'like', 'oft-%')->delete();
    DB::statement('SET FOREIGN_KEY_CHECKS=1');
}

function oftCtx(string $ncm, float $valor = 1000.0, ?int $override = null): ProdutoFiscalContext
{
    return new ProdutoFiscalContext(ncm: $ncm, valor: $valor, fiscal_rule_override_id: $override);
}

/** A migração de dado da thread 07, carregada do arquivo — rodá-la é rodar a de produção. */
function oftMigracaoVenda(): object
{
    return require base_path('Modules/NfeBrasil/Database/Migrations/2026_10_07_000002_seed_operacao_venda_padrao.php');
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('MySQL-only: tabela de operações e colunas de vigência vêm da migração 2026_10_07_000001.');
    }
    if (! Schema::hasTable('nfe_operacoes_fiscais') || ! Schema::hasColumn('nfe_fiscal_rules', 'valida_de')) {
        $this->markTestSkipped('Migração 2026_10_07_000001 não rodou — rode as migrations do módulo.');
    }

    NfeFiscalRule::esquecerVersionamento();
    oftLimpar();

    $this->configAntes = DB::table('nfe_business_configs')->where('business_id', oftBiz())->first();
    $this->vendaAntes  = DB::table('nfe_operacoes_fiscais')->where('business_id', oftBiz())->where('slug', 'venda')->first();
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('nfe_operacoes_fiscais')) {
        return;
    }
    oftLimpar();

    // Devolve o tenant 98 como estava: config e operação Venda.
    DB::table('nfe_business_configs')->where('business_id', oftBiz())->delete();
    if ($this->configAntes) {
        DB::table('nfe_business_configs')->insert((array) $this->configAntes);
    }
    DB::table('nfe_operacoes_fiscais')->where('business_id', oftBiz())->where('slug', 'venda')->delete();
    if ($this->vendaAntes) {
        DB::table('nfe_operacoes_fiscais')->insert((array) $this->vendaAntes);
    }
});

// ---------------------------------------------------------------------------------------
// R-NFE-020 · O padrão atual vira a regra geral da operação Venda sem perda  [fiscal]
// ---------------------------------------------------------------------------------------
it('R-NFE-020 · default vira regra geral da Venda', function () {
    // Padrão da empresa: CFOP 5102, CSOSN 102, ICMS 1,86%, PIS 0,65%, COFINS 3%.
    DB::table('nfe_business_configs')->where('business_id', oftBiz())->delete();
    DB::table('nfe_business_configs')->insert([
        'business_id'        => oftBiz(),
        'regime'             => 'simples',
        'tributacao_default' => json_encode([
            'cfop' => '5102', 'csosn' => '102',
            'aliquota_icms' => 0.0186, 'aliquota_pis' => 0.0065, 'aliquota_cofins' => 0.03,
        ]),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('nfe_operacoes_fiscais')->where('business_id', oftBiz())->where('slug', 'venda')->delete();

    // Antes da operação existir: N4 = tributacao_default.
    $antes = (new MotorTributarioService())->calcular(oftCtx('77071001'), oftBiz(), 'SP', 'SP');

    oftMigracaoVenda()->up();
    oftMigracaoVenda()->up(); // rodar duas vezes não duplica

    $vendas = DB::table('nfe_operacoes_fiscais')->where('business_id', oftBiz())->where('slug', 'venda')->get();
    expect($vendas)->toHaveCount(1)
        ->and((bool) $vendas[0]->padrao)->toBeTrue()
        // NULL = "usa o tributacao_default" — a tela ConfigDefault continua editando o que o motor lê.
        ->and($vendas[0]->regra_geral)->toBeNull();

    $depois = (new MotorTributarioService())->calcular(oftCtx('77071001'), oftBiz(), 'SP', 'SP');

    // Mesmo resultado, campo a campo. Conta à mão: ICMS 1.000 × 0,0186 = 18,60 ·
    // PIS 1.000 × 0,0065 = 6,50 · COFINS 1.000 × 0,03 = 30,00.
    expect(get_object_vars($depois))->toBe(get_object_vars($antes))
        ->and($depois->nivel_usado)->toBe(4)
        ->and($depois->cfop)->toBe('5102')
        ->and($depois->valor_icms)->toBe(18.6)
        ->and($depois->valor_pis)->toBe(6.5)
        ->and($depois->valor_cofins)->toBe(30.0);
});

// ---------------------------------------------------------------------------------------
// R-NFE-020d · Operações e versões de regra não atravessam empresas  [T0]
// ---------------------------------------------------------------------------------------
it('R-NFE-020d · operação isolada por business', function () {
    $geral = json_encode(['cfop' => '1202', 'csosn' => '900', 'aliquota_icms' => 0.0]);
    $daOutra = (int) DB::table('nfe_operacoes_fiscais')->insertGetId([
        'business_id' => oftBizOutro(), 'slug' => 'oft-devolucao', 'nome' => 'Devolução (outra empresa)',
        'finalidade' => 4, 'regra_geral' => $geral, 'padrao' => false,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $minha = (int) DB::table('nfe_operacoes_fiscais')->insertGetId([
        'business_id' => oftBiz(), 'slug' => 'oft-devolucao', 'nome' => 'Devolução',
        'finalidade' => 4, 'regra_geral' => $geral, 'padrao' => false,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    // Calcular com a operação da outra empresa: não usa — recusa.
    expect(fn () => (new MotorTributarioService())
        ->calcularComDestino(oftCtx('77071001'), oftBiz(), 'SP', 'SP', operacaoId: $daOutra))
        ->toThrow(TributacaoNaoConfiguradaException::class);

    // Listar como usuário da empresa 98: o escopo de tenant não traz a da 99.
    $usuario = test()->usuarioComPermissoes(['nfe.tributacao.manage']);
    test()->actingAs($usuario);
    session(['business.id' => oftBiz(), 'user.business_id' => oftBiz()]);
    $visiveis = NfeOperacaoFiscal::whereIn('id', [$daOutra, $minha])->pluck('id')->all();
    expect($visiveis)->toBe([$minha]);

    // Editar a versão de regra da outra empresa: 404, e a regra dela não muda.
    $regraOutra = NfeFiscalRule::withoutGlobalScopes()->create([ // SUPERADMIN: fixture do tenant vizinho
        'business_id' => oftBizOutro(), 'ncm' => '77071002', 'uf_origem' => 'SP', 'cfop' => '5102',
        'csosn' => '102', 'aliquota_icms' => 0.12, 'aliquota_pis' => 0, 'aliquota_cofins' => 0, 'aliquota_ipi' => 0,
    ]);
    test()->withSession(['business.id' => oftBiz(), 'user.business_id' => oftBiz()])
        ->put("/nfe-brasil/tributacao/regras/{$regraOutra->id}", [
            'ncm' => '77071002', 'uf_origem' => 'SP', 'uf_destino' => null, 'cfop' => '5102',
            'csosn' => '102', 'aliquota_icms' => 0.99, 'aliquota_pis' => 0, 'aliquota_cofins' => 0, 'aliquota_ipi' => 0,
        ])
        ->assertNotFound();
    expect(DB::table('nfe_fiscal_rules')->where('business_id', oftBizOutro())->where('ncm', '77071002')->count())->toBe(1)
        ->and((float) DB::table('nfe_fiscal_rules')->where('id', $regraOutra->id)->value('aliquota_icms'))->toBe(0.12);

    // CONTROLE POSITIVO — a empresa 98 usa a própria operação (Nível 4 da devolução, CFOP 1202).
    $dev = (new MotorTributarioService())
        ->calcularComDestino(oftCtx('77071001'), oftBiz(), 'SP', 'SP', operacaoId: $minha);
    expect($dev->nivel_usado)->toBe(4)->and($dev->cfop)->toBe('1202');
});

// ---------------------------------------------------------------------------------------
// R-NFE-019 (complemento) · a versão nova herda o tax_rate em vez de criar outro (ARQ-0005)
// ---------------------------------------------------------------------------------------
it('R-NFE-019 · versão nova herda o tax_rate da antiga — o cadastro de impostos não duplica', function () {
    // Sem Event::fake: o listener real SyncFiscalRuleToTaxRate cria o tax_rate da 1ª versão.
    $antiga = NfeFiscalRule::create([
        'business_id' => oftBiz(), 'ncm' => '77071001', 'uf_origem' => 'SP', 'cfop' => '5102',
        'csosn' => '102', 'aliquota_icms' => 0.12, 'aliquota_pis' => 0, 'aliquota_cofins' => 0, 'aliquota_ipi' => 0,
    ]);

    $link = DB::table('nfe_fiscal_rule_tax_rate_links')->where('fiscal_rule_id', $antiga->id)->first();
    expect($link)->not->toBeNull(); // pré-condição anti-vácuo: a 1ª versão tem tax_rate
    $taxasAntes = DB::table('tax_rates')->where('business_id', oftBiz())->count();

    $nova = $antiga->fresh()->novaVersao(['aliquota_icms' => 0.18]);

    expect(DB::table('tax_rates')->where('business_id', oftBiz())->count())->toBe($taxasAntes)
        ->and(DB::table('nfe_fiscal_rule_tax_rate_links')->where('fiscal_rule_id', $nova->id)->value('tax_rate_id'))
        ->toBe($link->tax_rate_id)
        ->and(DB::table('nfe_fiscal_rule_tax_rate_links')->where('fiscal_rule_id', $antiga->id)->count())->toBe(0)
        // O tax_rate herdado passa a mostrar a carga da versão nova (decimal, como o listener grava):
        // 0,18 + 0 + 0 + 0 = 0,18.
        ->and((float) DB::table('tax_rates')->where('id', $link->tax_rate_id)->value('amount'))->toBe(0.18);
});
