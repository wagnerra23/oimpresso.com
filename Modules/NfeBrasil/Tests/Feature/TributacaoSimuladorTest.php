<?php

declare(strict_types=1);

// @covers-us US-NFE-010 — "Preview de cálculo com produto exemplo" (simulador read-only, D-SIM).
// Contrato da tela: resources/js/Pages/NfeBrasil/Tributacao/Index.casos.md — UC-NFTR-08 · 09

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Events\FiscalRuleCreated;
use Modules\NfeBrasil\Events\FiscalRuleDeleted;
use Modules\NfeBrasil\Events\FiscalRuleUpdated;
use Modules\NfeBrasil\Services\MotorTributarioService;
use Modules\NfeBrasil\Services\NfeService;

uses(Tests\TestCase::class);

/**
 * Playbook Fiscal thread 08 · simulador read-only.
 *
 * Os casos derivam do aceite da thread (D-SIM: "chama o mesmo motor da emissão, zero cálculo no
 * front") e não do controller. Regra mestre de valor — DOIS caminhos independentes para o mesmo
 * número: (1) a montagem da emissão chamada direto, com a UF que o próprio `NfeService` resolve
 * (`resolverUF`, privado, por reflexão); (2) a conta à mão no comentário de cada valor.
 *
 * MySQL-only · tenant fictício 98 e vizinho 99 (ADR 0358). NCMs 7708xxxx reservados a este arquivo.
 */

function simBiz(): int
{
    return test()->seededTenant()->id;
}

function simBizOutro(): int
{
    return test()->seededSupportClientTenant()->id;
}

function simLimpar(): void
{
    $bizs = [simBiz(), simBizOutro()];
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    DB::table('nfe_fiscal_rules')->whereIn('business_id', $bizs)->where('ncm', 'like', '7708%')->delete();
    DB::table('products')->whereIn('business_id', $bizs)->where('sku', 'like', 'SIM08-%')->delete();
    DB::statement('SET FOREIGN_KEY_CHECKS=1');
}

function simProduto(int $biz, string $sku, string $ncm, int $userId): int
{
    $unit = (int) (DB::table('units')->where('business_id', $biz)->value('id')
        ?? DB::table('units')->insertGetId([
            'business_id' => $biz, 'actual_name' => 'Unidade simulador', 'short_name' => 'un',
            'allow_decimal' => 0, 'created_by' => $userId, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
        ]));

    return (int) DB::table('products')->insertGetId([
        'business_id' => $biz, 'name' => 'Produto ' . $sku, 'type' => 'single', 'unit_id' => $unit,
        'sku' => $sku, 'ncm' => $ncm, 'enable_stock' => 0, 'alert_quantity' => 0,
        'tax_type' => 'exclusive', 'barcode_type' => 'C128', 'created_by' => $userId,
        'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
    ]);
}

function simRegra(int $biz, string $ufOrigem, ?string $ufDestino, float $icms): void
{
    DB::table('nfe_fiscal_rules')->insert([
        'business_id' => $biz, 'ncm' => '77080001', 'uf_origem' => $ufOrigem, 'uf_destino' => $ufDestino,
        'cfop' => $ufDestino ? '6102' : '5102', 'csosn' => '102',
        'aliquota_icms' => $icms, 'aliquota_pis' => 0.0165, 'aliquota_cofins' => 0.076, 'aliquota_ipi' => 0,
        'c_class_trib' => '000001', 'cst_ibs' => '000', 'cst_cbs' => '000',
        'aliquota_ibs' => 0.001, 'aliquota_cbs' => 0.009,
        'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
    ]);
}

/** UF de origem pelo MESMO método da emissão (privado no NfeService). */
function simUfOrigem(): string
{
    $m = new ReflectionMethod(NfeService::class, 'resolverUF');
    $m->setAccessible(true);

    return $m->invoke(app(NfeService::class), DB::table('business')->where('id', simBiz())->first());
}

function simContagens(): array
{
    $tabelas = ['nfe_emissoes', 'nfe_fiscal_rules', 'nfe_business_configs', 'tax_rates', 'products'];
    if (Schema::hasTable('nfe_operacoes_fiscais')) {
        $tabelas[] = 'nfe_operacoes_fiscais';
    }

    return collect($tabelas)->filter(fn ($t) => Schema::hasTable($t))
        ->mapWithKeys(fn ($t) => [$t => DB::table($t)->count()])->all();
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('MySQL-only: o simulador lê products/business/business_locations reais.');
    }
    if (! Schema::hasColumn('products', 'ncm') || ! Schema::hasTable('nfe_fiscal_rules')) {
        $this->markTestSkipped('Schema do NfeBrasil ausente — rode as migrations do módulo.');
    }

    Event::fake([FiscalRuleCreated::class, FiscalRuleUpdated::class, FiscalRuleDeleted::class]);
    simLimpar();

    // NCM padrão da empresa (a emissão exige um válido). Guarda a config pra devolver no fim.
    $this->configAntes = DB::table('nfe_business_configs')->where('business_id', simBiz())->first();
    DB::table('nfe_business_configs')->where('business_id', simBiz())->delete();
    DB::table('nfe_business_configs')->insert([
        'business_id' => simBiz(), 'regime' => 'simples',
        'tributacao_default' => json_encode(['cfop' => '5102', 'csosn' => '102', 'ncm_default' => '77089999']),
        'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
    ]);

    $this->usuario = test()->usuarioComPermissoes(['nfe.tributacao.manage']);
    $this->actingAs($this->usuario);
    $this->withSession(['business.id' => simBiz(), 'user.business_id' => simBiz()]);
});

afterEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('nfe_fiscal_rules')) {
        return;
    }
    simLimpar();
    DB::table('nfe_business_configs')->where('business_id', simBiz())->delete();
    if ($this->configAntes) {
        DB::table('nfe_business_configs')->insert((array) $this->configAntes);
    }
});

// ---------------------------------------------------------------------------------------
// UC-NFTR-08 · O simulador devolve o mesmo cálculo da emissão e não grava nada  [fiscal]
// ---------------------------------------------------------------------------------------
it('UC-NFTR-08 · simulador = emissão e zero escrita', function () {
    $uf    = simUfOrigem();
    $outra = $uf === 'RJ' ? 'MG' : 'RJ';
    $pid   = simProduto(simBiz(), 'SIM08-A', '77080001', $this->usuario->id);
    simRegra(simBiz(), $uf, null, 0.12);   // N3: ICMS 12%
    simRegra(simBiz(), $uf, $outra, 0.07); // N2 para a outra UF: ICMS 7%

    $antes = simContagens();

    $res = $this->getJson("/nfe-brasil/tributacao/simular?product_id={$pid}&quantidade=3&valor_unitario=333.33&uf_destino={$uf}")
        ->assertOk()
        ->json();

    // Zero escrita: nenhuma tabela fiscal mudou de tamanho.
    expect(simContagens())->toBe($antes);

    // Caminho 1 — a montagem da emissão, chamada direto, com a UF de origem dela.
    $emissao = app(NfeService::class)->montarItensNfe(
        linhas: [[
            'sell_line_id' => null, 'product_id' => $pid, 'cprod' => 'SIM08-A', 'xprod' => 'Produto SIM08-A',
            'ncm' => '77080001', 'unidade' => 'UN', 'quantidade' => 3.0, 'valor_unitario' => 333.33,
        ]],
        valorNota: 999.99, frete: 0.0, ncmDefault: '77089999',
        motor: app(MotorTributarioService::class), businessId: simBiz(), ufOrigem: $uf, ufDestino: $uf,
    );
    $det = $emissao['dets'][0];
    $valores = array_column($res['tributos'], 'valor', 'tributo');

    expect($res['uf_origem'])->toBe($uf)
        ->and($res['cfop'])->toBe($det['cfop'])
        ->and($res['nivel'])->toBe($det['nivel_tributacao'])
        ->and((float) $valores['ICMS'])->toBe((float) $det['icms']['vicms'])
        ->and((float) $valores['PIS'])->toBe((float) $det['pis']['vpis'])
        ->and((float) $valores['COFINS'])->toBe((float) $det['cofins']['vcofins'])
        ->and((float) $valores['IBS'])->toBe((float) $det['ibscbs']['valor_ibs'])
        ->and((float) $valores['CBS'])->toBe((float) $det['ibscbs']['valor_cbs']);

    // Caminho 2 — conta à mão. Base = 3 × 333,33 = 999,99.
    //   ICMS   999,99 × 0,12   = 119,9988 → 120,00
    //   PIS    999,99 × 0,0165 =  16,4998 →  16,50
    //   COFINS 999,99 × 0,076  =  75,9992 →  76,00
    //   IBS    999,99 × 0,001  =   0,99999 →  1,00
    //   CBS    999,99 × 0,009  =   8,99991 →  9,00
    //   total destacado = 120 + 16,50 + 76 + 1 + 9 = 222,50
    expect($res['nivel'])->toBe(3)
        ->and($res['cfop'])->toBe('5102')
        ->and((float) $res['base'])->toBe(999.99)
        ->and((float) $valores['ICMS'])->toBe(120.0)
        ->and((float) $valores['PIS'])->toBe(16.5)
        ->and((float) $valores['COFINS'])->toBe(76.0)
        ->and((float) $valores['IBS'])->toBe(1.0)
        ->and((float) $valores['CBS'])->toBe(9.0)
        ->and((float) $res['total_destacado'])->toBe(222.5);

    // CONTROLE POSITIVO — trocar a UF para a da regra N2 muda o nível e o ICMS (999,99 × 0,07 = 70,00).
    $n2 = $this->getJson("/nfe-brasil/tributacao/simular?product_id={$pid}&quantidade=3&valor_unitario=333.33&uf_destino={$outra}")
        ->assertOk()
        ->json();
    expect($n2['nivel'])->toBe(2)
        ->and($n2['cfop'])->toBe('6102')
        ->and((float) array_column($n2['tributos'], 'valor', 'tributo')['ICMS'])->toBe(70.0);
});

// ---------------------------------------------------------------------------------------
// UC-NFTR-09 · Simular produto de outra empresa é 404, e sem permissão é 403  [T0]
// ---------------------------------------------------------------------------------------
it('UC-NFTR-09 · simulador isolado por tenant e por permissão', function () {
    $uf = simUfOrigem();
    simRegra(simBiz(), $uf, null, 0.12);
    $meu    = simProduto(simBiz(), 'SIM08-MEU', '77080001', $this->usuario->id);
    $alheio = simProduto(simBizOutro(), 'SIM08-ALHEIO', '77080001', $this->usuario->id);

    $res = $this->getJson("/nfe-brasil/tributacao/simular?product_id={$alheio}&quantidade=1&valor_unitario=100&uf_destino={$uf}");
    $res->assertNotFound();
    expect($res->getContent())->not->toContain('SIM08-ALHEIO');

    // CONTROLE POSITIVO — o produto da própria empresa simula.
    $this->getJson("/nfe-brasil/tributacao/simular?product_id={$meu}&quantidade=1&valor_unitario=100&uf_destino={$uf}")
        ->assertOk()
        ->assertJsonPath('produto.id', $meu);

    // Sem nfe.tributacao.manage → 403, mesmo para o próprio produto.
    $semPermissao = test()->usuarioComPermissoes([]);
    $this->actingAs($semPermissao)
        ->withSession(['business.id' => simBiz(), 'user.business_id' => simBiz()])
        ->getJson("/nfe-brasil/tributacao/simular?product_id={$meu}&quantidade=1&valor_unitario=100&uf_destino={$uf}")
        ->assertForbidden();
});
