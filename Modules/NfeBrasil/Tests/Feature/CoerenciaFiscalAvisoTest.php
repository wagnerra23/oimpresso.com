<?php

declare(strict_types=1);

// @covers-us US-NFE-010 — aviso de CST/CFOP incoerente (Sabor Brasil, [F] 2026-11-04).
// Contrato da tela: resources/js/Pages/NfeBrasil/Tributacao/Index.casos.md — UC-NFTR-40..43

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Services\NfeService;
use Modules\NfeBrasil\Services\Tributacao\CoerenciaFiscalDoItem as C;
use Modules\NfeBrasil\Services\Tributacao\SaudeFiscalService;

uses(Tests\TestCase::class);

/**
 * Derivado do pedido, não do código: "O Ricardo vai poder emitir com o CST e o CFOP que está
 * emitindo, mas o sistema vai avisar que está errado." Logo: avisa (lista não vazia) e NUNCA
 * recusa (nenhum caso aqui espera exceção). Combinações pelo Ajuste SINIEF 07/2005 (CFOP), tabela
 * B (CST/CSOSN) e Convênio ICMS 142/2018 (CEST com ST).
 *
 * UC-NFTR-40 roda sem DB. UC-NFTR-41..43 no MySQL · tenant 98 (ADR 0358). Fixtures: produtos SKU
 * `COER-*`, notas série `915`; o `nfe_business_configs` do tenant é guardado e devolvido.
 */

it('UC-NFTR-40 combinações coerentes não avisam; incoerentes avisam', function () {
    // Coerentes — nenhum aviso.
    expect(C::avisos(3, '5401', '10', null, '1703500'))->toBe([]);
    expect(C::avisos(1, '5401', null, '201', '1703500'))->toBe([]);
    expect(C::avisos(1, '5405', null, '500', '1703500'))->toBe([]);
    expect(C::avisos(3, '5405', '060', null, '1703500'))->toBe([]);
    expect(C::avisos(1, '5102', null, '102', ''))->toBe([]);

    // Substituto com código que não destaca ST.
    expect(C::avisos(3, '5401', '60', null)[0])->toContain('5401')->toContain('CST 10 ou 70');
    // CFOP de substituído com CST que destaca ST.
    expect(C::avisos(3, '5405', '10', null)[0])->toContain('já retido');
    // Venda comum com CSOSN de ST retido.
    expect(C::avisos(1, '5102', null, '500')[0])->toContain('5405');
    // Regime trocado.
    expect(C::avisos(3, '5102', null, '102')[0])->toContain('Regime Normal usa CST');
    expect(C::avisos(1, '5102', '00', null)[0])->toContain('Simples Nacional usa CSOSN');
    // Empresa substituta vendendo como substituída — é o caso do envasador.
    expect(implode(' ', C::avisos(1, '5405', null, '500', '1703500', substituto: true)))->toContain('substituta');
    expect(C::avisos(1, '5405', null, '500', '1703500', substituto: false))->toBe([]);
    // ST sem CEST no cadastro; CEST não verificado (null) não avisa.
    expect(C::avisos(1, '5405', null, '500', ''))->toContain('Operação com ICMS-ST sem CEST no cadastro do produto (Convênio ICMS 142/2018).');
    expect(C::avisos(1, '5405', null, '500', null))->toBe([]);
});

function coerPreparar(object $t): void
{
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('nfe_emissoes') || ! Schema::hasColumn('products', 'cest')) {
        $t->markTestSkipped('Schema NfeBrasil MySQL ausente — roda na lane nfebrasil-pest.');
    }
    $t->biz = test()->seededTenant()->id;
    $t->configAntes = DB::table('nfe_business_configs')->where('business_id', $t->biz)->first();
    DB::table('nfe_business_configs')->updateOrInsert(
        ['business_id' => $t->biz],
        ['regime' => 'simples', 'tributacao_default' => json_encode(['ncm_default' => '21039021', 'cfop' => '5405', 'substituto_tributario' => true]), 'updated_at' => now()],
    );
    $biz = $t->biz;
    $t->produto = fn (?string $cest) => (int) DB::table('products')->insertGetId([
        'name' => 'Tempero coerencia', 'business_id' => $biz, 'type' => 'single', 'tax_type' => 'exclusive',
        'sku' => 'COER-' . uniqid(), 'ncm' => '21039021', 'cest' => $cest, 'is_inactive' => 0,
        'created_by' => DB::table('business')->where('id', $biz)->value('owner_id'),
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

afterEach(function () {
    if (! isset($this->biz)) {
        return;
    }
    DB::table('products')->where('business_id', $this->biz)->where('sku', 'like', 'COER-%')->delete();
    DB::table('nfe_emissoes')->where('business_id', $this->biz)->where('serie', '915')->delete();
    DB::table('nfe_business_configs')->where('business_id', $this->biz)->delete();
    if ($this->configAntes) {
        DB::table('nfe_business_configs')->insert((array) $this->configAntes);
    }
});

it('UC-NFTR-41 a nota guarda o aviso por item e só dos itens incoerentes', function () {
    coerPreparar($this);
    $semCest = ($this->produto)(null);
    $comCest = ($this->produto)('1703500');
    $dets = [
        ['cfop' => '5405', 'icms' => ['cst_csosn' => '500']],
        ['cfop' => '5401', 'icms' => ['cst_csosn' => '201']],
    ];
    $linhas = [
        ['sell_line_id' => 11, 'product_id' => $semCest],
        ['sell_line_id' => 12, 'product_id' => $comCest],
    ];

    $avisos = app(NfeService::class)->avisosFiscaisDosItens($this->biz, $dets, $linhas, null);

    // Item 1: empresa substituta saindo como substituída + ST sem CEST. Item 2: coerente.
    expect($avisos)->toHaveCount(1);
    expect($avisos[0]['product_id'])->toBe($semCest);
    expect($avisos[0]['sell_line_id'])->toBe(11);
    expect($avisos[0]['avisos'])->toHaveCount(2);
});

it('UC-NFTR-42 [T0] CEST de produto de outra empresa não conta', function () {
    coerPreparar($this);
    $outro = (int) DB::table('business')->where('id', '!=', $this->biz)->orderBy('id')->value('id');
    $alheio = (int) DB::table('products')->insertGetId([
        'name' => 'Alheio coerencia', 'business_id' => $outro, 'type' => 'single', 'tax_type' => 'exclusive',
        'sku' => 'COER-' . uniqid(), 'ncm' => '21039021', 'cest' => '1703500', 'is_inactive' => 0,
        'created_by' => DB::table('business')->where('id', $outro)->value('owner_id'),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $avisos = app(NfeService::class)->avisosFiscaisDosItens(
        $this->biz, [['cfop' => '5401', 'icms' => ['cst_csosn' => '201']]], [['sell_line_id' => 1, 'product_id' => $alheio]], 1,
    );
    DB::table('products')->where('id', $alheio)->delete();

    // O CEST do outro tenant não é lido: o item conta como sem CEST.
    expect($avisos[0]['avisos'])->toBe(['Operação com ICMS-ST sem CEST no cadastro do produto (Convênio ICMS 142/2018).']);
});

it('UC-NFTR-43 a Saúde fiscal lista os produtos que saíram com aviso, sem bloquear', function () {
    coerPreparar($this);
    $p = ($this->produto)(null);
    DB::table('nfe_emissoes')->insert([
        'business_id' => $this->biz, 'modelo' => '55', 'serie' => '915', 'numero' => 1, 'status' => 'autorizada',
        'valor_total' => 10, 'metadata' => json_encode(['avisos_fiscais' => [['product_id' => $p, 'avisos' => ['x']]]]),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $r = app(SaudeFiscalService::class)->avaliar($this->biz);
    $item = collect($r['itens'])->firstWhere('tipo', 'emitido_com_cst_cfop_incoerente');

    expect($item)->not->toBeNull();
    expect($item['bloqueante'])->toBeFalse();
    expect($item['ids'])->toContain($p);
});
